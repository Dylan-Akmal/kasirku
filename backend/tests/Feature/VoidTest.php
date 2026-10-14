<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class VoidTest extends TestCase
{
    use CreatesPosData, RefreshDatabase;

    /**
     * Membuat satu penjualan 3 pcs oleh seorang kasir (stok awal 10, sisa 7).
     */
    private function makeSale(): array
    {
        $cashier = $this->cashier();
        $product = $this->product(['stock' => 10]);

        Sanctum::actingAs($cashier);
        $id = $this->checkout([['product_id' => $product->id, 'quantity' => 3]])->json('data.id');

        return [$id, $product, $cashier];
    }

    public function test_admin_can_void_and_stock_is_restored(): void
    {
        [$id, $product] = $this->makeSale();
        $this->assertSame(7, $product->fresh()->stock);

        $admin = $this->actingAsAdmin();

        $this->postJson("/api/transactions/{$id}/void", ['reason' => 'Salah input'])
            ->assertOk()
            ->assertJsonPath('data.status', 'voided')
            ->assertJsonPath('data.voided_by.name', $admin->name);

        $this->assertSame(10, $product->fresh()->stock);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'transaction_id' => $id,
            'type' => 'void_return',
            'quantity' => 3,
            'stock_after' => 10,
        ]);
    }

    public function test_a_transaction_cannot_be_voided_twice(): void
    {
        [$id, $product] = $this->makeSale();

        $this->actingAsAdmin();

        $this->postJson("/api/transactions/{$id}/void", ['reason' => 'Pertama'])->assertOk();
        $this->postJson("/api/transactions/{$id}/void", ['reason' => 'Kedua'])->assertUnprocessable();

        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_cashier_cannot_void(): void
    {
        [$id, $product, $cashier] = $this->makeSale();

        Sanctum::actingAs($cashier);

        $this->postJson("/api/transactions/{$id}/void", ['reason' => 'Coba'])->assertForbidden();

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_void_requires_a_reason(): void
    {
        [$id] = $this->makeSale();

        $this->actingAsAdmin();

        $this->postJson("/api/transactions/{$id}/void", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }
}