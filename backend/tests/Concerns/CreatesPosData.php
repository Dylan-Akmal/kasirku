<?php

namespace Tests\Concerns;

use App\Models\Product;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

trait CreatesPosData
{
    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function cashier(): User
    {
        return User::factory()->create();
    }

    protected function actingAsAdmin(): User
    {
        $user = $this->admin();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingAsCashier(): User
    {
        $user = $this->cashier();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Produk Uji '.fake()->unique()->numerify('####'),
            'sku' => fake()->unique()->numerify('899#########'),
            'price_buy' => 2000,
            'price_sell' => 3000,
            'stock' => 10,
            'min_stock' => 5,
        ], $attributes));
    }

    /**
     * Mengirim checkout. Secara bawaan tunai dengan uang yang sangat cukup.
     */
    protected function checkout(array $items, array $payload = []): TestResponse
    {
        return $this->postJson('/api/transactions', array_merge([
            'items' => $items,
            'payment_method' => 'cash',
            'amount_paid' => 1000000,
        ], $payload));
    }
}