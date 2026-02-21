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
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Create recurring transactions (Jan + Feb same merchant)
        Transaction::factory()->create([
            'description' => 'Netflix',
            'date' => '2026-01-15',
            'amount_cents' => -1599,
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'description' => 'Netflix',
            'date' => '2026-02-15',
            'amount_cents' => -1599,
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('recurring'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('recurring')
                ->has('recurringMerchants', 1)
                ->where('currentAccount', null)
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
                ->has('recurringMerchants', 0)
            );
    }

    public function test_recurring_page_filters_by_account(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $personalAccount = Account::factory()->personal()->create();
        $proAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Personal recurring
        Transaction::factory()->create([
            'description' => 'Netflix',
            'date' => '2026-01-15',
            'amount_cents' => -1599,
            'category_id' => $food->id,
            'account_id' => $personalAccount->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'description' => 'Netflix',
            'date' => '2026-02-15',
            'amount_cents' => -1599,
            'category_id' => $food->id,
            'account_id' => $personalAccount->id,
            'import_id' => $import->id,
        ]);

        // Professional recurring
        Transaction::factory()->create([
            'description' => 'Office 365',
            'date' => '2026-01-15',
            'amount_cents' => -2200,
            'category_id' => $food->id,
            'account_id' => $proAccount->id,
            'import_id' => $import->id,
        ]);
        Transaction::factory()->create([
            'description' => 'Office 365',
            'date' => '2026-02-15',
            'amount_cents' => -2200,
            'category_id' => $food->id,
            'account_id' => $proAccount->id,
            'import_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('recurring', ['account' => 'personal']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('recurring')
                ->has('recurringMerchants', 1)
                ->where('currentAccount', 'personal')
            );
    }
}
