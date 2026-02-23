<?php

namespace App\Services;

use App\Events\TransactionCategorized;
use App\Jobs\CategorizeTransactions;
use App\Models\Category;
use App\Models\Import;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // Pass 1: Bank hints (deterministic)
        $bankMatches = $this->applyBankHints($uncategorized);
        if (! empty($bankMatches)) {
            DB::transaction(function () use ($bankMatches) {
                $byCategoryId = [];
                foreach ($bankMatches as $transactionId => $categoryId) {
                    $byCategoryId[$categoryId][] = $transactionId;
                }
                foreach ($byCategoryId as $categoryId => $transactionIds) {
                    Transaction::whereIn('id', $transactionIds)->update(['category_id' => $categoryId]);
                }
            });

            foreach ($bankMatches as $transactionId => $categoryId) {
                $transaction = $uncategorized->firstWhere('id', $transactionId);
                TransactionCategorized::dispatch($transaction->fresh());
            }

            $uncategorized = $uncategorized->reject(fn ($t) => isset($bankMatches[$t->id]));
        }

        // Pass 2: Merchant rules
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

    /** @return array<int, int> transactionId => categoryId */
    private function applyBankHints(Collection $uncategorized): array
    {
        $transferCategory = Category::where('slug', 'transfer')->first();

        if (! $transferCategory) {
            Log::warning('Transfer category not found — skipping bank hint detection.');

            return [];
        }

        $matches = [];
        foreach ($uncategorized as $transaction) {
            $sousCategorie = $transaction->bank_metadata['sous_categorie'] ?? null;
            if ($sousCategorie === 'Virement interne') {
                $matches[$transaction->id] = $transferCategory->id;
            }
        }

        return $matches;
    }
}
