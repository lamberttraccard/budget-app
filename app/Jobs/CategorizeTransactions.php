<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\Transaction;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class CategorizeTransactions
{
    public static function dispatch(Import $import): void
    {
        $transactionIds = Transaction::where('import_id', $import->id)
            ->whereNull('category_id')
            ->pluck('id');

        if ($transactionIds->isEmpty()) {
            $import->update(['status' => 'categorized']);

            return;
        }

        $batchSize = config('categorization.batch_size', 50);
        $jobs = $transactionIds->chunk($batchSize)
            ->map(fn ($chunk) => new CategorizeTransactionChunk($chunk->values()->all()))
            ->all();

        $import->update(['status' => 'categorizing']);

        $batch = Bus::batch($jobs)
            ->allowFailures()
            ->catch(function (\Throwable $e) use ($import) {
                Log::error('Batch categorization had failures', [
                    'import_id' => $import->id,
                    'error' => $e->getMessage(),
                ]);
            })
            ->finally(function () use ($import) {
                $import->fresh()->update(['status' => 'categorized']);
            })
            ->dispatch();

        $import->update(['batch_id' => $batch->id]);
    }
}
