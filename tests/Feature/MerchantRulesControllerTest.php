<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\MerchantRule;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantRulesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_index_lists_rules_with_match_counts(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $food = Category::where('slug', 'food')->first();
        $import = Import::factory()->completed()->create();

        $rule = MerchantRule::factory()->create([
            'merchant_pattern' => 'carrefour city paris',
            'category_id' => $food->id,
        ]);

        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'description' => 'CARREFOUR CITY PARIS',
        ]);

        Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'description' => 'carrefour city paris',
        ]);

        $response = $this->actingAs($user)
            ->get('/settings/merchant-rules');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/merchant-rules')
            ->has('rules', 1)
            ->where('rules.0.merchantPattern', 'carrefour city paris')
            ->where('rules.0.categoryName', $food->name)
            ->where('rules.0.matchCount', 2)
        );
    }

    public function test_destroy_deletes_rule(): void
    {
        $user = User::factory()->create();
        $food = Category::where('slug', 'food')->first();

        $rule = MerchantRule::factory()->create([
            'merchant_pattern' => 'carrefour city paris',
            'category_id' => $food->id,
        ]);

        $response = $this->actingAs($user)
            ->delete("/settings/merchant-rules/{$rule->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('merchant_rules', ['id' => $rule->id]);
    }

    public function test_apply_updates_matching_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $food = Category::where('slug', 'food')->first();
        $restaurants = Category::where('slug', 'restaurants')->first();
        $import = Import::factory()->completed()->create();

        $rule = MerchantRule::factory()->create([
            'merchant_pattern' => 'carrefour city paris',
            'category_id' => $restaurants->id,
        ]);

        $t1 = Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'description' => 'CARREFOUR CITY PARIS',
        ]);

        $t2 = Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'description' => 'carrefour city paris',
        ]);

        $t3 = Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'description' => 'OTHER STORE',
        ]);

        $response = $this->actingAs($user)
            ->post("/settings/merchant-rules/{$rule->id}/apply");

        $response->assertRedirect();
        $response->assertSessionHas('updated_count', 2);

        $this->assertDatabaseHas('transactions', [
            'id' => $t1->id,
            'category_id' => $restaurants->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $t2->id,
            'category_id' => $restaurants->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $t3->id,
            'category_id' => $food->id,
        ]);
    }

    public function test_apply_returns_updated_count(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $food = Category::where('slug', 'food')->first();
        $import = Import::factory()->completed()->create();

        $rule = MerchantRule::factory()->create([
            'merchant_pattern' => 'test merchant',
            'category_id' => $food->id,
        ]);

        Transaction::factory()->count(3)->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'import_id' => $import->id,
            'description' => 'TEST MERCHANT',
        ]);

        $response = $this->actingAs($user)
            ->post("/settings/merchant-rules/{$rule->id}/apply");

        $response->assertRedirect();
        $response->assertSessionHas('updated_count', 3);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get('/settings/merchant-rules');
        $response->assertRedirect('/login');
    }

    public function test_destroy_requires_authentication(): void
    {
        $food = Category::where('slug', 'food')->first();
        $rule = MerchantRule::factory()->create([
            'merchant_pattern' => 'test',
            'category_id' => $food->id,
        ]);

        $response = $this->delete("/settings/merchant-rules/{$rule->id}");
        $response->assertRedirect('/login');
    }

    public function test_index_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/settings/merchant-rules');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/merchant-rules')
            ->has('rules', 0)
        );
    }
}
