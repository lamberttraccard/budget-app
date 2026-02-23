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

class RecurringControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('recurring'))
            ->assertRedirect(route('login'));
    }

    public function test_recurring_page_renders(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Create recurring transactions (3 consecutive months, same amount)
        foreach (['2026-01-15', '2026-02-15', '2026-03-15'] as $date) {
            Transaction::factory()->create([
                'description' => 'Netflix',
                'date' => $date,
                'amount_cents' => -1599,
                'category_id' => $food->id,
                'account_id' => $account->id,
                'import_id' => $import->id,
            ]);
        }

        $this->actingAs($user)
            ->get(route('recurring'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('recurring')
                ->where('selectedAccountIds', [])
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->has('recurringMerchants', 1)
                )
            );
    }

    public function test_recurring_page_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('recurring'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('recurring')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->has('recurringMerchants', 0)
                )
            );
    }

    public function test_recurring_page_filters_by_account(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $personalAccount = Account::factory()->create();
        $proAccount = Account::factory()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Personal recurring (3 consecutive months)
        foreach (['2026-01-15', '2026-02-15', '2026-03-15'] as $date) {
            Transaction::factory()->create([
                'description' => 'Netflix',
                'date' => $date,
                'amount_cents' => -1599,
                'category_id' => $food->id,
                'account_id' => $personalAccount->id,
                'import_id' => $import->id,
            ]);
        }

        // Professional recurring (3 consecutive months)
        foreach (['2026-01-15', '2026-02-15', '2026-03-15'] as $date) {
            Transaction::factory()->create([
                'description' => 'Office 365',
                'date' => $date,
                'amount_cents' => -2200,
                'category_id' => $food->id,
                'account_id' => $proAccount->id,
                'import_id' => $import->id,
            ]);
        }

        $this->actingAs($user)
            ->get(route('recurring', ['accounts' => $personalAccount->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('recurring')
                ->where('selectedAccountIds', [$personalAccount->id])
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->has('recurringMerchants', 1)
                )
            );
    }
}
