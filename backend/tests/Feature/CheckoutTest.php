<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use CreatesPosData, RefreshDatabase;

    public function test_cash_checkout_deducts_stock_and_records_movements(): void
    {
        $cashier = $this->actingAsCashier();
        $product = $this->product(['stock' => 10, 'price_buy' => 2000, 'price_sell' => 3000]);

        $response = $this->checkout(
            [['product_id' => $product->id, 'quantity' => 3]],
            ['amount_paid' => 10000],
        )
            ->assertCreated()
            ->assertJsonPath('data.subtotal', 9000)
            ->assertJsonPath('data.total', 9000)
            ->assertJsonPath('data.change_amount', 1000);

        $this->assertSame(
            'INV-'.now()->format('Ymd').'-0001',
            $response->json('data.invoice_no'),
        );

        $this->assertSame(7, $product->fresh()->stock);

        $this->assertDatabaseHas('transaction_items', [
            'product_id' => $product->id,
            'quantity' => 3,
            'price_buy' => 2000,
            'price_sell' => 3000,
            'subtotal' => 9000,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $cashier->id,
            'type' => 'out',
            'quantity' => -3,
            'stock_after' => 7,
        ]);
    }

    public function test_percent_discount_with_non_cash_payment_is_exact(): void
    {
        $this->actingAsCashier();
        $product = $this->product(['price_sell' => 11000]);

        $this->checkout(
            [['product_id' => $product->id, 'quantity' => 1]],
            [
                'payment_method' => 'qris',
                'discount_type' => 'percent',
                'discount_value' => 10,
            ],
        )
            ->assertCreated()
            ->assertJsonPath('data.subtotal', 11000)
            ->assertJsonPath('data.discount_amount', 1100)
            ->assertJsonPath('data.total', 9900)
            ->assertJsonPath('data.amount_paid', 9900)
            ->assertJsonPath('data.change_amount', 0);
    }

    public function test_insufficient_stock_is_rejected_without_side_effects(): void
    {
        $this->actingAsCashier();
        $product = $this->product(['stock' => 2]);

        $this->checkout([['product_id' => $product->id, 'quantity' => 5]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_cash_payment_below_total_is_rejected(): void
    {
        $this->actingAsCashier();
        $product = $this->product(['price_sell' => 3000]);

        $this->checkout(
            [['product_id' => $product->id, 'quantity' => 2]],
            ['amount_paid' => 5000],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_paid');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_discount_larger_than_subtotal_is_rejected(): void
    {
        $this->actingAsCashier();
        $product = $this->product(['price_sell' => 3000]);

        $this->checkout(
            [['product_id' => $product->id, 'quantity' => 1]],
            ['discount_type' => 'nominal', 'discount_value' => 5000],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount_value');
    }

    public function test_percent_discount_above_one_hundred_is_rejected(): void
    {
        $this->actingAsCashier();
        $product = $this->product();

        $this->checkout(
            [['product_id' => $product->id, 'quantity' => 1]],
            ['discount_type' => 'percent', 'discount_value' => 150],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount_value');
    }

    public function test_failed_checkout_leaves_every_product_untouched(): void
    {
        $this->actingAsCashier();
        $enough = $this->product(['stock' => 10]);
        $scarce = $this->product(['stock' => 1]);

        $this->checkout([
            ['product_id' => $enough->id, 'quantity' => 2],
            ['product_id' => $scarce->id, 'quantity' => 5],
        ])->assertUnprocessable();

        $this->assertSame(10, $enough->fresh()->stock);
        $this->assertSame(1, $scarce->fresh()->stock);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_duplicate_lines_for_the_same_product_are_merged(): void
    {
        $this->actingAsCashier();
        $product = $this->product(['stock' => 10]);

        $this->checkout([
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => $product->id, 'quantity' => 2],
        ])->assertCreated();

        $this->assertDatabaseCount('transaction_items', 1);
        $this->assertDatabaseHas('transaction_items', ['product_id' => $product->id, 'quantity' => 3]);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_deleted_product_cannot_be_sold(): void
    {
        $this->actingAsCashier();
        $product = $this->product();
        $product->delete();

        $this->checkout([['product_id' => $product->id, 'quantity' => 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_invoice_numbers_increment_within_the_same_day(): void
    {
        $this->actingAsCashier();
        $product = $this->product(['stock' => 10]);

        $first = $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->json('data.invoice_no');
        $second = $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->json('data.invoice_no');

        $prefix = 'INV-'.now()->format('Ymd').'-';

        $this->assertSame($prefix.'0001', $first);
        $this->assertSame($prefix.'0002', $second);
    }

    public function test_cashier_only_sees_own_transactions(): void
    {
        $cashierA = $this->cashier();
        $cashierB = $this->cashier();
        $product = $this->product(['stock' => 10]);

        Sanctum::actingAs($cashierA);
        $id = $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->json('data.id');

        Sanctum::actingAs($cashierB);
        $this->getJson('/api/transactions')->assertOk()->assertJsonPath('total', 0);
        $this->getJson("/api/transactions/{$id}")->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->getJson("/api/transactions/{$id}")->assertOk();
    }
}