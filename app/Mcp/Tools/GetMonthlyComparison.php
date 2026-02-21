<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsResponse;
use App\Services\BudgetQueryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_monthly_comparison')]
#[Description('Compare spending between two months, showing category-by-category totals with absolute and percentage changes. Useful for month-over-month trend analysis.')]
#[IsReadOnly]
class GetMonthlyComparison extends Tool
{
    use FormatsResponse;

    public function __construct(
        protected BudgetQueryService $queryService,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'base_year' => $schema->integer()->required()->min(2000)->max(2100)->description('Base year for comparison (e.g. 2026)'),
            'base_month' => $schema->integer()->required()->min(1)->max(12)->description('Base month number (1-12)'),
            'compare_year' => $schema->integer()->required()->min(2000)->max(2100)->description('Comparison year (e.g. 2026)'),
            'compare_month' => $schema->integer()->required()->min(1)->max(12)->description('Comparison month number (1-12)'),
            'category_id' => $schema->integer()->nullable()->description('Filter to a single category by ID'),
            'account_type' => $schema->string()->nullable()->enum(['personal', 'professional'])->description('Filter by account type'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $baseYear = (int) $request->get('base_year');
        $baseMonth = (int) $request->get('base_month');
        $compareYear = (int) $request->get('compare_year');
        $compareMonth = (int) $request->get('compare_month');
        $categoryId = $request->get('category_id') !== null ? (int) $request->get('category_id') : null;
        $accountType = $request->get('account_type');

        $data = $this->queryService->getMonthlyComparison(
            $baseYear, $baseMonth, $compareYear, $compareMonth, $categoryId, $accountType,
        );

        $formatted = array_map([$this, 'formatComparisonItem'], $data['categories']);

        $meta = [
            'base_period' => sprintf('%d-%02d', $baseYear, $baseMonth),
            'compare_period' => sprintf('%d-%02d', $compareYear, $compareMonth),
            'account' => $accountType ?? 'all',
            'category_filter' => $categoryId,
            'total_count' => count($formatted),
            'base_income_cents' => $data['baseIncomeCents'],
            'base_expenses_cents' => $data['baseExpensesCents'],
            'compare_income_cents' => $data['compareIncomeCents'],
            'compare_expenses_cents' => $data['compareExpensesCents'],
        ];

        return Response::structured($this->formatResponse($formatted, $meta));
    }
}
