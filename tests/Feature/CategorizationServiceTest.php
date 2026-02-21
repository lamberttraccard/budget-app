<?php

namespace Tests\Feature;

use App\Events\TransactionCategorized;
use App\Jobs\CategorizeTransactions;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\MerchantRule;
use App\Models\Transaction;
use App\Services\CategorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
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
        $this->account = Account::factory()->personal()->create();
        $this->import = Import::factory()->completed()->create();
        $this->seedCategories();
    }

    private function seedCategories(): void
    {
        $slugs = ['food', 'restaurants', 'transport', 'housing', 'entertainment', 'shopping', 'health', 'education', 'travel', 'insurance', 'income', 'professional', 'other'];
        $this->categories = [];
        foreach ($slugs as $slug) {
            $this->categories[$slug] = Category::factory()->create(['slug' => $slug, 'name' => ucfirst($slug)]);
        }
    }

    private function createUncategorizedTransaction(string $description): Transaction
    {
        return Transaction::factory()->create([
            'description' => $description,
            'account_id' => $this->account->id,
            'import_id' => $this->import->id,
            'category_id' => null,
        ]);
    }

    public function test_merchant_matched_transactions_get_instant_category_assignment(): void
    {
        MerchantRule::factory()->create([
            'merchant_pattern' => 'CARREFOUR CITY',
            'category_id' => $this->categories['food']->id,
        ]);
        $transaction = $this->createUncategorizedTransaction('CARREFOUR CITY');

        Queue::fake();
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        $this->assertSame($this->categories['food']->id, $transaction->fresh()->category_id);
        Queue::assertNotPushed(CategorizeTransactions::class);
        Event::assertDispatched(TransactionCategorized::class, 1);
    }

    public function test_unmatched_transactions_trigger_categorize_job_dispatch(): void
    {
        $this->createUncategorizedTransaction('UNKNOWN MERCHANT');

        Queue::fake();
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        Queue::assertPushed(CategorizeTransactions::class, function (CategorizeTransactions $job) {
            return $job->import->id === $this->import->id;
        });
    }

    public function test_hybrid_flow_some_instant_some_queued(): void
    {
        MerchantRule::factory()->create([
            'merchant_pattern' => 'CARREFOUR CITY',
            'category_id' => $this->categories['food']->id,
        ]);
        $matched = $this->createUncategorizedTransaction('CARREFOUR CITY');
        $unmatched = $this->createUncategorizedTransaction('UNKNOWN SHOP');

        Queue::fake();
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        $this->assertSame($this->categories['food']->id, $matched->fresh()->category_id);
        $this->assertNull($unmatched->fresh()->category_id);
        Queue::assertPushed(CategorizeTransactions::class);
    }

    public function test_no_job_dispatched_when_all_matched_by_merchant_rules(): void
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

        Queue::fake();
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        Queue::assertNotPushed(CategorizeTransactions::class);
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

        Queue::fake();
        Event::fake([TransactionCategorized::class]);

        $this->service->categorize($this->import);

        Event::assertDispatched(TransactionCategorized::class, 2);
    }
}
