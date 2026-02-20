<?php

namespace App\Services;

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
}
