<?php

namespace App\Services;

use App\Events\TransactionCategorized;
use App\Jobs\CategorizeTransactions;
use App\Models\Import;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CategorizationService
{
    public function __construct(private MerchantMatchingService $merchantMatcher) {}

    public function categorize(Import $import): void
    {
        $uncategorized = Transaction::where('import_id', $import->id)
            ->whereNull('category_id')
            ->get();

        if ($uncategorized->isEmpty()) {
            return;
        }

        $matches = $this->merchantMatcher->matchBatch($uncategorized);

        if (! empty($matches)) {
            DB::transaction(function () use ($matches) {
                $byCategoryId = [];
                foreach ($matches as $transactionId => $categoryId) {
                    $byCategoryId[$categoryId][] = $transactionId;
                }
                foreach ($byCategoryId as $categoryId => $transactionIds) {
                    Transaction::whereIn('id', $transactionIds)->update(['category_id' => $categoryId]);
                }
            });

            foreach ($matches as $transactionId => $categoryId) {
                $transaction = $uncategorized->firstWhere('id', $transactionId);
                TransactionCategorized::dispatch($transaction->fresh());
            }
        }

        $remainingCount = $uncategorized->count() - count($matches);

        if ($remainingCount > 0) {
            CategorizeTransactions::dispatch($import);
        } else {
            $import->update(['status' => 'categorized']);
        }
    }
}
