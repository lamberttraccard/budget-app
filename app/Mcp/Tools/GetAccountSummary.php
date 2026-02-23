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

#[Name('get_account_summary')]
#[Description('Get per-account income and expense totals. Returns each account with income, expenses, net balance, and transaction count. Optionally filter by period.')]
#[IsReadOnly]
class GetAccountSummary extends Tool
{
    use FormatsResponse;

    public function __construct(
        protected BudgetQueryService $queryService,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->nullable()->description('Filter to a specific year (e.g. 2026)'),
            'month' => $schema->integer()->nullable()->min(1)->max(12)->description('Filter to a specific month (1-12), requires year'),
            'account_ids' => $schema->array()->nullable()->items($schema->integer())->description('Filter by account IDs. Omit for all accounts.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $year = $request->get('year') !== null ? (int) $request->get('year') : null;
        $month = $request->get('month') !== null ? (int) $request->get('month') : null;
        $accountIds = $request->get('account_ids');

        if ($month !== null && $year === null) {
            return Response::error('The month parameter requires year to be specified.');
        }

        $data = $this->queryService->getAccountSummary($year, $month);

        $accounts = $data['accounts'];
        if ($accountIds !== null) {
            $accounts = array_values(array_filter($accounts, fn ($a) => in_array($a['id'], $accountIds)));
        }

        $formatted = array_map([$this, 'formatAccountItem'], $accounts);

        $period = $year !== null
            ? ($month !== null ? sprintf('%d-%02d', $year, $month) : (string) $year)
            : 'all';

        $meta = [
            'period' => $period,
            'total_count' => count($formatted),
            'total_income_cents' => $accountIds !== null
                ? array_sum(array_column($accounts, 'incomeCents'))
                : $data['totalIncomeCents'],
            'total_expenses_cents' => $accountIds !== null
                ? array_sum(array_column($accounts, 'expensesCents'))
                : $data['totalExpensesCents'],
        ];

        return Response::structured($this->formatResponse($formatted, $meta));
    }
}
