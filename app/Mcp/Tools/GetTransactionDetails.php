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

#[Name('get_transaction_details')]
#[Description('Get full details for a specific transaction including category, subcategory, account, and import source information.')]
#[IsReadOnly]
class GetTransactionDetails extends Tool
{
    use FormatsResponse;

    public function __construct(
        protected BudgetQueryService $queryService,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_id' => $schema->integer()->required()->description('The transaction ID to look up'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $detail = $this->queryService->getTransactionDetail(
            (int) $request->get('transaction_id')
        );

        if ($detail === null) {
            return Response::error('Transaction not found');
        }

        $formatted = [
            'transaction_id' => $detail['id'],
            'date' => $detail['date'],
            'merchant_name' => $detail['description'],
            'amount_cents' => $detail['amountCents'],
            'category_id' => $detail['categoryId'],
            'category_name' => $detail['categoryName'],
            'subcategory_name' => $detail['subcategoryName'],
            'account_id' => $detail['accountId'],
            'account_name' => $detail['accountName'],
            'account_type' => $detail['accountType'],
            'import_id' => $detail['importId'],
            'import_file_name' => $detail['importFileName'],
            'import_bank' => $detail['importBank'],
        ];

        $meta = [
            'transaction_id' => $detail['id'],
        ];

        return Response::structured($this->formatResponse($formatted, $meta));
    }
}
