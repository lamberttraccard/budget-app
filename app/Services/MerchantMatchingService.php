<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MerchantRule;
use Illuminate\Support\Collection;

class MerchantMatchingService
{
    public function match(string $description): ?Category
    {
        $rule = MerchantRule::whereRaw('LOWER(merchant_pattern) = LOWER(?)', [$description])
            ->first();

        return $rule?->category;
    }

    /**
     * @param  Collection<int, \App\Models\Transaction>  $transactions
     * @return array<int, int> [transactionId => categoryId]
     */
    public function matchBatch(Collection $transactions): array
    {
        $rules = MerchantRule::all();
        $ruleMap = $rules->keyBy(fn (MerchantRule $rule) => mb_strtolower($rule->merchant_pattern));

        $matches = [];
        foreach ($transactions as $transaction) {
            $key = mb_strtolower($transaction->description);
            if ($ruleMap->has($key)) {
                $matches[$transaction->id] = $ruleMap[$key]->category_id;
            }
        }

        return $matches;
    }
}
