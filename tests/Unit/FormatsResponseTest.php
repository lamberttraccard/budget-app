<?php

namespace Tests\Unit;

use App\Mcp\Concerns\FormatsResponse;
use PHPUnit\Framework\TestCase;

class FormatsResponseTest extends TestCase
{
    use FormatsResponse;

    public function test_format_response_wraps_data_with_meta(): void
    {
        $result = $this->formatResponse(
            [['id' => 1], ['id' => 2]],
            ['total_count' => 2, 'period' => '2026-02'],
        );

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(2, $result['meta']['total_count']);
        $this->assertEquals('2026-02', $result['meta']['period']);
    }

    public function test_format_response_defaults_to_empty_meta(): void
    {
        $result = $this->formatResponse([['id' => 1]]);

        $this->assertSame([], $result['meta']);
    }

    public function test_format_category_item_maps_keys_to_snake_case(): void
    {
        $category = [
            'id' => 5,
            'name' => 'Restaurants',
            'slug' => 'restaurants',
            'color' => '#FBBF24',
            'amountCents' => 52049,
            'transactionCount' => 12,
        ];

        $result = $this->formatCategoryItem($category);

        $this->assertEquals(5, $result['category_id']);
        $this->assertEquals('Restaurants', $result['category_name']);
        $this->assertEquals('restaurants', $result['category_slug']);
        $this->assertEquals('#FBBF24', $result['color']);
        $this->assertEquals(52049, $result['amount_cents']);
        $this->assertEquals(12, $result['transaction_count']);
    }

    public function test_format_category_item_handles_null_color(): void
    {
        $category = [
            'id' => 1,
            'name' => 'Other',
            'slug' => 'other',
            'amountCents' => 0,
            'transactionCount' => 0,
        ];

        $result = $this->formatCategoryItem($category);

        $this->assertNull($result['color']);
    }

    public function test_format_transaction_item_maps_keys_to_snake_case(): void
    {
        $transaction = [
            'id' => 42,
            'date' => '2026-02-19',
            'description' => 'Carrefour Market',
            'amountCents' => -3450,
            'categoryId' => 5,
        ];

        $result = $this->formatTransactionItem($transaction);

        $this->assertEquals(42, $result['transaction_id']);
        $this->assertEquals('2026-02-19', $result['date']);
        $this->assertEquals('Carrefour Market', $result['merchant_name']);
        $this->assertEquals(-3450, $result['amount_cents']);
        $this->assertEquals(5, $result['category_id']);
    }

    public function test_format_transaction_item_handles_null_category(): void
    {
        $transaction = [
            'id' => 99,
            'date' => '2026-01-15',
            'description' => 'Unknown Merchant',
            'amountCents' => -1200,
        ];

        $result = $this->formatTransactionItem($transaction);

        $this->assertNull($result['category_id']);
    }
}
