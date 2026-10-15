<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class StockTest extends TestCase
{
    use CreatesPosData, RefreshDatabase;

    public function test_stock_in_increases_stock_and_logs_the_movement(): void
    {
        $admin = $this->actingAsAdmin();
        $product = $this->product(['stock' => 4]);

        $this->postJson("/api/products/{$product->id}/stock-in", [
            'quantity' => 20,
            'note' => 'Restock',
        ])
            ->assertCreated()
            ->assertJsonPath('data.product.stock', 24);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'type' => 'in',
            'quantity' => 20,
            'stock_after' => 24,
            'note' => 'Restock',
        ]);
    }

    public function test_stock_in_rejects_a_non_positive_quantity(): void
    {
        $this->actingAsAdmin();
        $product = $this->product();

        $this->postJson("/api/products/{$product->id}/stock-in", ['quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_adjustment_records_the_signed_difference(): void
    {
        $this->actingAsAdmin();
        $product = $this->product(['stock' => 24]);

        $this->postJson("/api/products/{$product->id}/stock-adjust", [
            'new_stock' => 22,
            'note' => 'Stock opname, 2 pcs rusak',
        ])
            ->assertOk()
            ->assertJsonPath('data.product.stock', 22);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => -2,
            'stock_after' => 22,
        ]);
    }

    public function test_adjustment_to_the_same_stock_is_rejected(): void
    {
        $this->actingAsAdmin();
        $product = $this->product(['stock' => 10]);

        $this->postJson("/api/products/{$product->id}/stock-adjust", [
            'new_stock' => 10,
            'note' => 'Tidak berubah',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_adjustment_requires_a_reason(): void
    {
        $this->actingAsAdmin();
        $product = $this->product();

        $this->postJson("/api/products/{$product->id}/stock-adjust", ['new_stock' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');
    }

    public function test_movement_history_can_be_filtered(): void
    {
        $this->actingAsAdmin();
        $product = $this->product(['stock' => 10]);

        $this->postJson("/api/products/{$product->id}/stock-in", ['quantity' => 5])->assertCreated();
        $this->postJson("/api/products/{$product->id}/stock-adjust", [
            'new_stock' => 12,
            'note' => 'Hitung ulang',
        ])->assertOk();

        $this->getJson('/api/stock-movements?type=in')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/stock-movements?type=adjustment')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/stock-movements?product_id={$product->id}")->assertOk()->assertJsonCount(2, 'data');
    }
}