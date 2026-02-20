<?php

namespace Tests\Feature;

use App\Events\CategoryCorrected;
use App\Listeners\UpdateMerchantRule;
use App\Models\Account;
use App\Models\Category;
use App\Models\MerchantRule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MerchantMatchingService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateMerchantRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
    }

    public function test_listener_creates_merchant_rule_on_correction(): void
    {
        $account = Account::factory()->personal()->create();
        $food = Category::where('slug', 'food')->first();
        $restaurants = Category::where('slug', 'restaurants')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $food->id,
            'account_id' => $account->id,
            'description' => 'BOULANGERIE LEFEVRE',
        ]);

        $event = new CategoryCorrected($transaction, $food->id, $restaurants->id);
        $listener = new UpdateMerchantRule();
        $listener->handle($event);

        $this->assertDatabaseHas('merchant_rules', [
            'merchant_pattern' => 'BOULANGERIE LEFEVRE',
            'category_id' => $restaurants->id,
        ]);
    }

    public function test_listener_updates_existing_rule_on_re_correction(): void
    {
        $account = Account::factory()->personal()->create();
        $restaurants = Category::where('slug', 'restaurants')->first();
        $transport = Category::where('slug', 'transport')->first();

        MerchantRule::create([
            'merchant_pattern' => 'UBER EATS',
            'category_id' => $restaurants->id,
        ]);

        $transaction = Transaction::factory()->create([
            'category_id' => $transport->id,
            'account_id' => $account->id,
            'description' => 'UBER EATS',
        ]);

        $event = new CategoryCorrected($transaction, $restaurants->id, $transport->id);
        $listener = new UpdateMerchantRule();
        $listener->handle($event);

        $this->assertDatabaseCount('merchant_rules', 1);
        $this->assertDatabaseHas('merchant_rules', [
            'merchant_pattern' => 'UBER EATS',
            'category_id' => $transport->id,
        ]);
    }

    public function test_integration_correction_enables_future_matching(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->personal()->create();
        $other = Category::where('slug', 'other')->first();
        $food = Category::where('slug', 'food')->first();

        $transaction = Transaction::factory()->create([
            'category_id' => $other->id,
            'account_id' => $account->id,
            'description' => 'PICARD SURGELES',
        ]);

        $this->actingAs($user)
            ->patch("/transactions/{$transaction->id}", [
                'category_id' => $food->id,
            ]);

        $this->assertDatabaseHas('merchant_rules', [
            'merchant_pattern' => 'PICARD SURGELES',
            'category_id' => $food->id,
        ]);

        $matchingService = app(MerchantMatchingService::class);
        $matchedCategory = $matchingService->match('PICARD SURGELES');

        $this->assertNotNull($matchedCategory);
        $this->assertEquals($food->id, $matchedCategory->id);
    }

    public function test_merchant_rules_persist_across_queries(): void
    {
        $food = Category::where('slug', 'food')->first();

        MerchantRule::create([
            'merchant_pattern' => 'MONOPRIX',
            'category_id' => $food->id,
        ]);

        $this->assertDatabaseHas('merchant_rules', [
            'merchant_pattern' => 'MONOPRIX',
            'category_id' => $food->id,
        ]);

        $matchingService = app(MerchantMatchingService::class);
        $matched = $matchingService->match('MONOPRIX');
        $this->assertNotNull($matched);
        $this->assertEquals($food->id, $matched->id);
    }
}
