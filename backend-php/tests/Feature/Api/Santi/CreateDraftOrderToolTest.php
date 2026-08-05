<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Product;
use App\Models\User;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolResult;
use App\Services\Chatbot\Tools\CreateDraftOrderTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CreateDraftOrderToolTest extends TestCase
{
    use RefreshDatabase;

    /** Validates: Requirements 4.1, 4.2, 5.2, 5.6, 6.7, 8.9 */
    public function test_it_requires_auth_and_returns_only_safe_draft_data(): void
    {
        $tool = new CreateDraftOrderTool();
        $responseSchema = $tool->responseSchema();
        $requestId = (string) Str::uuid();
        $items = [['product_identifier' => 'missing-product', 'quantity' => 1]];

        $unauthenticated = $tool->handle(['items' => $items], new ToolContext(
            user: null,
            correlationId: 'create-draft-unauthenticated',
            draftRequestId: $requestId,
        ))->toFunctionResponse($responseSchema);

        $this->assertSame([
            'ok' => false,
            'error_code' => 'AUTH_REQUIRED',
            'message' => 'Debes iniciar sesión para crear una propuesta.',
        ], $unauthenticated);
        $this->assertDatabaseCount('orders', 0);

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'price' => 7990,
            'stock' => 10,
            'is_active' => true,
        ]);
        $context = $this->contextFor($user, $requestId);

        $response = $tool->handle([
            'items' => [['product_identifier' => (string) $product->id, 'quantity' => 2]],
        ], $context)->toFunctionResponse($responseSchema);

        $this->assertSame(true, $response['ok']);
        $this->assertSame([
            'order_number',
            'status',
            'subtotal_clp',
            'items',
            'expires_at',
            'requires_human_confirmation',
        ], array_keys($response['data']));
        $this->assertSame('draft', $response['data']['status']);
        $this->assertSame(15980, $response['data']['subtotal_clp']);
        $this->assertSame([[
            'name' => $product->name,
            'quantity' => 2,
            'unit_price_clp' => 7990,
            'subtotal_clp' => 15980,
        ]], $response['data']['items']);
        $this->assertTrue($response['data']['requires_human_confirmation']);
        $this->assertNotSame($requestId, json_encode($response));
        $this->assertDatabaseCount('orders', 1);
    }

    /** Validates: Requirements 6.7 */
    public function test_response_serialization_strips_unapproved_draft_fields_at_every_level(): void
    {
        $tool = new CreateDraftOrderTool();
        $response = ToolResult::ok([
            'order_number' => 'IST-20260514-0007',
            'status' => 'draft',
            'subtotal_clp' => 15980,
            'items' => [[
                'name' => 'Cable USB-C 1m',
                'quantity' => 2,
                'unit_price_clp' => 7990,
                'subtotal_clp' => 15980,
                'product_id' => 12,
                'supplier_cost_clp' => 3000,
            ]],
            'expires_at' => '2026-05-16T18:00:00Z',
            'requires_human_confirmation' => true,
            'draft_request_id' => '00000000-0000-4000-8000-000000000001',
            'shipping_address' => 'Dato no permitido',
        ])->toFunctionResponse($tool->responseSchema());

        $this->assertSame([
            'ok' => true,
            'data' => [
                'order_number' => 'IST-20260514-0007',
                'status' => 'draft',
                'subtotal_clp' => 15980,
                'items' => [[
                    'name' => 'Cable USB-C 1m',
                    'quantity' => 2,
                    'unit_price_clp' => 7990,
                    'subtotal_clp' => 15980,
                ]],
                'expires_at' => '2026-05-16T18:00:00Z',
                'requires_human_confirmation' => true,
            ],
        ], $response);
        $this->assertArrayNotHasKey('draft_request_id', $response['data']);
        $this->assertArrayNotHasKey('product_id', $response['data']['items'][0]);
        $this->assertArrayNotHasKey('supplier_cost_clp', $response['data']['items'][0]);
    }

    /** Validates: Requirements 4.14, 8.9 */
    public function test_validation_errors_and_idempotent_retries_do_not_consume_the_ten_committed_drafts_quota(): void
    {
        $tool = new CreateDraftOrderTool();
        $responseSchema = $tool->responseSchema();
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 100, 'is_active' => true]);
        $rateLimitKey = "santi:draft:{$user->id}";
        RateLimiter::clear($rateLimitKey);

        $invalid = $tool->handle([
            'items' => [['product_identifier' => (string) $product->id, 'quantity' => 0]],
        ], $this->contextFor($user, (string) Str::uuid()))->toFunctionResponse($responseSchema);

        $this->assertSame('VALIDATION_ERROR', $invalid['error_code']);
        $this->assertSame(0, RateLimiter::attempts($rateLimitKey));
        $this->assertDatabaseCount('orders', 0);

        $firstRequestId = (string) Str::uuid();
        $firstContext = $this->contextFor($user, $firstRequestId);
        $first = $tool->handle([
            'items' => [['product_identifier' => (string) $product->id, 'quantity' => 1]],
        ], $firstContext)->toFunctionResponse($responseSchema);

        $this->assertSame(true, $first['ok']);
        $this->assertSame(1, RateLimiter::attempts($rateLimitKey));

        for ($draftNumber = 2; $draftNumber <= 10; $draftNumber++) {
            $created = $tool->handle([
                'items' => [['product_identifier' => (string) $product->id, 'quantity' => 1]],
            ], $this->contextFor($user, (string) Str::uuid()))->toFunctionResponse($responseSchema);

            $this->assertSame(true, $created['ok']);
        }

        $this->assertDatabaseCount('orders', 10);
        $this->assertSame(10, RateLimiter::attempts($rateLimitKey));

        $limited = $tool->handle([
            'items' => [['product_identifier' => (string) $product->id, 'quantity' => 1]],
        ], $this->contextFor($user, (string) Str::uuid()))->toFunctionResponse($responseSchema);
        $idempotent = $tool->handle([
            'items' => [['product_identifier' => (string) $product->id, 'quantity' => 1]],
        ], $firstContext)->toFunctionResponse($responseSchema);

        $this->assertSame('RATE_LIMITED', $limited['error_code']);
        $this->assertSame(true, $idempotent['ok']);
        $this->assertSame($first['data'], $idempotent['data']);
        $this->assertDatabaseCount('orders', 10);
        $this->assertSame(10, RateLimiter::attempts($rateLimitKey));
    }

    private function contextFor(User $user, string $requestId): ToolContext
    {
        return new ToolContext($user, 'create-draft-tool-test', $requestId);
    }
}
