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
        $this->service = new BudgetQueryService;
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

    public function test_get_monthly_data_filters_by_account_type()
    {
        $this->seed(CategorySeeder::class);
        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        Transaction::factory()->create([
            'amount_cents' => -5000,
            'date' => '2026-02-10',
            'category_id' => $food->id,
            'account_id' => $personalAccount->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -9000,
            'date' => '2026-02-15',
            'category_id' => $food->id,
            'account_id' => $professionalAccount->id,
            'import_id' => $import->id,
        ]);

        $personalResult = $this->service->getMonthlyData(2026, 2, 'personal');
        $this->assertCount(1, $personalResult['categoryTotals']);
        $this->assertEquals(-5000, $personalResult['categoryTotals'][0]['amountCents']);

        $professionalResult = $this->service->getMonthlyData(2026, 2, 'professional');
        $this->assertCount(1, $professionalResult['categoryTotals']);
        $this->assertEquals(-9000, $professionalResult['categoryTotals'][0]['amountCents']);
    }

    public function test_get_available_months_filters_by_account_type()
    {
        $this->seed(CategorySeeder::class);
        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $personalAccount->id,
            'import_id' => $import->id,
            'date' => '2026-01-15',
            'amount_cents' => -5000,
        ]);
        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $professionalAccount->id,
            'import_id' => $import->id,
            'date' => '2026-02-15',
            'amount_cents' => -9000,
        ]);

        $personalMonths = $this->service->getAvailableMonths('personal');
        $this->assertCount(1, $personalMonths);
        $this->assertEquals('2026-01', $personalMonths[0]);

        $professionalMonths = $this->service->getAvailableMonths('professional');
        $this->assertCount(1, $professionalMonths);
        $this->assertEquals('2026-02', $professionalMonths[0]);

        // No filter returns both
        $allMonths = $this->service->getAvailableMonths();
        $this->assertCount(2, $allMonths);
    }

    public function test_get_category_detail_filters_by_account_type()
    {
        $this->seed(CategorySeeder::class);
        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $personalAccount->id,
            'import_id' => $import->id,
            'date' => '2026-02-10',
            'amount_cents' => -5000,
            'description' => 'Personal food',
        ]);
        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $professionalAccount->id,
            'import_id' => $import->id,
            'date' => '2026-02-15',
            'amount_cents' => -9000,
            'description' => 'Professional food',
        ]);

        $personalResult = $this->service->getCategoryDetail($food->id, 2026, 2, 'personal');
        $this->assertCount(1, $personalResult['transactions']);
        $this->assertEquals('Personal food', $personalResult['transactions'][0]['description']);
        $this->assertEquals(-5000, $personalResult['totalAmountCents']);

        $professionalResult = $this->service->getCategoryDetail($food->id, 2026, 2, 'professional');
        $this->assertCount(1, $professionalResult['transactions']);
        $this->assertEquals('Professional food', $professionalResult['transactions'][0]['description']);
        $this->assertEquals(-9000, $professionalResult['totalAmountCents']);
    }

    public function test_subcategory_transactions_roll_up_to_parent()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $fuel = Category::where('slug', 'transport-fuel')->first();

        Transaction::factory()->create([
            'amount_cents' => -3000,
            'date' => '2026-02-10',
            'category_id' => $fuel->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $result = $this->service->getMonthlyData(2026, 2);

        $this->assertCount(1, $result['categoryTotals']);
        $total = $result['categoryTotals'][0];
        $this->assertEquals('transport', $total['slug']);
        $this->assertEquals('Transport', $total['name']);
        $this->assertEquals(-3000, $total['amountCents']);
        $this->assertEquals(1, $total['transactionCount']);
        $this->assertIsInt($total['id']);
    }

    public function test_parent_and_subcategory_transactions_aggregate_together()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $transport = Category::where('slug', 'transport')->first();
        $fuel = Category::where('slug', 'transport-fuel')->first();

        Transaction::factory()->create([
            'amount_cents' => -2000,
            'date' => '2026-02-10',
            'category_id' => $transport->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -3000,
            'date' => '2026-02-11',
            'category_id' => $fuel->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $result = $this->service->getMonthlyData(2026, 2);

        $this->assertCount(1, $result['categoryTotals']);
        $total = $result['categoryTotals'][0];
        $this->assertEquals('transport', $total['slug']);
        $this->assertEquals(-5000, $total['amountCents']);
        $this->assertEquals(2, $total['transactionCount']);
    }

    public function test_income_subcategory_transactions_roll_up_to_income_total()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $salary = Category::where('slug', 'income-salary')->first();

        Transaction::factory()->create([
            'amount_cents' => 150000,
            'date' => '2026-02-05',
            'category_id' => $salary->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $result = $this->service->getMonthlyData(2026, 2);

        $this->assertCount(1, $result['categoryTotals']);
        $this->assertEquals('income', $result['categoryTotals'][0]['slug']);
        $this->assertEquals(150000, $result['incomeCents']);
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
