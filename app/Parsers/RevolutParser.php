<?php

namespace App\Parsers;

use App\Exceptions\CsvParseException;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RevolutParser implements CsvParserInterface
{
    private const PERSONAL_HEADERS = [
        'Type',
        'Product',
        'Started Date',
        'Completed Date',
        'Description',
        'Amount',
        'Fee',
        'Currency',
        'State',
        'Balance',
    ];

    private const BUSINESS_REQUIRED_HEADERS = [
        'Date started (UTC)',
        'Date completed (UTC)',
        'Description',
        'Amount',
    ];

    public function canParse(string $content, string $filename): bool
    {
        $firstLine = explode("\n", str_replace("\r\n", "\n", $content), 2)[0];

        if (trim($firstLine) === '') {
            return false;
        }

        $headers = str_getcsv(trim($firstLine), ',', '"', '');

        if ($headers === self::PERSONAL_HEADERS) {
            return true;
        }

        // Check for Business format by verifying required columns are present
        foreach (self::BUSINESS_REQUIRED_HEADERS as $required) {
            if (! in_array($required, $headers, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return Collection<int, TransactionData> */
    public function parse(string $content): Collection
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $content));

        $headerLine = array_shift($lines);

        if ($headerLine === null || trim($headerLine) === '') {
            throw new CsvParseException('CSV content is empty.');
        }

        $headers = str_getcsv(trim($headerLine), ',', '"', '');
        $isPersonal = in_array('State', $headers, true);
        $headerMap = array_flip($headers);

        $dateCol = $isPersonal ? 'Completed Date' : 'Date completed (UTC)';
        $descCol = 'Description';
        $amountCol = 'Amount';
        $stateCol = $isPersonal ? 'State' : null;

        if (! isset($headerMap[$dateCol], $headerMap[$descCol], $headerMap[$amountCol])) {
            throw new CsvParseException('Invalid Revolut CSV headers.');
        }

        $transactions = new Collection;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            $fields = str_getcsv($trimmed, ',', '"', '');

            if (count($fields) < count($headers)) {
                continue;
            }

            // Filter by State for Personal format
            if ($stateCol !== null) {
                $state = trim($fields[$headerMap[$stateCol]]);

                if ($state !== 'COMPLETED') {
                    continue;
                }
            }

            $dateValue = trim($fields[$headerMap[$dateCol]]);

            // Skip rows with empty completion date (pending transactions)
            if ($dateValue === '') {
                continue;
            }

            try {
                $transactions->push(new TransactionData(
                    date: $this->parseDate($dateValue),
                    description: trim($fields[$headerMap[$descCol]]),
                    amountCents: $this->parseAmount($fields[$headerMap[$amountCol]]),
                    rawLine: $trimmed,
                ));
            } catch (CsvParseException) {
                continue;
            }
        }

        return $transactions;
    }

    private function parseDate(string $date): string
    {
        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', trim($date))->format('Y-m-d');
        } catch (\Exception) {
            throw new CsvParseException("Invalid date format: '{$date}'. Expected YYYY-MM-DD HH:MM:SS.");
        }
    }

    private function parseAmount(string $amount): int
    {
        $trimmed = trim($amount);

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            throw new CsvParseException("Invalid amount: '{$trimmed}'.");
        }

        return (int) round(floatval($trimmed) * 100);
    }
}
