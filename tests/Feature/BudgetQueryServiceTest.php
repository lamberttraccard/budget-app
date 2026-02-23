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

    // --- Monthly Averages Tests ---

    public function test_monthly_averages_returns_correct_structure()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Seed 3 months of data (past months, not current)
        foreach (['2025-10-15', '2025-11-15', '2025-12-15'] as $date) {
            Transaction::factory()->create([
                'amount_cents' => -30000,
                'date' => $date,
                'category_id' => $food->id,
                'account_id' => $account->id,
                'import_id' => $import->id,
            ]);
        }

        $result = $this->service->getMonthlyAverages();

        $this->assertArrayHasKey('avgIncomeCents', $result);
        $this->assertArrayHasKey('avgExpensesCents', $result);
        $this->assertArrayHasKey('categoryAverages', $result);
        $this->assertArrayHasKey('completeMonthCount', $result);
        $this->assertIsInt($result['avgIncomeCents']);
        $this->assertIsInt($result['avgExpensesCents']);
        $this->assertIsArray($result['categoryAverages']);
        $this->assertIsInt($result['completeMonthCount']);
    }

    public function test_monthly_averages_calculates_correct_values()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $income = Category::where('slug', 'income')->first();

        // 3 months: Oct, Nov, Dec 2025
        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -60000, 'date' => '2025-11-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -90000, 'date' => '2025-12-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        Transaction::factory()->create(['amount_cents' => 150000, 'date' => '2025-10-05', 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => 150000, 'date' => '2025-11-05', 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => 150000, 'date' => '2025-12-05', 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $result = $this->service->getMonthlyAverages();

        // Food total = -180000, avg = -60000 per month
        $this->assertEquals(-60000, $result['categoryAverages']['food']['avgAmountCents']);
        // Income total = 450000, avg = 150000 per month
        $this->assertEquals(150000, $result['categoryAverages']['income']['avgAmountCents']);
        $this->assertEquals(150000, $result['avgIncomeCents']);
        // Expenses avg = abs(-180000) / 3 = 60000
        $this->assertEquals(60000, $result['avgExpensesCents']);
        $this->assertEquals(3, $result['completeMonthCount']);
    }

    public function test_monthly_averages_excludes_current_month()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        $now = now()->format('Y-m');

        // Past months
        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-11-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        // Current month — should be excluded
        Transaction::factory()->create(['amount_cents' => -999999, 'date' => "{$now}-15", 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $result = $this->service->getMonthlyAverages();

        $this->assertEquals(2, $result['completeMonthCount']);
        // Average of -30000 + -30000 over 2 months = -30000
        $this->assertEquals(-30000, $result['categoryAverages']['food']['avgAmountCents']);
    }

    public function test_monthly_averages_filters_by_account_type()
    {
        $this->seed(CategorySeeder::class);
        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Personal: 2 months
        Transaction::factory()->create(['amount_cents' => -10000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $personalAccount->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -20000, 'date' => '2025-11-15', 'category_id' => $food->id, 'account_id' => $personalAccount->id, 'import_id' => $import->id]);

        // Professional: 2 months
        Transaction::factory()->create(['amount_cents' => -50000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $professionalAccount->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -60000, 'date' => '2025-11-15', 'category_id' => $food->id, 'account_id' => $professionalAccount->id, 'import_id' => $import->id]);

        $personal = $this->service->getMonthlyAverages('personal');
        $this->assertEquals(-15000, $personal['categoryAverages']['food']['avgAmountCents']);

        $professional = $this->service->getMonthlyAverages('professional');
        $this->assertEquals(-55000, $professional['categoryAverages']['food']['avgAmountCents']);
    }

    public function test_monthly_averages_returns_empty_when_insufficient_data()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Only 1 past month (need >= 2)
        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $result = $this->service->getMonthlyAverages();

        $this->assertLessThan(2, $result['completeMonthCount']);
        $this->assertEquals(0, $result['avgIncomeCents']);
        $this->assertEquals(0, $result['avgExpensesCents']);
        $this->assertEmpty($result['categoryAverages']);
    }

    public function test_monthly_averages_rolls_up_subcategories()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $fuel = Category::where('slug', 'transport-fuel')->first();
        $transport = Category::where('slug', 'transport')->first();

        // Subcategory transaction in 2 months
        Transaction::factory()->create(['amount_cents' => -10000, 'date' => '2025-10-15', 'category_id' => $fuel->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -20000, 'date' => '2025-11-15', 'category_id' => $fuel->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        // Parent category transaction
        Transaction::factory()->create(['amount_cents' => -5000, 'date' => '2025-10-15', 'category_id' => $transport->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -5000, 'date' => '2025-11-15', 'category_id' => $transport->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $result = $this->service->getMonthlyAverages();

        // Should roll up to parent: total = -40000 over 2 months = -20000 avg
        $this->assertArrayHasKey('transport', $result['categoryAverages']);
        $this->assertArrayNotHasKey('transport-fuel', $result['categoryAverages']);
        $this->assertEquals(-20000, $result['categoryAverages']['transport']['avgAmountCents']);
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

    // --- Monthly Sparkline Data Tests ---

    public function test_sparkline_data_returns_correct_structure()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $income = Category::where('slug', 'income')->first();

        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => 150000, 'date' => '2025-10-05', 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $result = $this->service->getMonthlyExpenseAndIncomeTotals('personal');

        $this->assertArrayHasKey('monthlyIncomeTotals', $result);
        $this->assertArrayHasKey('monthlyExpenseTotals', $result);
        $this->assertIsArray($result['monthlyIncomeTotals']);
        $this->assertIsArray($result['monthlyExpenseTotals']);
    }

    public function test_sparkline_data_separates_income_and_expenses()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $transport = Category::where('slug', 'transport')->first();
        $income = Category::where('slug', 'income')->first();

        // Oct 2025: income 150000, expenses -30000 food + -20000 transport = 50000
        Transaction::factory()->create(['amount_cents' => 150000, 'date' => '2025-10-05', 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-10', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -20000, 'date' => '2025-10-15', 'category_id' => $transport->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        // Nov 2025: income 200000, expenses -40000 food
        Transaction::factory()->create(['amount_cents' => 200000, 'date' => '2025-11-05', 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -40000, 'date' => '2025-11-10', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $result = $this->service->getMonthlyExpenseAndIncomeTotals('personal');

        $this->assertCount(2, $result['monthlyIncomeTotals']);
        $this->assertEquals('2025-10', $result['monthlyIncomeTotals'][0]['month']);
        $this->assertEquals(150000, $result['monthlyIncomeTotals'][0]['amountCents']);
        $this->assertEquals('2025-11', $result['monthlyIncomeTotals'][1]['month']);
        $this->assertEquals(200000, $result['monthlyIncomeTotals'][1]['amountCents']);

        $this->assertCount(2, $result['monthlyExpenseTotals']);
        $this->assertEquals('2025-10', $result['monthlyExpenseTotals'][0]['month']);
        $this->assertEquals(50000, $result['monthlyExpenseTotals'][0]['amountCents']); // absolute value
        $this->assertEquals('2025-11', $result['monthlyExpenseTotals'][1]['month']);
        $this->assertEquals(40000, $result['monthlyExpenseTotals'][1]['amountCents']);
    }

    public function test_sparkline_data_caps_at_12_months()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Create 14 months of data
        for ($i = 0; $i < 14; $i++) {
            $date = now()->subMonths(14 - $i)->startOfMonth()->addDays(14)->toDateString();
            Transaction::factory()->create(['amount_cents' => -10000, 'date' => $date, 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        }

        $result = $this->service->getMonthlyExpenseAndIncomeTotals('personal');

        $this->assertLessThanOrEqual(12, count($result['monthlyExpenseTotals']));
    }

    public function test_sparkline_data_filters_by_account_type()
    {
        $this->seed(CategorySeeder::class);
        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        Transaction::factory()->create(['amount_cents' => -10000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $personalAccount->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -50000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $professionalAccount->id, 'import_id' => $import->id]);

        $personal = $this->service->getMonthlyExpenseAndIncomeTotals('personal');
        $this->assertEquals(10000, $personal['monthlyExpenseTotals'][0]['amountCents']);

        $professional = $this->service->getMonthlyExpenseAndIncomeTotals('professional');
        $this->assertEquals(50000, $professional['monthlyExpenseTotals'][0]['amountCents']);
    }

    public function test_sparkline_data_returns_empty_arrays_with_no_data()
    {
        $result = $this->service->getMonthlyExpenseAndIncomeTotals('personal');

        $this->assertEmpty($result['monthlyIncomeTotals']);
        $this->assertEmpty($result['monthlyExpenseTotals']);
    }

    // --- Transfer Exclusion Tests ---

    public function test_transfer_excluded_from_expenses_in_monthly_data()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $transfer = Category::where('slug', 'transfer')->first();
        $income = Category::where('slug', 'income')->first();

        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2026-02-10', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -50000, 'date' => '2026-02-12', 'category_id' => $transfer->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => 100000, 'date' => '2026-02-01', 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $result = $this->service->getMonthlyData(2026, 2);

        // Transfer excluded from expenses
        $this->assertEquals(30000, $result['expensesCents']);
        // Income unaffected
        $this->assertEquals(100000, $result['incomeCents']);
        // Transfer still in categoryTotals
        $transferTotal = collect($result['categoryTotals'])->firstWhere('slug', 'transfer');
        $this->assertNotNull($transferTotal);
        $this->assertEquals(-50000, $transferTotal['amountCents']);
    }

    public function test_transfer_excluded_from_monthly_averages()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $transfer = Category::where('slug', 'transfer')->first();
        $income = Category::where('slug', 'income')->first();

        foreach (['2025-10-15', '2025-11-15'] as $date) {
            Transaction::factory()->create(['amount_cents' => -30000, 'date' => $date, 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
            Transaction::factory()->create(['amount_cents' => -50000, 'date' => $date, 'category_id' => $transfer->id, 'account_id' => $account->id, 'import_id' => $import->id]);
            Transaction::factory()->create(['amount_cents' => 150000, 'date' => $date, 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        }

        $result = $this->service->getMonthlyAverages();

        // Only food in expenses avg, transfer excluded
        $this->assertEquals(30000, $result['avgExpensesCents']);
        $this->assertEquals(150000, $result['avgIncomeCents']);
        // Transfer still has a categoryAverages entry
        $this->assertArrayHasKey('transfer', $result['categoryAverages']);
    }

    public function test_transfer_excluded_from_sparkline_expenses()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $transfer = Category::where('slug', 'transfer')->first();
        $income = Category::where('slug', 'income')->first();

        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -50000, 'date' => '2025-10-15', 'category_id' => $transfer->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => 150000, 'date' => '2025-10-05', 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $result = $this->service->getMonthlyExpenseAndIncomeTotals('personal');

        $this->assertCount(1, $result['monthlyExpenseTotals']);
        // Only food expense, transfer excluded
        $this->assertEquals(30000, $result['monthlyExpenseTotals'][0]['amountCents']);
        $this->assertEquals(150000, $result['monthlyIncomeTotals'][0]['amountCents']);
    }

    public function test_transfer_excluded_from_account_summary_expenses()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $transfer = Category::where('slug', 'transfer')->first();
        $income = Category::where('slug', 'income')->first();

        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2026-02-10', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -50000, 'date' => '2026-02-12', 'category_id' => $transfer->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => 100000, 'date' => '2026-02-01', 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $result = $this->service->getAccountSummary(2026, 2);

        $this->assertEquals(30000, $result['totalExpensesCents']);
        $this->assertEquals(100000, $result['totalIncomeCents']);
        $this->assertEquals(30000, $result['accounts'][0]['expensesCents']);
    }

    public function test_sparkline_data_includes_months_with_zero_for_missing_side()
    {
        $this->seed(CategorySeeder::class);
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Only expenses in Oct, no income
        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $result = $this->service->getMonthlyExpenseAndIncomeTotals('personal');

        $this->assertCount(1, $result['monthlyExpenseTotals']);
        $this->assertEquals(30000, $result['monthlyExpenseTotals'][0]['amountCents']);
        // Income should have an entry for Oct with 0
        $this->assertCount(1, $result['monthlyIncomeTotals']);
        $this->assertEquals(0, $result['monthlyIncomeTotals'][0]['amountCents']);
    }
}
