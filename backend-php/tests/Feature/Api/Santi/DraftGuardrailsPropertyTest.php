<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DraftGuardrailsPropertyTest extends TestCase
{
    use RefreshDatabase;
    use WithSantiGenerators;

    private const PROPERTY_SEED = 5_100_5026;

    /**
     * Property 9a: Subtotal cap ($5,000,000 CLP) is enforced on draft creation.
     *
     * **Validates: Requirement 4.7**
     */
    public function test_subtotal_cap_is_enforced(): void
    {
        $executor = app(ToolExecutor::class);

        $this->runSantiProperty(function (int $iteration, int $seed) use ($executor): void {
            $user = $this->santiCustomer();
            $product = $this->santiCatalog(1, [
                'price' => 3_000_000,
                'stock' => 10,
                'is_active' => true,
            ])->first();

            $context = new ToolContext(
                user: $user,
                correlationId: (string) Str::uuid(),
                draftRequestId: (string) Str::uuid(),
            );

            // 2 items * 3,000,000 CLP = 6,000,000 CLP > 5,000,000 CLP limit
            $ordersBefore = Order::count();
            $response = $executor->execute('create_draft_order', [
                'items' => [['product_identifier' => (string) $product->id, 'quantity' => 2]],
            ], $context)->toFunctionResponse();

            $this->assertFalse($response['ok'], 'Draft creation exceeding 5M CLP must fail.');
            $this->assertSame(
                'SUBTOTAL_LIMIT_EXCEEDED',
                $response['error_code'],
                'Draft exceeding 5M CLP must return SUBTOTAL_LIMIT_EXCEEDED error code.',
            );
            $this->assertSame($ordersBefore, Order::count(), 'No order rows created when subtotal limit exceeded.');
        }, seed: self::PROPERTY_SEED, iterations: self::SANTI_PROPERTY_ITERATIONS);
    }

    /**
     * Property 9b: Inactive products (is_active = false) are rejected on draft creation.
     *
     * **Validates: Requirement 4.6**
     */
    public function test_inactive_products_are_rejected(): void
    {
        $executor = app(ToolExecutor::class);

        $this->runSantiProperty(function (int $iteration, int $seed) use ($executor): void {
            $user = $this->santiCustomer();
            $inactiveProduct = $this->santiCatalog(1, [
                'price' => 10000,
                'stock' => 10,
                'is_active' => false,
            ])->first();

            $context = new ToolContext(
                user: $user,
                correlationId: (string) Str::uuid(),
                draftRequestId: (string) Str::uuid(),
            );

            $ordersBefore = Order::count();
            $response = $executor->execute('create_draft_order', [
                'items' => [['product_identifier' => (string) $inactiveProduct->id, 'quantity' => 1]],
            ], $context)->toFunctionResponse();

            $this->assertFalse($response['ok'], 'Draft creation with inactive product must fail.');
            $this->assertSame(
                'PRODUCT_UNAVAILABLE',
                $response['error_code'],
                'Draft creation with inactive product must return PRODUCT_UNAVAILABLE error code.',
            );
            $this->assertSame($ordersBefore, Order::count(), 'No order rows created for inactive product.');
        }, seed: self::PROPERTY_SEED, iterations: self::SANTI_PROPERTY_ITERATIONS);
    }

    /**
     * Property 9c: Requesting quantity greater than available stock is rejected.
     *
     * **Validates: Requirement 4.8**
     */
    public function test_insufficient_stock_is_rejected(): void
    {
        $executor = app(ToolExecutor::class);

        $this->runSantiProperty(function (int $iteration, int $seed) use ($executor): void {
            $user = $this->santiCustomer();
            $product = $this->santiCatalog(1, [
                'price' => 10000,
                'stock' => 3,
                'is_active' => true,
            ])->first();

            $context = new ToolContext(
                user: $user,
                correlationId: (string) Str::uuid(),
                draftRequestId: (string) Str::uuid(),
            );

            // Requested 5 > Available Stock 3
            $ordersBefore = Order::count();
            $response = $executor->execute('create_draft_order', [
                'items' => [['product_identifier' => (string) $product->id, 'quantity' => 5]],
            ], $context)->toFunctionResponse();

            $this->assertFalse($response['ok'], 'Draft creation exceeding stock must fail.');
            $this->assertSame(
                'INSUFFICIENT_STOCK',
                $response['error_code'],
                'Draft creation exceeding stock must return INSUFFICIENT_STOCK error code.',
            );
            $this->assertSame($ordersBefore, Order::count(), 'No order rows created when stock insufficient.');
        }, seed: self::PROPERTY_SEED, iterations: self::SANTI_PROPERTY_ITERATIONS);
    }
}
