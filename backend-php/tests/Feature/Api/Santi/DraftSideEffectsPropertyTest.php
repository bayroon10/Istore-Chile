<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DraftSideEffectsPropertyTest extends TestCase
{
    use RefreshDatabase;
    use WithSantiGenerators;

    private const PROPERTY_SEED = 5_800_2026;

    /**
     * Property 7: Creating a draft does not alter stock, sales count, cart, or fire order-confirmed side effects.
     *
     * **Validates: Requirements 4.11, 4.12, 9.4**
     */
    public function test_creating_a_draft_has_no_side_effects_on_stock_cart_or_events(): void
    {
        $this->runSantiProperty(function (int $iteration, int $seed): void {
            Queue::fake();

            $product = $this->santiCatalog(1, [
                'stock' => 50,
                'sales_count' => 10,
                'is_active' => true,
                'price' => 10000,
            ])->first();

            $user = $this->santiCustomer();
            $cart = Cart::create(['user_id' => $user->id]);
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

            $initialStock = $product->stock;
            $initialSalesCount = $product->sales_count;
            $cartItemsBefore = CartItem::count();

            $executor = app(ToolExecutor::class);
            $context = new ToolContext(
                user: $user,
                correlationId: (string) Str::uuid(),
                draftRequestId: (string) Str::uuid(),
            );

            $response = $executor->execute('create_draft_order', [
                'items' => [['product_identifier' => (string) $product->id, 'quantity' => 5]],
            ], $context)->toFunctionResponse();

            $this->assertTrue(
                $response['ok'],
                'Draft creation failed: ' . json_encode($response),
            );

            // Verify Stock & Sales Count intact
            $product->refresh();
            $this->assertSame($initialStock, $product->stock, 'Draft creation must NOT decrement product stock.');
            $this->assertSame($initialSalesCount, $product->sales_count, 'Draft creation must NOT alter product sales count.');

            // Verify Cart intact
            $this->assertSame($cartItemsBefore, CartItem::count(), 'Draft creation must NOT touch cart items.');
            $this->assertDatabaseHas('cart_items', [
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

            // Verify Event & Queue isolation
            Queue::assertNothingPushed();
        }, seed: self::PROPERTY_SEED, iterations: self::SANTI_PROPERTY_ITERATIONS);
    }
}
