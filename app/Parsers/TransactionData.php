<?php

namespace App\Parsers;

readonly class TransactionData
{
    public function __construct(
        public string $date,
        public string $description,
        public int $amountCents,
        public string $rawLine,
    ) {}
}
