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
        $this->createTransaction('Netflix', '2026-01-15', -1599);
        $this->createTransaction('Netflix', '2026-02-15', -1599);
        $this->createTransaction('Netflix', '2026-03-15', -1599);

        $result = $this->service->detectRecurring();

        $this->assertCount(1, $result);
        $this->assertEqualsIgnoringCase('netflix', strtolower($result[0]['merchantName']));
        $this->assertEquals(3, $result[0]['monthCount']);
        $this->assertEquals('Monthly', $result[0]['frequency']);
    }

    public function test_excludes_non_consecutive_months(): void
    {
        // Jan, March, May — gaps between each
        $this->createTransaction('Random Store', '2026-01-15', -2000);
        $this->createTransaction('Random Store', '2026-03-15', -2000);
        $this->createTransaction('Random Store', '2026-05-15', -2000);

        $result = $this->service->detectRecurring();

        $this->assertCount(0, $result);
    }

    public function test_handles_multiple_recurring_merchants(): void
    {
        // Netflix: Jan + Feb + Mar
        $this->createTransaction('Netflix', '2026-01-15', -1599);
        $this->createTransaction('Netflix', '2026-02-15', -1599);
        $this->createTransaction('Netflix', '2026-03-15', -1599);
        // Spotify: Jan + Feb + Mar
        $this->createTransaction('Spotify', '2026-01-10', -999);
        $this->createTransaction('Spotify', '2026-02-10', -999);
        $this->createTransaction('Spotify', '2026-03-10', -999);
        // Rent: Jan + Feb + Mar
        $this->createTransaction('Rent Payment', '2026-01-01', -90000);
        $this->createTransaction('Rent Payment', '2026-02-01', -90000);
        $this->createTransaction('Rent Payment', '2026-03-01', -90000);

        $result = $this->service->detectRecurring();

        $this->assertCount(3, $result);

        // Should be sorted by absolute typical amount descending
        $this->assertEqualsIgnoringCase('rent payment', strtolower($result[0]['merchantName']));
    }

    public function test_flags_varying_amounts_within_threshold(): void
    {
        // Electricity with amounts varying noticeably but within 20% of median
        $this->createTransaction('EDF Electricity', '2026-01-15', -4200);
        $this->createTransaction('EDF Electricity', '2026-02-15', -5000);
        $this->createTransaction('EDF Electricity', '2026-03-15', -5800);

        $result = $this->service->detectRecurring();

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['amountVaries']);
    }

    public function test_rejects_wildly_varying_amounts(): void
    {
        // Shopping at same store with very different amounts (like Truffaut)
        $this->createTransaction('TRUFFAUT LIMOG', '2026-01-13', -4090);
        $this->createTransaction('TRUFFAUT LIMOG', '2026-02-24', -2825);
        $this->createTransaction('TRUFFAUT LIMOG', '2026-03-13', -12735);

        $result = $this->service->detectRecurring();

        $this->assertCount(0, $result);
    }

    public function test_detects_subscription_with_one_outlier_month(): void
    {
        // Canal Plus: 11 months at 38.99€, 1 month (Dec) at 28.69€ (proration)
        // The outlier month should be discarded, not kill the whole detection
        $this->createTransaction('CANAL PLUS', '2025-01-06', -3899);
        $this->createTransaction('CANAL PLUS', '2025-02-05', -3899);
        $this->createTransaction('CANAL PLUS', '2025-03-05', -3899);
        $this->createTransaction('CANAL PLUS', '2025-04-05', -3899);
        $this->createTransaction('CANAL PLUS', '2025-05-06', -3899);
        $this->createTransaction('CANAL PLUS', '2025-06-05', -3899);
        $this->createTransaction('CANAL PLUS', '2025-07-05', -3899);
        $this->createTransaction('CANAL PLUS', '2025-08-05', -3899);
        $this->createTransaction('CANAL PLUS', '2025-09-05', -3899);
        $this->createTransaction('CANAL PLUS', '2025-10-06', -3899);
        $this->createTransaction('CANAL PLUS', '2025-11-05', -3899);
        $this->createTransaction('CANAL PLUS', '2025-12-01', -2869); // outlier

        $result = $this->service->detectRecurring();

        $this->assertCount(1, $result);
        $this->assertEqualsIgnoringCase('canal plus', strtolower($result[0]['merchantName']));
    }

    public function test_stable_amounts_not_flagged(): void
    {
        $this->createTransaction('Netflix', '2026-01-15', -1599);
        $this->createTransaction('Netflix', '2026-02-15', -1599);
        $this->createTransaction('Netflix', '2026-03-15', -1599);

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
        $this->createTransaction('Netflix', '2026-03-15', -1599);
        // Professional recurring
        $this->createTransaction('Office 365', '2026-01-15', -2200, null, $proAccount);
        $this->createTransaction('Office 365', '2026-02-15', -2200, null, $proAccount);
        $this->createTransaction('Office 365', '2026-03-15', -2200, null, $proAccount);

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
        $this->createTransaction('Netflix', '2026-03-15', -1599);

        $result = $this->service->detectRecurring();

        $this->assertCount(1, $result);
        $this->assertCount(3, $result[0]['transactions']);

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

    public function test_two_months_not_enough(): void
    {
        // Only 2 consecutive months — below the 3-month minimum
        $this->createTransaction('Netflix', '2026-01-15', -1599);
        $this->createTransaction('Netflix', '2026-02-15', -1599);

        $result = $this->service->detectRecurring();

        $this->assertCount(0, $result);
    }

    public function test_most_recent_amount_as_typical(): void
    {
        $this->createTransaction('EDF', '2026-01-15', -5000);
        $this->createTransaction('EDF', '2026-02-15', -5200);
        $this->createTransaction('EDF', '2026-03-15', -5100);

        $result = $this->service->detectRecurring();

        $this->assertCount(1, $result);
        // Most recent (Mar) should be the typical amount
        $this->assertEquals(-5100, $result[0]['typicalAmountCents']);
    }

    public function test_rejects_multiple_transactions_per_month(): void
    {
        // Same store visited multiple times per month = shopping, not subscription
        $this->createTransaction('Carrefour', '2026-01-05', -3500);
        $this->createTransaction('Carrefour', '2026-01-20', -4200);
        $this->createTransaction('Carrefour', '2026-02-08', -2800);
        $this->createTransaction('Carrefour', '2026-02-22', -5100);
        $this->createTransaction('Carrefour', '2026-03-10', -3900);
        $this->createTransaction('Carrefour', '2026-03-25', -4500);

        $result = $this->service->detectRecurring();

        $this->assertCount(0, $result);
    }

    public function test_mixed_single_and_multi_tx_months(): void
    {
        // Some months have 1 tx, some have 2 — only 2 single-tx months are valid,
        // which is below the 3-month threshold
        $this->createTransaction('Store', '2026-01-15', -2000);
        $this->createTransaction('Store', '2026-02-10', -2000);
        $this->createTransaction('Store', '2026-02-25', -2000); // 2nd tx in Feb
        $this->createTransaction('Store', '2026-03-15', -2000);

        $result = $this->service->detectRecurring();

        // Only Jan and Mar are single-tx months — not consecutive, so rejected
        $this->assertCount(0, $result);
    }

    public function test_enough_single_tx_months_but_longest_run_too_short(): void
    {
        // 4 single-tx months total (passes early count gate)
        // but two separate runs of 2 — longest consecutive run is 2, below minimum 3
        $this->createTransaction('Gym', '2026-01-15', -3000);
        $this->createTransaction('Gym', '2026-02-15', -3000);
        // gap in March
        $this->createTransaction('Gym', '2026-04-15', -3000);
        $this->createTransaction('Gym', '2026-05-15', -3000);

        $result = $this->service->detectRecurring();

        $this->assertCount(0, $result);
    }
}
