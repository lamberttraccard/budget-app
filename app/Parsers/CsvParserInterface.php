<?php

namespace App\Parsers;

use Illuminate\Support\Collection;

interface CsvParserInterface
{
    public function canParse(string $content, string $filename): bool;

    /** @return Collection<int, TransactionData> */
    public function parse(string $content): Collection;
}
