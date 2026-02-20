<?php

namespace Tests\Feature;

use App\Ai\Agents\TransactionCategorizer;
use App\Events\TransactionCategorized;
use App\Jobs\CategorizeTransactions;
use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CategorizeTransactionsTest extends TestCase
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

    public function test_job_processes_uncategorized_transactions_and_assigns_categories(): void
    {
        $t1 = $this->createUncategorizedTransaction('CARREFOUR CITY');
        $t2 = $this->createUncategorizedTransaction('NETFLIX.COM');

        TransactionCategorizer::fake([
            [
                'results' => [
                    ['index' => 1, 'description' => 'CARREFOUR CITY', 'category_slug' => 'food', 'confidence' => 0.95],
                    ['index' => 2, 'description' => 'NETFLIX.COM', 'category_slug' => 'subscriptions', 'confidence' => 0.99],
                ],
            ],
        ]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactions($this->import))->handle();

        $this->assertSame($this->categories['food']->id, $t1->fresh()->category_id);
        $this->assertSame($this->categories['subscriptions']->id, $t2->fresh()->category_id);
    }

    public function test_job_assigns_other_category_when_confidence_below_threshold(): void
    {
        $t1 = $this->createUncategorizedTransaction('AMBIGUOUS MERCHANT');

        TransactionCategorizer::fake([
            [
                'results' => [
                    ['index' => 1, 'description' => 'AMBIGUOUS MERCHANT', 'category_slug' => 'shopping', 'confidence' => 0.3],
                ],
            ],
        ]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactions($this->import))->handle();

        $this->assertSame($this->categories['other']->id, $t1->fresh()->category_id);
    }

    public function test_job_assigns_other_category_when_agent_throws_exception(): void
    {
        $t1 = $this->createUncategorizedTransaction('SOME MERCHANT');
        $t2 = $this->createUncategorizedTransaction('ANOTHER MERCHANT');

        TransactionCategorizer::fake(function () {
            throw new \RuntimeException('API error');
        });

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactions($this->import))->handle();

        $this->assertSame($this->categories['other']->id, $t1->fresh()->category_id);
        $this->assertSame($this->categories['other']->id, $t2->fresh()->category_id);
        Event::assertDispatched(TransactionCategorized::class, 2);
    }

    public function test_job_dispatches_transaction_categorized_event_for_each_transaction(): void
    {
        $t1 = $this->createUncategorizedTransaction('CARREFOUR CITY');
        $t2 = $this->createUncategorizedTransaction('NETFLIX.COM');

        TransactionCategorizer::fake([
            [
                'results' => [
                    ['index' => 1, 'description' => 'CARREFOUR CITY', 'category_slug' => 'food', 'confidence' => 0.95],
                    ['index' => 2, 'description' => 'NETFLIX.COM', 'category_slug' => 'subscriptions', 'confidence' => 0.99],
                ],
            ],
        ]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactions($this->import))->handle();

        Event::assertDispatched(TransactionCategorized::class, 2);
    }

    public function test_job_handles_empty_import_without_errors(): void
    {
        TransactionCategorizer::fake([]);

        (new CategorizeTransactions($this->import))->handle();

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_job_only_processes_transactions_for_given_import(): void
    {
        $otherImport = Import::factory()->completed()->create();
        $ourTransaction = $this->createUncategorizedTransaction('CARREFOUR CITY');
        $otherTransaction = Transaction::factory()->create([
            'description' => 'LIDL',
            'account_id' => $this->account->id,
            'import_id' => $otherImport->id,
            'category_id' => null,
        ]);

        TransactionCategorizer::fake([
            [
                'results' => [
                    ['index' => 1, 'description' => 'CARREFOUR CITY', 'category_slug' => 'food', 'confidence' => 0.95],
                ],
            ],
        ]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactions($this->import))->handle();

        $this->assertSame($this->categories['food']->id, $ourTransaction->fresh()->category_id);
        $this->assertNull($otherTransaction->fresh()->category_id);
    }

    public function test_job_retry_configuration(): void
    {
        $job = new CategorizeTransactions($this->import);

        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 60, 120], $job->backoff);
    }

    public function test_job_handles_duplicate_descriptions_in_same_batch(): void
    {
        $t1 = $this->createUncategorizedTransaction('CARREFOUR CITY', -3000);
        $t2 = $this->createUncategorizedTransaction('CARREFOUR CITY', -5000);

        TransactionCategorizer::fake([
            [
                'results' => [
                    ['index' => 1, 'description' => 'CARREFOUR CITY', 'category_slug' => 'food', 'confidence' => 0.95],
                    ['index' => 2, 'description' => 'CARREFOUR CITY', 'category_slug' => 'food', 'confidence' => 0.92],
                ],
            ],
        ]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactions($this->import))->handle();

        $this->assertSame($this->categories['food']->id, $t1->fresh()->category_id);
        $this->assertSame($this->categories['food']->id, $t2->fresh()->category_id);
        Event::assertDispatched(TransactionCategorized::class, 2);
    }

    public function test_agent_is_prompted_with_transaction_descriptions(): void
    {
        $this->createUncategorizedTransaction('CARREFOUR CITY', -5042);

        TransactionCategorizer::fake([
            [
                'results' => [
                    ['index' => 1, 'description' => 'CARREFOUR CITY', 'category_slug' => 'food', 'confidence' => 0.95],
                ],
            ],
        ]);

        Event::fake([TransactionCategorized::class]);

        (new CategorizeTransactions($this->import))->handle();

        TransactionCategorizer::assertPrompted(function ($prompt) {
            return str_contains($prompt->prompt, 'CARREFOUR CITY')
                && str_contains($prompt->prompt, '-5042');
        });
    }
}
