<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use CreatesPosData, RefreshDatabase;

    public function test_admin_creates_a_product_with_an_initial_stock_movement(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->postJson('/api/products', [
            'name' => 'Teh Kotak',
            'sku' => '8990001',
            'price_buy' => 3000,
            'price_sell' => 4500,
            'stock' => 24,
            'min_stock' => 6,
        ])
            ->assertCreated()
            ->assertJsonPath('data.stock', 24);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $response->json('data.id'),
            'user_id' => $admin->id,
            'type' => 'in',
            'quantity' => 24,
            'stock_after' => 24,
            'note' => 'Stok awal',
        ]);
    }

    public function test_sku_must_be_unique(): void
    {
        $this->actingAsAdmin();
        $this->product(['sku' => '8990001']);

        $this->postJson('/api/products', [
            'name' => 'Produk Lain',
            'sku' => '8990001',
            'price_buy' => 1000,
            'price_sell' => 2000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
    }

    public function test_update_cannot_change_stock_directly(): void
    {
        $this->actingAsAdmin();
        $product = $this->product(['stock' => 10]);

        $this->putJson("/api/products/{$product->id}", [
            'price_sell' => 3500,
            'stock' => 999,
        ])->assertOk();

        $fresh = $product->fresh();

        $this->assertSame(3500, $fresh->price_sell);
        $this->assertSame(10, $fresh->stock);
    }

    public function test_search_sku_lookup_and_low_stock_filter(): void
    {
        $this->actingAsCashier();
        $this->product(['name' => 'Indomie Goreng', 'sku' => '111', 'stock' => 50]);
        $this->product(['name' => 'Aqua', 'sku' => '222', 'stock' => 2, 'min_stock' => 5]);

        $this->getJson('/api/products?q=indomie')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/products?sku=222')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Aqua');

        $this->getJson('/api/products?low_stock=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Aqua');
    }

    public function test_deleted_product_is_hidden_but_transaction_history_is_kept(): void
    {
        $this->actingAsCashier();
        $product = $this->product(['stock' => 10]);

        $transactionId = $this->checkout([['product_id' => $product->id, 'quantity' => 1]])
            ->json('data.id');

        $this->actingAsAdmin();

        $this->deleteJson("/api/products/{$product->id}")->assertOk();

        $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $this->getJson("/api/transactions/{$transactionId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name', $product->name);
    }

    public function test_admin_can_upload_and_remove_a_product_image(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Ekstensi GD tidak tersedia.');
        }

        Storage::fake('public');
        $this->actingAsAdmin();
        $product = $this->product();

        $response = $this->post(
            "/api/products/{$product->id}/image",
            ['image' => UploadedFile::fake()->image('foto.jpg', 200, 200)],
            ['Accept' => 'application/json'],
        )->assertOk();

        $path = $product->fresh()->image_path;

        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($response->json('data.image_url'));

        $this->deleteJson("/api/products/{$product->id}/image")->assertOk();

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($product->fresh()->image_path);
    }

    public function test_image_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $product = $this->product();

        $this->post(
            "/api/products/{$product->id}/image",
            ['image' => UploadedFile::fake()->create('dokumen.pdf', 50, 'application/pdf')],
            ['Accept' => 'application/json'],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }
}