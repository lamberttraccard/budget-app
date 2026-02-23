<?php

namespace Tests\Feature;

use App\Ai\Agents\TransactionCategorizer;
use App\Events\TransactionCategorized;
use App\Jobs\CategorizeTransactionChunk;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CategorizeTransactionChunkTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private Import $import;

    private array $categories;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = Account::factory()->create();
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

    private function createUncategorizedTransaction(string $description, int $amountCents = -5000): Transaction
    {
        return Transaction::factory()->create([
            'description' => $description,
            'amount_cents' => $amountCents,
            'account_id' => $this->account->id,
            'import_id' => $this->import->id,
            'category_id' => null,
        ]);
    }

    public function test_chunk_processes_transactions_and_assigns_categories(): void
    {
        $t1 = $this->createUncategorizedTransaction('CARREFOUR CITY');
        $t2 = $this->createUncategorizedTransaction('NETFLIX.COM');

        TransactionCategorizer::fake([
            [
                'results' => [
                    ['index' => 1, 'description' => 'CARREFOUR CITY', 'category_slug' => 'food', 'confidence' => 0.95],
                    ['index' => 2, 'description' => 'NETFLIX.COM', 'category_slug' => 'entertainment', 'confidence' => 0.99],
                ],
            ],
        ]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactionChunk([$t1->id, $t2->id]))->handle();

        $this->assertSame($this->categories['food']->id, $t1->fresh()->category_id);
        $this->assertSame($this->categories['entertainment']->id, $t2->fresh()->category_id);
    }

    public function test_chunk_leaves_transactions_null_on_failure(): void
    {
        $t1 = $this->createUncategorizedTransaction('SOME MERCHANT');
        $t2 = $this->createUncategorizedTransaction('ANOTHER MERCHANT');

        TransactionCategorizer::fake(function () {
            throw new \RuntimeException('API error');
        });

        Event::fake([TransactionCategorized::class]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API error');

        (new CategorizeTransactionChunk([$t1->id, $t2->id]))->handle();

        $this->assertNull($t1->fresh()->category_id);
        $this->assertNull($t2->fresh()->category_id);
        Event::assertNotDispatched(TransactionCategorized::class);
    }

    public function test_chunk_assigns_other_category_to_low_confidence_transactions(): void
    {
        $t1 = $this->createUncategorizedTransaction('AMBIGUOUS MERCHANT');
        $otherCategory = Category::where('slug', 'other')->whereNull('parent_id')->first();

        TransactionCategorizer::fake([
            [
                'results' => [
                    ['index' => 1, 'description' => 'AMBIGUOUS MERCHANT', 'category_slug' => 'shopping', 'confidence' => 0.3],
                ],
            ],
        ]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactionChunk([$t1->id]))->handle();

        $this->assertEquals($otherCategory->id, $t1->fresh()->category_id);
        Event::assertDispatched(TransactionCategorized::class);
    }

    public function test_chunk_is_idempotent_skips_already_categorized(): void
    {
        $t1 = $this->createUncategorizedTransaction('CARREFOUR CITY');
        $t1->update(['category_id' => $this->categories['food']->id]);

        TransactionCategorizer::fake([]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactionChunk([$t1->id]))->handle();

        // Should not have changed the category
        $this->assertSame($this->categories['food']->id, $t1->fresh()->category_id);
        Event::assertNotDispatched(TransactionCategorized::class);
    }

    public function test_chunk_dispatches_transaction_categorized_events(): void
    {
        $t1 = $this->createUncategorizedTransaction('CARREFOUR CITY');
        $t2 = $this->createUncategorizedTransaction('NETFLIX.COM');

        TransactionCategorizer::fake([
            [
                'results' => [
                    ['index' => 1, 'description' => 'CARREFOUR CITY', 'category_slug' => 'food', 'confidence' => 0.95],
                    ['index' => 2, 'description' => 'NETFLIX.COM', 'category_slug' => 'entertainment', 'confidence' => 0.99],
                ],
            ],
        ]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactionChunk([$t1->id, $t2->id]))->handle();

        Event::assertDispatched(TransactionCategorized::class, 2);
    }

    public function test_chunk_timeout_configuration(): void
    {
        $job = new CategorizeTransactionChunk([1]);

        $this->assertSame(75, $job->timeout);
        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 60, 120], $job->backoff);
    }

    public function test_agent_is_prompted_with_transaction_descriptions(): void
    {
        $t1 = $this->createUncategorizedTransaction('CARREFOUR CITY', -5042);

        TransactionCategorizer::fake([
            [
                'results' => [
                    ['index' => 1, 'description' => 'CARREFOUR CITY', 'category_slug' => 'food', 'confidence' => 0.95],
                ],
            ],
        ]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactionChunk([$t1->id]))->handle();

        TransactionCategorizer::assertPrompted(function ($prompt) {
            return str_contains($prompt->prompt, 'CARREFOUR CITY')
                && str_contains($prompt->prompt, '-5042');
        });
    }

    public function test_chunk_includes_bank_hint_in_prompt(): void
    {
        $t1 = Transaction::factory()->create([
            'description' => 'CARREFOUR CITY',
            'amount_cents' => -4250,
            'account_id' => $this->account->id,
            'import_id' => $this->import->id,
            'category_id' => null,
            'bank_metadata' => [
                'type_operation' => 'Carte',
                'categorie' => 'Alimentation',
                'sous_categorie' => 'Hyper/supermarche',
            ],
        ]);

        TransactionCategorizer::fake([
            [
                'results' => [
                    ['index' => 1, 'description' => 'CARREFOUR CITY', 'category_slug' => 'food', 'confidence' => 0.95],
                ],
            ],
        ]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactionChunk([$t1->id]))->handle();

        TransactionCategorizer::assertPrompted(function ($prompt) {
            return str_contains($prompt->prompt, '[bank hint: Alimentation > Hyper/supermarche]');
        });
    }

    public function test_chunk_no_bank_hint_when_metadata_is_null(): void
    {
        $t1 = $this->createUncategorizedTransaction('CARREFOUR CITY', -4250);

        TransactionCategorizer::fake([
            [
                'results' => [
                    ['index' => 1, 'description' => 'CARREFOUR CITY', 'category_slug' => 'food', 'confidence' => 0.95],
                ],
            ],
        ]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactionChunk([$t1->id]))->handle();

        TransactionCategorizer::assertPrompted(function ($prompt) {
            return ! str_contains($prompt->prompt, '[bank hint:');
        });
    }

    public function test_chunk_handles_empty_transaction_ids(): void
    {
        TransactionCategorizer::fake([]);
        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactionChunk([]))->handle();

        Event::assertNotDispatched(TransactionCategorized::class);
    }
}
