<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use CreatesPosData, RefreshDatabase;

    public function test_guest_cannot_access_protected_endpoints(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
        $this->getJson('/api/transactions')->assertUnauthorized();
    }

    public function test_cashier_is_forbidden_from_admin_endpoints(): void
    {
        $this->actingAsCashier();
        $product = $this->product();

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/categories', ['name' => 'Baru'])->assertForbidden();
        $this->postJson('/api/products', [
            'name' => 'Produk',
            'price_buy' => 1000,
            'price_sell' => 2000,
        ])->assertForbidden();
        $this->postJson("/api/products/{$product->id}/stock-in", ['quantity' => 1])->assertForbidden();
        $this->getJson('/api/stock-movements')->assertForbidden();
        $this->getJson('/api/reports/sales')->assertForbidden();
        $this->putJson('/api/store-settings', ['store_name' => 'Toko'])->assertForbidden();
    }

    public function test_cost_price_is_hidden_from_cashier_but_visible_to_admin(): void
    {
        $this->product(['price_buy' => 2000]);

        $this->actingAsCashier();
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonMissingPath('data.0.price_buy');

        $this->actingAsAdmin();
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.price_buy', 2000);
    }
}