<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test getting the cart when it is empty.
     */
    public function test_can_get_empty_cart(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cart');

        $response->assertStatus(200)
            ->assertJsonPath('data.items', []);
    }

    /**
     * Test adding items to the cart.
     */
    public function test_can_add_item_to_cart(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create([
            'price' => 1000,
            'stock' => 10,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.total_price', 2000);
    }

    /**
     * Test updating the quantity of a cart item.
     */
    public function test_can_update_cart_item_quantity(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create([
            'price' => 500,
            'stock' => 10,
            'is_active' => true,
        ]);

        // Add item
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->putJson("/api/cart/items/{$product->id}", [
            'quantity' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.quantity', 5)
            ->assertJsonPath('data.total_price', 2500);
    }

    /**
     * Test removing an item from the cart.
     */
    public function test_can_remove_item_from_cart(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create([
            'price' => 500,
            'stock' => 10,
            'is_active' => true,
        ]);

        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->deleteJson("/api/cart/items/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.items', []);
    }

    /**
     * Test clearing the cart.
     */
    public function test_can_clear_cart(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create([
            'price' => 500,
            'stock' => 10,
            'is_active' => true,
        ]);

        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->deleteJson('/api/cart');

        $response->assertStatus(200)
            ->assertJsonPath('data.items', []);
    }

    /**
     * Test guest cart works with a valid UUIDv4 header.
     */
    public function test_guest_cart_works_with_valid_uuidv4_header(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10, 'is_active' => true]);

        $response = $this->withHeader('X-Session-Id', $uuid)
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.product_id', $product->id);

        $this->assertDatabaseHas('carts', ['session_id' => $uuid]);
    }

    /**
     * Test guest cart rejects missing or malformed session ID with HTTP 422 without creating DB rows.
     */
    public function test_guest_cart_rejects_missing_or_malformed_session_id_with_422(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10, 'is_active' => true]);

        // Missing header
        $responseMissing = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $responseMissing->assertStatus(422);

        // Malformed header
        $responseMalformed = $this->withHeader('X-Session-Id', 'not-a-valid-uuid')
            ->getJson('/api/cart');
        $responseMalformed->assertStatus(422);

        $this->assertDatabaseCount('carts', 0);
    }
}
