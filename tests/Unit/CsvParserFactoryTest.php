<?php

namespace Tests\Unit;

use App\Exceptions\UnrecognizedCsvFormatException;
use App\Parsers\BanquePopulaireParser;
use App\Parsers\CsvParserFactory;
use Tests\TestCase;

class CsvParserFactoryTest extends TestCase
{
    public function test_returns_banque_populaire_parser_for_bp_content(): void
    {
        $factory = new CsvParserFactory([BanquePopulaireParser::class]);

        $csv = mb_convert_encoding(
            "Compte;Date de comptabilisation;Date opération;Libellé;Référence;Date valeur;Montant\r\n"
            ."34405000000;30/07/2020;30/09/2020;TEST;REF;30/09/2020;-6,00\r\n",
            'ISO-8859-15',
            'UTF-8',
        );

        $parser = $factory->detect($csv, 'export.csv');

        $this->assertInstanceOf(BanquePopulaireParser::class, $parser);
    }

    public function test_throws_exception_for_unknown_format(): void
    {
        $factory = new CsvParserFactory([BanquePopulaireParser::class]);

        $this->expectException(UnrecognizedCsvFormatException::class);

        $factory->detect('Date,Description,Amount', 'unknown.csv');
    }

    public function test_exception_message_lists_expected_formats(): void
    {
        $factory = new CsvParserFactory([BanquePopulaireParser::class]);

        $this->expectException(UnrecognizedCsvFormatException::class);
        $this->expectExceptionMessage('Banque Populaire');

        $factory->detect('random content', 'file.csv');
    }
}
