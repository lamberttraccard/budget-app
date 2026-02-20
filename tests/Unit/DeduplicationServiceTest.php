<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Transaction;
use App\Parsers\TransactionData;
use App\Services\DeduplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DeduplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeduplicationService;
    }

    public function test_generate_hash_produces_64_character_hex_sha256(): void
    {
        $hash = $this->service->generateHash('2026-01-15', -1500, 'Coffee Shop', 1);

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function test_generate_hash_is_deterministic(): void
    {
        $hash1 = $this->service->generateHash('2026-01-15', -1500, 'Coffee Shop', 1);
        $hash2 = $this->service->generateHash('2026-01-15', -1500, 'Coffee Shop', 1);

        $this->assertSame($hash1, $hash2);
    }

    public function test_generate_hash_differs_for_different_date(): void
    {
        $hash1 = $this->service->generateHash('2026-01-15', -1500, 'Coffee Shop', 1);
        $hash2 = $this->service->generateHash('2026-01-16', -1500, 'Coffee Shop', 1);

        $this->assertNotSame($hash1, $hash2);
    }

    public function test_generate_hash_differs_for_different_amount(): void
    {
        $hash1 = $this->service->generateHash('2026-01-15', -1500, 'Coffee Shop', 1);
        $hash2 = $this->service->generateHash('2026-01-15', -2000, 'Coffee Shop', 1);

        $this->assertNotSame($hash1, $hash2);
    }

    public function test_generate_hash_differs_for_different_description(): void
    {
        $hash1 = $this->service->generateHash('2026-01-15', -1500, 'Coffee Shop', 1);
        $hash2 = $this->service->generateHash('2026-01-15', -1500, 'Restaurant', 1);

        $this->assertNotSame($hash1, $hash2);
    }

    public function test_generate_hash_differs_for_different_account(): void
    {
        $hash1 = $this->service->generateHash('2026-01-15', -1500, 'Coffee Shop', 1);
        $hash2 = $this->service->generateHash('2026-01-15', -1500, 'Coffee Shop', 2);

        $this->assertNotSame($hash1, $hash2);
    }

    public function test_filter_duplicates_returns_all_when_no_duplicates_exist(): void
    {
        $account = Account::factory()->personal()->create();

        $transactions = new Collection([
            new TransactionData('2026-01-15', 'Coffee Shop', -450, 'raw1'),
            new TransactionData('2026-01-16', 'Grocery Store', -2350, 'raw2'),
        ]);

        $result = $this->service->filterDuplicates($transactions, $account->id);

        $this->assertCount(2, $result);
        $this->assertSame('Coffee Shop', $result[0]['transaction']->description);
        $this->assertSame('Grocery Store', $result[1]['transaction']->description);
    }

    public function test_filter_duplicates_skips_existing_hashes(): void
    {
        $account = Account::factory()->personal()->create();

        $existingHash = $this->service->generateHash('2026-01-15', -450, 'Coffee Shop', $account->id);
        Transaction::factory()->create([
            'account_id' => $account->id,
            'dedup_hash' => $existingHash,
        ]);

        $transactions = new Collection([
            new TransactionData('2026-01-15', 'Coffee Shop', -450, 'raw1'),
            new TransactionData('2026-01-16', 'Grocery Store', -2350, 'raw2'),
        ]);

        $result = $this->service->filterDuplicates($transactions, $account->id);

        $this->assertCount(1, $result);
        $this->assertSame('Grocery Store', $result[0]['transaction']->description);
    }

    public function test_filter_duplicates_handles_mixed_batch(): void
    {
        $account = Account::factory()->personal()->create();

        $hash1 = $this->service->generateHash('2026-01-15', -450, 'Coffee Shop', $account->id);
        $hash3 = $this->service->generateHash('2026-01-17', -1200, 'Restaurant', $account->id);
        Transaction::factory()->create(['account_id' => $account->id, 'dedup_hash' => $hash1]);
        Transaction::factory()->create(['account_id' => $account->id, 'dedup_hash' => $hash3]);

        $transactions = new Collection([
            new TransactionData('2026-01-15', 'Coffee Shop', -450, 'raw1'),
            new TransactionData('2026-01-16', 'Grocery Store', -2350, 'raw2'),
            new TransactionData('2026-01-17', 'Restaurant', -1200, 'raw3'),
        ]);

        $result = $this->service->filterDuplicates($transactions, $account->id);

        $this->assertCount(1, $result);
        $this->assertSame('Grocery Store', $result[0]['transaction']->description);
    }

    public function test_filter_duplicates_returns_empty_when_all_are_duplicates(): void
    {
        $account = Account::factory()->personal()->create();

        $hash1 = $this->service->generateHash('2026-01-15', -450, 'Coffee Shop', $account->id);
        $hash2 = $this->service->generateHash('2026-01-16', -2350, 'Grocery Store', $account->id);
        Transaction::factory()->create(['account_id' => $account->id, 'dedup_hash' => $hash1]);
        Transaction::factory()->create(['account_id' => $account->id, 'dedup_hash' => $hash2]);

        $transactions = new Collection([
            new TransactionData('2026-01-15', 'Coffee Shop', -450, 'raw1'),
            new TransactionData('2026-01-16', 'Grocery Store', -2350, 'raw2'),
        ]);

        $result = $this->service->filterDuplicates($transactions, $account->id);

        $this->assertCount(0, $result);
    }

    public function test_filter_duplicates_scopes_by_account_id(): void
    {
        $account1 = Account::factory()->personal()->create();
        $account2 = Account::factory()->professional()->create();

        $hash = $this->service->generateHash('2026-01-15', -450, 'Coffee Shop', $account1->id);
        Transaction::factory()->create(['account_id' => $account1->id, 'dedup_hash' => $hash]);

        $transactions = new Collection([
            new TransactionData('2026-01-15', 'Coffee Shop', -450, 'raw1'),
        ]);

        // Same data for account2 should NOT be considered a duplicate
        $result = $this->service->filterDuplicates($transactions, $account2->id);

        $this->assertCount(1, $result);
    }

    public function test_filter_duplicates_deduplicates_within_batch(): void
    {
        $account = Account::factory()->personal()->create();

        // Two identical transactions in the same batch (e.g., two coffees same day, same amount)
        $transactions = new Collection([
            new TransactionData('2026-01-15', 'Coffee Shop', -450, 'raw1'),
            new TransactionData('2026-01-15', 'Coffee Shop', -450, 'raw2'),
            new TransactionData('2026-01-16', 'Grocery Store', -2350, 'raw3'),
        ]);

        $result = $this->service->filterDuplicates($transactions, $account->id);

        // Should keep only unique hashes — first Coffee Shop + Grocery Store
        $this->assertCount(2, $result);
        $this->assertSame('Coffee Shop', $result[0]['transaction']->description);
        $this->assertSame('Grocery Store', $result[1]['transaction']->description);
    }
}
