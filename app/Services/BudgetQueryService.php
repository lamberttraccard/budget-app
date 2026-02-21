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
                DB::raw('COALESCE(parent_cat.id, categories.id) as id'),
                DB::raw('COALESCE(parent_cat.name, categories.name) as name'),
                DB::raw('COALESCE(parent_cat.slug, categories.slug) as slug'),
                DB::raw('COALESCE(parent_cat.color, categories.color) as color'),
                DB::raw('COALESCE(parent_cat.icon, categories.icon) as icon'),
                DB::raw('SUM(transactions.amount_cents) as total_amount'),
                DB::raw('COUNT(transactions.id) as transaction_count'),
            ])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->leftJoin('categories as parent_cat', 'categories.parent_id', '=', 'parent_cat.id')
            ->whereYear('transactions.date', $year)
            ->whereMonth('transactions.date', $month)
            ->groupBy(
                DB::raw('COALESCE(parent_cat.id, categories.id)'),
                DB::raw('COALESCE(parent_cat.name, categories.name)'),
                DB::raw('COALESCE(parent_cat.slug, categories.slug)'),
                DB::raw('COALESCE(parent_cat.color, categories.color)'),
                DB::raw('COALESCE(parent_cat.icon, categories.icon)'),
            )
            ->orderByRaw('ABS(SUM(transactions.amount_cents)) DESC');

        if ($accountType !== null) {
            $query->join('accounts', 'transactions.account_id', '=', 'accounts.id')
                ->where('accounts.type', $accountType);
        }

        $rows = $query->get();

        $categoryTotals = $rows->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'slug' => $row->slug,
            'color' => $row->color,
            'icon' => $row->icon,
            'amountCents' => (int) $row->total_amount,
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
                    'id' => $row->id,
                    'name' => $row->name,
                    'amountCents' => (int) $row->total_amount,
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
                'id' => $t->id,
                'date' => $t->date->toDateString(),
                'description' => $t->description,
                'amountCents' => $t->amount_cents,
                'categoryId' => $t->category_id,
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
     * @param  array{q?: string, category_id?: int, date_from?: string, date_to?: string, account_type?: string, limit?: int, offset?: int}  $filters
     * @return array{transactions: array, totalCount: int}
     */
    public function queryTransactions(array $filters): array
    {
        $limit = min($filters['limit'] ?? 50, 200);
        $offset = $filters['offset'] ?? 0;

        $query = Transaction::query()
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->select([
                'transactions.id',
                'transactions.date',
                'transactions.description',
                'transactions.amount_cents',
                'transactions.category_id',
                'categories.name as category_name',
                'categories.color as category_color',
                'accounts.name as account_name',
            ]);

        if (isset($filters['q']) && strlen($filters['q']) >= 2) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']);
            $query->where('transactions.description', 'ILIKE', '%'.$escaped.'%');
        }

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
                'categoryName' => $row->category_name ?? 'Uncategorized',
                'categoryColor' => $row->category_color ?? '#6B7280',
                'accountName' => $row->account_name,
            ])
            ->values()
            ->toArray();

        return compact('transactions', 'totalCount');
    }

    /**
     * Compare category spending between two months with absolute and percentage deltas.
     */
    public function getMonthlyComparison(
        int $baseYear,
        int $baseMonth,
        int $compareYear,
        int $compareMonth,
        ?int $categoryId = null,
        ?string $accountType = null,
    ): array {
        $baseData = $this->getMonthlyData($baseYear, $baseMonth, $accountType);
        $compareData = $this->getMonthlyData($compareYear, $compareMonth, $accountType);

        $baseByCategory = collect($baseData['categoryTotals'])->keyBy('id');
        $compareByCategory = collect($compareData['categoryTotals'])->keyBy('id');

        $allCategoryIds = $baseByCategory->keys()->merge($compareByCategory->keys())->unique();

        $categories = $allCategoryIds->map(function ($id) use ($baseByCategory, $compareByCategory) {
            $base = $baseByCategory->get($id);
            $compare = $compareByCategory->get($id);

            $baseAmount = $base ? $base['amountCents'] : 0;
            $compareAmount = $compare ? $compare['amountCents'] : 0;

            if ($baseAmount === 0 && $compareAmount === 0) {
                return null;
            }

            $changeCents = $compareAmount - $baseAmount;
            $changePct = $baseAmount !== 0
                ? round(($changeCents / abs($baseAmount)) * 100, 2)
                : null;

            $reference = $base ?? $compare;

            return [
                'id' => $id,
                'name' => $reference['name'],
                'slug' => $reference['slug'],
                'color' => $reference['color'] ?? null,
                'icon' => $reference['icon'] ?? null,
                'baseAmountCents' => $baseAmount,
                'compareAmountCents' => $compareAmount,
                'changeCents' => $changeCents,
                'changePct' => $changePct,
            ];
        })->filter()->values();

        if ($categoryId !== null) {
            $categories = $categories->where('id', $categoryId)->values();
        }

        return [
            'categories' => $categories->toArray(),
            'baseIncomeCents' => $baseData['incomeCents'],
            'baseExpensesCents' => $baseData['expensesCents'],
            'compareIncomeCents' => $compareData['incomeCents'],
            'compareExpensesCents' => $compareData['expensesCents'],
        ];
    }

    /**
     * Get per-account income and expense totals, optionally filtered by period.
     */
    public function getAccountSummary(?int $year = null, ?int $month = null): array
    {
        $query = DB::table('accounts')
            ->leftJoin('transactions', function ($join) use ($year, $month) {
                $join->on('accounts.id', '=', 'transactions.account_id');
                if ($year !== null) {
                    $join->whereYear('transactions.date', $year);
                    if ($month !== null) {
                        $join->whereMonth('transactions.date', $month);
                    }
                }
            })
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->select([
                'accounts.id',
                'accounts.name',
                'accounts.bank',
                'accounts.type',
                DB::raw("COALESCE(SUM(CASE WHEN categories.slug = 'income' THEN transactions.amount_cents ELSE 0 END), 0) as income_cents"),
                DB::raw("COALESCE(SUM(CASE WHEN categories.slug != 'income' THEN ABS(transactions.amount_cents) ELSE 0 END), 0) as expenses_cents"),
                DB::raw('COUNT(transactions.id) as transaction_count'),
            ])
            ->groupBy('accounts.id', 'accounts.name', 'accounts.bank', 'accounts.type');

        $rows = $query->orderBy('accounts.name')->get();

        $accounts = $rows->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->name,
            'bank' => $row->bank,
            'type' => $row->type,
            'incomeCents' => (int) $row->income_cents,
            'expensesCents' => (int) $row->expenses_cents,
            'netCents' => (int) $row->income_cents - (int) $row->expenses_cents,
            'transactionCount' => (int) $row->transaction_count,
        ])->toArray();

        return [
            'accounts' => $accounts,
            'totalIncomeCents' => (int) $rows->sum('income_cents'),
            'totalExpensesCents' => (int) $rows->sum('expenses_cents'),
        ];
    }

    /**
     * Get full detail for a single transaction with all relationships.
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
