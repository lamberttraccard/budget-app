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

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function createSearchableTransaction(string $description, Category $category, Account $account, Import $import, string $date = '2026-02-10', int $amountCents = -5000): Transaction
    {
        return Transaction::factory()->create([
            'description' => $description,
            'category_id' => $category->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => $date,
            'amount_cents' => $amountCents,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('search'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_access_search(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('search'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('results', 0)
                ->where('totalCount', 0)
                ->where('query', '')
                ->has('allCategories')
            );
    }

    public function test_search_returns_matching_transactions(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        $this->createSearchableTransaction('CARREFOUR CITY PARIS', $food, $account, $import);
        $this->createSearchableTransaction('BOULANGERIE LEFEVRE', $food, $account, $import);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'carrefour']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('results', 1)
                ->where('totalCount', 1)
                ->where('results.0.description', 'CARREFOUR CITY PARIS')
            );
    }

    public function test_search_requires_minimum_2_characters(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        $this->createSearchableTransaction('CARREFOUR CITY PARIS', $food, $account, $import);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'c']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('results', 0)
                ->where('totalCount', 0)
            );
    }

    public function test_search_is_case_insensitive(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        $this->createSearchableTransaction('CARREFOUR CITY', $food, $account, $import);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'carrefour']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('results', 1)
                ->where('results.0.description', 'CARREFOUR CITY')
            );
    }

    public function test_search_handles_french_accented_characters(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        $this->createSearchableTransaction('Boulangerie Lefèvre', $food, $account, $import);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'lefèvre']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('results', 1)
            );
    }

    public function test_search_filters_by_account_type(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        $this->createSearchableTransaction('CARREFOUR PERSONAL', $food, $personalAccount, $import);
        $this->createSearchableTransaction('CARREFOUR PROFESSIONAL', $food, $professionalAccount, $import);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'carrefour', 'account' => 'personal']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('results', 1)
                ->where('results.0.description', 'CARREFOUR PERSONAL')
            );
    }

    public function test_search_returns_category_details(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        $this->createSearchableTransaction('CARREFOUR CITY', $food, $account, $import);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'carrefour']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('results', 1)
                ->where('results.0.categoryName', $food->name)
                ->where('results.0.categoryColor', $food->color)
            );
    }

    public function test_search_with_no_matches_returns_empty(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        $this->createSearchableTransaction('CARREFOUR CITY', $food, $account, $import);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'nonexistent']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('results', 0)
                ->where('totalCount', 0)
            );
    }

    public function test_search_escapes_special_characters(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        $this->createSearchableTransaction('50% OFF SALE', $food, $account, $import);
        $this->createSearchableTransaction('NORMAL PURCHASE', $food, $account, $import);

        // Searching for "50%" should match literally, not as a wildcard
        $this->actingAs($user)
            ->get(route('search', ['q' => '50%']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('results', 1)
                ->where('results.0.description', '50% OFF SALE')
            );
    }

    public function test_search_returns_all_categories_for_picker(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('search'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('allCategories')
                ->has('allCategories.0', fn (Assert $cat) => $cat
                    ->has('id')
                    ->has('name')
                    ->has('color')
                    ->has('icon')
                )
            );
    }

    public function test_search_without_account_returns_all_accounts(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $personalAccount = Account::factory()->personal()->create();
        $professionalAccount = Account::factory()->professional()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        $this->createSearchableTransaction('CARREFOUR PERSONAL', $food, $personalAccount, $import);
        $this->createSearchableTransaction('CARREFOUR PROFESSIONAL', $food, $professionalAccount, $import);

        // Without account param, search should return results from ALL accounts
        $this->actingAs($user)
            ->get(route('search', ['q' => 'carrefour']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('results', 2)
                ->where('totalCount', 2)
                ->where('currentAccount', null)
            );
    }

    public function test_search_includes_uncategorized_transactions(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        // Categorized transaction
        $this->createSearchableTransaction('CARREFOUR CITY', $food, $account, $import);

        // Uncategorized transaction (category_id = null)
        Transaction::factory()->create([
            'description' => 'CARREFOUR EXPRESS',
            'category_id' => null,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'date' => '2026-02-10',
            'amount_cents' => -3000,
        ]);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'carrefour']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('results', 2)
                ->where('totalCount', 2)
            );
    }

    public function test_search_results_include_account_name(): void
    {
        $this->seed(CategorySeeder::class);
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $food = Category::where('slug', 'food')->first();

        $this->createSearchableTransaction('CARREFOUR CITY', $food, $account, $import);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'carrefour']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search')
                ->has('results.0.accountName')
            );
    }
}
