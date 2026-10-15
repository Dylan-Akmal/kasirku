<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use CreatesPosData, RefreshDatabase;

    public function test_admin_can_create_update_and_delete_a_category(): void
    {
        $this->actingAsAdmin();

        $id = $this->postJson('/api/categories', ['name' => 'Minuman'])
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/api/categories/{$id}", ['name' => 'Minuman Dingin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Minuman Dingin');

        $this->deleteJson("/api/categories/{$id}")->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $id]);
    }

    public function test_category_name_must_be_unique(): void
    {
        $this->actingAsAdmin();
        Category::create(['name' => 'Snack']);

        $this->postJson('/api/categories', ['name' => 'Snack'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_deleting_a_category_keeps_its_products(): void
    {
        $this->actingAsAdmin();
        $category = Category::create(['name' => 'Snack']);
        $product = $this->product(['category_id' => $category->id]);

        $this->deleteJson("/api/categories/{$category->id}")->assertOk();

        $this->assertNull($product->fresh()->category_id);
    }

    public function test_cashier_can_list_categories_with_product_counts(): void
    {
        $category = Category::create(['name' => 'Snack']);
        $this->product(['category_id' => $category->id]);
        $this->product(['category_id' => $category->id]);

        $this->actingAsCashier();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.products_count', 2);
    }
}