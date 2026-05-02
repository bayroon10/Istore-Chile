<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test paginated listing of products.
     * Rule: Default per_page should be 12.
     */
    public function test_can_list_paginated_products(): void
    {
        // Arrange
        Product::factory()->count(15)->create(['is_active' => true]);

        // Act
        $response = $this->getJson('/api/products');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 15);
    }

    /**
     * Test filtering products by search term.
     */
    public function test_can_filter_products_by_search(): void
    {
        // Arrange
        Product::factory()->create(['name' => 'Microscopio Avanzado', 'is_active' => true]);
        Product::factory()->create(['name' => 'Tubo de Ensayo', 'is_active' => true]);
        Product::factory()->create(['name' => 'Centrífuga Digital', 'is_active' => true]);

        // Act
        $response = $this->getJson('/api/products?search=Microscopio');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Microscopio Avanzado');
    }

    /**
     * Test filtering products by category slug.
     */
    public function test_can_filter_products_by_category_slug(): void
    {
        // Arrange
        $catEquipos = Category::factory()->create(['name' => 'Equipos', 'slug' => 'equipos']);
        $catInsumos = Category::factory()->create(['name' => 'Insumos', 'slug' => 'insumos']);

        Product::factory()->count(3)->create(['category_id' => $catEquipos->id, 'is_active' => true]);
        Product::factory()->count(2)->create(['category_id' => $catInsumos->id, 'is_active' => true]);

        // Act
        $response = $this->getJson('/api/products?category=equipos');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /**
     * Test filtering products by category ID.
     */
    public function test_can_filter_products_by_category_id(): void
    {
        // Arrange
        $category = Category::factory()->create();
        Product::factory()->count(5)->create(['category_id' => $category->id, 'is_active' => true]);
        Product::factory()->count(2)->create(['is_active' => true]); // Other categories

        // Act
        $response = $this->getJson("/api/products?category={$category->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /**
     * Test that inactive products are excluded from the public listing.
     */
    public function test_cannot_see_inactive_products_in_listing(): void
    {
        // Arrange
        Product::factory()->count(5)->create(['is_active' => true]);
        Product::factory()->count(3)->inactive()->create();

        // Act
        $response = $this->getJson('/api/products');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 5);
    }

    /**
     * Test showing a single product by ID.
     */
    public function test_can_show_product_by_id(): void
    {
        // Arrange
        $product = Product::factory()->create(['is_active' => true]);

        // Act
        $response = $this->getJson("/api/products/{$product->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', $product->name);
    }

    /**
     * Test showing a single product by Slug.
     */
    public function test_can_show_product_by_slug(): void
    {
        // Arrange
        $product = Product::factory()->create(['slug' => 'test-product-slug', 'is_active' => true]);

        // Act
        $response = $this->getJson('/api/products/test-product-slug');

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'test-product-slug');
    }
}
