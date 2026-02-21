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

#[Name('query_transactions')]
#[Description('Search transactions with optional filters for category, date range, and account type. Returns paginated results with merchant name, amount, and category.')]
#[IsReadOnly]
class QueryTransactions extends Tool
{
    use FormatsResponse;

    public function __construct(
        protected BudgetQueryService $queryService,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'category_id' => $schema->integer()->nullable()->description('Filter by category ID'),
            'date_from' => $schema->string()->nullable()->format('date')->description('Start date (YYYY-MM-DD)'),
            'date_to' => $schema->string()->nullable()->format('date')->description('End date (YYYY-MM-DD)'),
            'account_type' => $schema->string()->nullable()->enum(['personal', 'professional'])->description('Filter by account type'),
            'limit' => $schema->integer()->default(50)->min(1)->max(200)->description('Max results per page (default 50, max 200)'),
            'offset' => $schema->integer()->default(0)->min(0)->description('Number of results to skip for pagination'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $filters = array_filter($request->all(), fn ($v) => $v !== null);

        $result = $this->queryService->queryTransactions($filters);

        $formatted = array_map([$this, 'formatTransactionItem'], $result['transactions']);

        $meta = [
            'total_count' => $result['totalCount'],
            'limit' => min($filters['limit'] ?? 50, 200),
            'offset' => $filters['offset'] ?? 0,
            'filters' => array_filter([
                'category_id' => $request->get('category_id'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'account_type' => $request->get('account_type'),
            ], fn ($v) => $v !== null),
        ];

        return Response::structured($this->formatResponse($formatted, $meta));
    }
}
