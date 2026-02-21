<?php

namespace Tests\Feature;

use App\Mcp\Servers\BudgetServer;
use App\Mcp\Tools\GetCategoryBreakdown;
use App\Mcp\Tools\GetCategoryTotals;
use App\Mcp\Tools\GetTransactionDetails;
use App\Mcp\Tools\QueryTransactions;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetQueryService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpToolTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $personal;

    private Account $professional;

    private Category $food;

    private Category $transport;

    private Category $income;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);

        $this->user = User::factory()->create();
        $this->personal = Account::factory()->personal()->create(['name' => 'Personal Checking', 'bank' => 'Banque Populaire']);
        $this->professional = Account::factory()->professional()->create(['name' => 'Pro Account', 'bank' => 'Revolut']);

        $this->food = Category::where('slug', 'food')->first();
        $this->transport = Category::where('slug', 'transport')->first();
        $this->income = Category::where('slug', 'income')->first();
    }

    // ──────────────────────────────────────────────────
    // BudgetQueryService::queryTransactions() tests
    // ──────────────────────────────────────────────────

    public function test_query_transactions_returns_all_with_no_filters(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -3500, 'date' => '2026-02-10',
            'description' => 'CARREFOUR', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -1200, 'date' => '2026-02-12',
            'description' => 'UBER', 'category_id' => $this->transport->id,
            'account_id' => $this->personal->id,
        ]);

        $service = app(BudgetQueryService::class);
        $result = $service->queryTransactions([]);

        $this->assertCount(2, $result['transactions']);
        $this->assertEquals(2, $result['totalCount']);
    }

    public function test_query_transactions_filters_by_category(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -3500, 'date' => '2026-02-10',
            'description' => 'CARREFOUR', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -1200, 'date' => '2026-02-12',
            'description' => 'UBER', 'category_id' => $this->transport->id,
            'account_id' => $this->personal->id,
        ]);

        $service = app(BudgetQueryService::class);
        $result = $service->queryTransactions(['category_id' => $this->food->id]);

        $this->assertCount(1, $result['transactions']);
        $this->assertEquals('CARREFOUR', $result['transactions'][0]['description']);
    }

    public function test_query_transactions_filters_by_date_range(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -3500, 'date' => '2026-01-15',
            'description' => 'JAN TX', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -1200, 'date' => '2026-02-10',
            'description' => 'FEB TX', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -800, 'date' => '2026-03-05',
            'description' => 'MAR TX', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);

        $service = app(BudgetQueryService::class);
        $result = $service->queryTransactions([
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-28',
        ]);

        $this->assertCount(1, $result['transactions']);
        $this->assertEquals('FEB TX', $result['transactions'][0]['description']);
    }

    public function test_query_transactions_filters_by_account_type(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -3500, 'date' => '2026-02-10',
            'description' => 'PERSONAL TX', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -1200, 'date' => '2026-02-10',
            'description' => 'PRO TX', 'category_id' => $this->food->id,
            'account_id' => $this->professional->id,
        ]);

        $service = app(BudgetQueryService::class);
        $result = $service->queryTransactions(['account_type' => 'personal']);

        $this->assertCount(1, $result['transactions']);
        $this->assertEquals('PERSONAL TX', $result['transactions'][0]['description']);
    }

    public function test_query_transactions_supports_pagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Transaction::factory()->create([
                'amount_cents' => -1000 * $i, 'date' => "2026-02-0{$i}",
                'description' => "TX{$i}", 'category_id' => $this->food->id,
                'account_id' => $this->personal->id,
            ]);
        }

        $service = app(BudgetQueryService::class);
        $result = $service->queryTransactions(['limit' => 2, 'offset' => 0]);

        $this->assertCount(2, $result['transactions']);
        $this->assertEquals(5, $result['totalCount']);

        $page2 = $service->queryTransactions(['limit' => 2, 'offset' => 2]);
        $this->assertCount(2, $page2['transactions']);
    }

    public function test_query_transactions_includes_category_and_account_names(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -3500, 'date' => '2026-02-10',
            'description' => 'CARREFOUR', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);

        $service = app(BudgetQueryService::class);
        $result = $service->queryTransactions([]);

        $tx = $result['transactions'][0];
        $this->assertEquals('Food', $tx['categoryName']);
        $this->assertEquals('Personal Checking', $tx['accountName']);
    }

    // ──────────────────────────────────────────────────
    // BudgetQueryService::getTransactionDetail() tests
    // ──────────────────────────────────────────────────

    public function test_get_transaction_detail_returns_full_record(): void
    {
        $import = Import::factory()->completed()->create([
            'file_name' => 'export_2026_02.csv',
            'bank' => 'Banque Populaire',
        ]);

        $tx = Transaction::factory()->create([
            'amount_cents' => -5200, 'date' => '2026-02-15',
            'description' => 'CARREFOUR CITY', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id, 'import_id' => $import->id,
        ]);

        $service = app(BudgetQueryService::class);
        $detail = $service->getTransactionDetail($tx->id);

        $this->assertNotNull($detail);
        $this->assertEquals($tx->id, $detail['id']);
        $this->assertEquals('2026-02-15', $detail['date']);
        $this->assertEquals('CARREFOUR CITY', $detail['description']);
        $this->assertEquals(-5200, $detail['amountCents']);
        $this->assertEquals('Food', $detail['categoryName']);
        $this->assertNull($detail['subcategoryName']);
        $this->assertEquals('Personal Checking', $detail['accountName']);
        $this->assertEquals('personal', $detail['accountType']);
        $this->assertEquals('export_2026_02.csv', $detail['importFileName']);
        $this->assertEquals('Banque Populaire', $detail['importBank']);
    }

    public function test_get_transaction_detail_returns_subcategory_name(): void
    {
        $subcategory = Category::factory()->create([
            'name' => 'Groceries',
            'slug' => 'groceries',
            'parent_id' => $this->food->id,
        ]);

        $tx = Transaction::factory()->create([
            'amount_cents' => -2500, 'date' => '2026-02-10',
            'description' => 'LIDL', 'category_id' => $subcategory->id,
            'account_id' => $this->personal->id,
        ]);

        $service = app(BudgetQueryService::class);
        $detail = $service->getTransactionDetail($tx->id);

        $this->assertEquals('Food', $detail['categoryName']);
        $this->assertEquals('Groceries', $detail['subcategoryName']);
    }

    public function test_get_transaction_detail_returns_null_for_invalid_id(): void
    {
        $service = app(BudgetQueryService::class);
        $detail = $service->getTransactionDetail(99999);

        $this->assertNull($detail);
    }

    public function test_get_transaction_detail_handles_no_import(): void
    {
        $tx = Transaction::factory()->create([
            'amount_cents' => -1500, 'date' => '2026-02-10',
            'description' => 'MANUAL', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id, 'import_id' => null,
        ]);

        $service = app(BudgetQueryService::class);
        $detail = $service->getTransactionDetail($tx->id);

        $this->assertNotNull($detail);
        $this->assertNull($detail['importFileName']);
        $this->assertNull($detail['importBank']);
    }

    // ──────────────────────────────────────────────────
    // GetCategoryTotals MCP tool tests
    // ──────────────────────────────────────────────────

    public function test_get_category_totals_returns_monthly_aggregation(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -5000, 'date' => '2026-02-15',
            'description' => 'CARREFOUR', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -3000, 'date' => '2026-02-20',
            'description' => 'LIDL', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -1500, 'date' => '2026-02-10',
            'description' => 'UBER', 'category_id' => $this->transport->id,
            'account_id' => $this->personal->id,
        ]);

        $response = BudgetServer::actingAs($this->user)
            ->tool(GetCategoryTotals::class, ['year' => 2026, 'month' => 2]);

        $response->assertOk();
        $response->assertSee('food');
        $response->assertSee('transport');
    }

    public function test_get_category_totals_with_account_filter(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -5000, 'date' => '2026-02-15',
            'description' => 'CARREFOUR', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -2000, 'date' => '2026-02-15',
            'description' => 'OFFICE SUPPLY', 'category_id' => $this->food->id,
            'account_id' => $this->professional->id,
        ]);

        $response = BudgetServer::actingAs($this->user)
            ->tool(GetCategoryTotals::class, ['year' => 2026, 'month' => 2, 'account_type' => 'personal']);

        $response->assertOk();
        $response->assertSee('-5000');
        $response->assertDontSee('-2000');
    }

    public function test_get_category_totals_empty_month_returns_valid_response(): void
    {
        $response = BudgetServer::actingAs($this->user)
            ->tool(GetCategoryTotals::class, ['year' => 2099, 'month' => 12]);

        $response->assertOk();
        $response->assertSee('"data":[]');
    }

    // ──────────────────────────────────────────────────
    // QueryTransactions MCP tool tests
    // ──────────────────────────────────────────────────

    public function test_query_transactions_tool_with_category_filter(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -5000, 'date' => '2026-02-15',
            'description' => 'CARREFOUR', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -1200, 'date' => '2026-02-12',
            'description' => 'UBER', 'category_id' => $this->transport->id,
            'account_id' => $this->personal->id,
        ]);

        $response = BudgetServer::actingAs($this->user)
            ->tool(QueryTransactions::class, ['category_id' => $this->food->id]);

        $response->assertOk();
        $response->assertSee('CARREFOUR');
        $response->assertDontSee('UBER');
    }

    public function test_query_transactions_tool_with_date_range(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -3500, 'date' => '2026-01-15',
            'description' => 'JAN TX', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -1200, 'date' => '2026-02-10',
            'description' => 'FEB TX', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);

        $response = BudgetServer::actingAs($this->user)
            ->tool(QueryTransactions::class, [
                'date_from' => '2026-02-01',
                'date_to' => '2026-02-28',
            ]);

        $response->assertOk();
        $response->assertSee('FEB TX');
        $response->assertDontSee('JAN TX');
    }

    public function test_query_transactions_tool_with_account_filter(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -5000, 'date' => '2026-02-15',
            'description' => 'PERSONAL TX', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -2000, 'date' => '2026-02-15',
            'description' => 'PRO TX', 'category_id' => $this->food->id,
            'account_id' => $this->professional->id,
        ]);

        $response = BudgetServer::actingAs($this->user)
            ->tool(QueryTransactions::class, ['account_type' => 'professional']);

        $response->assertOk();
        $response->assertSee('PRO TX');
        $response->assertDontSee('PERSONAL TX');
    }

    public function test_query_transactions_tool_includes_category_and_account_names(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -5000, 'date' => '2026-02-15',
            'description' => 'CARREFOUR', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);

        $response = BudgetServer::actingAs($this->user)
            ->tool(QueryTransactions::class, ['category_id' => $this->food->id]);

        $response->assertOk();
        $response->assertSee('category_name');
        $response->assertSee('account_name');
        $response->assertSee('Food');
        $response->assertSee('Personal Checking');
    }

    public function test_query_transactions_tool_pagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Transaction::factory()->create([
                'amount_cents' => -1000 * $i, 'date' => "2026-02-0{$i}",
                'description' => "TX{$i}", 'category_id' => $this->food->id,
                'account_id' => $this->personal->id,
            ]);
        }

        $response = BudgetServer::actingAs($this->user)
            ->tool(QueryTransactions::class, ['limit' => 2, 'offset' => 0]);

        $response->assertOk();
        $response->assertSee('"total_count":5');
    }

    // ──────────────────────────────────────────────────
    // GetTransactionDetails MCP tool tests
    // ──────────────────────────────────────────────────

    public function test_get_transaction_details_returns_full_record(): void
    {
        $import = Import::factory()->completed()->create([
            'file_name' => 'export_2026_02.csv',
            'bank' => 'Banque Populaire',
        ]);

        $tx = Transaction::factory()->create([
            'amount_cents' => -5200, 'date' => '2026-02-15',
            'description' => 'CARREFOUR CITY', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id, 'import_id' => $import->id,
        ]);

        $response = BudgetServer::actingAs($this->user)
            ->tool(GetTransactionDetails::class, ['transaction_id' => $tx->id]);

        $response->assertOk();
        $response->assertSee('CARREFOUR CITY');
        $response->assertSee('Food');
        $response->assertSee('Personal Checking');
        $response->assertSee('Banque Populaire');
    }

    public function test_get_transaction_details_invalid_id_returns_error(): void
    {
        $response = BudgetServer::actingAs($this->user)
            ->tool(GetTransactionDetails::class, ['transaction_id' => 99999]);

        $response->assertHasErrors();
    }

    // ──────────────────────────────────────────────────
    // GetCategoryBreakdown MCP tool tests
    // ──────────────────────────────────────────────────

    public function test_get_category_breakdown_returns_subcategories_and_transactions(): void
    {
        $subcategory = Category::factory()->create([
            'name' => 'Groceries', 'slug' => 'groceries',
            'parent_id' => $this->food->id,
        ]);

        Transaction::factory()->create([
            'amount_cents' => -5000, 'date' => '2026-02-15',
            'description' => 'CARREFOUR', 'category_id' => $subcategory->id,
            'account_id' => $this->personal->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -2000, 'date' => '2026-02-20',
            'description' => 'MARKET', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);

        $response = BudgetServer::actingAs($this->user)
            ->tool(GetCategoryBreakdown::class, [
                'category_id' => $this->food->id,
                'year' => 2026,
                'month' => 2,
            ]);

        $response->assertOk();
        $response->assertSee('Groceries');
        $response->assertSee('CARREFOUR');
        $response->assertSee('MARKET');
    }

    public function test_get_category_breakdown_with_no_subcategories(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -1500, 'date' => '2026-02-10',
            'description' => 'UBER', 'category_id' => $this->transport->id,
            'account_id' => $this->personal->id,
        ]);

        $response = BudgetServer::actingAs($this->user)
            ->tool(GetCategoryBreakdown::class, [
                'category_id' => $this->transport->id,
                'year' => 2026,
                'month' => 2,
            ]);

        $response->assertOk();
        $response->assertSee('UBER');
    }

    // ──────────────────────────────────────────────────
    // Authentication tests
    // ──────────────────────────────────────────────────

    public function test_all_tools_require_authentication(): void
    {
        $payload = fn (string $toolName, array $args) => [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
            'params' => ['name' => $toolName, 'arguments' => $args],
        ];

        $tools = [
            ['get_category_totals', ['year' => 2026, 'month' => 2]],
            ['query_transactions', []],
            ['get_transaction_details', ['transaction_id' => 1]],
            ['get_category_breakdown', ['category_id' => 1, 'year' => 2026, 'month' => 2]],
        ];

        foreach ($tools as [$name, $args]) {
            $this->postJson('/mcp', $payload($name, $args))
                ->assertUnauthorized();
        }
    }

    // ──────────────────────────────────────────────────
    // Response format tests
    // ──────────────────────────────────────────────────

    public function test_response_format_uses_snake_case_keys(): void
    {
        Transaction::factory()->create([
            'amount_cents' => -5000, 'date' => '2026-02-15',
            'description' => 'CARREFOUR', 'category_id' => $this->food->id,
            'account_id' => $this->personal->id,
        ]);

        $response = BudgetServer::actingAs($this->user)
            ->tool(GetCategoryTotals::class, ['year' => 2026, 'month' => 2]);

        $response->assertOk();
        $response->assertSee('category_id');
        $response->assertSee('category_name');
        $response->assertSee('amount_cents');
        $response->assertSee('transaction_count');
        $response->assertDontSee('amountCents');
        $response->assertDontSee('transactionCount');
    }

    public function test_response_format_uses_data_meta_wrapper(): void
    {
        $response = BudgetServer::actingAs($this->user)
            ->tool(GetCategoryTotals::class, ['year' => 2026, 'month' => 2]);

        $response->assertOk();
        $response->assertSee('"data"');
        $response->assertSee('"meta"');
    }
}
