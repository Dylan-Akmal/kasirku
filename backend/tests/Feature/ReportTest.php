<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use CreatesPosData, RefreshDatabase;

    /**
     * Harga beli 2.000 dan harga jual 3.000, sehingga laba per pcs 1.000.
     */
    private function seedSales(): void
    {
        $this->actingAsCashier();
        $product = $this->product(['price_buy' => 2000, 'price_sell' => 3000, 'stock' => 100]);

        $this->checkout(
            [['product_id' => $product->id, 'quantity' => 3]],
            ['discount_type' => 'nominal', 'discount_value' => 500],
        )->assertCreated();

        $this->checkout(
            [['product_id' => $product->id, 'quantity' => 2]],
            ['payment_method' => 'qris'],
        )->assertCreated();

        $voidedId = $this->checkout(
            [['product_id' => $product->id, 'quantity' => 1]],
        )->json('data.id');

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/transactions/{$voidedId}/void", ['reason' => 'Uji'])->assertOk();
    }

    public function test_sales_summary_is_computed_correctly_and_excludes_voided(): void
    {
        $this->seedSales();

        $today = now()->toDateString();

        $this->getJson("/api/reports/sales?from={$today}&to={$today}")
            ->assertOk()
            ->assertJsonPath('data.summary.transaction_count', 2)
            ->assertJsonPath('data.summary.revenue', 14500)
            ->assertJsonPath('data.summary.discount', 500)
            ->assertJsonPath('data.summary.gross_profit', 4500)
            ->assertJsonPath('data.summary.average_transaction', 7250)
            ->assertJsonPath('data.summary.voided_count', 1)
            ->assertJsonCount(1, 'data.by_day')
            ->assertJsonCount(2, 'data.by_payment_method');
    }

    public function test_top_products_aggregates_sold_quantity(): void
    {
        $this->seedSales();

        $today = now()->toDateString();

        $this->getJson("/api/reports/top-products?from={$today}&to={$today}")
            ->assertOk()
            ->assertJsonPath('data.products.0.quantity_sold', 5)
            ->assertJsonPath('data.products.0.revenue', 15000);
    }

    public function test_csv_export_lists_completed_transactions_only(): void
    {
        $this->seedSales();

        $today = now()->toDateString();
        $prefix = 'INV-'.now()->format('Ymd').'-';

        $response = $this->get("/api/reports/export?from={$today}&to={$today}")->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString($prefix.'0001', $content);
        $this->assertStringContainsString($prefix.'0002', $content);
        $this->assertStringNotContainsString($prefix.'0003', $content);
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/reports/sales?from=2026-10-10&to=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }
}