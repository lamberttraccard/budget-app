<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\Transaction;
use App\Services\RecurringDetectionService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringDetectionTest extends TestCase
{
    use RefreshDatabase;

    private RecurringDetectionService $service;

    private Account $account;

    private Import $import;

    private Category $food;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->service = app(RecurringDetectionService::class);
        $this->account = Account::factory()->personal()->create();
        $this->import = Import::factory()->completed()->create();
        $this->food = Category::where('slug', 'food')->first();
    }

    private function createTransaction(string $description, string $date, int $amountCents = -5000, ?Category $category = null, ?Account $account = null): Transaction
    {
        return Transaction::factory()->create([
            'description' => $description,
            'date' => $date,
            'amount_cents' => $amountCents,
            'category_id' => ($category ?? $this->food)->id,
            'account_id' => ($account ?? $this->account)->id,
            'import_id' => $this->import->id,
        ]);
    }

    public function test_detects_recurring_with_consecutive_months(): void
    {
        $this->createTransaction('Netflix', '2026-01-15');
        $this->createTransaction('Netflix', '2026-02-15');

        $result = $this->service->detectRecurring();

        $this->assertCount(1, $result);
        $this->assertEqualsIgnoringCase('netflix', strtolower($result[0]['merchantName']));
        $this->assertEquals(2, $result[0]['monthCount']);
        $this->assertEquals('Monthly', $result[0]['frequency']);
    }

    public function test_excludes_non_consecutive_months(): void
    {
        // Jan and March — gap in February
        $this->createTransaction('Random Store', '2026-01-15');
        $this->createTransaction('Random Store', '2026-03-15');

        $result = $this->service->detectRecurring();

        $this->assertCount(0, $result);
    }

    public function test_handles_multiple_recurring_merchants(): void
    {
        // Netflix: Jan + Feb
        $this->createTransaction('Netflix', '2026-01-15');
        $this->createTransaction('Netflix', '2026-02-15');
        // Spotify: Jan + Feb
        $this->createTransaction('Spotify', '2026-01-10');
        $this->createTransaction('Spotify', '2026-02-10');
        // Rent: Jan + Feb
        $this->createTransaction('Rent Payment', '2026-01-01', -90000);
        $this->createTransaction('Rent Payment', '2026-02-01', -90000);

        $result = $this->service->detectRecurring();

        $this->assertCount(3, $result);

        // Should be sorted by absolute typical amount descending
        $this->assertEqualsIgnoringCase('rent payment', strtolower($result[0]['merchantName']));
    }

    public function test_flags_varying_amounts(): void
    {
        // Electricity with amounts varying > 20%
        $this->createTransaction('EDF Electricity', '2026-01-15', -4500);
        $this->createTransaction('EDF Electricity', '2026-02-15', -6200);

        $result = $this->service->detectRecurring();

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['amountVaries']);
        $this->assertEquals(-6200, $result[0]['minAmountCents']);
        $this->assertEquals(-4500, $result[0]['maxAmountCents']);
    }

    public function test_stable_amounts_not_flagged(): void
    {
        $this->createTransaction('Netflix', '2026-01-15', -1599);
        $this->createTransaction('Netflix', '2026-02-15', -1599);

        $result = $this->service->detectRecurring();

        $this->assertCount(1, $result);
        $this->assertFalse($result[0]['amountVaries']);
    }

    public function test_filters_by_account_type(): void
    {
        $proAccount = Account::factory()->professional()->create();

        // Personal recurring
        $this->createTransaction('Netflix', '2026-01-15', -1599);
        $this->createTransaction('Netflix', '2026-02-15', -1599);
        // Professional recurring
        $this->createTransaction('Office 365', '2026-01-15', -2200, null, $proAccount);
        $this->createTransaction('Office 365', '2026-02-15', -2200, null, $proAccount);

        $personalResult = $this->service->detectRecurring('personal');
        $proResult = $this->service->detectRecurring('professional');

        $this->assertCount(1, $personalResult);
        $this->assertEqualsIgnoringCase('netflix', strtolower($personalResult[0]['merchantName']));

        $this->assertCount(1, $proResult);
        $this->assertEqualsIgnoringCase('office 365', strtolower($proResult[0]['merchantName']));
    }

    public function test_returns_transaction_history(): void
    {
        $this->createTransaction('Netflix', '2026-01-15', -1599);
        $this->createTransaction('Netflix', '2026-02-15', -1599);

        $result = $this->service->detectRecurring();

        $this->assertCount(1, $result);
        $this->assertCount(2, $result[0]['transactions']);

        // Verify transaction structure
        $tx = $result[0]['transactions'][0];
        $this->assertArrayHasKey('id', $tx);
        $this->assertArrayHasKey('date', $tx);
        $this->assertArrayHasKey('amountCents', $tx);
        $this->assertArrayHasKey('accountName', $tx);
    }

    public function test_empty_when_no_transactions(): void
    {
        $result = $this->service->detectRecurring();

        $this->assertCount(0, $result);
        $this->assertIsArray($result);
    }

    public function test_single_month_merchant_excluded(): void
    {
        $this->createTransaction('OneTimePurchase', '2026-01-15');

        $result = $this->service->detectRecurring();

        $this->assertCount(0, $result);
    }

    public function test_most_recent_amount_as_typical(): void
    {
        $this->createTransaction('EDF', '2026-01-15', -4500);
        $this->createTransaction('EDF', '2026-02-15', -6200);

        $result = $this->service->detectRecurring();

        $this->assertCount(1, $result);
        // Most recent (Feb) should be the typical amount
        $this->assertEquals(-6200, $result[0]['typicalAmountCents']);
    }
}
