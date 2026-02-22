<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('home'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('home'));
        $response->assertOk();
    }

    public function test_dashboard_returns_correct_inertia_props()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->has('categoryTotals')
                ->has('incomeCents')
                ->has('expensesCents')
                ->has('currentMonth')
                ->has('currentAccount')
                ->has('availableMonths')
            );
    }

    public function test_category_totals_are_sorted_by_absolute_amount_descending()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();

        $food = Category::where('slug', 'food')->first();
        $restaurants = Category::where('slug', 'restaurants')->first();
        $transport = Category::where('slug', 'transport')->first();

        $now = now()->format('Y-m');

        Transaction::factory()->create([
            'amount_cents' => -50000,
            'date' => "{$now}-10",
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -20000,
            'date' => "{$now}-15",
            'category_id' => $restaurants->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -80000,
            'date' => "{$now}-20",
            'category_id' => $transport->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('categoryTotals.0.slug', 'transport')
                ->where('categoryTotals.1.slug', 'food')
                ->where('categoryTotals.2.slug', 'restaurants')
            );
    }

    public function test_income_cents_is_sum_of_income_category_transactions()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $income = Category::where('slug', 'income')->first();
        $now = now()->format('Y-m');

        Transaction::factory()->create([
            'amount_cents' => 150000,
            'date' => "{$now}-05",
            'category_id' => $income->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => 50000,
            'date' => "{$now}-20",
            'category_id' => $income->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('incomeCents', 200000)
            );
    }

    public function test_expenses_cents_is_absolute_value_of_non_income_transactions()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $transport = Category::where('slug', 'transport')->first();
        $income = Category::where('slug', 'income')->first();
        $now = now()->format('Y-m');

        Transaction::factory()->create([
            'amount_cents' => -30000,
            'date' => "{$now}-10",
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -20000,
            'date' => "{$now}-15",
            'category_id' => $transport->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        // Income should NOT be counted in expenses
        Transaction::factory()->create([
            'amount_cents' => 100000,
            'date' => "{$now}-01",
            'category_id' => $income->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('expensesCents', 50000)
            );
    }

    public function test_only_returns_transactions_for_requested_month()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
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

        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-01']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->has('categoryTotals', 1)
                ->where('categoryTotals.0.amountCents', -10000)
            );
    }

    public function test_month_param_returns_data_for_specified_month()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        Transaction::factory()->create([
            'amount_cents' => -45000,
            'date' => '2026-01-20',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-01']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('currentMonth', '2026-01')
                ->where('categoryTotals.0.amountCents', -45000)
            );
    }

    public function test_invalid_month_param_falls_back_to_current_month()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home', ['month' => 'invalid']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('currentMonth', now()->format('Y-m'))
            );
    }

    public function test_out_of_range_month_param_falls_back_to_current_month()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-13']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('currentMonth', now()->format('Y-m'))
            );
    }

    public function test_empty_state_when_no_transactions_for_month()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home', ['month' => '2025-06']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('categoryTotals', [])
                ->where('incomeCents', 0)
                ->where('expensesCents', 0)
            );
    }

    public function test_dashboard_returns_available_months_prop()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => '2026-01-15',
            'amount_cents' => -5000,
        ]);
        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => '2026-02-10',
            'amount_cents' => -3000,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->has('availableMonths', 2)
                ->where('availableMonths.0', '2026-01')
                ->where('availableMonths.1', '2026-02')
            );
    }

    public function test_dashboard_returns_empty_available_months_when_no_transactions()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->has('availableMonths', 0)
            );
    }

    public function test_dashboard_available_months_ordered_ascending()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Create in reverse order to verify ordering
        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => '2026-03-10',
            'amount_cents' => -1000,
        ]);
        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => '2026-01-15',
            'amount_cents' => -2000,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->has('availableMonths', 2)
                ->where('availableMonths.0', '2026-01')
                ->where('availableMonths.1', '2026-03')
            );
    }

    public function test_dashboard_defaults_to_personal_account()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $now = now()->format('Y-m');

        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $personalAccount->id,
            'import_id' => $import->id,
            'date' => "{$now}-10",
            'amount_cents' => -5000,
        ]);
        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $professionalAccount->id,
            'import_id' => $import->id,
            'date' => "{$now}-15",
            'amount_cents' => -9000,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('currentAccount', 'personal')
                ->has('categoryTotals', 1)
                ->where('categoryTotals.0.amountCents', -5000)
            );
    }

    public function test_dashboard_professional_filter_excludes_personal()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $now = now()->format('Y-m');

        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $personalAccount->id,
            'import_id' => $import->id,
            'date' => "{$now}-10",
            'amount_cents' => -5000,
        ]);
        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $professionalAccount->id,
            'import_id' => $import->id,
            'date' => "{$now}-15",
            'amount_cents' => -9000,
        ]);

        $this->actingAs($user)
            ->get(route('home', ['account' => 'professional']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('currentAccount', 'professional')
                ->has('categoryTotals', 1)
                ->where('categoryTotals.0.amountCents', -9000)
            );
    }

    public function test_dashboard_personal_filter_excludes_professional()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $now = now()->format('Y-m');

        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $personalAccount->id,
            'import_id' => $import->id,
            'date' => "{$now}-10",
            'amount_cents' => -5000,
        ]);
        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $professionalAccount->id,
            'import_id' => $import->id,
            'date' => "{$now}-15",
            'amount_cents' => -9000,
        ]);

        $this->actingAs($user)
            ->get(route('home', ['account' => 'personal']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->has('categoryTotals', 1)
                ->where('categoryTotals.0.amountCents', -5000)
            );
    }

    public function test_dashboard_invalid_account_defaults_to_personal()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home', ['account' => 'invalid']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('currentAccount', 'personal')
            );
    }

    public function test_dashboard_account_and_month_filters_combine()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
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
            'date' => '2026-01-20',
            'amount_cents' => -9000,
        ]);
        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $personalAccount->id,
            'import_id' => $import->id,
            'date' => '2026-02-15',
            'amount_cents' => -3000,
        ]);

        // January + Professional = only the 9000 transaction
        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-01', 'account' => 'professional']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('currentMonth', '2026-01')
                ->where('currentAccount', 'professional')
                ->has('categoryTotals', 1)
                ->where('categoryTotals.0.amountCents', -9000)
            );
    }

    public function test_available_months_scoped_to_account()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
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

        // Personal view should only see January
        $this->actingAs($user)
            ->get(route('home', ['account' => 'personal']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->has('availableMonths', 1)
                ->where('availableMonths.0', '2026-01')
            );
    }

    public function test_shared_props_include_account_contexts()
    {
        $user = User::factory()->create();
        Account::factory()->personal()->create();
        Account::factory()->professional()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->has('accountContexts', 2)
            );
    }

    public function test_dashboard_empty_month_returns_zero_totals_with_available_months()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => '2026-01-15',
            'amount_cents' => -5000,
        ]);

        // Request March (no data)
        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-03']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('currentMonth', '2026-03')
                ->has('categoryTotals', 0)
                ->where('incomeCents', 0)
                ->where('expensesCents', 0)
                ->has('availableMonths', 1)
                ->where('availableMonths.0', '2026-01')
            );
    }

    // --- Average Data Tests ---

    public function test_average_data_deferred_prop_returns_correct_structure()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $income = Category::where('slug', 'income')->first();

        // Seed 3 past months
        foreach (['2025-10-15', '2025-11-15', '2025-12-15'] as $date) {
            Transaction::factory()->create(['amount_cents' => -30000, 'date' => $date, 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
            Transaction::factory()->create(['amount_cents' => 150000, 'date' => $date, 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        }

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->loadDeferredProps('averages', fn (Assert $page) => $page
                    ->has('averageData.avgIncomeCents')
                    ->has('averageData.avgExpensesCents')
                    ->has('averageData.categoryAverages')
                    ->has('averageData.completeMonthCount')
                )
            );
    }

    public function test_average_data_includes_per_category_averages()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $transport = Category::where('slug', 'transport')->first();

        // 2 past months of data
        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -50000, 'date' => '2025-11-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -20000, 'date' => '2025-10-15', 'category_id' => $transport->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -40000, 'date' => '2025-11-15', 'category_id' => $transport->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->loadDeferredProps('averages', fn (Assert $page) => $page
                    ->where('averageData.categoryAverages.food.avgAmountCents', -40000)
                    ->where('averageData.categoryAverages.transport.avgAmountCents', -30000)
                )
            );
    }

    public function test_average_data_excludes_current_month()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $now = now()->format('Y-m');

        // 2 past months
        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-11-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        // Current month (should be excluded from average)
        Transaction::factory()->create(['amount_cents' => -999999, 'date' => "{$now}-15", 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->loadDeferredProps('averages', fn (Assert $page) => $page
                    ->where('averageData.completeMonthCount', 2)
                    ->where('averageData.categoryAverages.food.avgAmountCents', -30000)
                )
            );
    }

    public function test_average_data_respects_account_filter()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Personal: 2 months
        Transaction::factory()->create(['amount_cents' => -10000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $personalAccount->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -20000, 'date' => '2025-11-15', 'category_id' => $food->id, 'account_id' => $personalAccount->id, 'import_id' => $import->id]);

        // Professional: 2 months (should not affect personal)
        Transaction::factory()->create(['amount_cents' => -50000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $professionalAccount->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -60000, 'date' => '2025-11-15', 'category_id' => $food->id, 'account_id' => $professionalAccount->id, 'import_id' => $import->id]);

        // Personal avg: (-10000 + -20000) / 2 = -15000
        $this->actingAs($user)
            ->get(route('home', ['account' => 'personal']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->loadDeferredProps('averages', fn (Assert $page) => $page
                    ->where('averageData.categoryAverages.food.avgAmountCents', -15000)
                )
            );
    }

    public function test_average_data_null_when_fewer_than_two_months()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Only 1 past month
        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->loadDeferredProps('averages', fn (Assert $page) => $page
                    ->where('averageData', null)
                )
            );
    }

    public function test_average_data_absent_for_category_only_in_current_month()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $transport = Category::where('slug', 'transport')->first();
        $now = now()->format('Y-m');

        // Food in 2 past months (will have an average)
        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
        Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-11-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        // Transport ONLY in current month (should have no average)
        Transaction::factory()->create(['amount_cents' => -15000, 'date' => "{$now}-10", 'category_id' => $transport->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->loadDeferredProps('averages', fn (Assert $page) => $page
                    ->has('averageData.categoryAverages.food')
                    ->missing('averageData.categoryAverages.transport')
                )
            );
    }

    public function test_category_totals_no_longer_include_trend_percentage()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $now = now()->format('Y-m');

        Transaction::factory()->create(['amount_cents' => -10000, 'date' => "{$now}-15", 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->missing('categoryTotals.0.trendPercentage')
            );
    }
}
