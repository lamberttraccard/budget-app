<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecurringDetectionService
{
    private const MIN_CONSECUTIVE_MONTHS = 3;

    private const MAX_AMOUNT_VARIATION = 0.20;

    /**
     * Detect recurring transactions (subscriptions) by finding merchants with
     * consistent monthly charges: same amount, one charge per month, 3+ consecutive months.
     *
     * @param  int[]|null  $accountIds  Filter by account IDs (null = all accounts)
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
    public function detectRecurring(?array $accountIds = null): array
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

        if ($accountIds !== null) {
            $query->whereIn('transactions.account_id', $accountIds);
        }

        $rows = $query->get();

        // Group rows by normalized merchant
        $groupedByMerchant = $rows->groupBy('normalized_merchant');

        // Phase 2: Filter for subscription-like patterns
        $recurringMerchants = [];

        foreach ($groupedByMerchant as $normalizedMerchant => $monthRows) {
            // Only keep months with exactly 1 transaction (subscriptions = 1 charge/month)
            $singleTxMonths = $monthRows->filter(fn ($row) => (int) $row->tx_count === 1);

            if ($singleTxMonths->count() < self::MIN_CONSECUTIVE_MONTHS) {
                continue;
            }

            // Filter to months with consistent amounts (within 20% of median)
            // This discards outlier months (prorations, price changes) instead of rejecting the merchant
            $consistentMonths = $this->filterConsistentMonths($singleTxMonths);

            if ($consistentMonths->count() < self::MIN_CONSECUTIVE_MONTHS) {
                continue;
            }

            // Check for consecutive months among consistent months
            $months = $consistentMonths->pluck('month')->toArray();
            $consecutiveRun = $this->longestConsecutiveRun($months);

            if ($consecutiveRun < self::MIN_CONSECUTIVE_MONTHS) {
                continue;
            }

            $displayMerchant = $consistentMonths->last()->display_merchant;

            $recurringMerchants[] = [
                'normalizedMerchant' => $normalizedMerchant,
                'displayMerchant' => $displayMerchant,
                'months' => $months,
                'monthCount' => $consecutiveRun,
            ];
        }

        if (empty($recurringMerchants)) {
            return [];
        }

        // Fetch full transaction history for recurring merchants
        $normalizedNames = collect($recurringMerchants)->pluck('normalizedMerchant');
        $transactions = $this->fetchTransactionHistory($normalizedNames, $accountIds);

        // Build final result with computed fields
        return $this->buildResult($recurringMerchants, $transactions);
    }

    /**
     * Filter month rows to those with amounts within 20% of the median.
     * Outlier months (prorations, price changes) are discarded rather than
     * rejecting the entire merchant.
     */
    private function filterConsistentMonths(Collection $singleTxMonths): Collection
    {
        $amounts = $singleTxMonths->pluck('total_amount')->map(fn ($v) => abs((int) $v));
        $median = $this->median($amounts);

        if ($median == 0) {
            return $singleTxMonths->filter(fn ($row) => abs((int) $row->total_amount) == 0);
        }

        return $singleTxMonths->filter(
            fn ($row) => abs(abs((int) $row->total_amount) - $median) / $median <= self::MAX_AMOUNT_VARIATION
        );
    }

    private function median(Collection $values): float
    {
        $sorted = $values->sort()->values();

        if ($sorted->isEmpty()) {
            return 0;
        }

        $count = $sorted->count();

        return $count % 2 === 0
            ? ($sorted[$count / 2 - 1] + $sorted[$count / 2]) / 2
            : $sorted[intdiv($count, 2)];
    }

    /**
     * Find the longest run of consecutive months in the given list.
     */
    private function longestConsecutiveRun(array $months): int
    {
        if (count($months) < 2) {
            return count($months);
        }

        sort($months);
        $maxRun = 1;
        $currentRun = 1;

        for ($i = 1; $i < count($months); $i++) {
            [$py, $pm] = explode('-', $months[$i - 1]);
            [$cy, $cm] = explode('-', $months[$i]);
            $isConsecutive = ((int) $cy * 12 + (int) $cm) - ((int) $py * 12 + (int) $pm) === 1;

            if ($isConsecutive) {
                $currentRun++;
                $maxRun = max($maxRun, $currentRun);
            } else {
                $currentRun = 1;
            }
        }

        return $maxRun;
    }

    private function fetchTransactionHistory(Collection $normalizedNames, ?array $accountIds): Collection
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

        if ($accountIds !== null) {
            $query->whereIn('accounts.id', $accountIds);
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

            // UI hint: flag if amounts aren't all identical (user sees min–max range)
            $amountVaries = $absAmounts->min() !== $absAmounts->max();

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
