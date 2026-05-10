<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test checkout of cart into an order with mock StripeService.
     */
    public function test_can_checkout_cart_to_order(): void
    {
        // 1. Arrange: Mock StripeService
        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn('pi_test_secret_123456');
        });

        // 2. Arrange: Create user, product, cart and cart item
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create([
            'price' => 15000,
            'stock' => 5,
            'is_active' => true,
        ]);

        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        // 3. Act: Send checkout request
        $response = $this->postJson('/api/orders/checkout', [
            'shipping_name' => 'Bairon Castro',
            'shipping_phone' => '+56998765432',
            'shipping_street' => 'Avenida Apoquindo 3000',
            'shipping_city' => 'Las Condes',
            'shipping_region' => 'Metropolitana',
            'shipping_method' => 'Chilexpress',
        ]);

        // 4. Assert: Correct HTTP status, client secret and order numbers
        $response->assertStatus(201)
            ->assertJsonPath('client_secret', 'pi_test_secret_123456')
            ->assertJsonPath('data.shipping.name', 'Bairon Castro')
            ->assertJsonPath('data.subtotal', 15000)
            ->assertJsonPath('data.shipping_cost', 4500)
            ->assertJsonPath('data.total', 19500);

        // Verify cart is empty
        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cart->id,
        ]);

        // Verify product stock is decremented
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 4,
        ]);
    }

    /**
     * Test list user orders.
     */
    public function test_can_list_user_orders(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Order::create([
            'user_id' => $user->id,
            'shipping_name' => 'Bairon Castro',
            'shipping_phone' => '+56998765432',
            'shipping_street' => 'Avenida Apoquindo 3000',
            'shipping_city' => 'Las Condes',
            'shipping_region' => 'Metropolitana',
            'shipping_method' => 'Retiro',
            'status' => 'pending',
            'subtotal' => 10000,
            'shipping_cost' => 0,
            'discount' => 0,
            'total' => 10000,
            'payment_method' => 'stripe',
        ]);

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.shipping.name', 'Bairon Castro');
    }
}
