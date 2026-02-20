<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\MerchantRule;
use App\Models\Transaction;
use App\Services\MerchantMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private MerchantMatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MerchantMatchingService;
    }

    public function test_match_returns_category_when_exact_match_exists(): void
    {
        $category = Category::factory()->create(['slug' => 'food']);
        MerchantRule::factory()->create([
            'merchant_pattern' => 'CARREFOUR CITY PARIS 15',
            'category_id' => $category->id,
        ]);

        $result = $this->service->match('CARREFOUR CITY PARIS 15');

        $this->assertNotNull($result);
        $this->assertSame($category->id, $result->id);
    }

    public function test_match_returns_null_when_no_match_exists(): void
    {
        $result = $this->service->match('UNKNOWN MERCHANT');

        $this->assertNull($result);
    }

    public function test_match_is_case_insensitive(): void
    {
        $category = Category::factory()->create(['slug' => 'food']);
        MerchantRule::factory()->create([
            'merchant_pattern' => 'carrefour',
            'category_id' => $category->id,
        ]);

        $result = $this->service->match('CARREFOUR');

        $this->assertNotNull($result);
        $this->assertSame($category->id, $result->id);
    }

    public function test_match_batch_returns_correct_category_ids_for_matched_transactions(): void
    {
        $foodCategory = Category::factory()->create(['slug' => 'food']);
        $healthCategory = Category::factory()->create(['slug' => 'health']);
        MerchantRule::factory()->create(['merchant_pattern' => 'CARREFOUR CITY', 'category_id' => $foodCategory->id]);
        MerchantRule::factory()->create(['merchant_pattern' => 'PHARMACIE CENTRALE', 'category_id' => $healthCategory->id]);

        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $t1 = Transaction::factory()->create([
            'description' => 'CARREFOUR CITY',
            'account_id' => $account->id,
            'import_id' => $import->id,
            'category_id' => null,
        ]);
        $t2 = Transaction::factory()->create([
            'description' => 'PHARMACIE CENTRALE',
            'account_id' => $account->id,
            'import_id' => $import->id,
            'category_id' => null,
        ]);

        $result = $this->service->matchBatch(collect([$t1, $t2]));

        $this->assertCount(2, $result);
        $this->assertSame($foodCategory->id, $result[$t1->id]);
        $this->assertSame($healthCategory->id, $result[$t2->id]);
    }

    public function test_match_batch_returns_empty_array_when_no_transactions_match(): void
    {
        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $t1 = Transaction::factory()->create([
            'description' => 'UNKNOWN MERCHANT',
            'account_id' => $account->id,
            'import_id' => $import->id,
            'category_id' => null,
        ]);

        $result = $this->service->matchBatch(collect([$t1]));

        $this->assertSame([], $result);
    }

    public function test_match_batch_handles_mixed_batch(): void
    {
        $category = Category::factory()->create(['slug' => 'food']);
        MerchantRule::factory()->create(['merchant_pattern' => 'CARREFOUR', 'category_id' => $category->id]);

        $account = Account::factory()->personal()->create();
        $import = Import::factory()->completed()->create();
        $matched = Transaction::factory()->create([
            'description' => 'CARREFOUR',
            'account_id' => $account->id,
            'import_id' => $import->id,
            'category_id' => null,
        ]);
        $unmatched = Transaction::factory()->create([
            'description' => 'UNKNOWN SHOP',
            'account_id' => $account->id,
            'import_id' => $import->id,
            'category_id' => null,
        ]);

        $result = $this->service->matchBatch(collect([$matched, $unmatched]));

        $this->assertCount(1, $result);
        $this->assertSame($category->id, $result[$matched->id]);
        $this->assertArrayNotHasKey($unmatched->id, $result);
    }
}
