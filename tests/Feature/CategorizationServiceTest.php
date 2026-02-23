<?php

namespace Tests\Feature;

use App\Events\TransactionCategorized;
use App\Jobs\CategorizeTransactionChunk;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\MerchantRule;
use App\Models\Transaction;
use App\Services\CategorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CategorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategorizationService $service;

    private Account $account;

    private Import $import;

    private array $categories;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CategorizationService::class);
        $this->account = Account::factory()->create();
        $this->import = Import::factory()->completed()->create();
        $this->seedCategories();
    }

    private function seedCategories(): void
    {
        $slugs = ['food', 'restaurants', 'transport', 'housing', 'entertainment', 'shopping', 'health', 'education', 'travel', 'insurance', 'income', 'professional', 'transfer', 'other'];
        $this->categories = [];
        foreach ($slugs as $slug) {
            $this->categories[$slug] = Category::factory()->create(['slug' => $slug, 'name' => ucfirst($slug)]);
        }
    }

    private function createUncategorizedTransaction(string $description, ?array $bankMetadata = null): Transaction
    {
        return Transaction::factory()->create([
            'description' => $description,
            'account_id' => $this->account->id,
            'import_id' => $this->import->id,
            'category_id' => null,
            'bank_metadata' => $bankMetadata,
        ]);
    }

    public function test_merchant_matched_transactions_get_instant_category_assignment(): void
    {
        MerchantRule::factory()->create([
            'merchant_pattern' => 'CARREFOUR CITY',
            'category_id' => $this->categories['food']->id,
        ]);
        $transaction = $this->createUncategorizedTransaction('CARREFOUR CITY');

        Bus::fake([CategorizeTransactionChunk::class]);
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        $this->assertSame($this->categories['food']->id, $transaction->fresh()->category_id);
        Event::assertDispatched(TransactionCategorized::class, 1);
    }

    public function test_unmatched_transactions_trigger_batch_dispatch(): void
    {
        $this->createUncategorizedTransaction('UNKNOWN MERCHANT');

        Bus::fake([CategorizeTransactionChunk::class]);
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 1;
        });
    }

    public function test_hybrid_flow_some_instant_some_batched(): void
    {
        MerchantRule::factory()->create([
            'merchant_pattern' => 'CARREFOUR CITY',
            'category_id' => $this->categories['food']->id,
        ]);
        $matched = $this->createUncategorizedTransaction('CARREFOUR CITY');
        $unmatched = $this->createUncategorizedTransaction('UNKNOWN SHOP');

        Bus::fake([CategorizeTransactionChunk::class]);
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        $this->assertSame($this->categories['food']->id, $matched->fresh()->category_id);
        $this->assertNull($unmatched->fresh()->category_id);
        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 1;
        });
    }

    public function test_no_batch_dispatched_when_all_matched_by_merchant_rules(): void
    {
        MerchantRule::factory()->create([
            'merchant_pattern' => 'CARREFOUR CITY',
            'category_id' => $this->categories['food']->id,
        ]);
        MerchantRule::factory()->create([
            'merchant_pattern' => 'PHARMACIE CENTRALE',
            'category_id' => $this->categories['health']->id,
        ]);
        $this->createUncategorizedTransaction('CARREFOUR CITY');
        $this->createUncategorizedTransaction('PHARMACIE CENTRALE');

        Bus::fake([CategorizeTransactionChunk::class]);
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        Bus::assertNothingBatched();
    }

    public function test_all_matched_sets_import_status_to_categorized(): void
    {
        MerchantRule::factory()->create([
            'merchant_pattern' => 'CARREFOUR CITY',
            'category_id' => $this->categories['food']->id,
        ]);
        $this->createUncategorizedTransaction('CARREFOUR CITY');

        Bus::fake([CategorizeTransactionChunk::class]);
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        $this->assertSame('categorized', $this->import->fresh()->status);
    }

    public function test_transaction_categorized_event_fires_for_each_instant_match(): void
    {
        MerchantRule::factory()->create([
            'merchant_pattern' => 'CARREFOUR CITY',
            'category_id' => $this->categories['food']->id,
        ]);
        MerchantRule::factory()->create([
            'merchant_pattern' => 'PHARMACIE CENTRALE',
            'category_id' => $this->categories['health']->id,
        ]);
        $this->createUncategorizedTransaction('CARREFOUR CITY');
        $this->createUncategorizedTransaction('PHARMACIE CENTRALE');

        Bus::fake([CategorizeTransactionChunk::class]);
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        Event::assertDispatched(TransactionCategorized::class, 2);
    }

    // --- Bank Hints Tests ---

    public function test_virement_interne_auto_categorized_as_transfer_without_ai(): void
    {
        $transaction = $this->createUncategorizedTransaction('VIREMENT COMPTE EPARGNE', [
            'type_operation' => 'Virement',
            'categorie' => 'Transaction exclue',
            'sous_categorie' => 'Virement interne',
        ]);

        Bus::fake([CategorizeTransactionChunk::class]);
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        $this->assertSame($this->categories['transfer']->id, $transaction->fresh()->category_id);
        Event::assertDispatched(TransactionCategorized::class, 1);
        Bus::assertNothingBatched();
    }

    public function test_bank_hints_and_merchant_rules_categorize_all_without_ai(): void
    {
        MerchantRule::factory()->create([
            'merchant_pattern' => 'CARREFOUR CITY',
            'category_id' => $this->categories['food']->id,
        ]);

        $transfer = $this->createUncategorizedTransaction('VIREMENT COMPTE EPARGNE', [
            'type_operation' => 'Virement',
            'categorie' => 'Transaction exclue',
            'sous_categorie' => 'Virement interne',
        ]);
        $merchant = $this->createUncategorizedTransaction('CARREFOUR CITY');

        Bus::fake([CategorizeTransactionChunk::class]);
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        $this->assertSame($this->categories['transfer']->id, $transfer->fresh()->category_id);
        $this->assertSame($this->categories['food']->id, $merchant->fresh()->category_id);
        Bus::assertNothingBatched();
        $this->assertSame('categorized', $this->import->fresh()->status);
    }

    public function test_apply_bank_hints_graceful_when_transfer_category_not_seeded(): void
    {
        // Delete the transfer category
        Category::where('slug', 'transfer')->delete();

        $transaction = $this->createUncategorizedTransaction('VIREMENT COMPTE EPARGNE', [
            'type_operation' => 'Virement',
            'categorie' => 'Transaction exclue',
            'sous_categorie' => 'Virement interne',
        ]);

        Bus::fake([CategorizeTransactionChunk::class]);
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        // Transaction should NOT be categorized by bank hints
        $this->assertNull($transaction->fresh()->category_id);
        // Should fall through to AI
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
    }

    public function test_non_virement_interne_bank_metadata_falls_through_to_ai(): void
    {
        $transaction = $this->createUncategorizedTransaction('CARREFOUR CITY', [
            'type_operation' => 'Carte',
            'categorie' => 'Alimentation',
            'sous_categorie' => 'Hyper/supermarche',
        ]);

        Bus::fake([CategorizeTransactionChunk::class]);
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        // Not categorized by bank hints (not "Virement interne")
        $this->assertNull($transaction->fresh()->category_id);
        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
    }
}
