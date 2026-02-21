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

    // --- Trend Percentage Tests (Story 6.1) ---

    public function test_trend_percentage_positive_when_spending_increased()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // January: Food = -38000 (abs: 38000)
        Transaction::factory()->create([
            'amount_cents' => -38000,
            'date' => '2026-01-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        // February: Food = -42000 (abs: 42000, spending increased)
        Transaction::factory()->create([
            'amount_cents' => -42000,
            'date' => '2026-02-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        // ((42000 - 38000) / 38000) * 100 = (4000/38000)*100 = 10.526... ≈ 11
        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-02']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('categoryTotals.0.trendPercentage', 11)
            );
    }

    public function test_trend_percentage_negative_when_spending_decreased()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // January: Food = -50000 (abs: 50000)
        Transaction::factory()->create([
            'amount_cents' => -50000,
            'date' => '2026-01-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        // February: Food = -40000 (abs: 40000, spending decreased)
        Transaction::factory()->create([
            'amount_cents' => -40000,
            'date' => '2026-02-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        // ((40000 - 50000) / 50000) * 100 = (-10000/50000)*100 = -20
        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-02']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('categoryTotals.0.trendPercentage', -20)
            );
    }

    public function test_trend_percentage_zero_when_spending_unchanged()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // January: Food = -15000
        Transaction::factory()->create([
            'amount_cents' => -15000,
            'date' => '2026-01-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        // February: Food = -15000
        Transaction::factory()->create([
            'amount_cents' => -15000,
            'date' => '2026-02-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-02']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('categoryTotals.0.trendPercentage', 0)
            );
    }

    public function test_trend_percentage_null_when_category_not_in_previous_month()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Only February data, no January
        Transaction::factory()->create([
            'amount_cents' => -30000,
            'date' => '2026-02-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-02']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('categoryTotals.0.trendPercentage', null)
            );
    }

    public function test_trend_percentage_null_when_previous_month_amount_is_zero()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // January: Food = 0 (two transactions that cancel out)
        Transaction::factory()->create([
            'amount_cents' => -10000,
            'date' => '2026-01-10',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => 10000,
            'date' => '2026-01-20',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        // February: Food = -5000
        Transaction::factory()->create([
            'amount_cents' => -5000,
            'date' => '2026-02-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-02']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('categoryTotals.0.trendPercentage', null)
            );
    }

    public function test_trend_percentage_january_uses_december_previous_year()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // December 2025: Food = -20000
        Transaction::factory()->create([
            'amount_cents' => -20000,
            'date' => '2025-12-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        // January 2026: Food = -30000
        Transaction::factory()->create([
            'amount_cents' => -30000,
            'date' => '2026-01-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        // abs: ((30000 - 20000) / 20000) * 100 = (10000/20000)*100 = 50
        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-01']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('categoryTotals.0.trendPercentage', 50)
            );
    }

    public function test_trend_percentage_account_filter_applies_to_both_months()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // January: Personal Food = -10000, Professional Food = -50000
        Transaction::factory()->create([
            'amount_cents' => -10000,
            'date' => '2026-01-15',
            'category_id' => $food->id,
            'account_id' => $personalAccount->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -50000,
            'date' => '2026-01-15',
            'category_id' => $food->id,
            'account_id' => $professionalAccount->id,
            'import_id' => $import->id,
        ]);
        // February: Personal Food = -20000
        Transaction::factory()->create([
            'amount_cents' => -20000,
            'date' => '2026-02-15',
            'category_id' => $food->id,
            'account_id' => $personalAccount->id,
            'import_id' => $import->id,
        ]);

        // Personal account abs: ((20000 - 10000) / 10000) * 100 = (10000/10000)*100 = 100
        // Should NOT use the professional January amount
        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-02', 'account' => 'personal']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('categoryTotals.0.trendPercentage', 100)
            );
    }

    public function test_trend_percentage_included_in_category_totals_props()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();
        $now = now()->format('Y-m');

        Transaction::factory()->create([
            'amount_cents' => -10000,
            'date' => "{$now}-15",
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->has('categoryTotals.0.trendPercentage')
            );
    }

    public function test_trend_percentage_correct_for_income_category_with_positive_amounts()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $income = Category::where('slug', 'income')->first();

        // January: Income = 200000
        Transaction::factory()->create([
            'amount_cents' => 200000,
            'date' => '2026-01-15',
            'category_id' => $income->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        // February: Income = 250000
        Transaction::factory()->create([
            'amount_cents' => 250000,
            'date' => '2026-02-15',
            'category_id' => $income->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        // ((250000 - 200000) / 200000) * 100 = 25
        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-02']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('categoryTotals.0.trendPercentage', 25)
            );
    }

    public function test_has_previous_month_data_true_when_previous_month_has_transactions()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        Transaction::factory()->create([
            'amount_cents' => -10000,
            'date' => '2026-01-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'amount_cents' => -20000,
            'date' => '2026-02-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-02']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('hasPreviousMonthData', true)
            );
    }

    public function test_has_previous_month_data_false_when_previous_month_is_empty()
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Only February data, no January
        Transaction::factory()->create([
            'amount_cents' => -20000,
            'date' => '2026-02-15',
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('home', ['month' => '2026-02']))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('hasPreviousMonthData', false)
            );
    }
}
