<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsResponse;
use App\Services\RecurringDetectionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_recurring_transactions')]
#[Description('Detect and return recurring transactions (subscriptions, rent, salary). Identifies merchants with consistent monthly charges: same amount, one charge per month, 3+ consecutive months. Returns merchant name, category, typical amount, frequency, and full transaction history.')]
#[IsReadOnly]
class GetRecurringTransactions extends Tool
{
    use FormatsResponse;

    public function __construct(
        protected RecurringDetectionService $recurringDetectionService,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_type' => $schema->string()->nullable()->enum(['personal', 'professional'])->description('Filter by account type'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $accountType = $request->get('account_type');

        $recurringMerchants = $this->recurringDetectionService->detectRecurring($accountType);

        $formatted = array_map(fn (array $merchant) => [
            'merchant_name' => $merchant['merchantName'],
            'category_name' => $merchant['categoryName'],
            'category_id' => $merchant['categoryId'],
            'typical_amount_cents' => $merchant['typicalAmountCents'],
            'frequency' => strtolower($merchant['frequency']),
            'last_seen_month' => $merchant['lastSeenMonth'],
            'month_count' => $merchant['monthCount'],
            'amount_varies' => $merchant['amountVaries'],
            'min_amount_cents' => $merchant['minAmountCents'],
            'max_amount_cents' => $merchant['maxAmountCents'],
            'transactions' => array_map(fn (array $tx) => [
                'transaction_id' => $tx['id'],
                'date' => $tx['date'],
                'amount_cents' => $tx['amountCents'],
                'account_name' => $tx['accountName'],
            ], $merchant['transactions']),
        ], $recurringMerchants);

        $meta = [
            'total_count' => count($formatted),
            'account' => $accountType ?? 'all',
        ];

        return Response::structured($this->formatResponse($formatted, $meta));
    }
}
