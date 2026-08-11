<?php

namespace Tests\Unit\Services\Chatbot;

use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolContract;
use App\Services\Chatbot\ToolExecutor;
use App\Services\Chatbot\ToolResult;
use Closure;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class ToolExecutorTest extends TestCase
{
    /** Validates: Requirements 1.2, 1.6, 5.1, 5.2, 6.1, 6.2, 6.3, 6.4, 6.6, 8.5, 8.6 */
    public function test_it_declares_exactly_the_three_approved_tools(): void
    {
        $declarations = (new ToolExecutor())->declarations();

        $this->assertSame(['check_stock', 'search_products', 'create_draft_order'], array_column(
            $declarations[0]['function_declarations'],
            'name',
        ));
    }

    /** Validates: Requirements 1.6, 6.4 */
    public function test_it_rejects_an_unknown_tool_without_invoking_a_handler(): void
    {
        [$executor, $stock] = $this->executor();

        $response = $executor->execute('delete_catalog', [], $this->context())->toFunctionResponse();

        $this->assertSame('UNKNOWN_TOOL', $response['error_code']);
        $this->assertSame(0, $stock->calls);
    }

    /** Validates: Requirements 4.15, 4.16, 9.2, 9.3 */
    public function test_it_rejects_known_payment_or_order_operation_names_before_execution(): void
    {
        [$executor, $stock] = $this->executor();

        $response = $executor->execute('refund_payment', [], $this->context())->toFunctionResponse();

        $this->assertSame('FORBIDDEN_OPERATION', $response['error_code']);
        $this->assertSame(0, $stock->calls);
    }

    /** Validates: Requirements 6.2, 6.3, 6.6 */
    public function test_it_rejects_undeclared_keys_at_top_level_and_inside_items(): void
    {
        [$executor, $stock, $search, $draft] = $this->executor();

        $topLevel = $executor->execute('search_products', ['query' => 'cable', 'price' => 1], $this->context())->toFunctionResponse();
        $nested = $executor->execute('create_draft_order', [
            'items' => [['product_identifier' => 'cable-usb-c', 'quantity' => 1, 'status' => 'paid']],
        ], $this->context(user: new \App\Models\User()))->toFunctionResponse();

        $this->assertSame('VALIDATION_ERROR', $topLevel['error_code']);
        $this->assertSame('VALIDATION_ERROR', $nested['error_code']);
        $this->assertSame(0, $stock->calls);
        $this->assertSame(0, $search->calls);
        $this->assertSame(0, $draft->calls);
    }

    /** Validates: Requirements 6.2, 6.3, 6.6 */
    public function test_it_recursively_rejects_dangerous_content_before_schema_or_handler_execution(): void
    {
        [$executor, $stock, $search, $draft] = $this->executor();

        $sql = $executor->execute('check_stock', ['product_identifier' => '1; DROP TABLE products;'], $this->context())->toFunctionResponse();
        $script = $executor->execute('search_products', ['query' => '<script>alert(1)</script>'], $this->context())->toFunctionResponse();
        $url = $executor->execute('create_draft_order', [
            'items' => [['product_identifier' => 'https://attacker.invalid/product', 'quantity' => 1]],
        ], $this->context(user: new \App\Models\User()))->toFunctionResponse();

        $this->assertSame('VALIDATION_ERROR', $sql['error_code']);
        $this->assertSame('VALIDATION_ERROR', $script['error_code']);
        $this->assertSame('VALIDATION_ERROR', $url['error_code']);
        $this->assertSame(0, $stock->calls);
        $this->assertSame(0, $search->calls);
        $this->assertSame(0, $draft->calls);
    }

    /** Validates: Requirements 5.2, 5.3, 6.2, 6.3 */
    public function test_it_uses_laravel_validation_and_requires_authentication_before_mutating_handlers(): void
    {
        [$executor, $stock, $search, $draft] = $this->executor();

        $invalid = $executor->execute('search_products', ['query' => 10], $this->context())->toFunctionResponse();
        $unauthenticated = $executor->execute('create_draft_order', [
            'items' => [['product_identifier' => 'cable-usb-c', 'quantity' => 1]],
        ], $this->context())->toFunctionResponse();

        $this->assertSame('VALIDATION_ERROR', $invalid['error_code']);
        $this->assertSame('AUTH_REQUIRED', $unauthenticated['error_code']);
        $this->assertSame(0, $stock->calls);
        $this->assertSame(0, $search->calls);
        $this->assertSame(0, $draft->calls);
    }

    /** Validates: Requirements 6.3, 8.1, 8.5, 8.6 */
    public function test_it_sanitizes_handler_failures_and_writes_only_structured_safe_log_fields(): void
    {
        [$executor, $stock] = $this->executor(handler: static function (): ToolResult {
            throw new \RuntimeException('database credentials are secret');
        });
        Log::spy();

        $response = $executor->execute('check_stock', ['product_identifier' => 'cable-usb-c'], $this->context())->toFunctionResponse();

        $this->assertSame([
            'ok' => false,
            'error_code' => 'DEPENDENCY_ERROR',
            'message' => 'No fue posible completar la solicitud. Inténtalo nuevamente.',
        ], $response);
        $this->assertSame(1, $stock->calls);
        Log::shouldHaveReceived('info')->once()->with(
            'santi.tool_execution',
            \Mockery::on(static fn (array $context): bool => array_keys($context) === [
                'correlation_id',
                'tool',
                'outcome',
                'duration_ms',
            ] && $context['correlation_id'] === 'correlation-id'
                && $context['tool'] === 'check_stock'
                && $context['outcome'] === 'DEPENDENCY_ERROR'
                && is_int($context['duration_ms']),
        ));
    }

    /**
     * @return array{ToolExecutor, FakeTool, FakeTool, FakeTool}
     */
    private function executor(?Closure $handler = null): array
    {
        $stock = new FakeTool(
            name: 'check_stock',
            rules: ['product_identifier' => 'required|string|max:255'],
            handler: $handler,
        );
        $search = new FakeTool(
            name: 'search_products',
            rules: ['query' => 'required|string|min:1|max:100'],
        );
        $draft = new FakeTool(
            name: 'create_draft_order',
            rules: [
                'items' => 'required|array|min:1|max:20',
                'items.*.product_identifier' => 'required|string|max:255',
                'items.*.quantity' => 'required|integer|min:1|max:99',
            ],
            requiresAuth: true,
            declaration: [
                'type' => 'object',
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'product_identifier' => ['type' => 'string'],
                                'quantity' => ['type' => 'integer'],
                            ],
                        ],
                    ],
                ],
            ],
        );

        return [new ToolExecutor([$stock, $search, $draft]), $stock, $search, $draft];
    }

    private function context(?\App\Models\User $user = null): ToolContext
    {
        return new ToolContext($user, 'correlation-id', 'draft-request-id');
    }
}

final class FakeTool implements ToolContract
{
    public int $calls = 0;

    /**
     * @param array<string, string|array> $rules
     * @param array<string, mixed>|null $declaration
     */
    public function __construct(
        private readonly string $name,
        private readonly array $rules,
        private readonly bool $requiresAuth = false,
        private readonly ?Closure $handler = null,
        private readonly ?array $declaration = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return array<string, mixed> */
    public function declaration(): array
    {
        return [
            'name' => $this->name,
            'parameters' => $this->declaration ?? [
                'type' => 'object',
                'properties' => array_fill_keys(array_keys($this->rules), ['type' => 'string']),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'handled' => ['type' => 'string'],
            ],
        ];
    }

    /** @return array<string, string|array> */
    public function rules(): array
    {
        return $this->rules;
    }

    public function requiresAuth(): bool
    {
        return $this->requiresAuth;
    }

    /** @param array<string, mixed> $args */
    public function handle(array $args, ToolContext $ctx): ToolResult
    {
        $this->calls++;

        return $this->handler !== null
            ? ($this->handler)($args, $ctx)
            : ToolResult::ok(['handled' => $this->name]);
    }
}
