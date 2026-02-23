<?php

namespace Tests\Feature;

use App\Events\ImportCompleted;
use App\Exceptions\UnrecognizedCsvFormatException;
use App\Models\Account;
use App\Models\Import;
use App\Models\Transaction;
use App\Services\CsvImportService;
use App\Services\DeduplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CsvImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private CsvImportService $service;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CsvImportService::class);
        $this->account = Account::factory()->create();
    }

    private function bpCsvContent(): string
    {
        return mb_convert_encoding(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."34405000000;30/07/2020;15/01/2026;BOULANGERIE DUPONT;REF001;15/01/2026;-4,50\r\n"
            ."34405000000;30/07/2020;16/01/2026;EURO VIR M SMITH;REF002;16/01/2026;245,00\r\n"
            ."34405000000;30/07/2020;17/01/2026;SUPERMARCHE LECLERC;REF003;17/01/2026;-68,32\r\n",
            'ISO-8859-15',
            'UTF-8',
        );
    }

    private function revolutPersonalCsvContent(): string
    {
        return "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2026-01-15 14:30:00,2026-01-15 15:00:00,Carrefour,-42.50,0.00,EUR,COMPLETED,972.50\n"
            ."TRANSFER,Current,2026-01-16 09:00:00,2026-01-16 09:05:00,John Doe,150.00,0.00,EUR,COMPLETED,1015.00\n";
    }

    public function test_import_bp_csv_creates_import_record(): void
    {
        Event::fake([ImportCompleted::class]);

        $result = $this->service->import($this->bpCsvContent(), 'export_bp.csv', $this->account->id);

        $this->assertSame('export_bp.csv', $result->file_name);
        $this->assertSame('Banque Populaire', $result->bank);
        $this->assertSame('completed', $result->status);
        $this->assertSame(3, $result->transaction_count);
        $this->assertSame('2026-01-15', $result->date_from->format('Y-m-d'));
        $this->assertSame('2026-01-17', $result->date_to->format('Y-m-d'));
        $this->assertNotNull($result->imported_at);
    }

    public function test_import_revolut_csv_creates_import_with_revolut_bank(): void
    {
        Event::fake([ImportCompleted::class]);

        $result = $this->service->import($this->revolutPersonalCsvContent(), 'revolut.csv', $this->account->id);

        $this->assertSame('Revolut', $result->bank);
        $this->assertSame('completed', $result->status);
        $this->assertSame(2, $result->transaction_count);
    }

    public function test_imported_transactions_have_non_null_dedup_hash(): void
    {
        Event::fake([ImportCompleted::class]);

        $this->service->import($this->bpCsvContent(), 'export.csv', $this->account->id);

        $transactions = Transaction::all();
        foreach ($transactions as $transaction) {
            $this->assertNotNull($transaction->dedup_hash);
            $this->assertSame(64, strlen($transaction->dedup_hash));
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $transaction->dedup_hash);
        }
    }

    public function test_imported_transactions_linked_to_import_record(): void
    {
        Event::fake([ImportCompleted::class]);

        $import = $this->service->import($this->bpCsvContent(), 'export.csv', $this->account->id);

        $transactions = Transaction::where('import_id', $import->id)->get();
        $this->assertCount(3, $transactions);
    }

    public function test_imported_transactions_linked_to_correct_account(): void
    {
        Event::fake([ImportCompleted::class]);

        $this->service->import($this->bpCsvContent(), 'export.csv', $this->account->id);

        $transactions = Transaction::all();
        foreach ($transactions as $transaction) {
            $this->assertSame($this->account->id, $transaction->account_id);
        }
    }

    public function test_reimport_same_csv_produces_zero_new_transactions(): void
    {
        Event::fake([ImportCompleted::class]);

        $this->service->import($this->bpCsvContent(), 'export.csv', $this->account->id);
        $this->assertDatabaseCount('transactions', 3);

        $secondImport = $this->service->import($this->bpCsvContent(), 'export.csv', $this->account->id);

        $this->assertDatabaseCount('transactions', 3);
        $this->assertSame(0, $secondImport->transaction_count);
        $this->assertSame('completed', $secondImport->status);
    }

    public function test_partial_reimport_inserts_only_new_transactions(): void
    {
        Event::fake([ImportCompleted::class]);

        // First import: 3 BP transactions
        $this->service->import($this->bpCsvContent(), 'export.csv', $this->account->id);
        $this->assertDatabaseCount('transactions', 3);

        // Second import: 5 transactions (3 overlapping + 2 new)
        $extendedCsv = mb_convert_encoding(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."34405000000;30/07/2020;15/01/2026;BOULANGERIE DUPONT;REF001;15/01/2026;-4,50\r\n"
            ."34405000000;30/07/2020;16/01/2026;EURO VIR M SMITH;REF002;16/01/2026;245,00\r\n"
            ."34405000000;30/07/2020;17/01/2026;SUPERMARCHE LECLERC;REF003;17/01/2026;-68,32\r\n"
            ."34405000000;30/07/2020;18/01/2026;PHARMACIE CENTRALE;REF004;18/01/2026;-12,90\r\n"
            ."34405000000;30/07/2020;19/01/2026;STATION TOTAL;REF005;19/01/2026;-55,00\r\n",
            'ISO-8859-15',
            'UTF-8',
        );

        $secondImport = $this->service->import($extendedCsv, 'export2.csv', $this->account->id);

        $this->assertDatabaseCount('transactions', 5);
        $this->assertSame(2, $secondImport->transaction_count);
    }

    public function test_import_completed_event_is_dispatched(): void
    {
        Event::fake([ImportCompleted::class]);

        $this->service->import($this->bpCsvContent(), 'export.csv', $this->account->id);

        Event::assertDispatched(ImportCompleted::class, function (ImportCompleted $event) {
            return $event->import->status === 'completed' && $event->import->transaction_count === 3;
        });
    }

    public function test_import_completed_event_dispatched_when_all_duplicates(): void
    {
        Event::fake([ImportCompleted::class]);

        // First import
        $this->service->import($this->bpCsvContent(), 'export.csv', $this->account->id);

        // Second import — all duplicates
        $this->service->import($this->bpCsvContent(), 'export2.csv', $this->account->id);

        Event::assertDispatched(ImportCompleted::class, function (ImportCompleted $event) {
            return $event->import->transaction_count === 0;
        });
    }

    public function test_import_throws_and_no_import_created_when_format_unrecognized(): void
    {
        try {
            $this->service->import('totally,invalid,csv,format', 'bad.csv', $this->account->id);
            $this->fail('Expected UnrecognizedCsvFormatException');
        } catch (UnrecognizedCsvFormatException) {
            $this->assertDatabaseCount('imports', 0);
        }
    }

    public function test_batch_insert_with_many_transactions(): void
    {
        Event::fake([ImportCompleted::class]);

        $lines = "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n";
        for ($i = 1; $i <= 55; $i++) {
            $day = str_pad($i % 28 + 1, 2, '0', STR_PAD_LEFT);
            $month = $i <= 28 ? '01' : '02';
            $lines .= "34405000000;30/07/2020;{$day}/{$month}/2026;Transaction {$i};REF{$i};{$day}/{$month}/2026;-"
                .number_format($i * 1.5, 2, ',', '')."\r\n";
        }

        $csv = mb_convert_encoding($lines, 'ISO-8859-15', 'UTF-8');
        $result = $this->service->import($csv, 'large.csv', $this->account->id);

        $this->assertSame(55, $result->transaction_count);
        $this->assertDatabaseCount('transactions', 55);

        // Verify all transactions have correct account and dedup hash
        $this->assertSame(0, Transaction::where('import_id', $result->id)
            ->where(fn ($q) => $q->where('account_id', '!=', $this->account->id)->orWhereNull('dedup_hash'))
            ->count());

    }

    public function test_date_from_and_date_to_computed_correctly(): void
    {
        Event::fake([ImportCompleted::class]);

        $result = $this->service->import($this->bpCsvContent(), 'export.csv', $this->account->id);

        // BP CSV has dates: 15/01/2026, 16/01/2026, 17/01/2026
        $this->assertSame('2026-01-15', $result->date_from->format('Y-m-d'));
        $this->assertSame('2026-01-17', $result->date_to->format('Y-m-d'));
    }

    private function bpEnrichedCsvContent(): string
    {
        return mb_convert_encoding(
            "Date de comptabilisation;Libelle simplifie;Libelle operation;Reference;Informations complementaires;Type operation;Categorie;Sous categorie;Debit;Credit;Date operation;Date de valeur;Pointage operation\r\n"
            ."30/01/2026;VIR INTERNE;VIREMENT COMPTE EPARGNE;REF001;Info1;Virement;Transaction exclue;Virement interne;-500,00;;30/01/2026;30/01/2026;P\r\n"
            ."31/01/2026;CARREFOUR;CARTE CARREFOUR CITY;REF002;Info2;Carte;Alimentation;Hyper/supermarche;-42,50;;31/01/2026;31/01/2026;P\r\n",
            'ISO-8859-15',
            'UTF-8',
        );
    }

    public function test_import_enriched_bp_csv_persists_bank_metadata(): void
    {
        Event::fake([ImportCompleted::class]);

        $import = $this->service->import($this->bpEnrichedCsvContent(), 'enriched.csv', $this->account->id);

        $transactions = Transaction::where('import_id', $import->id)->orderBy('date')->get();
        $this->assertCount(2, $transactions);

        // First transaction: Virement interne
        $this->assertNotNull($transactions[0]->bank_metadata);
        $this->assertSame('Virement interne', $transactions[0]->bank_metadata['sous_categorie']);
        $this->assertSame('Transaction exclue', $transactions[0]->bank_metadata['categorie']);

        // Second transaction: regular category
        $this->assertNotNull($transactions[1]->bank_metadata);
        $this->assertSame('Alimentation', $transactions[1]->bank_metadata['categorie']);
    }

    public function test_import_classic_bp_csv_has_null_bank_metadata(): void
    {
        Event::fake([ImportCompleted::class]);

        $import = $this->service->import($this->bpCsvContent(), 'classic.csv', $this->account->id);

        $transactions = Transaction::where('import_id', $import->id)->get();
        foreach ($transactions as $transaction) {
            $this->assertNull($transaction->bank_metadata);
        }
    }

    public function test_import_revolut_csv_has_null_bank_metadata(): void
    {
        Event::fake([ImportCompleted::class]);

        $import = $this->service->import($this->revolutPersonalCsvContent(), 'revolut.csv', $this->account->id);

        $transactions = Transaction::where('import_id', $import->id)->get();
        foreach ($transactions as $transaction) {
            $this->assertNull($transaction->bank_metadata);
        }
    }

    public function test_database_transaction_atomicity(): void
    {
        Event::fake([ImportCompleted::class]);

        // First import: populate DB with 3 transactions
        $this->service->import($this->bpCsvContent(), 'export.csv', $this->account->id);
        $this->assertDatabaseCount('transactions', 3);

        // Mock DeduplicationService to bypass filter, causing unique constraint violation
        $this->mock(DeduplicationService::class, function ($mock) {
            $mock->shouldReceive('filterDuplicates')
                ->andReturnUsing(function ($transactions, $accountId) {
                    $service = new DeduplicationService;

                    return $transactions->map(fn ($t) => [
                        'transaction' => $t,
                        'hash' => $service->generateHash($t->date, $t->amountCents, $t->description, $accountId),
                    ])->all();
                });
        });

        $service = app(CsvImportService::class);

        try {
            $service->import($this->bpCsvContent(), 'export2.csv', $this->account->id);
            $this->fail('Expected exception due to unique constraint violation');
        } catch (\Throwable) {
            // Verify no new transactions were added (rolled back)
            $this->assertDatabaseCount('transactions', 3);

            // Verify Import record exists with 'failed' status
            $failedImport = Import::where('file_name', 'export2.csv')->first();
            $this->assertNotNull($failedImport);
            $this->assertSame('failed', $failedImport->status);
        }
    }
}
