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

#[Name('get_category_breakdown')]
#[Description('Get detailed breakdown of a category showing subcategories with their totals and individual transactions for a given month.')]
#[IsReadOnly]
class GetCategoryBreakdown extends Tool
{
    use FormatsResponse;

    public function __construct(
        protected BudgetQueryService $queryService,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'category_id' => $schema->integer()->required()->description('The parent category ID to break down'),
            'year' => $schema->integer()->required()->description('Year (e.g. 2026)'),
            'month' => $schema->integer()->required()->min(1)->max(12)->description('Month number (1-12)'),
            'account_ids' => $schema->array()->nullable()->items($schema->integer())->description('Filter by account IDs. Omit for all accounts.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $categoryId = (int) $request->get('category_id');
        $year = (int) $request->get('year');
        $month = (int) $request->get('month');
        $accountIds = $request->get('account_ids');

        $data = $this->queryService->getCategoryDetail($categoryId, $year, $month, $accountIds);

        $formattedSubcategories = array_map(fn (array $sub) => [
            'category_id' => $sub['id'],
            'category_name' => $sub['name'],
            'amount_cents' => $sub['amountCents'],
            'transaction_count' => $sub['transactionCount'],
        ], $data['subcategories']);

        $formattedTransactions = array_map([$this, 'formatTransactionItem'], $data['transactions']);

        $formatted = [
            'subcategories' => $formattedSubcategories,
            'transactions' => $formattedTransactions,
        ];

        $meta = [
            'category_id' => $categoryId,
            'period' => sprintf('%d-%02d', $year, $month),
            'account_ids' => $accountIds,
            'total_amount_cents' => $data['totalAmountCents'],
            'transaction_count' => $data['transactionCount'],
        ];

        return Response::structured($this->formatResponse($formatted, $meta));
    }
}
