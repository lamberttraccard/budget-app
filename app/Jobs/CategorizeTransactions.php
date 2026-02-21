<?php

namespace App\Jobs;

use App\Ai\Agents\TransactionCategorizer;
use App\Events\TransactionCategorized;
use App\Models\Category;
use App\Models\Import;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategorizeTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(public Import $import) {}

    public function handle(): void
    {
        $uncategorized = Transaction::where('import_id', $this->import->id)
            ->whereNull('category_id')
            ->get();

        if ($uncategorized->isEmpty()) {
            return;
        }

        $categories = Category::whereNull('parent_id')->get();
        $slugToId = $categories->pluck('id', 'slug');
        $fallbackCategoryId = $slugToId->get(config('categorization.fallback_category_slug'))
            ?? $slugToId->get('other');

        if ($fallbackCategoryId === null) {
            Log::warning('No fallback category found — categories may not be seeded', [
                'import_id' => $this->import->id,
            ]);

            return;
        }

        $batchSize = config('categorization.batch_size', 50);
        $confidenceThreshold = config('categorization.confidence_threshold', 0.5);

        foreach ($uncategorized->chunk($batchSize) as $chunk) {
            try {
                $prompt = "Categorize these bank transactions:\n\n";
                $indexedTransactions = [];
                foreach ($chunk->values() as $i => $transaction) {
                    $index = $i + 1;
                    $indexedTransactions[$index] = $transaction;
                    $prompt .= "{$index}. \"{$transaction->description}\" ({$transaction->amount_cents} cents, {$transaction->date->format('Y-m-d')})\n";
                }

                $response = (new TransactionCategorizer)->prompt($prompt);

                $resultsByIndex = collect($response['results'])->keyBy('index');

                DB::transaction(function () use ($indexedTransactions, $resultsByIndex, $slugToId, $fallbackCategoryId, $confidenceThreshold) {
                    foreach ($indexedTransactions as $index => $transaction) {
                        $result = $resultsByIndex->get($index);

                        if ($result && $result['confidence'] >= $confidenceThreshold && $slugToId->has($result['category_slug'])) {
                            $transaction->update(['category_id' => $slugToId->get($result['category_slug'])]);
                        } else {
                            $transaction->update(['category_id' => $fallbackCategoryId]);
                        }

                        TransactionCategorized::dispatch($transaction->fresh());
                    }
                });
            } catch (\Throwable $e) {
                Log::error('AI categorization failed', [
                    'import_id' => $this->import->id,
                    'attempt' => $this->job?->attempts(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }
    }

}
