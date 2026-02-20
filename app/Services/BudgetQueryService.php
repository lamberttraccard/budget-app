<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class BudgetQueryService
{
    public function getMonthlyData(int $year, int $month, ?string $accountType = null): array
    {
        $query = Transaction::query()
            ->select([
                'categories.id',
                'categories.name',
                'categories.slug',
                'categories.color',
                DB::raw('SUM(transactions.amount_cents) as total_amount'),
                DB::raw('COUNT(transactions.id) as transaction_count'),
            ])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereYear('transactions.date', $year)
            ->whereMonth('transactions.date', $month)
            ->groupBy('categories.id', 'categories.name', 'categories.slug', 'categories.color')
            ->orderByRaw('ABS(SUM(transactions.amount_cents)) DESC');

        if ($accountType !== null) {
            $query->join('accounts', 'transactions.account_id', '=', 'accounts.id')
                  ->where('accounts.type', $accountType);
        }

        $rows = $query->get();

        $categoryTotals = $rows->map(fn ($row) => [
            'id'               => $row->id,
            'name'             => $row->name,
            'slug'             => $row->slug,
            'color'            => $row->color,
            'amountCents'      => (int) $row->total_amount,
            'transactionCount' => (int) $row->transaction_count,
        ])->values()->toArray();

        $incomeRow = $rows->firstWhere('slug', 'income');
        $incomeCents = $incomeRow ? (int) $incomeRow->total_amount : 0;

        $expensesCents = abs(
            $rows->filter(fn ($r) => $r->slug !== 'income')
                 ->sum(fn ($r) => (int) $r->total_amount)
        );

        return compact('categoryTotals', 'incomeCents', 'expensesCents');
    }

    /**
     * Get distinct months that have transaction data, ordered ascending.
     *
     * @return string[] Array of "YYYY-MM" strings
     */
    public function getAvailableMonths(?string $accountType = null): array
    {
        $query = Transaction::query()
            ->select(DB::raw("DISTINCT TO_CHAR(date, 'YYYY-MM') as month"));

        if ($accountType !== null) {
            $query->join('accounts', 'transactions.account_id', '=', 'accounts.id')
                  ->where('accounts.type', $accountType);
        }

        return $query->orderBy('month')
            ->pluck('month')
            ->toArray();
    }

    public function getCategoryDetail(int $categoryId, int $year, int $month, ?string $accountType = null): array
    {
        $subcategoryIds = Category::where('parent_id', $categoryId)->pluck('id');

        $allCategoryIds = collect([$categoryId])->merge($subcategoryIds);

        $subcategories = [];
        if ($subcategoryIds->isNotEmpty()) {
            $query = Transaction::query()
                ->select([
                    'categories.id',
                    'categories.name',
                    DB::raw('SUM(transactions.amount_cents) as total_amount'),
                    DB::raw('COUNT(transactions.id) as transaction_count'),
                ])
                ->join('categories', 'transactions.category_id', '=', 'categories.id')
                ->whereIn('transactions.category_id', $subcategoryIds)
                ->whereYear('transactions.date', $year)
                ->whereMonth('transactions.date', $month);

            if ($accountType !== null) {
                $query->join('accounts', 'transactions.account_id', '=', 'accounts.id')
                      ->where('accounts.type', $accountType);
            }

            $subcategories = $query
                ->groupBy('categories.id', 'categories.name')
                ->orderByRaw('ABS(SUM(transactions.amount_cents)) DESC')
                ->get()
                ->map(fn ($row) => [
                    'id'               => $row->id,
                    'name'             => $row->name,
                    'amountCents'      => (int) $row->total_amount,
                    'transactionCount' => (int) $row->transaction_count,
                ])
                ->values()
                ->toArray();
        }

        $txQuery = Transaction::query()
            ->whereIn('transactions.category_id', $allCategoryIds)
            ->whereYear('transactions.date', $year)
            ->whereMonth('transactions.date', $month);

        if ($accountType !== null) {
            $txQuery->join('accounts', 'transactions.account_id', '=', 'accounts.id')
                    ->where('accounts.type', $accountType)
                    ->select('transactions.*');
        }

        $transactions = $txQuery
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->get()
            ->map(fn (Transaction $t) => [
                'id'          => $t->id,
                'date'        => $t->date->toDateString(),
                'description' => $t->description,
                'amountCents' => $t->amount_cents,
                'categoryId'  => $t->category_id,
            ])
            ->values()
            ->toArray();

        $totalAmountCents = (int) collect($transactions)->sum('amountCents');
        $transactionCount = count($transactions);

        return compact('subcategories', 'transactions', 'totalAmountCents', 'transactionCount');
    }
}
