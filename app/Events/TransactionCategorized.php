<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;

class TransactionCategorized
{
    use Dispatchable;

    public function __construct(public Transaction $transaction) {}
}
