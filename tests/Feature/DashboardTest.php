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
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_returns_correct_inertia_props()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
                     ->has('categoryTotals')
                     ->has('incomeCents')
                     ->has('expensesCents')
                     ->has('currentMonth')
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
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
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
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
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
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
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
            ->get(route('dashboard', ['month' => '2026-01']))
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
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
            ->get(route('dashboard', ['month' => '2026-01']))
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
                     ->where('currentMonth', '2026-01')
                     ->where('categoryTotals.0.amountCents', -45000)
            );
    }

    public function test_invalid_month_param_falls_back_to_current_month()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => 'invalid']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
                     ->where('currentMonth', now()->format('Y-m'))
            );
    }

    public function test_out_of_range_month_param_falls_back_to_current_month()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2026-13']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
                     ->where('currentMonth', now()->format('Y-m'))
            );
    }

    public function test_empty_state_when_no_transactions_for_month()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['month' => '2025-06']))
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
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
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
                     ->has('availableMonths', 2)
                     ->where('availableMonths.0', '2026-01')
                     ->where('availableMonths.1', '2026-02')
            );
    }

    public function test_dashboard_returns_empty_available_months_when_no_transactions()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
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
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
                     ->has('availableMonths', 2)
                     ->where('availableMonths.0', '2026-01')
                     ->where('availableMonths.1', '2026-03')
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
            ->get(route('dashboard', ['month' => '2026-03']))
            ->assertInertia(fn (Assert $page) =>
                $page->component('dashboard')
                     ->where('currentMonth', '2026-03')
                     ->has('categoryTotals', 0)
                     ->where('incomeCents', 0)
                     ->where('expensesCents', 0)
                     ->has('availableMonths', 1)
                     ->where('availableMonths.0', '2026-01')
            );
    }
}
