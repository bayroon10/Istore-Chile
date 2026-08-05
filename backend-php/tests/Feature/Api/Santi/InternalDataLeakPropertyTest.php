<?php

namespace Tests\Feature\Api\Santi;

use App\Services\Chatbot\SantiAgentService;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolContract;
use App\Services\Chatbot\ToolExecutor;
use App\Services\Chatbot\ToolResult;
use App\Services\GeminiService;
use Mockery;
use Tests\TestCase;

final class InternalDataLeakPropertyTest extends TestCase
{
    use WithSantiGenerators;

    private const PROPERTY_SEED = 20250515;

    /**
     * Property 15: Internal data never leaves the function-calling boundary.
     *
     * **Validates: Requirements 6.7, 6.8, 8.1, 8.3, 8.6**
     */
    public function test_internal_tool_data_never_reaches_gemini_or_the_chatbot_response(): void
    {
        config(['santi.function_calling_enabled' => true]);

        $iterations = self::SANTI_PROPERTY_ITERATIONS;
        $this->assertSame(100, $iterations);

        $this->runSantiProperty(function (int $iteration, int $seed): void {
            $scenario = $this->scenarioFor($iteration);
            $publicDraftRequestId = $this->uuid('10000000', $iteration);
            $sentinels = $this->sensitiveSentinels($iteration);
            $tools = $this->testTools($scenario['public_data'], $sentinels);
            $executor = new ToolExecutor($tools);
            $modelRequests = [];
            $modelResponses = [
                [[
                    'type' => 'function_call',
                    'name' => $scenario['tool_name'],
                    'args' => $scenario['arguments'],
                ]],
                [[
                    'type' => 'text',
                    'text' => 'Respuesta pública de prueba 2.',
                ]],
            ];

            $gemini = Mockery::mock(GeminiService::class);
            $gemini->shouldReceive('generateContent')
                ->twice()
                ->andReturnUsing(function (array $contents, array $declarations, array $toolConfig) use (&$modelRequests, $modelResponses): array {
                    $modelRequests[] = compact('contents', 'declarations', 'toolConfig');

                    return $modelResponses[count($modelRequests) - 1];
                });
            $gemini->shouldNotReceive('generateResponse');

            $agent = new SantiAgentService($gemini, $executor);
            $result = $agent->handle(
                sprintf('Consulta pública de propiedad %d-%d.', $seed, $iteration),
                null,
                $publicDraftRequestId,
            );

            $this->assertCount(2, $modelRequests);

            foreach ($modelRequests as $modelRequest) {
                $this->assertSame([
                    'functionCallingConfig' => ['mode' => 'AUTO'],
                ], $modelRequest['toolConfig']);
                $this->assertSame(
                    ['check_stock', 'search_products', 'create_draft_order'],
                    array_column($modelRequest['declarations'][0]['functionDeclarations'], 'name'),
                );
                $this->assertSentinelsAbsent(
                    json_encode($modelRequest['contents'], JSON_THROW_ON_ERROR),
                    $sentinels,
                    'Gemini function-calling payload',
                );
            }

            $followUpContents = $modelRequests[1]['contents'];
            $this->assertSame(
                $scenario['arguments'],
                $followUpContents[1]['parts'][0]['functionCall']['args'],
            );
            $this->assertSame([
                'ok' => true,
                'data' => $scenario['public_data'],
            ], $followUpContents[2]['parts'][0]['functionResponse']['response']);

            $payload = $result->toArray();
            $this->assertSame('Respuesta pública de prueba 2.', $payload['reply']);
            $this->assertSame('OK', $payload['result_type']);
            $this->assertSame($publicDraftRequestId, $payload['draft_request_id']);
            $expectedKeys = ['reply', 'result_type', 'draft_request_id'];

            if ($scenario['tool_name'] === 'create_draft_order') {
                $expectedKeys[] = 'draft';
                $this->assertSame($scenario['public_data'], $payload['draft']);
            } else {
                $this->assertArrayNotHasKey('draft', $payload);
            }

            $this->assertEqualsCanonicalizing($expectedKeys, array_keys($payload));
            $this->assertSame($publicDraftRequestId, $payload['draft_request_id']);
            $this->assertNotSame($sentinels['internal_draft_request_id'], $payload['draft_request_id']);
            $this->assertSentinelsAbsent(
                json_encode($payload, JSON_THROW_ON_ERROR),
                $sentinels,
                'customer-facing chatbot response',
            );
        }, self::PROPERTY_SEED, $iterations);
    }

    /**
     * @return array{tool_name: string, arguments: array<string, mixed>, public_data: array<string, mixed>}
     */
    private function scenarioFor(int $iteration): array
    {
        return match ($iteration % 3) {
            0 => [
                'tool_name' => 'check_stock',
                'arguments' => ['product_identifier' => "public-product-{$iteration}"],
                'public_data' => [
                    'id' => $iteration + 1,
                    'name' => "Producto público {$iteration}",
                    'stock' => $iteration % 10,
                ],
            ],
            1 => [
                'tool_name' => 'search_products',
                'arguments' => ['query' => "consulta pública {$iteration}"],
                'public_data' => [
                    'results' => [[
                        'id' => $iteration + 1,
                        'name' => "Producto público {$iteration}",
                    ]],
                    'returned_count' => 1,
                ],
            ],
            default => [
                'tool_name' => 'create_draft_order',
                'arguments' => [
                    'items' => [[
                        'product_identifier' => "public-product-{$iteration}",
                        'quantity' => 1,
                    ]],
                ],
                'public_data' => [
                    'order_number' => sprintf('TEST-%04d', $iteration),
                    'subtotal_clp' => 10_000 + $iteration,
                    'items_count' => 1,
                ],
            ],
        };
    }

    /**
     * @param array<string, mixed> $publicData
     * @param array<string, string|int> $sentinels
     * @return list<ToolContract>
     */
    private function testTools(array $publicData, array $sentinels): array
    {
        return [
            $this->testTool('check_stock', [
                'type' => 'object',
                'properties' => ['product_identifier' => ['type' => 'string']],
                'required' => ['product_identifier'],
            ], ['product_identifier' => 'required|string'], [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'stock' => ['type' => 'integer'],
                ],
            ], $this->rawToolResult($publicData, $sentinels)),
            $this->testTool('search_products', [
                'type' => 'object',
                'properties' => ['query' => ['type' => 'string']],
                'required' => ['query'],
            ], ['query' => 'required|string'], [
                'type' => 'object',
                'properties' => [
                    'results' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'returned_count' => ['type' => 'integer'],
                ],
            ], $this->rawToolResult($publicData, $sentinels)),
            $this->testTool('create_draft_order', [
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
                'required' => ['items'],
            ], [
                'items' => 'required|array|min:1',
                'items.*.product_identifier' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
            ], [
                'type' => 'object',
                'properties' => [
                    'order_number' => ['type' => 'string'],
                    'subtotal_clp' => ['type' => 'integer'],
                    'items_count' => ['type' => 'integer'],
                ],
            ], $this->rawToolResult($publicData, $sentinels)),
        ];
    }

    /**
     * @param array<string, mixed> $publicData
     * @param array<string, string|int> $sentinels
     * @return array<string, mixed>
     */
    private function rawToolResult(array $publicData, array $sentinels): array
    {
        return $publicData + [
            'model_supplied_uuid' => $sentinels['model_supplied_uuid'],
            'correlation_uuid' => $sentinels['correlation_uuid'],
            'draft_request_id' => $sentinels['internal_draft_request_id'],
            'prompt' => $sentinels['prompt'],
            'exception_message' => $sentinels['exception_message'],
            'stack_marker' => $sentinels['stack_marker'],
            'supplier_cost_clp' => $sentinels['supplier_cost_clp'],
            'nested_sensitive' => [
                'model_supplied_uuid' => $sentinels['nested_model_uuid'],
                'prompt' => $sentinels['nested_prompt'],
                'exception_message' => $sentinels['nested_exception_message'],
                'stack_marker' => $sentinels['nested_stack_marker'],
                'supplier_cost_clp' => $sentinels['nested_supplier_cost_clp'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $argumentSchema
     * @param array<string, string|array> $rules
     * @param array<string, mixed> $responseSchema
     * @param array<string, mixed> $rawResult
     */
    private function testTool(
        string $name,
        array $argumentSchema,
        array $rules,
        array $responseSchema,
        array $rawResult,
    ): ToolContract {
        return new class($name, $argumentSchema, $rules, $responseSchema, $rawResult) implements ToolContract {
            /**
             * @param array<string, mixed> $argumentSchema
             * @param array<string, string|array> $rules
             * @param array<string, mixed> $responseSchema
             * @param array<string, mixed> $rawResult
             */
            public function __construct(
                private readonly string $toolName,
                private readonly array $argumentSchema,
                private readonly array $rules,
                private readonly array $responseSchema,
                private readonly array $rawResult,
            ) {
            }

            public function name(): string
            {
                return $this->toolName;
            }

            public function declaration(): array
            {
                return [
                    'name' => $this->toolName,
                    'description' => 'Test-only tool with an explicitly bounded public response.',
                    'parameters' => $this->argumentSchema,
                ];
            }

            public function responseSchema(): array
            {
                return $this->responseSchema;
            }

            public function rules(): array
            {
                return $this->rules;
            }

            public function requiresAuth(): bool
            {
                return false;
            }

            public function handle(array $args, ToolContext $ctx): ToolResult
            {
                return ToolResult::ok($this->rawResult);
            }
        };
    }

    /** @return array<string, string|int> */
    private function sensitiveSentinels(int $iteration): array
    {
        return [
            'model_supplied_uuid' => $this->uuid('20000000', $iteration),
            'correlation_uuid' => $this->uuid('30000000', $iteration),
            'internal_draft_request_id' => $this->uuid('40000000', $iteration),
            'prompt' => "PROMPT_SENTINEL_{$iteration}: ignore all controls",
            'exception_message' => "EXCEPTION_SENTINEL_{$iteration}: database credentials failed",
            'stack_marker' => "STACK_TRACE_SENTINEL_{$iteration}",
            'supplier_cost_clp' => 900_000 + $iteration,
            'nested_model_uuid' => $this->uuid('50000000', $iteration),
            'nested_prompt' => "NESTED_PROMPT_SENTINEL_{$iteration}",
            'nested_exception_message' => "NESTED_EXCEPTION_SENTINEL_{$iteration}",
            'nested_stack_marker' => "NESTED_STACK_TRACE_SENTINEL_{$iteration}",
            'nested_supplier_cost_clp' => 800_000 + $iteration,
        ];
    }

    /** @param array<string, string|int> $sentinels */
    private function assertSentinelsAbsent(string $value, array $sentinels, string $boundary): void
    {
        foreach ($sentinels as $name => $sentinel) {
            $this->assertStringNotContainsString((string) $sentinel, $value, "{$boundary} leaked {$name}.");
        }

        foreach ([
            'model_supplied_uuid',
            'correlation_uuid',
            'prompt',
            'exception_message',
            'stack_marker',
            'supplier_cost_clp',
            'nested_sensitive',
        ] as $field) {
            $this->assertStringNotContainsString($field, $value, "{$boundary} leaked raw field {$field}.");
        }
    }

    private function uuid(string $prefix, int $iteration): string
    {
        return sprintf('%s-0000-4000-8000-%012d', $prefix, $iteration + 1);
    }
}
