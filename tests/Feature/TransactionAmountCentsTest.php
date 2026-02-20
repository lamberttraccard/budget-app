<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionAmountCentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_amount_cents_is_cast_to_integer(): void
    {
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'amount_cents' => 52049,
        ]);

        $fetched = Transaction::find($transaction->id);

        $this->assertIsInt($fetched->amount_cents);
        $this->assertSame(52049, $fetched->amount_cents);
    }

    public function test_amount_cents_stores_large_values_correctly(): void
    {
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'amount_cents' => 1234567, // 12345.67 EUR
        ]);

        $this->assertSame(1234567, Transaction::find($transaction->id)->amount_cents);
    }

    public function test_amount_cents_stores_negative_values_for_expenses(): void
    {
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'amount_cents' => -5000, // -50.00 EUR expense
        ]);

        $fetched = Transaction::find($transaction->id);

        $this->assertIsInt($fetched->amount_cents);
        $this->assertSame(-5000, $fetched->amount_cents);
    }

    public function test_amount_cents_stores_zero(): void
    {
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'amount_cents' => 0,
        ]);

        $this->assertSame(0, Transaction::find($transaction->id)->amount_cents);
    }

    public function test_date_is_cast_to_carbon_date(): void
    {
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'date' => '2026-02-19',
        ]);

        $fetched = Transaction::find($transaction->id);

        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $fetched->date);
        $this->assertSame('2026-02-19', $fetched->date->toDateString());
    }

    public function test_dedup_hash_is_unique_constraint(): void
    {
        $account = Account::factory()->create();

        Transaction::factory()->create([
            'account_id' => $account->id,
            'dedup_hash' => 'abc123uniquehash',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'dedup_hash' => 'abc123uniquehash',
        ]);
    }
}
