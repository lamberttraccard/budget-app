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

#[Name('get_category_totals')]
#[Description('Get total spending per category for a given month. Returns all categories with their total amount in cents and transaction counts.')]
#[IsReadOnly]
class GetCategoryTotals extends Tool
{
    use FormatsResponse;

    public function __construct(
        protected BudgetQueryService $queryService,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->required()->description('Year (e.g. 2026)'),
            'month' => $schema->integer()->required()->min(1)->max(12)->description('Month number (1-12)'),
            'account_ids' => $schema->array()->nullable()->items($schema->integer())->description('Filter by account IDs. Omit for all accounts.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $year = (int) $request->get('year');
        $month = (int) $request->get('month');
        $accountIds = $request->get('account_ids');

        $data = $this->queryService->getMonthlyData($year, $month, $accountIds);

        $formatted = array_map([$this, 'formatCategoryItem'], $data['categoryTotals']);

        $meta = [
            'period' => sprintf('%d-%02d', $year, $month),
            'account_ids' => $accountIds,
            'total_count' => count($formatted),
            'income_cents' => $data['incomeCents'],
            'expenses_cents' => $data['expensesCents'],
        ];

        return Response::structured($this->formatResponse($formatted, $meta));
    }
}
