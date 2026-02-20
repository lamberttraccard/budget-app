<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CategoryCorrected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public int $oldCategoryId,
        public int $newCategoryId,
    ) {}
}
