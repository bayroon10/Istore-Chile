<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ToolArgumentValidationPropertyTest extends TestCase
{
    use RefreshDatabase;
    use WithSantiGenerators;

    private const PROPERTY_SEED = 7_300_2026;

    /**
     * Property 2: Model arguments are always validated before touching the database.
     *
     * **Validates: Requirements 3.6, 4.1, 4.2, 6.1, 6.2, 6.3, 6.6**
     */
    public function test_invalid_tool_arguments_are_rejected_with_validation_error_without_database_writes(): void
    {
        $executor = app(ToolExecutor::class);
        $user = User::factory()->create();

        $this->runSantiProperty(function (int $iteration, int $seed) use ($executor, $user): void {
            $context = new ToolContext(
                user: $user,
                correlationId: (string) Str::uuid(),
                draftRequestId: (string) Str::uuid(),
            );

            $invalidToolCall = $this->invalidArgumentPayload($iteration);
            $ordersBefore = Order::count();
            $itemsBefore = OrderItem::count();

            $response = $executor->execute($invalidToolCall['tool'], $invalidToolCall['args'], $context)->toFunctionResponse();

            $this->assertFalse(
                $response['ok'],
                "Invalid payload for {$invalidToolCall['tool']} ({$invalidToolCall['case']}) must fail validation.",
            );
            $this->assertContains(
                $response['error_code'],
                ['VALIDATION_ERROR', 'INVALID_PRICE_RANGE'],
                "Invalid payload for {$invalidToolCall['tool']} ({$invalidToolCall['case']}) must return VALIDATION_ERROR or INVALID_PRICE_RANGE.",
            );
            $this->assertSame($ordersBefore, Order::count(), 'Invalid tool call must not persist order rows.');
            $this->assertSame($itemsBefore, OrderItem::count(), 'Invalid tool call must not persist order item rows.');
        }, seed: self::PROPERTY_SEED, iterations: self::SANTI_PROPERTY_ITERATIONS);
    }

    /**
     * @return array{tool: string, args: array<string, mixed>, case: string}
     */
    private function invalidArgumentPayload(int $iteration): array
    {
        $cases = [
            // create_draft_order invalid quantities
            [
                'tool' => 'create_draft_order',
                'args' => ['items' => [['product_identifier' => 'mouse', 'quantity' => 0]]],
                'case' => 'zero quantity',
            ],
            [
                'tool' => 'create_draft_order',
                'args' => ['items' => [['product_identifier' => 'mouse', 'quantity' => -5]]],
                'case' => 'negative quantity',
            ],
            [
                'tool' => 'create_draft_order',
                'args' => ['items' => [['product_identifier' => 'mouse', 'quantity' => 100]]],
                'case' => 'quantity > 99',
            ],
            [
                'tool' => 'create_draft_order',
                'args' => ['items' => [['product_identifier' => 'mouse', 'quantity' => 2.5]]],
                'case' => 'float quantity',
            ],
            [
                'tool' => 'create_draft_order',
                'args' => ['items' => [['product_identifier' => 'mouse', 'quantity' => 'five']]],
                'case' => 'string quantity',
            ],

            // create_draft_order invalid product_identifier
            [
                'tool' => 'create_draft_order',
                'args' => ['items' => [['product_identifier' => '', 'quantity' => 1]]],
                'case' => 'empty product_identifier',
            ],
            [
                'tool' => 'create_draft_order',
                'args' => ['items' => [['product_identifier' => str_repeat('a', 256), 'quantity' => 1]]],
                'case' => 'product_identifier > 255 chars',
            ],
            [
                'tool' => 'create_draft_order',
                'args' => ['items' => [['product_identifier' => ['nested-array'], 'quantity' => 1]]],
                'case' => 'array product_identifier',
            ],
            [
                'tool' => 'create_draft_order',
                'args' => ['items' => []],
                'case' => 'empty items array',
            ],

            // search_products invalid arguments
            [
                'tool' => 'search_products',
                'args' => ['query' => ''],
                'case' => 'empty search query',
            ],
            [
                'tool' => 'search_products',
                'args' => ['query' => '   '],
                'case' => 'whitespace search query',
            ],
            [
                'tool' => 'search_products',
                'args' => ['query' => str_repeat('q', 101)],
                'case' => 'search query > 100 chars',
            ],
            [
                'tool' => 'search_products',
                'args' => ['query' => 'laptop', 'min_price' => 50000, 'max_price' => 10000],
                'case' => 'min_price > max_price',
            ],
            [
                'tool' => 'search_products',
                'args' => ['query' => 'laptop', 'min_price' => -100],
                'case' => 'negative min_price',
            ],

            // check_stock invalid arguments
            [
                'tool' => 'check_stock',
                'args' => ['product_identifier' => ''],
                'case' => 'empty check_stock identifier',
            ],
            [
                'tool' => 'check_stock',
                'args' => ['product_identifier' => str_repeat('p', 256)],
                'case' => 'check_stock identifier > 255 chars',
            ],
        ];

        return $cases[$iteration % count($cases)];
    }
}
