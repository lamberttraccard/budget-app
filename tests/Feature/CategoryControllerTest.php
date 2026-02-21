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

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createTransactionsForCategory(Category $category, int $count = 3, string $month = '2026-02'): void
    {
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();

        for ($i = 0; $i < $count; $i++) {
            Transaction::factory()->create([
                'category_id' => $category->id,
                'account_id' => $account->id,
                'import_id' => $import->id,
                'date' => "{$month}-".str_pad($i + 1, 2, '0', STR_PAD_LEFT),
                'amount_cents' => -($i + 1) * 1000,
            ]);
        }
    }

    public function test_show_returns_inertia_response_with_category_data(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $category = Category::where('slug', 'transport')->first();
        $this->createTransactionsForCategory($category, 3, '2026-02');

        $this->actingAs($user)
            ->get(route('categories.show', ['category' => $category->id, 'month' => '2026-02']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('categories/show')
                ->where('category.id', $category->id)
                ->where('category.name', $category->name)
                ->where('category.color', $category->color)
                ->where('category.slug', $category->slug)
                ->has('subcategories')
                ->has('transactions', 3)
                ->where('transactionCount', 3)
                ->where('currentMonth', '2026-02')
            );
    }

    public function test_show_with_month_parameter_filters_transactions(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $category = Category::where('slug', 'food')->first();

        // Create transactions in different months
        $this->createTransactionsForCategory($category, 2, '2026-01');
        $this->createTransactionsForCategory($category, 3, '2026-02');

        $this->actingAs($user)
            ->get(route('categories.show', ['category' => $category->id, 'month' => '2026-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('categories/show')
                ->has('transactions', 2)
                ->where('transactionCount', 2)
                ->where('currentMonth', '2026-01')
            );
    }

    public function test_show_with_subcategories_returns_subcategory_data(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $parent = Category::where('slug', 'food')->first();

        // Create a subcategory
        $subcategory = Category::create([
            'name' => 'Groceries',
            'slug' => 'groceries',
            'color' => '#A3E635',
            'parent_id' => $parent->id,
        ]);

        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();

        // Transactions on the subcategory
        Transaction::factory()->create([
            'category_id' => $subcategory->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => '2026-02-10',
            'amount_cents' => -5000,
        ]);

        // Transactions on the parent category directly
        Transaction::factory()->create([
            'category_id' => $parent->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => '2026-02-15',
            'amount_cents' => -3000,
        ]);

        $this->actingAs($user)
            ->get(route('categories.show', ['category' => $parent->id, 'month' => '2026-02']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('categories/show')
                ->has('subcategories', 1)
                ->where('subcategories.0.name', 'Groceries')
                ->where('subcategories.0.amountCents', -5000)
                ->where('subcategories.0.transactionCount', 1)
                ->has('transactions', 2)
                ->where('transactionCount', 2)
                // Verify categoryId is present on transactions (used for client-side subcategory filtering)
                ->where('transactions.0.categoryId', $parent->id)
                ->where('transactions.1.categoryId', $subcategory->id)
            );
    }

    public function test_show_with_no_transactions_returns_empty_arrays(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $category = Category::where('slug', 'transport')->first();

        $this->actingAs($user)
            ->get(route('categories.show', ['category' => $category->id, 'month' => '2026-02']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('categories/show')
                ->has('subcategories', 0)
                ->has('transactions', 0)
                ->where('totalAmountCents', 0)
                ->where('transactionCount', 0)
            );
    }

    public function test_show_requires_authentication(): void
    {
        $this->seed(CategorySeeder::class);
        $category = Category::where('slug', 'transport')->first();

        $this->get(route('categories.show', ['category' => $category->id]))
            ->assertRedirect(route('login'));
    }

    public function test_show_with_invalid_category_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/categories/99999')
            ->assertNotFound();
    }

    public function test_show_with_invalid_month_defaults_to_current(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $category = Category::where('slug', 'food')->first();

        $this->actingAs($user)
            ->get(route('categories.show', ['category' => $category->id, 'month' => 'invalid']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('categories/show')
                ->where('currentMonth', now()->format('Y-m'))
            );
    }

    public function test_show_returns_transactions_in_date_descending_order(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $category = Category::where('slug', 'food')->first();
        $this->createTransactionsForCategory($category, 3, '2026-02');

        // Transactions created with dates 2026-02-01, 02, 03; amounts -1000, -2000, -3000
        $this->actingAs($user)
            ->get(route('categories.show', ['category' => $category->id, 'month' => '2026-02']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('categories/show')
                ->has('transactions', 3)
                // Most recent date first (date DESC)
                ->where('transactions.0.date', '2026-02-03')
                ->where('transactions.1.date', '2026-02-02')
                ->where('transactions.2.date', '2026-02-01')
            );
    }

    public function test_show_filters_by_account_type(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $category = Category::where('slug', 'food')->first();

        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();

        Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $personalAccount->id,
            'import_id' => $import->id,
            'date' => '2026-02-10',
            'amount_cents' => -5000,
            'description' => 'Personal food',
        ]);
        Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $professionalAccount->id,
            'import_id' => $import->id,
            'date' => '2026-02-15',
            'amount_cents' => -9000,
            'description' => 'Professional food',
        ]);

        $this->actingAs($user)
            ->get(route('categories.show', ['category' => $category->id, 'month' => '2026-02', 'account' => 'personal']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('categories/show')
                ->where('currentAccount', 'personal')
                ->has('transactions', 1)
                ->where('transactions.0.description', 'Personal food')
            );
    }

    public function test_show_returns_correct_total_amount(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $category = Category::where('slug', 'restaurants')->first();

        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();

        Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => '2026-02-05',
            'amount_cents' => -2500,
        ]);

        Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => '2026-02-10',
            'amount_cents' => -7500,
        ]);

        $this->actingAs($user)
            ->get(route('categories.show', ['category' => $category->id, 'month' => '2026-02']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('categories/show')
                ->where('totalAmountCents', -10000)
                ->where('transactionCount', 2)
            );
    }

    public function test_show_includes_all_categories_for_picker(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $category = Category::where('slug', 'food')->first();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();

        Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => now(),
            'amount_cents' => -5000,
        ]);

        $this->actingAs($user)
            ->get(route('categories.show', ['category' => $category->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('allCategories', 14)
                ->has('allCategories.0', fn (Assert $cat) => $cat
                    ->has('id')
                    ->has('name')
                    ->has('color')
                )
            );
    }
}
