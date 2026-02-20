<?php

namespace App\Services;

use App\Events\ImportCompleted;
use App\Models\Import;
use App\Models\Transaction;
use App\Parsers\CsvParserFactory;
use Illuminate\Support\Facades\DB;

class CsvImportService
{
    public function __construct(
        private CsvParserFactory $parserFactory,
        private DeduplicationService $deduplicationService,
    ) {}

    public function import(string $content, string $filename, int $accountId): Import
    {
        $parser = $this->parserFactory->detect($content, $filename);
        $transactions = $parser->parse($content);

        $import = Import::create([
            'file_name' => $filename,
            'bank' => $parser->bankName(),
            'status' => 'pending',
            'transaction_count' => 0,
        ]);

        try {
            DB::transaction(function () use ($transactions, $import, $accountId) {
                $uniqueEntries = $this->deduplicationService->filterDuplicates($transactions, $accountId);
                $now = now();

                if (count($uniqueEntries) > 0) {
                    $rows = array_map(fn ($entry) => [
                        'amount_cents' => $entry['transaction']->amountCents,
                        'date' => $entry['transaction']->date,
                        'description' => $entry['transaction']->description,
                        'dedup_hash' => $entry['hash'],
                        'category_id' => null,
                        'account_id' => $accountId,
                        'import_id' => $import->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $uniqueEntries);

                    collect($rows)->chunk(500)->each(fn ($chunk) => Transaction::insert($chunk->all()));

                    $dateValues = array_map(fn ($e) => $e['transaction']->date, $uniqueEntries);

                    $import->update([
                        'status' => 'completed',
                        'transaction_count' => count($uniqueEntries),
                        'date_from' => min($dateValues),
                        'date_to' => max($dateValues),
                        'imported_at' => $now,
                    ]);
                } else {
                    $import->update([
                        'status' => 'completed',
                        'transaction_count' => 0,
                        'imported_at' => $now,
                    ]);
                }
            });

            ImportCompleted::dispatch($import->fresh());
        } catch (\Throwable $e) {
            $import->refresh();
            $import->update(['status' => 'failed']);

            throw $e;
        }

        return $import->fresh();
    }
}
