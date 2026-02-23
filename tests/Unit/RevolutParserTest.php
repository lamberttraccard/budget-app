<?php

namespace Tests\Unit;

use App\Exceptions\CsvParseException;
use App\Parsers\RevolutParser;
use App\Parsers\TransactionData;
use PHPUnit\Framework\TestCase;

class RevolutParserTest extends TestCase
{
    private RevolutParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RevolutParser;
    }

    private function personalCsvContent(): string
    {
        return "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2023-03-15 14:30:00,2023-03-16 08:00:00,Carrefour,-42.50,0.00,EUR,COMPLETED,972.50\n"
            ."TRANSFER,Current,2023-03-14 09:00:00,2023-03-14 09:05:00,John Doe,150.00,0.00,EUR,COMPLETED,1015.00\n"
            ."TOPUP,Current,2023-03-12 08:00:00,2023-03-12 08:01:00,Top-Up by *1234,500.00,0.00,EUR,COMPLETED,895.00\n";
    }

    private function personalFrCsvContent(): string
    {
        return "Type,Produit,Date de début,Date de fin,Description,Montant,Frais,Devise,État,Solde\n"
            ."Paiement par carte,Pro,2025-01-04 08:41:36,2025-01-05 12:58:21,Namecheap,-16.71,0.17,EUR,TERMINÉ,1827.01\n"
            ."Ajout de fonds,Pro,2025-01-02 11:54:58,2025-01-02 11:54:59,Paiement envoyé par L'IMPERIAL,65.00,0.00,EUR,TERMINÉ,1843.89\n"
            ."CASHBACK,Pro,2025-01-02 01:28:41,2025-01-02 01:28:41,Cashback Pro,0.83,0.00,EUR,TERMINÉ,1778.89\n";
    }

    private function businessCsvContent(): string
    {
        return "Date started (UTC),Date completed (UTC),ID,Type,Description,Reference,Payer,Card number,Orig currency,Orig amount,Payment currency,Amount,Fee,Balance,Account,Beneficiary account number,Beneficiary sort code or routing number,Beneficiary IBAN,Beneficiary BIC\n"
            ."2023-03-15 14:30:00,2023-03-16 08:00:00,abc123,CARD_PAYMENT,Restaurant Le Provençal,,Lambert T,*1234,EUR,-55.00,EUR,-55.00,0.00,1945.00,Main,,,,\n"
            ."2023-03-14 09:00:00,2023-03-14 09:05:00,def456,TRANSFER,Client Payment,INV-2023-042,ACME Corp,,EUR,2500.00,EUR,2500.00,0.00,2000.00,Main,,,,\n";
    }

    // --- canParse() tests ---

    public function test_can_parse_returns_true_for_valid_revolut_personal_csv(): void
    {
        $this->assertTrue($this->parser->canParse($this->personalCsvContent(), 'revolut.csv'));
    }

    public function test_can_parse_returns_true_for_valid_revolut_business_csv(): void
    {
        $this->assertTrue($this->parser->canParse($this->businessCsvContent(), 'revolut-business.csv'));
    }

    public function test_can_parse_returns_true_for_valid_revolut_personal_fr_csv(): void
    {
        $this->assertTrue($this->parser->canParse($this->personalFrCsvContent(), 'revolut-fr.csv'));
    }

    public function test_can_parse_returns_false_for_bp_csv(): void
    {
        $csv = mb_convert_encoding(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\n"
            ."34405000000;30/07/2020;30/09/2020;TEST;REF;30/09/2020;-6,00\n",
            'ISO-8859-15',
            'UTF-8',
        );

        $this->assertFalse($this->parser->canParse($csv, 'bp-export.csv'));
    }

    public function test_can_parse_returns_false_for_random_text(): void
    {
        $this->assertFalse($this->parser->canParse('Hello world, this is not CSV', 'file.txt'));
    }

    public function test_can_parse_returns_false_for_partial_business_headers(): void
    {
        $csv = "Date started (UTC),Description,Amount\n"
            ."2023-03-15 14:30:00,Test,-10.00\n";

        $this->assertFalse($this->parser->canParse($csv, 'partial.csv'));
    }

    public function test_can_parse_returns_false_for_empty_content(): void
    {
        $this->assertFalse($this->parser->canParse('', 'empty.csv'));
    }

    // --- Personal format parsing tests ---

    public function test_parses_valid_personal_multi_row_csv(): void
    {
        $transactions = $this->parser->parse($this->personalCsvContent());

        $this->assertCount(3, $transactions);
        $this->assertInstanceOf(TransactionData::class, $transactions[0]);
    }

    public function test_parses_correct_personal_transaction_data(): void
    {
        $transactions = $this->parser->parse($this->personalCsvContent());

        $first = $transactions[0];
        $this->assertSame('2023-03-16', $first->date);
        $this->assertSame('Carrefour', $first->description);
        $this->assertSame(-4250, $first->amountCents);

        $second = $transactions[1];
        $this->assertSame('2023-03-14', $second->date);
        $this->assertSame('John Doe', $second->description);
        $this->assertSame(15000, $second->amountCents);

        $third = $transactions[2];
        $this->assertSame('Top-Up by *1234', $third->description);
        $this->assertSame(50000, $third->amountCents);
    }

    public function test_converts_datetime_to_iso_8601_date(): void
    {
        $transactions = $this->parser->parse($this->personalCsvContent());

        $this->assertSame('2023-03-16', $transactions[0]->date);
        $this->assertSame('2023-03-14', $transactions[1]->date);
    }

    public function test_converts_dot_decimal_to_integer_cents(): void
    {
        $csv = "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2023-01-01 10:00:00,2023-01-01 10:00:00,Item A,-42.50,0.00,EUR,COMPLETED,100.00\n"
            ."CARD_PAYMENT,Current,2023-01-01 10:00:00,2023-01-01 10:00:00,Item B,-8.99,0.00,EUR,COMPLETED,100.00\n"
            ."TRANSFER,Current,2023-01-01 10:00:00,2023-01-01 10:00:00,Item C,150.00,0.00,EUR,COMPLETED,100.00\n"
            ."CARD_PAYMENT,Current,2023-01-01 10:00:00,2023-01-01 10:00:00,Item D,0.00,0.00,EUR,COMPLETED,100.00\n";

        $transactions = $this->parser->parse($csv);

        $this->assertSame(-4250, $transactions[0]->amountCents);
        $this->assertSame(-899, $transactions[1]->amountCents);
        $this->assertSame(15000, $transactions[2]->amountCents);
        $this->assertSame(0, $transactions[3]->amountCents);
    }

    public function test_only_completed_transactions_are_included(): void
    {
        $csv = "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2023-03-15 14:30:00,2023-03-16 08:00:00,Completed One,-10.00,0.00,EUR,COMPLETED,100.00\n"
            ."CARD_PAYMENT,Current,2023-03-13 18:22:00,,Pending One,-29.99,0.00,EUR,PENDING,100.00\n"
            ."CARD_PAYMENT,Current,2023-03-11 12:00:00,2023-03-11 12:00:00,Reverted One,-8.50,0.00,EUR,REVERTED,100.00\n"
            ."CARD_PAYMENT,Current,2023-03-10 09:00:00,2023-03-10 09:00:00,Failed One,-5.00,0.00,EUR,FAILED,100.00\n"
            ."CARD_PAYMENT,Current,2023-03-09 09:00:00,2023-03-09 09:00:00,Declined One,-3.00,0.00,EUR,DECLINED,100.00\n"
            ."TRANSFER,Current,2023-03-08 09:00:00,2023-03-08 09:05:00,Completed Two,200.00,0.00,EUR,COMPLETED,300.00\n";

        $transactions = $this->parser->parse($csv);

        $this->assertCount(2, $transactions);
        $this->assertSame('Completed One', $transactions[0]->description);
        $this->assertSame('Completed Two', $transactions[1]->description);
    }

    // --- French personal format parsing tests ---

    public function test_parses_valid_personal_fr_csv(): void
    {
        $transactions = $this->parser->parse($this->personalFrCsvContent());

        $this->assertCount(3, $transactions);

        $first = $transactions[0];
        $this->assertSame('2025-01-05', $first->date);
        $this->assertSame('Namecheap', $first->description);
        $this->assertSame(-1671, $first->amountCents);

        $second = $transactions[1];
        $this->assertSame('2025-01-02', $second->date);
        $this->assertSame("Paiement envoyé par L'IMPERIAL", $second->description);
        $this->assertSame(6500, $second->amountCents);
    }

    public function test_fr_format_filters_non_termine_states(): void
    {
        $csv = "Type,Produit,Date de début,Date de fin,Description,Montant,Frais,Devise,État,Solde\n"
            ."Paiement par carte,Pro,2025-01-04 08:41:36,2025-01-05 12:58:21,Completed,-16.71,0.17,EUR,TERMINÉ,1827.01\n"
            ."Paiement par carte,Pro,2025-01-04 08:41:36,,Pending,-10.00,0.00,EUR,EN ATTENTE,1837.01\n"
            ."Paiement par carte,Pro,2025-01-04 08:41:36,2025-01-05 12:58:21,Reverted,-5.00,0.00,EUR,ANNULÉ,1842.01\n";

        $transactions = $this->parser->parse($csv);

        $this->assertCount(1, $transactions);
        $this->assertSame('Completed', $transactions[0]->description);
    }

    // --- Business format parsing tests ---

    public function test_parses_valid_business_csv(): void
    {
        $transactions = $this->parser->parse($this->businessCsvContent());

        $this->assertCount(2, $transactions);

        $first = $transactions[0];
        $this->assertSame('2023-03-16', $first->date);
        $this->assertSame('Restaurant Le Provençal', $first->description);
        $this->assertSame(-5500, $first->amountCents);

        $second = $transactions[1];
        $this->assertSame('2023-03-14', $second->date);
        $this->assertSame('Client Payment', $second->description);
        $this->assertSame(250000, $second->amountCents);
    }

    // --- Edge case tests ---

    public function test_skips_malformed_rows_with_insufficient_columns(): void
    {
        $csv = "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2023-03-15 14:30:00,2023-03-16 08:00:00,Item A,-10.00,0.00,EUR,COMPLETED,100.00\n"
            ."short,row\n"
            ."TRANSFER,Current,2023-03-14 09:00:00,2023-03-14 09:05:00,Item B,50.00,0.00,EUR,COMPLETED,150.00\n";

        $transactions = $this->parser->parse($csv);

        $this->assertCount(2, $transactions);
    }

    public function test_skips_empty_lines(): void
    {
        $csv = "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."\n"
            ."CARD_PAYMENT,Current,2023-03-15 14:30:00,2023-03-16 08:00:00,Item A,-10.00,0.00,EUR,COMPLETED,100.00\n"
            ."\n"
            ."TRANSFER,Current,2023-03-14 09:00:00,2023-03-14 09:05:00,Item B,50.00,0.00,EUR,COMPLETED,150.00\n"
            ."\n";

        $transactions = $this->parser->parse($csv);

        $this->assertCount(2, $transactions);
    }

    public function test_skips_rows_with_empty_completed_date(): void
    {
        $csv = "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2023-03-15 14:30:00,,Pending Item,-10.00,0.00,EUR,COMPLETED,100.00\n"
            ."CARD_PAYMENT,Current,2023-03-14 09:00:00,2023-03-14 09:05:00,Valid Item,-5.00,0.00,EUR,COMPLETED,95.00\n";

        $transactions = $this->parser->parse($csv);

        $this->assertCount(1, $transactions);
        $this->assertSame('Valid Item', $transactions[0]->description);
    }

    public function test_preserves_utf8_characters_in_description(): void
    {
        $csv = "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2023-03-15 14:30:00,2023-03-16 08:00:00,Café des Résistants,-12.00,0.00,EUR,COMPLETED,100.00\n"
            ."CARD_PAYMENT,Current,2023-03-14 09:00:00,2023-03-14 09:05:00,Crêperie du Château,-8.50,0.00,EUR,COMPLETED,100.00\n";

        $transactions = $this->parser->parse($csv);

        $this->assertCount(2, $transactions);
        $this->assertSame('Café des Résistants', $transactions[0]->description);
        $this->assertSame('Crêperie du Château', $transactions[1]->description);
    }

    public function test_raw_line_preserves_original_csv_line(): void
    {
        $transactions = $this->parser->parse($this->personalCsvContent());

        $this->assertStringContainsString('Carrefour', $transactions[0]->rawLine);
        $this->assertStringContainsString('-42.50', $transactions[0]->rawLine);
        $this->assertStringContainsString('COMPLETED', $transactions[0]->rawLine);
    }

    public function test_throws_csv_parse_exception_for_empty_content(): void
    {
        $this->expectException(CsvParseException::class);
        $this->expectExceptionMessage('CSV content is empty.');

        $this->parser->parse('');
    }

    public function test_throws_csv_parse_exception_for_invalid_headers(): void
    {
        $this->expectException(CsvParseException::class);
        $this->expectExceptionMessage('Invalid Revolut CSV headers.');

        $this->parser->parse("Wrong,Headers,Here\n");
    }

    public function test_handles_crlf_line_endings(): void
    {
        $csv = "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\r\n"
            ."CARD_PAYMENT,Current,2023-03-15 14:30:00,2023-03-16 08:00:00,Item A,-10.00,0.00,EUR,COMPLETED,100.00\r\n";

        $transactions = $this->parser->parse($csv);

        $this->assertCount(1, $transactions);
        $this->assertSame('Item A', $transactions[0]->description);
        $this->assertSame(-1000, $transactions[0]->amountCents);
    }

    public function test_skips_rows_with_invalid_dates(): void
    {
        $csv = "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2023-03-15 14:30:00,INVALID,Item A,-10.00,0.00,EUR,COMPLETED,100.00\n"
            ."CARD_PAYMENT,Current,2023-03-14 09:00:00,2023-03-14 09:05:00,Item B,-5.00,0.00,EUR,COMPLETED,95.00\n";

        $transactions = $this->parser->parse($csv);

        $this->assertCount(1, $transactions);
        $this->assertSame('Item B', $transactions[0]->description);
    }

    public function test_skips_rows_with_empty_amounts(): void
    {
        $csv = "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2023-03-15 14:30:00,2023-03-16 08:00:00,Item A,,0.00,EUR,COMPLETED,100.00\n"
            ."CARD_PAYMENT,Current,2023-03-14 09:00:00,2023-03-14 09:05:00,Item B,-5.00,0.00,EUR,COMPLETED,95.00\n";

        $transactions = $this->parser->parse($csv);

        $this->assertCount(1, $transactions);
        $this->assertSame('Item B', $transactions[0]->description);
    }
}
