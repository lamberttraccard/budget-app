<?php

namespace App\Parsers;

use App\Exceptions\CsvParseException;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BanquePopulaireParser implements CsvParserInterface
{
    private const EXPECTED_HEADERS = [
        'Compte',
        'Date de comptabilisation',
        'Date opération',
        'Libellé',
        'Référence',
        'Date valeur',
        'Montant',
    ];

    public function canParse(string $content, string $filename): bool
    {
        $utf8 = $this->convertEncoding($content);
        $firstLine = explode("\n", $utf8, 2)[0];

        if (trim($firstLine) === '') {
            return false;
        }

        $headers = str_getcsv(trim($firstLine), ';', '"', '');

        return $headers === self::EXPECTED_HEADERS;
    }

    /** @return Collection<int, TransactionData> */
    public function parse(string $content): Collection
    {
        $utf8 = $this->convertEncoding($content);
        $lines = explode("\n", str_replace("\r\n", "\n", $utf8));

        $headerLine = array_shift($lines);

        if ($headerLine === null || trim($headerLine) === '') {
            throw new CsvParseException('CSV content is empty.');
        }

        $headers = str_getcsv(trim($headerLine), ';', '"', '');

        if ($headers !== self::EXPECTED_HEADERS) {
            throw new CsvParseException('Invalid Banque Populaire CSV headers.');
        }

        $transactions = new Collection;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            $fields = str_getcsv($trimmed, ';', '"', '');

            if (count($fields) < 7) {
                continue;
            }

            try {
                $transactions->push(new TransactionData(
                    date: $this->parseDate($fields[2]),
                    description: trim($fields[3]),
                    amountCents: $this->parseAmount($fields[6]),
                    rawLine: $trimmed,
                ));
            } catch (CsvParseException) {
                continue;
            }
        }

        return $transactions;
    }

    private function convertEncoding(string $content): string
    {
        return mb_convert_encoding($content, 'UTF-8', 'ISO-8859-15');
    }

    private function parseDate(string $date): string
    {
        try {
            return Carbon::createFromFormat('d/m/Y', trim($date))->format('Y-m-d');
        } catch (\Exception) {
            throw new CsvParseException("Invalid date format: '{$date}'. Expected DD/MM/YYYY.");
        }
    }

    private function parseAmount(string $amount): int
    {
        $trimmed = trim($amount);
        $normalized = str_replace(',', '.', $trimmed);

        if ($normalized === '' || !is_numeric($normalized)) {
            throw new CsvParseException("Invalid amount: '{$trimmed}'.");
        }

        return (int) round(floatval($normalized) * 100);
    }
}
