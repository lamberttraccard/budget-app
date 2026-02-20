<?php

namespace Tests\Unit;

use App\Exceptions\UnrecognizedCsvFormatException;
use App\Parsers\BanquePopulaireParser;
use App\Parsers\CsvParserFactory;
use App\Parsers\RevolutParser;
use Tests\TestCase;

class CsvParserFactoryTest extends TestCase
{
    private function factoryWithBothParsers(): CsvParserFactory
    {
        return new CsvParserFactory([
            BanquePopulaireParser::class,
            RevolutParser::class,
        ]);
    }

    public function test_returns_banque_populaire_parser_for_bp_content(): void
    {
        $factory = $this->factoryWithBothParsers();

        $csv = mb_convert_encoding(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."34405000000;30/07/2020;30/09/2020;TEST;REF;30/09/2020;-6,00\r\n",
            'ISO-8859-15',
            'UTF-8',
        );

        $parser = $factory->detect($csv, 'export.csv');

        $this->assertInstanceOf(BanquePopulaireParser::class, $parser);
    }

    public function test_returns_revolut_parser_for_revolut_personal_content(): void
    {
        $factory = $this->factoryWithBothParsers();

        $csv = "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2023-03-15 14:30:00,2023-03-16 08:00:00,Carrefour,-42.50,0.00,EUR,COMPLETED,972.50\n";

        $parser = $factory->detect($csv, 'revolut.csv');

        $this->assertInstanceOf(RevolutParser::class, $parser);
    }

    public function test_returns_revolut_parser_for_revolut_business_content(): void
    {
        $factory = $this->factoryWithBothParsers();

        $csv = "Date started (UTC),Date completed (UTC),ID,Type,Description,Reference,Payer,Card number,Orig currency,Orig amount,Payment currency,Amount,Fee,Balance,Account,Beneficiary account number,Beneficiary sort code or routing number,Beneficiary IBAN,Beneficiary BIC\n"
            ."2023-03-15 14:30:00,2023-03-16 08:00:00,abc123,CARD_PAYMENT,Test,,,,EUR,-55.00,EUR,-55.00,0.00,1945.00,Main,,,,\n";

        $parser = $factory->detect($csv, 'revolut-business.csv');

        $this->assertInstanceOf(RevolutParser::class, $parser);
    }

    public function test_distinguishes_bp_from_revolut_formats(): void
    {
        $factory = $this->factoryWithBothParsers();

        $bpCsv = mb_convert_encoding(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."34405000000;30/07/2020;30/09/2020;TEST;REF;30/09/2020;-6,00\r\n",
            'ISO-8859-15',
            'UTF-8',
        );

        $revolutCsv = "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n"
            ."CARD_PAYMENT,Current,2023-03-15 14:30:00,2023-03-16 08:00:00,Test,-10.00,0.00,EUR,COMPLETED,100.00\n";

        $this->assertInstanceOf(BanquePopulaireParser::class, $factory->detect($bpCsv, 'bp.csv'));
        $this->assertInstanceOf(RevolutParser::class, $factory->detect($revolutCsv, 'revolut.csv'));
    }

    public function test_throws_exception_for_unknown_format(): void
    {
        $factory = $this->factoryWithBothParsers();

        $this->expectException(UnrecognizedCsvFormatException::class);

        $factory->detect('Unknown,Format,Here', 'unknown.csv');
    }

    public function test_exception_message_lists_both_expected_formats(): void
    {
        $factory = $this->factoryWithBothParsers();

        try {
            $factory->detect('random content', 'file.csv');
            $this->fail('Expected UnrecognizedCsvFormatException was not thrown.');
        } catch (UnrecognizedCsvFormatException $e) {
            $this->assertStringContainsString('Banque Populaire', $e->getMessage());
            $this->assertStringContainsString('Revolut', $e->getMessage());
        }
    }
}
