<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class DraftCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    /** Validates: Requirements 10.1, 10.4, 10.7 */
    public function test_pre_existing_order_states_are_preserved(): void
    {
        $user = User::factory()->create();
        $existingStatuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'];

        foreach ($existingStatuses as $status) {
            $this->createOrder($user, $status);
        }

        $persistedStatuses = Order::query()
            ->orderBy('id')
            ->pluck('status')
            ->all();

        $this->assertSame($existingStatuses, $persistedStatuses);
    }

    /** Validates: Requirements 10.5, 10.6 */
    public function test_drafts_are_excluded_from_customer_and_admin_order_listings(): void
    {
        $customer = User::factory()->create();
        $existingOrder = $this->createOrder($customer, 'pending');
        $draft = $this->createOrder($customer, 'draft');

        Sanctum::actingAs($customer);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $existingOrder->id)
            ->assertJsonMissing(['id' => $draft->id]);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $existingOrder->id)
            ->assertJsonMissing(['id' => $draft->id]);
    }

    /** Validates: Requirements 10.5, 10.6 */
    public function test_checkout_creates_an_order_with_the_existing_non_draft_initial_status(): void
    {
        $this->mock(StripeService::class, function ($mock): void {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturn('pi_draft_compatibility_test');
        });

        $user = User::factory()->create();
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

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders/checkout', [
            'shipping_name' => 'Cliente de Prueba',
            'shipping_phone' => '+56912345678',
            'shipping_street' => 'Avenida de Prueba 123',
            'shipping_city' => 'Santiago',
            'shipping_region' => 'Metropolitana',
            'shipping_method' => 'Retiro',
        ], [
            'Idempotency-Key' => 'f1c0a8c4-d5bd-4a41-a6c0-0fa4b7e9f2dc',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    private function createOrder(User $user, string $status): Order
    {
        $isDraft = $status === 'draft';

        return Order::create([
            'user_id' => $user->id,
            'shipping_name' => $isDraft ? null : 'Cliente Existente',
            'shipping_phone' => $isDraft ? null : '+56987654321',
            'shipping_street' => $isDraft ? null : 'Calle Existente 456',
            'shipping_city' => $isDraft ? null : 'Santiago',
            'shipping_region' => $isDraft ? null : 'Metropolitana',
            'shipping_method' => $isDraft ? null : 'Retiro',
            'status' => $status,
            'subtotal' => 10000,
            'shipping_cost' => 0,
            'discount' => 0,
            'total' => 10000,
            'payment_method' => $isDraft ? null : 'stripe',
        ]);
    }
}
