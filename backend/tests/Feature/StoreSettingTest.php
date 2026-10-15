<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class StoreSettingTest extends TestCase
{
    use CreatesPosData, RefreshDatabase;

    public function test_cashier_can_read_but_not_update_store_settings(): void
    {
        $this->actingAsCashier();

        $this->getJson('/api/store-settings')->assertOk();

        $this->putJson('/api/store-settings', ['store_name' => 'Toko Baru'])->assertForbidden();
    }

    public function test_store_name_is_required(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/store-settings', ['store_name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('store_name');
    }

    public function test_receipt_uses_the_updated_store_settings(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/store-settings', [
            'store_name' => 'Warung Maju',
            'address' => 'Jl. Contoh No. 1',
            'receipt_footer' => 'Terima kasih',
        ])->assertOk();

        $this->actingAsCashier();
        $product = $this->product(['stock' => 10]);

        $transactionId = $this->checkout([['product_id' => $product->id, 'quantity' => 1]])
            ->json('data.id');

        $this->getJson("/api/transactions/{$transactionId}/receipt")
            ->assertOk()
            ->assertJsonPath('data.store.name', 'Warung Maju')
            ->assertJsonPath('data.footer', 'Terima kasih');
    }
}