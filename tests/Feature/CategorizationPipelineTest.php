<?php

namespace Tests\Feature;

use App\Events\ImportCompleted;
use App\Events\TransactionCategorized;
use App\Jobs\CategorizeTransactions;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\MerchantRule;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CategorizationPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private Import $import;

    private array $categories;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = Account::factory()->personal()->create();
        $this->import = Import::factory()->completed()->create();
        $this->seedCategories();
    }

    private function seedCategories(): void
    {
        $slugs = ['food', 'restaurants', 'transport', 'housing', 'entertainment', 'shopping', 'health', 'subscriptions', 'education', 'travel', 'insurance', 'income', 'professional', 'other'];
        $this->categories = [];
        foreach ($slugs as $slug) {
            $this->categories[$slug] = Category::factory()->create(['slug' => $slug, 'name' => ucfirst($slug)]);
        }
    }

    public function test_import_completed_event_triggers_categorization_pipeline(): void
    {
        MerchantRule::factory()->create([
            'merchant_pattern' => 'CARREFOUR CITY',
            'category_id' => $this->categories['food']->id,
        ]);

        $matched = Transaction::factory()->create([
            'description' => 'CARREFOUR CITY',
            'account_id' => $this->account->id,
            'import_id' => $this->import->id,
            'category_id' => null,
        ]);
        $unmatched = Transaction::factory()->create([
            'description' => 'SNCF VOYAGES',
            'account_id' => $this->account->id,
            'import_id' => $this->import->id,
            'category_id' => null,
        ]);

        Queue::fake();
        Event::fake([TransactionCategorized::class]);

        ImportCompleted::dispatch($this->import);

        $this->assertSame($this->categories['food']->id, $matched->fresh()->category_id);
        $this->assertNull($unmatched->fresh()->category_id);

        Queue::assertPushed(CategorizeTransactions::class, function (CategorizeTransactions $job) {
            return $job->import->id === $this->import->id;
        });

        Event::assertDispatched(TransactionCategorized::class, 1);
    }

    public function test_pipeline_with_all_merchant_matched_no_job_dispatched(): void
    {
        MerchantRule::factory()->create([
            'merchant_pattern' => 'CARREFOUR CITY',
            'category_id' => $this->categories['food']->id,
        ]);
        MerchantRule::factory()->create([
            'merchant_pattern' => 'PHARMACIE CENTRALE',
            'category_id' => $this->categories['health']->id,
        ]);

        Transaction::factory()->create([
            'description' => 'CARREFOUR CITY',
            'account_id' => $this->account->id,
            'import_id' => $this->import->id,
            'category_id' => null,
        ]);
        Transaction::factory()->create([
            'description' => 'PHARMACIE CENTRALE',
            'account_id' => $this->account->id,
            'import_id' => $this->import->id,
            'category_id' => null,
        ]);

        Queue::fake();
        Event::fake([TransactionCategorized::class]);

        ImportCompleted::dispatch($this->import);

        Queue::assertNotPushed(CategorizeTransactions::class);
        Event::assertDispatched(TransactionCategorized::class, 2);
    }

    public function test_pipeline_with_no_uncategorized_transactions(): void
    {
        Transaction::factory()->create([
            'description' => 'CARREFOUR CITY',
            'account_id' => $this->account->id,
            'import_id' => $this->import->id,
            'category_id' => $this->categories['food']->id,
        ]);

        Queue::fake();
        Event::fake([TransactionCategorized::class]);

        ImportCompleted::dispatch($this->import);

        Queue::assertNotPushed(CategorizeTransactions::class);
        Event::assertNotDispatched(TransactionCategorized::class);
    }
}
