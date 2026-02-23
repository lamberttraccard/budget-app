<?php

namespace App\Mcp\Concerns;

/**
 * Standardizes MCP tool response format.
 *
 * Convention for all budget-app MCP tools:
 * - Keys: snake_case (never camelCase -- that's for Inertia props)
 * - Amounts: integer cents (never floats, never formatted strings)
 * - Dates: ISO 8601 (YYYY-MM-DD)
 * - Wrapper: { data: [...], meta: { total_count, period, account, ... } }
 */
trait FormatsResponse
{
    protected function formatResponse(array $data, array $meta = []): array
    {
        return [
            'data' => $data,
            'meta' => $meta,
        ];
    }

    protected function formatCategoryItem(array $category): array
    {
        return [
            'category_id' => $category['id'],
            'category_name' => $category['name'],
            'category_slug' => $category['slug'],
            'color' => $category['color'] ?? null,
            'amount_cents' => $category['amountCents'],
            'transaction_count' => $category['transactionCount'],
        ];
    }

    protected function formatTransactionItem(array $transaction): array
    {
        return [
            'transaction_id' => $transaction['id'],
            'date' => $transaction['date'],
            'merchant_name' => $transaction['description'],
            'amount_cents' => $transaction['amountCents'],
            'category_id' => $transaction['categoryId'] ?? null,
            'category_name' => $transaction['categoryName'] ?? null,
            'account_name' => $transaction['accountName'] ?? null,
        ];
    }

    protected function formatComparisonItem(array $comparison): array
    {
        return [
            'category_id' => $comparison['id'],
            'category_name' => $comparison['name'],
            'category_slug' => $comparison['slug'],
            'color' => $comparison['color'] ?? null,
            'base_amount_cents' => $comparison['baseAmountCents'],
            'compare_amount_cents' => $comparison['compareAmountCents'],
            'change_cents' => $comparison['changeCents'],
            'change_pct' => $comparison['changePct'],
        ];
    }

    protected function formatAccountItem(array $account): array
    {
        return [
            'account_id' => $account['id'],
            'account_name' => $account['name'],
            'account_bank' => $account['bank'],
            'income_cents' => $account['incomeCents'],
            'expenses_cents' => $account['expensesCents'],
            'net_cents' => $account['netCents'],
            'transaction_count' => $account['transactionCount'],
        ];
    }
}
