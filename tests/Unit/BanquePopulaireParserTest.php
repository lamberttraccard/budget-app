<?php

namespace Tests\Unit;

use App\Exceptions\CsvParseException;
use App\Parsers\BanquePopulaireParser;
use App\Parsers\TransactionData;
use PHPUnit\Framework\TestCase;

class BanquePopulaireParserTest extends TestCase
{
    private BanquePopulaireParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BanquePopulaireParser;
    }

    /** Encode UTF-8 test content to ISO-8859-15 as real BP exports would be. */
    private function encode(string $utf8): string
    {
        return mb_convert_encoding($utf8, 'ISO-8859-15', 'UTF-8');
    }

    private function validCsvContent(): string
    {
        return $this->encode(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."34405000000;30/07/2020;30/09/2020;EURO VIR TRESORERIE;QWERTYU;30/09/2020;-6,00\r\n"
            ."34405000000;30/07/2020;30/09/2020;BOULANGERIE DUPONT;AZERTYU;30/09/2020;-4,50\r\n"
            ."34405000000;30/07/2020;30/09/2020;EURO VIR M SMITH;0045POIU;30/09/2020;245,00\r\n"
        );
    }

    public function test_can_parse_returns_true_for_valid_bp_csv(): void
    {
        $this->assertTrue($this->parser->canParse($this->validCsvContent(), 'export.csv'));
    }

    public function test_can_parse_returns_false_for_non_bp_csv(): void
    {
        $csv = "Date,Description,Amount\n2024-01-01,Coffee,5.00\n";

        $this->assertFalse($this->parser->canParse($csv, 'revolut.csv'));
    }

    public function test_can_parse_returns_false_for_random_text(): void
    {
        $this->assertFalse($this->parser->canParse('Hello world, this is not CSV', 'file.txt'));
    }

    public function test_can_parse_returns_false_for_empty_content(): void
    {
        $this->assertFalse($this->parser->canParse('', 'empty.csv'));
    }

    public function test_parses_valid_multi_row_csv(): void
    {
        $transactions = $this->parser->parse($this->validCsvContent());

        $this->assertCount(3, $transactions);
        $this->assertInstanceOf(TransactionData::class, $transactions[0]);
    }

    public function test_parses_correct_transaction_data(): void
    {
        $transactions = $this->parser->parse($this->validCsvContent());

        $first = $transactions[0];
        $this->assertSame('2020-09-30', $first->date);
        $this->assertSame('EURO VIR TRESORERIE', $first->description);
        $this->assertSame(-600, $first->amountCents);

        $third = $transactions[2];
        $this->assertSame('EURO VIR M SMITH', $third->description);
        $this->assertSame(24500, $third->amountCents);
    }

    public function test_converts_dd_mm_yyyy_to_iso_8601(): void
    {
        $transactions = $this->parser->parse($this->validCsvContent());

        $this->assertSame('2020-09-30', $transactions[0]->date);
    }

    public function test_converts_comma_decimal_to_integer_cents(): void
    {
        $csv = $this->encode(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."34405000000;30/07/2020;30/09/2020;ITEM A;REF1;30/09/2020;-6,00\r\n"
            ."34405000000;30/07/2020;30/09/2020;ITEM B;REF2;30/09/2020;-81,4\r\n"
            ."34405000000;30/07/2020;30/09/2020;ITEM C;REF3;30/09/2020;245,00\r\n"
            ."34405000000;30/07/2020;30/09/2020;ITEM D;REF4;30/09/2020;-4,50\r\n"
        );

        $transactions = $this->parser->parse($csv);

        $this->assertSame(-600, $transactions[0]->amountCents);
        $this->assertSame(-8140, $transactions[1]->amountCents);
        $this->assertSame(24500, $transactions[2]->amountCents);
        $this->assertSame(-450, $transactions[3]->amountCents);
    }

    public function test_decodes_iso_8859_15_french_characters(): void
    {
        $csv = $this->encode(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."34405000000;30/07/2020;30/09/2020;BOULANGERIE LEFÈVRE;AZERTYU;30/09/2020;-4,50\r\n"
            ."34405000000;30/07/2020;30/09/2020;CAFÉ DES RÉSISTANTS;QWERTYU;30/09/2020;-3,20\r\n"
            ."34405000000;30/07/2020;30/09/2020;CRÊPERIE DU CHÂTEAU;ABCDEFU;30/09/2020;-12,00\r\n"
        );

        $transactions = $this->parser->parse($csv);

        $this->assertCount(3, $transactions);
        $this->assertSame('BOULANGERIE LEFÈVRE', $transactions[0]->description);
        $this->assertSame('CAFÉ DES RÉSISTANTS', $transactions[1]->description);
        $this->assertSame('CRÊPERIE DU CHÂTEAU', $transactions[2]->description);
    }

    public function test_skips_empty_lines(): void
    {
        $csv = $this->encode(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."\r\n"
            ."34405000000;30/07/2020;30/09/2020;ITEM A;REF1;30/09/2020;-6,00\r\n"
            ."\r\n"
            ."34405000000;30/07/2020;30/09/2020;ITEM B;REF2;30/09/2020;-4,50\r\n"
            ."\r\n"
        );

        $transactions = $this->parser->parse($csv);

        $this->assertCount(2, $transactions);
    }

    public function test_skips_malformed_rows_with_insufficient_columns(): void
    {
        $csv = $this->encode(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."34405000000;30/07/2020;30/09/2020;ITEM A;REF1;30/09/2020;-6,00\r\n"
            ."short;row\r\n"
            ."34405000000;30/07/2020;30/09/2020;ITEM B;REF2;30/09/2020;-4,50\r\n"
        );

        $transactions = $this->parser->parse($csv);

        $this->assertCount(2, $transactions);
    }

    public function test_handles_crlf_line_endings(): void
    {
        $csv = $this->encode(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."34405000000;30/07/2020;30/09/2020;ITEM A;REF1;30/09/2020;-6,00\r\n"
        );

        $transactions = $this->parser->parse($csv);

        $this->assertCount(1, $transactions);
        $this->assertSame('ITEM A', $transactions[0]->description);
        $this->assertSame(-600, $transactions[0]->amountCents);
    }

    public function test_raw_line_preserves_original_csv_line(): void
    {
        $transactions = $this->parser->parse($this->validCsvContent());

        $this->assertStringContainsString('EURO VIR TRESORERIE', $transactions[0]->rawLine);
        $this->assertStringContainsString(';', $transactions[0]->rawLine);
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
        $this->expectExceptionMessage('Invalid Banque Populaire CSV headers.');

        $this->parser->parse("Wrong;Headers;Here\n");
    }

    public function test_skips_rows_with_invalid_dates(): void
    {
        $csv = $this->encode(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."34405000000;30/07/2020;INVALID;ITEM A;REF1;30/09/2020;-6,00\r\n"
            ."34405000000;30/07/2020;30/09/2020;ITEM B;REF2;30/09/2020;-4,50\r\n"
        );

        $transactions = $this->parser->parse($csv);

        $this->assertCount(1, $transactions);
        $this->assertSame('ITEM B', $transactions[0]->description);
    }

    public function test_skips_rows_with_empty_amounts(): void
    {
        $csv = $this->encode(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."34405000000;30/07/2020;30/09/2020;ITEM A;REF1;30/09/2020;\r\n"
            ."34405000000;30/07/2020;30/09/2020;ITEM B;REF2;30/09/2020;-4,50\r\n"
        );

        $transactions = $this->parser->parse($csv);

        $this->assertCount(1, $transactions);
        $this->assertSame('ITEM B', $transactions[0]->description);
    }
}
