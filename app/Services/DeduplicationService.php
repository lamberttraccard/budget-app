<?php

namespace App\Services;

use App\Models\Transaction;
use App\Parsers\TransactionData;
use Illuminate\Support\Collection;

class DeduplicationService
{
    public function generateHash(string $date, int $amountCents, string $description, int $accountId): string
    {
        return hash('sha256', "{$date}|{$amountCents}|{$description}|{$accountId}");
    }

    /**
     * @param  Collection<int, TransactionData>  $transactions
     * @return array<int, array{transaction: TransactionData, hash: string}>
     */
    public function filterDuplicates(Collection $transactions, int $accountId): array
    {
        $entries = $transactions->map(fn (TransactionData $t) => [
            'transaction' => $t,
            'hash' => $this->generateHash($t->date, $t->amountCents, $t->description, $accountId),
        ])->unique('hash');

        $existingHashes = Transaction::whereIn('dedup_hash', $entries->pluck('hash'))
            ->pluck('dedup_hash')
            ->flip();

        return $entries->reject(fn ($entry) => $existingHashes->has($entry['hash']))->values()->all();
    }
}
