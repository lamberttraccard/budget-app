<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\Transaction;
use App\Services\BudgetQueryService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private BudgetQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BudgetQueryService();
    }

    public function test_returns_correct_structure()
    {
        $result = $this->service->getMonthlyData(2026, 2);

        $this->assertArrayHasKey('categoryTotals', $result);
        $this->assertArrayHasKey('incomeCents', $result);
        $this->assertArrayHasKey('expensesCents', $result);
        $this->assertIsArray($result['categoryTotals']);
        $this->assertIsInt($result['incomeCents']);
        $this->assertIsInt($result['expensesCents']);
    }

    public function test_category_totals_include_required_fields()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        Transaction::factory()->create([
            'amount_cents' => -5000,
            'date' => '2026-02-10',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $result = $this->service->getMonthlyData(2026, 2);

        $this->assertCount(1, $result['categoryTotals']);
        $total = $result['categoryTotals'][0];
        $this->assertArrayHasKey('id', $total);
        $this->assertArrayHasKey('name', $total);
        $this->assertArrayHasKey('slug', $total);
        $this->assertArrayHasKey('color', $total);
        $this->assertArrayHasKey('amountCents', $total);
        $this->assertArrayHasKey('transactionCount', $total);
        $this->assertEquals('food', $total['slug']);
        $this->assertEquals(-5000, $total['amountCents']);
        $this->assertEquals(1, $total['transactionCount']);
    }

    public function test_categories_with_no_transactions_are_excluded()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        Transaction::factory()->create([
            'amount_cents' => -3000,
            'date' => '2026-02-10',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $result = $this->service->getMonthlyData(2026, 2);

        // Only food should appear, not all 14 seeded categories
        $this->assertCount(1, $result['categoryTotals']);
        $this->assertEquals('food', $result['categoryTotals'][0]['slug']);
    }

    public function test_income_cents_correctly_sums_income_category()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $income = Category::where('slug', 'income')->first();

        Transaction::factory()->create([
            'amount_cents' => 150000,
            'date' => '2026-02-05',
            'category_id' => $income->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => 50000,
            'date' => '2026-02-20',
            'category_id' => $income->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $result = $this->service->getMonthlyData(2026, 2);

        $this->assertEquals(200000, $result['incomeCents']);
    }

    public function test_expenses_cents_is_absolute_value_of_non_income_sums()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $transport = Category::where('slug', 'transport')->first();
        $income = Category::where('slug', 'income')->first();

        Transaction::factory()->create([
            'amount_cents' => -30000,
            'date' => '2026-02-10',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -20000,
            'date' => '2026-02-15',
            'category_id' => $transport->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => 100000,
            'date' => '2026-02-01',
            'category_id' => $income->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $result = $this->service->getMonthlyData(2026, 2);

        $this->assertEquals(50000, $result['expensesCents']);
    }

    public function test_sorting_by_highest_absolute_amount_first()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $transport = Category::where('slug', 'transport')->first();
        $income = Category::where('slug', 'income')->first();

        Transaction::factory()->create([
            'amount_cents' => -10000,
            'date' => '2026-02-10',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -50000,
            'date' => '2026-02-15',
            'category_id' => $transport->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => 200000,
            'date' => '2026-02-01',
            'category_id' => $income->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $result = $this->service->getMonthlyData(2026, 2);

        // Income (200000) > Transport (50000) > Food (10000) by absolute value
        $this->assertEquals('income', $result['categoryTotals'][0]['slug']);
        $this->assertEquals('transport', $result['categoryTotals'][1]['slug']);
        $this->assertEquals('food', $result['categoryTotals'][2]['slug']);
    }

    public function test_month_filtering_only_includes_specified_month()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // January transaction
        Transaction::factory()->create([
            'amount_cents' => -10000,
            'date' => '2026-01-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        // February transaction
        Transaction::factory()->create([
            'amount_cents' => -20000,
            'date' => '2026-02-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $janResult = $this->service->getMonthlyData(2026, 1);
        $febResult = $this->service->getMonthlyData(2026, 2);

        $this->assertCount(1, $janResult['categoryTotals']);
        $this->assertEquals(-10000, $janResult['categoryTotals'][0]['amountCents']);

        $this->assertCount(1, $febResult['categoryTotals']);
        $this->assertEquals(-20000, $febResult['categoryTotals'][0]['amountCents']);
    }
}
