<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecurringDetectionService
{
    /**
     * Detect recurring transactions by finding merchants with 2+ consecutive months.
     *
     * @param  string|null  $accountType  Filter by account type (personal/professional)
     * @return array<int, array{
     *     merchantName: string,
     *     normalizedMerchant: string,
     *     categoryId: int|null,
     *     categoryName: string,
     *     categoryColor: string,
     *     typicalAmountCents: int,
     *     frequency: string,
     *     lastSeenMonth: string,
     *     monthCount: int,
     *     amountVaries: bool,
     *     minAmountCents: int,
     *     maxAmountCents: int,
     *     transactions: array,
     * }>
     */
    public function detectRecurring(?string $accountType = null): array
    {
        // Phase 1: SQL aggregation — group by normalized merchant + month
        $query = Transaction::query()
            ->select([
                DB::raw('LOWER(TRIM(transactions.description)) as normalized_merchant'),
                DB::raw('MAX(transactions.description) as display_merchant'),
                DB::raw("TO_CHAR(transactions.date, 'YYYY-MM') as month"),
                DB::raw('COUNT(*) as tx_count'),
                DB::raw('SUM(transactions.amount_cents) as total_amount'),
            ])
            ->groupBy(
                DB::raw('LOWER(TRIM(transactions.description))'),
                DB::raw("TO_CHAR(transactions.date, 'YYYY-MM')")
            )
            ->orderBy('normalized_merchant')
            ->orderBy('month');

        if ($accountType !== null) {
            $query->join('accounts', 'transactions.account_id', '=', 'accounts.id')
                ->where('accounts.type', $accountType);
        }

        $rows = $query->get();

        // Group rows by normalized merchant
        $groupedByMerchant = $rows->groupBy('normalized_merchant');

        // Phase 2: PHP consecutive-month detection
        $recurringMerchants = [];

        foreach ($groupedByMerchant as $normalizedMerchant => $monthRows) {
            $months = $monthRows->pluck('month')->toArray();

            if (! $this->hasConsecutiveMonths($months)) {
                continue;
            }

            $displayMerchant = $monthRows->last()->display_merchant;

            $recurringMerchants[] = [
                'normalizedMerchant' => $normalizedMerchant,
                'displayMerchant' => $displayMerchant,
                'months' => $months,
                'monthCount' => count($months),
            ];
        }

        if (empty($recurringMerchants)) {
            return [];
        }

        // Fetch full transaction history for recurring merchants
        $normalizedNames = collect($recurringMerchants)->pluck('normalizedMerchant');
        $transactions = $this->fetchTransactionHistory($normalizedNames, $accountType);

        // Build final result with computed fields
        return $this->buildResult($recurringMerchants, $transactions);
    }

    private function hasConsecutiveMonths(array $months): bool
    {
        if (count($months) < 2) {
            return false;
        }

        sort($months);

        for ($i = 1; $i < count($months); $i++) {
            $prev = Carbon::createFromFormat('Y-m', $months[$i - 1]);
            $current = Carbon::createFromFormat('Y-m', $months[$i]);

            if ($prev->copy()->addMonth()->format('Y-m') === $current->format('Y-m')) {
                return true;
            }
        }

        return false;
    }

    private function fetchTransactionHistory(Collection $normalizedNames, ?string $accountType): Collection
    {
        $placeholders = $normalizedNames->map(fn () => '?')->implode(',');

        $query = Transaction::query()
            ->whereRaw(
                "LOWER(TRIM(transactions.description)) IN ({$placeholders})",
                $normalizedNames->toArray()
            )
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->select([
                'transactions.id',
                'transactions.date',
                'transactions.description',
                'transactions.amount_cents',
                'transactions.category_id',
                'categories.name as category_name',
                'categories.color as category_color',
                'accounts.name as account_name',
                DB::raw('LOWER(TRIM(transactions.description)) as normalized_merchant'),
            ]);

        if ($accountType !== null) {
            $query->where('accounts.type', $accountType);
        }

        return $query->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->get();
    }

    private function buildResult(array $recurringMerchants, Collection $allTransactions): array
    {
        $transactionsByMerchant = $allTransactions->groupBy('normalized_merchant');
        $result = [];

        foreach ($recurringMerchants as $merchant) {
            $normalized = $merchant['normalizedMerchant'];
            $merchantTransactions = $transactionsByMerchant->get($normalized, collect());

            if ($merchantTransactions->isEmpty()) {
                continue;
            }

            // Most recent transaction determines typical amount and category
            $mostRecent = $merchantTransactions->first();

            $amounts = $merchantTransactions->pluck('amount_cents')->map(fn ($v) => (int) $v);
            $absAmounts = $amounts->map(fn ($v) => abs($v));
            $minAmount = $amounts->min();
            $maxAmount = $amounts->max();
            $avgAmount = $absAmounts->avg();

            // Amount varies if spread > 20% of mean absolute amount
            $amountVaries = $avgAmount > 0
                ? ($absAmounts->max() - $absAmounts->min()) / $avgAmount > 0.20
                : false;

            $result[] = [
                'merchantName' => $merchant['displayMerchant'],
                'categoryId' => $mostRecent->category_id,
                'categoryName' => $mostRecent->category_name ?? 'Uncategorized',
                'categoryColor' => $mostRecent->category_color ?? '#6B7280',
                'typicalAmountCents' => (int) $mostRecent->amount_cents,
                'frequency' => 'Monthly',
                'lastSeenMonth' => Carbon::parse($mostRecent->date)->format('Y-m'),
                'monthCount' => $merchant['monthCount'],
                'amountVaries' => $amountVaries,
                'minAmountCents' => (int) $minAmount,
                'maxAmountCents' => (int) $maxAmount,
                'transactions' => $merchantTransactions->map(fn ($t) => [
                    'id' => $t->id,
                    'date' => Carbon::parse($t->date)->toDateString(),
                    'amountCents' => (int) $t->amount_cents,
                    'accountName' => $t->account_name,
                ])->values()->toArray(),
            ];
        }

        // Sort by absolute typical amount descending (highest cost first)
        usort($result, fn ($a, $b) => abs($b['typicalAmountCents']) <=> abs($a['typicalAmountCents']));

        return $result;
    }
}
