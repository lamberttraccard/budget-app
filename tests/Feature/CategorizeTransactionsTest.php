<?php

namespace Tests\Feature;

use App\Jobs\CategorizeTransactionChunk;
use App\Jobs\CategorizeTransactions;
use App\Models\Account;
use App\Models\Import;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CategorizeTransactionsTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private Import $import;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = Account::factory()->personal()->create();
        $this->import = Import::factory()->completed()->create();
    }

    private function createUncategorizedTransactions(int $count): void
    {
        Transaction::factory()->count($count)->create([
            'account_id' => $this->account->id,
            'import_id' => $this->import->id,
            'category_id' => null,
        ]);
    }

    public function test_dispatch_creates_batch_with_correct_number_of_chunk_jobs(): void
    {
        Bus::fake([CategorizeTransactionChunk::class]);

        $this->createUncategorizedTransactions(120);

        CategorizeTransactions::dispatch($this->import);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 3; // 120 / 50 = 2.4 → 3 chunks
        });
    }

    public function test_dispatch_respects_configured_batch_size(): void
    {
        Bus::fake([CategorizeTransactionChunk::class]);
        config(['categorization.batch_size' => 25]);

        $this->createUncategorizedTransactions(100);

        CategorizeTransactions::dispatch($this->import);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 4; // 100 / 25 = 4 chunks
        });
    }

    public function test_dispatch_sets_import_status_to_categorizing_with_batch_id(): void
    {
        Bus::fake([CategorizeTransactionChunk::class]);

        $this->createUncategorizedTransactions(10);

        CategorizeTransactions::dispatch($this->import);

        $this->import->refresh();
        $this->assertSame('categorizing', $this->import->status);
        $this->assertNotNull($this->import->batch_id);
    }

    public function test_dispatch_with_no_uncategorized_sets_status_to_categorized(): void
    {
        Bus::fake([CategorizeTransactionChunk::class]);

        CategorizeTransactions::dispatch($this->import);

        $this->import->refresh();
        $this->assertSame('categorized', $this->import->status);
        Bus::assertNothingBatched();
    }

    public function test_chunk_jobs_contain_correct_transaction_ids(): void
    {
        Bus::fake([CategorizeTransactionChunk::class]);
        config(['categorization.batch_size' => 2]);

        $this->createUncategorizedTransactions(3);
        $allIds = Transaction::pluck('id')->sort()->values()->all();

        CategorizeTransactions::dispatch($this->import);

        Bus::assertBatched(function ($batch) use ($allIds) {
            $batchedIds = $batch->jobs
                ->flatMap(fn ($job) => $job->transactionIds)
                ->sort()
                ->values()
                ->all();

            return $batchedIds === $allIds;
        });
    }
}
