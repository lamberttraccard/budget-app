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

    /**
     * Query transactions with flexible filters and pagination.
     *
     * @param  array{category_id?: int, date_from?: string, date_to?: string, account_type?: string, limit?: int, offset?: int}  $filters
     * @return array{transactions: array, totalCount: int}
     */
    public function queryTransactions(array $filters): array
    {
        $limit = min($filters['limit'] ?? 50, 200);
        $offset = $filters['offset'] ?? 0;

        $query = Transaction::query()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->select([
                'transactions.id',
                'transactions.date',
                'transactions.description',
                'transactions.amount_cents',
                'transactions.category_id',
                'categories.name as category_name',
                'accounts.name as account_name',
            ]);

        if (isset($filters['category_id'])) {
            $query->where('transactions.category_id', $filters['category_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('transactions.date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('transactions.date', '<=', $filters['date_to']);
        }

        if (isset($filters['account_type'])) {
            $query->where('accounts.type', $filters['account_type']);
        }

        $totalCount = $query->count();

        $transactions = $query
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'date' => $row->date->toDateString(),
                'description' => $row->description,
                'amountCents' => (int) $row->amount_cents,
                'categoryId' => $row->category_id,
                'categoryName' => $row->category_name,
                'accountName' => $row->account_name,
            ])
            ->values()
            ->toArray();

        return compact('transactions', 'totalCount');
    }

    /**
     * Get full detail for a single transaction with all relationships.
     *
     * @return array|null
     */
    public function getTransactionDetail(int $transactionId): ?array
    {
        $transaction = Transaction::with(['category.parent', 'account', 'import'])
            ->find($transactionId);

        if (! $transaction) {
            return null;
        }

        $category = $transaction->category;
        $isSubcategory = $category && $category->parent_id !== null;

        return [
            'id' => $transaction->id,
            'date' => $transaction->date->toDateString(),
            'description' => $transaction->description,
            'amountCents' => $transaction->amount_cents,
            'categoryId' => $transaction->category_id,
            'categoryName' => $isSubcategory ? $category->parent->name : ($category->name ?? null),
            'subcategoryName' => $isSubcategory ? $category->name : null,
            'accountId' => $transaction->account_id,
            'accountName' => $transaction->account?->name,
            'accountType' => $transaction->account?->type,
            'importId' => $transaction->import_id,
            'importFileName' => $transaction->import?->file_name,
            'importBank' => $transaction->import?->bank,
        ];
    }
}
