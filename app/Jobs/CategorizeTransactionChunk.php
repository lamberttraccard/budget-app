<?php

namespace App\Jobs;

use App\Ai\Agents\TransactionCategorizer;
use App\Events\TransactionCategorized;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategorizeTransactionChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public int $timeout = 75;

    /**
     * @param  array<int>  $transactionIds
     */
    public function __construct(public array $transactionIds) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $uncategorized = Transaction::whereIn('id', $this->transactionIds)
            ->whereNull('category_id')
            ->get();

        if ($uncategorized->isEmpty()) {
            return;
        }

        $categories = Category::whereNull('parent_id')->get();
        $slugToId = $categories->pluck('id', 'slug');
        $confidenceThreshold = config('categorization.confidence_threshold', 0.5);

        try {
            $prompt = "Categorize these bank transactions:\n\n";
            $indexedTransactions = [];
            foreach ($uncategorized->values() as $i => $transaction) {
                $index = $i + 1;
                $indexedTransactions[$index] = $transaction;
                $hint = '';
                if ($transaction->bank_metadata) {
                    $cat = $transaction->bank_metadata['categorie'] ?? '';
                    $sub = $transaction->bank_metadata['sous_categorie'] ?? '';
                    if ($cat) {
                        $hint = ' [bank hint: '.$cat.($sub ? " > {$sub}" : '').']';
                    }
                }
                $prompt .= "{$index}. \"{$transaction->description}\" ({$transaction->amount_cents} cents, {$transaction->date->format('Y-m-d')}){$hint}\n";
            }

            $response = (new TransactionCategorizer)->prompt($prompt);

            $resultsByIndex = collect($response['results'])->keyBy('index');

            $otherCategoryId = $slugToId->get('other');

            DB::transaction(function () use ($indexedTransactions, $resultsByIndex, $slugToId, $confidenceThreshold, $otherCategoryId) {
                foreach ($indexedTransactions as $index => $transaction) {
                    $result = $resultsByIndex->get($index);

                    if ($result && $result['confidence'] >= $confidenceThreshold && $slugToId->has($result['category_slug'])) {
                        $transaction->update(['category_id' => $slugToId->get($result['category_slug'])]);
                        TransactionCategorized::dispatch($transaction->fresh());
                    } elseif ($result && $otherCategoryId) {
                        $transaction->update(['category_id' => $otherCategoryId]);
                        TransactionCategorized::dispatch($transaction->fresh());
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('AI categorization chunk failed', [
                'transaction_ids' => $this->transactionIds,
                'attempt' => $this->job?->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
