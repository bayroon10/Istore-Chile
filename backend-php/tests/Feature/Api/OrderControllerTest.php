<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\StripeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_replaying_the_same_key_returns_one_order_and_recovers_the_client_secret(): void
    {
        $this->mock(StripeService::class, function ($mock): void {
            $mock->shouldReceive('createPaymentIntent')
                ->once()
                ->andReturnUsing(function (Order $order): string {
                    $order->update(['stripe_payment_id' => 'pi_checkout_replay']);

                    return 'cs_created_once';
                });
            $mock->shouldReceive('retrievePaymentIntent')
                ->once()
                ->with('pi_checkout_replay')
                ->andReturn((object) ['client_secret' => 'cs_recovered_on_replay']);
        });

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'price' => 15000,
            'stock' => 5,
            'is_active' => true,
        ]);
        $cart = $this->addCartItem($user, $product, 1);
        $key = '8c9f7758-181e-4e6f-8ea4-92ae310e9c0a';

        $firstResponse = $this->checkoutAs($user, $key);
        $firstResponse->assertCreated()
            ->assertJsonPath('client_secret', 'cs_created_once')
            ->assertJsonPath('data.shipping.name', 'Bairon Castro')
            ->assertJsonPath('data.subtotal', 15000)
            ->assertJsonPath('data.shipping_cost', 4500)
            ->assertJsonPath('data.total', 19500);

        $secondResponse = $this->checkoutAs($user, $key);
        $secondResponse->assertOk()
            ->assertJsonPath('client_secret', 'cs_recovered_on_replay')
            ->assertJsonPath('data.id', $firstResponse->json('data.id'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 4]);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'checkout_idempotency_key' => $key,
            'currency' => 'clp',
            'stripe_payment_id' => 'pi_checkout_replay',
        ]);
    }

    public function test_same_key_for_another_user_creates_only_that_users_order_and_hides_the_first(): void
    {
        $this->mock(StripeService::class, function ($mock): void {
            $mock->shouldReceive('createPaymentIntent')
                ->twice()
                ->andReturnUsing(function (Order $order): string {
                    $paymentIntentId = "pi_user_{$order->user_id}";
                    $order->update(['stripe_payment_id' => $paymentIntentId]);

                    return "cs_{$paymentIntentId}";
                });
        });

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5, 'is_active' => true]);
        $this->addCartItem($firstUser, $product, 1);
        $this->addCartItem($secondUser, $product, 1);
        $key = '4d80ed89-cc7e-4974-bc59-cf03bc0dfe46';

        $firstResponse = $this->checkoutAs($firstUser, $key);
        $firstResponse->assertCreated();
        $firstOrderId = $firstResponse->json('data.id');

        $secondResponse = $this->checkoutAs($secondUser, $key);
        $secondResponse->assertCreated()
            ->assertJsonPath('data.shipping.name', 'Bairon Castro');
        $secondOrderId = $secondResponse->json('data.id');

        $this->assertNotSame($firstOrderId, $secondOrderId);
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseHas('orders', [
            'id' => $firstOrderId,
            'user_id' => $firstUser->id,
            'checkout_idempotency_key' => $key,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $secondOrderId,
            'user_id' => $secondUser->id,
            'checkout_idempotency_key' => $key,
        ]);

        Sanctum::actingAs($secondUser);
        $this->getJson("/api/orders/{$firstOrderId}")
            ->assertNotFound()
            ->assertJson(['error' => 'No se pudo procesar la solicitud']);
    }

    public function test_missing_invalid_or_body_idempotency_key_does_not_mutate_checkout_state(): void
    {
        $this->mock(StripeService::class, function ($mock): void {
            $mock->shouldNotReceive('createPaymentIntent');
            $mock->shouldNotReceive('retrievePaymentIntent');
        });

        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5, 'is_active' => true]);
        $cart = $this->addCartItem($user, $product, 1);
        Sanctum::actingAs($user);

        $this->postJson('/api/orders/checkout', $this->checkoutPayload())
            ->assertUnprocessable();
        $this->postJson('/api/orders/checkout', $this->checkoutPayload(), [
            'Idempotency-Key' => 'not-a-uuid-v4',
        ])->assertUnprocessable();
        $this->postJson('/api/orders/checkout', $this->checkoutPayload([
            'idempotency_key' => '8c9f7758-181e-4e6f-8ea4-92ae310e9c0a',
        ]), [
            'Idempotency-Key' => '8c9f7758-181e-4e6f-8ea4-92ae310e9c0a',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
    }

    /**
     * SQLite in-memory cannot provide a second independent connection for a real
     * checkout race. This deterministically simulates the unique-index collision
     * after a winner exists and verifies its recovery path and rolled-back stock.
     */
    public function test_checkout_unique_key_collision_recovers_the_winner_without_a_second_decrement(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5, 'is_active' => true]);
        $cart = $this->addCartItem($user, $product, 1);
        $key = 'ab8fa8bd-4f80-46ea-bf4e-ea50c683bf0a';
        $winner = $this->createPendingStripeOrder($user, $key, 'pi_collision_winner');

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('retrievePaymentIntent')
            ->once()
            ->with('pi_collision_winner')
            ->andReturn((object) ['client_secret' => 'cs_collision_winner']);

        $service = Mockery::mock(OrderService::class, [$stripe])->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('findCheckoutOrder')
            ->times(3)
            ->andReturn(null, null, $winner);
        $service->shouldReceive('createPendingOrder')
            ->once()
            ->andThrow(new QueryException(
                'sqlite',
                'insert into "orders"',
                [],
                new \RuntimeException('UNIQUE constraint failed: orders.user_id, orders.checkout_idempotency_key'),
            ));
        $this->app->instance(OrderService::class, $service);

        $response = $this->checkoutAs($user, $key);

        $response->assertOk()
            ->assertJsonPath('data.id', $winner->id)
            ->assertJsonPath('client_secret', 'cs_collision_winner');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
    }

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

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.shipping.name', 'Bairon Castro');
    }

    private function checkoutAs(User $user, string $idempotencyKey): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($user);

        return $this->postJson('/api/orders/checkout', $this->checkoutPayload(), [
            'Idempotency-Key' => $idempotencyKey,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function checkoutPayload(array $overrides = []): array
    {
        return array_replace([
            'shipping_name' => 'Bairon Castro',
            'shipping_phone' => '+56998765432',
            'shipping_street' => 'Avenida Apoquindo 3000',
            'shipping_city' => 'Las Condes',
            'shipping_region' => 'Metropolitana',
            'shipping_method' => 'Chilexpress',
        ], $overrides);
    }

    private function addCartItem(User $user, Product $product, int $quantity): Cart
    {
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);

        return $cart;
    }

    private function createPendingStripeOrder(User $user, string $key, string $paymentIntentId): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'shipping_name' => 'Winner',
            'shipping_phone' => '+56900000000',
            'shipping_street' => 'Winner street',
            'shipping_city' => 'Santiago',
            'shipping_region' => 'Metropolitana',
            'shipping_method' => 'Retiro',
            'status' => 'pending',
            'subtotal' => 10000,
            'shipping_cost' => 0,
            'discount' => 0,
            'total' => 10000,
            'payment_method' => 'stripe',
            'stripe_payment_id' => $paymentIntentId,
            'checkout_idempotency_key' => $key,
            'currency' => 'clp',
        ]);
    }
}
