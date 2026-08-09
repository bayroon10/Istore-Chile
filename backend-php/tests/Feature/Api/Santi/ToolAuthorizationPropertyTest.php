<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\Tools\CheckStockTool;
use App\Services\Chatbot\Tools\CreateDraftOrderTool;
use App\Services\Chatbot\Tools\SearchProductsTool;
use App\Services\Chatbot\ToolExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ToolAuthorizationPropertyTest extends TestCase
{
    use RefreshDatabase;
    use WithSantiGenerators;

    private const PROPERTY_SEED = 5_100_2026;

    /**
     * Property 10a: An authenticated user can NEVER create a draft for another user_id.
     *
     * **Validates: Requirements 5.2, 5.4**
     */
    public function test_authenticated_user_cannot_create_draft_for_another_user_id(): void
    {
        $this->runSantiProperty(function (int $iteration, int $seed): void {
            $catalog = $this->santiCatalog(2, ['is_active' => true, 'stock' => 50, 'price' => 10000]);
            $owner = $this->santiCustomer(['name' => "Owner-{$seed}-{$iteration}"]);
            $attacker = $this->santiCustomer(['name' => "Attacker-{$seed}-{$iteration}"]);

            $executor = app(ToolExecutor::class);

            // Case 1: Attacker sends extra undeclared user identity parameters in payload.
            // ToolExecutor MUST reject undeclared arguments with VALIDATION_ERROR before execution.
            $hostilePayloads = [
                ['user_id' => $attacker->id],
                ['user_id' => 999999],
                ['user' => ['id' => $attacker->id]],
                ['customer_id' => $attacker->id],
                ['owner_id' => $attacker->id],
                ['user_email' => 'attacker@evil.invalid'],
            ];

            $extraArgs = $hostilePayloads[$iteration % count($hostilePayloads)];
            $itemsRejected = $this->santiValidItems($catalog, 1);
            $argsWithExtra = array_merge(['items' => $itemsRejected], $extraArgs);

            $context = new ToolContext(
                user: $owner,
                correlationId: (string) Str::uuid(),
                draftRequestId: (string) Str::uuid(),
            );

            $rejectedResponse = $executor->execute('create_draft_order', $argsWithExtra, $context)->toFunctionResponse();

            $this->assertFalse(
                $rejectedResponse['ok'],
                'Undeclared identity arguments in tool call must be rejected by ToolExecutor.',
            );
            $this->assertSame(
                'VALIDATION_ERROR',
                $rejectedResponse['error_code'],
                'Undeclared identity arguments must return VALIDATION_ERROR.',
            );

            // Case 2: Clean declared arguments call — Draft must belong ONLY to $context->user
            $catalogClean = $this->santiCatalog(2, ['is_active' => true, 'stock' => 50, 'price' => 10000]);
            $itemsClean = $this->santiValidItems($catalogClean, 1);
            $cleanContext = new ToolContext(
                user: $owner,
                correlationId: (string) Str::uuid(),
                draftRequestId: (string) Str::uuid(),
            );

            $successResponse = $executor->execute('create_draft_order', ['items' => $itemsClean], $cleanContext)->toFunctionResponse();

            $this->assertTrue(
                $successResponse['ok'],
                'Valid declared tool call must succeed for owner.',
            );
            $orderNumber = $successResponse['data']['order_number'];
            $order = Order::where('order_number', $orderNumber)->first();

            $this->assertNotNull($order);
            $this->assertSame(
                $owner->id,
                $order->user_id,
                "Order user_id must match authenticated user ({$owner->id}).",
            );
            $this->assertNotSame(
                $attacker->id,
                $order->user_id,
                "Order user_id must NEVER be assigned to attacker ({$attacker->id}).",
            );
        }, seed: self::PROPERTY_SEED, iterations: self::SANTI_PROPERTY_ITERATIONS);
    }

    /**
     * Property 10b: Unauthenticated visitors CANNOT execute create_draft_order.
     *
     * **Validates: Requirements 5.2, 5.3**
     */
    public function test_unauthenticated_visitor_cannot_create_draft_order(): void
    {
        $this->runSantiProperty(function (int $iteration, int $seed): void {
            $catalog = $this->santiCatalog(1, ['is_active' => true, 'stock' => 10, 'price' => 10000]);
            $items = $this->santiValidItems($catalog, 1);
            $ordersBefore = Order::count();
            $itemsBefore = OrderItem::count();

            $executor = app(ToolExecutor::class);

            $context = new ToolContext(
                user: null, // Unauthenticated visitor
                correlationId: (string) Str::uuid(),
                draftRequestId: (string) Str::uuid(),
            );

            $response = $executor->execute('create_draft_order', ['items' => $items], $context)->toFunctionResponse();

            $this->assertFalse($response['ok'], 'Unauthenticated create_draft_order must fail.');
            $this->assertSame(
                'AUTH_REQUIRED',
                $response['error_code'],
                'Unauthenticated create_draft_order must fail with AUTH_REQUIRED.',
            );
            $this->assertSame($ordersBefore, Order::count(), 'No order rows must be created for unauthenticated visitor.');
            $this->assertSame($itemsBefore, OrderItem::count(), 'No order item rows must be created for unauthenticated visitor.');
        }, seed: self::PROPERTY_SEED, iterations: self::SANTI_PROPERTY_ITERATIONS);
    }

    /**
     * Property 10c: Public tools (check_stock, search_products) work without authentication, whereas create_draft_order requires it.
     *
     * **Validates: Requirements 5.1, 5.2, 5.3**
     */
    public function test_tool_executor_enforces_auth_requirements_correctly(): void
    {
        $checkStockTool = app(CheckStockTool::class);
        $searchProductsTool = app(SearchProductsTool::class);
        $createDraftTool = app(CreateDraftOrderTool::class);

        $this->assertFalse($checkStockTool->requiresAuth(), 'check_stock must be public (requiresAuth = false).');
        $this->assertFalse($searchProductsTool->requiresAuth(), 'search_products must be public (requiresAuth = false).');
        $this->assertTrue($createDraftTool->requiresAuth(), 'create_draft_order must require auth (requiresAuth = true).');

        $executor = app(ToolExecutor::class);
        $product = $this->santiCatalog(1, ['name' => 'Mouse Gaming', 'is_active' => true, 'stock' => 5, 'price' => 10000])->first();

        $unauthContext = new ToolContext(
            user: null,
            correlationId: (string) Str::uuid(),
            draftRequestId: (string) Str::uuid(),
        );

        // Public tool 1: check_stock
        $checkStockResp = $executor->execute('check_stock', [
            'product_identifier' => (string) $product->id,
        ], $unauthContext)->toFunctionResponse();

        $this->assertTrue($checkStockResp['ok'], 'check_stock must succeed for unauthenticated visitor.');

        // Public tool 2: search_products
        $searchResp = $executor->execute('search_products', [
            'query' => 'Mouse',
        ], $unauthContext)->toFunctionResponse();

        $this->assertTrue($searchResp['ok'], 'search_products must succeed for unauthenticated visitor.');

        // Protected tool: create_draft_order
        $draftResp = $executor->execute('create_draft_order', [
            'items' => [['product_identifier' => (string) $product->id, 'quantity' => 1]],
        ], $unauthContext)->toFunctionResponse();

        $this->assertFalse($draftResp['ok'], 'create_draft_order must be rejected for unauthenticated visitor.');
        $this->assertSame('AUTH_REQUIRED', $draftResp['error_code']);
    }
}
