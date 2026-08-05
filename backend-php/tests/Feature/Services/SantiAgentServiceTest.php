<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Services\Chatbot\AgentResult;
use App\Services\Chatbot\SantiAgentService;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolContract;
use App\Services\Chatbot\ToolExecutor;
use App\Services\Chatbot\ToolResult;
use App\Services\GeminiService;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SantiAgentServiceTest extends TestCase
{
    /** Validates: Requirements 1.1, 1.4, 3.11, 7.6, 7.7, 9.1 */
    public function test_it_auto_resolves_and_returns_usable_final_text_without_catalog_preload(): void
    {
        config(['santi.function_calling_enabled' => true]);
        $requests = [];
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateContent')->once()->andReturnUsing(
            function (array $contents, array $tools, array $toolConfig) use (&$requests): array {
                $requests[] = compact('contents', 'tools', 'toolConfig');

                return [['type' => 'text', 'text' => 'Claro, puedo ayudarte con eso.']];
            },
        );
        $gemini->shouldNotReceive('generateResponse');
        $this->app->instance(GeminiService::class, $gemini);

        $agent = app(SantiAgentService::class);
        $result = $agent->handle('Necesito ayuda con un cargador.', null, null);

        $this->assertInstanceOf(SantiAgentService::class, $agent);
        $this->assertSame(AgentResult::RESULT_TYPE_OK, $result->resultType);
        $this->assertSame('Claro, puedo ayudarte con eso.', $result->reply);
        $this->assertNotNull($result->draftRequestId);
        $this->assertTrue(Str::isUuid($result->draftRequestId));
        $this->assertSame(['check_stock', 'search_products', 'create_draft_order'], array_column(
            $requests[0]['tools'][0]['functionDeclarations'],
            'name',
        ));
        $this->assertSame(['functionCallingConfig' => ['mode' => 'AUTO']], $requests[0]['toolConfig']);
        $prompt = $requests[0]['contents'][0]['parts'][0]['text'];
        $this->assertStringContainsString('Santi', $prompt);
        $this->assertStringNotContainsString('INVENTARIO REAL ACTUAL', $prompt);
        $this->assertStringNotContainsString('Cable USB-C', $prompt);
    }

    /** Validates: Requirements 1.2, 1.3, 5.6, 6.7, 7.2, 8.4 */
    public function test_it_uses_server_tool_context_appends_function_responses_and_stores_bounded_drafts(): void
    {
        config(['santi.function_calling_enabled' => true, 'santi.max_tool_rounds' => 3, 'santi.max_tool_calls' => 6]);
        [$executor, , , $draftTool] = $this->executor();
        $requests = [];
        $serverDraftRequestId = '18c53e6c-1940-4fd9-9c83-2b2b56497dc1';
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateContent')->twice()->andReturnUsing(
            function (array $contents, array $tools, array $toolConfig) use (&$requests): array {
                $requests[] = compact('contents', 'tools', 'toolConfig');

                return count($requests) === 1
                    ? [[
                        'type' => 'function_call',
                        'name' => 'create_draft_order',
                        'args' => ['items' => [['product_identifier' => 'cable-usb-c', 'quantity' => 2]]],
                    ]]
                    : [['type' => 'text', 'text' => 'Preparé una propuesta para que la revises antes de confirmar.']];
            },
        );

        $result = (new SantiAgentService($gemini, $executor))->handle(
            'Arma una propuesta con dos cables.',
            new User(),
            $serverDraftRequestId,
        );

        $this->assertSame(AgentResult::RESULT_TYPE_OK, $result->resultType);
        $this->assertSame($serverDraftRequestId, $result->draftRequestId);
        $this->assertSame([
            'order_number' => 'IST-TEST-0001',
            'status' => 'draft',
            'subtotal_clp' => 15980,
            'items' => [[
                'name' => 'Cable USB-C',
                'quantity' => 2,
                'unit_price_clp' => 7990,
                'subtotal_clp' => 15980,
            ]],
            'expires_at' => '2026-05-16T18:00:00Z',
            'requires_human_confirmation' => true,
        ], $result->draft);
        $this->assertCount(1, $draftTool->contexts);
        $this->assertSame($serverDraftRequestId, $draftTool->contexts[0]->draftRequestId);
        $this->assertTrue(Str::isUuid($draftTool->contexts[0]->correlationId));
        $this->assertNotSame($serverDraftRequestId, $draftTool->contexts[0]->correlationId);
        $this->assertStringNotContainsString($serverDraftRequestId, json_encode($requests));
        $functionResponse = $requests[1]['contents'][2]['parts'][0]['functionResponse']['response'];
        $this->assertSame('create_draft_order', $requests[1]['contents'][2]['parts'][0]['functionResponse']['name']);
        $this->assertArrayNotHasKey('draft_request_id', $functionResponse['data']);
        $this->assertArrayNotHasKey('internal_note', $functionResponse['data']);
    }

    /** Validates: Requirements 4.14, 6.2, 6.6, 8.9 */
    public function test_it_ignores_model_supplied_draft_request_ids(): void
    {
        config(['santi.function_calling_enabled' => true]);
        [$executor, , , $draftTool] = $this->executor();
        $requests = [];
        $serverDraftRequestId = '31f20a20-4a4d-4b89-a0af-d98e9f47a511';
        $modelDraftRequestId = '00000000-0000-4000-8000-000000000002';
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateContent')->twice()->andReturnUsing(
            function (array $contents, array $tools, array $toolConfig) use (&$requests, $modelDraftRequestId): array {
                $requests[] = compact('contents', 'tools', 'toolConfig');

                return count($requests) === 1
                    ? [[
                        'type' => 'function_call',
                        'name' => 'create_draft_order',
                        'args' => [
                            'items' => [['product_identifier' => 'cable-usb-c', 'quantity' => 1]],
                            'draft_request_id' => $modelDraftRequestId,
                        ],
                    ]]
                    : [['type' => 'text', 'text' => 'No pude crear una propuesta con esos datos.']];
            },
        );

        $result = (new SantiAgentService($gemini, $executor))->handle(
            'Arma una propuesta.',
            new User(),
            $serverDraftRequestId,
        );

        $this->assertSame(AgentResult::RESULT_TYPE_OK, $result->resultType);
        $this->assertSame($serverDraftRequestId, $result->draftRequestId);
        $this->assertSame(0, $draftTool->calls);
        $this->assertStringNotContainsString($modelDraftRequestId, json_encode($requests[1]));
        $response = $requests[1]['contents'][2]['parts'][0]['functionResponse']['response'];
        $this->assertSame('VALIDATION_ERROR', $response['error_code']);
    }

    /** Validates: Requirements 1.7, 8.2 */
    public function test_it_returns_safe_retry_before_executing_a_tool_call_that_exceeds_the_limit(): void
    {
        config(['santi.function_calling_enabled' => true, 'santi.max_tool_rounds' => 3, 'santi.max_tool_calls' => 1]);
        [$executor, $stockTool] = $this->executor();
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateContent')->once()->andReturn([
            ['type' => 'function_call', 'name' => 'check_stock', 'args' => ['product_identifier' => 'first']],
            ['type' => 'function_call', 'name' => 'check_stock', 'args' => ['product_identifier' => 'second']],
        ]);

        $result = (new SantiAgentService($gemini, $executor))->handle('Revisa stock.', null, null);

        $this->assertSame(AgentResult::RESULT_TYPE_SAFE_RETRY, $result->resultType);
        $this->assertSame(0, $stockTool->calls);
        $this->assertNotNull($result->draftRequestId);
        $this->assertTrue(Str::isUuid($result->draftRequestId));
    }

    /** Validates: Requirements 1.5, 8.2 */
    public function test_it_returns_safe_retry_without_executing_a_follow_up_tool_call_after_the_configured_number_of_tool_rounds(): void
    {
        config(['santi.function_calling_enabled' => true, 'santi.max_tool_rounds' => 1, 'santi.max_tool_calls' => 6]);
        [$executor, $stockTool] = $this->executor();
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateContent')->twice()->andReturn(
            [['type' => 'function_call', 'name' => 'check_stock', 'args' => ['product_identifier' => 'cable-usb-c']]],
            [['type' => 'function_call', 'name' => 'check_stock', 'args' => ['product_identifier' => 'cable-usb-c']]],
        );

        $result = (new SantiAgentService($gemini, $executor))->handle('Revisa stock.', null, null);

        $this->assertSame(AgentResult::RESULT_TYPE_SAFE_RETRY, $result->resultType);
        $this->assertSame(1, $stockTool->calls);
    }

    /** Validates: Requirements 1.3, 1.4, 1.5 */
    public function test_it_returns_final_text_after_the_last_allowed_tool_round(): void
    {
        config(['santi.function_calling_enabled' => true, 'santi.max_tool_rounds' => 1, 'santi.max_tool_calls' => 6]);
        [$executor, $stockTool] = $this->executor();
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateContent')->twice()->andReturn(
            [['type' => 'function_call', 'name' => 'check_stock', 'args' => ['product_identifier' => 'cable-usb-c']]],
            [['type' => 'text', 'text' => 'El Cable USB-C tiene 10 unidades disponibles.']],
        );

        $result = (new SantiAgentService($gemini, $executor))->handle('Revisa stock.', null, null);

        $this->assertSame(AgentResult::RESULT_TYPE_OK, $result->resultType);
        $this->assertSame('El Cable USB-C tiene 10 unidades disponibles.', $result->reply);
        $this->assertSame(1, $stockTool->calls);
    }

    /**
     * @param array<int, array<string, mixed>> $response
     * Validates: Requirements 8.2, 8.3
     */
    #[DataProvider('unsafeModelResponses')]
    public function test_it_safely_retries_for_dependency_and_malformed_model_responses(array $response): void
    {
        config(['santi.function_calling_enabled' => true]);
        [$executor] = $this->executor();
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateContent')->once()->andReturn($response);

        $result = (new SantiAgentService($gemini, $executor))->handle('Mensaje privado.', null, null);

        $this->assertSame(AgentResult::RESULT_TYPE_SAFE_RETRY, $result->resultType);
        $this->assertSame('Perdona, no pude completar la solicitud en este momento. ¿Lo intentamos de nuevo?', $result->reply);
        $this->assertStringNotContainsString('api_key', $result->reply);
    }

    /** @return array<string, array{0: array<int, array<string, mixed>>}> */
    public static function unsafeModelResponses(): array
    {
        return [
            'dependency error' => [['error' => 'api_key=super-secret']],
            'empty response' => [[]],
            'malformed function call' => [[[
                'type' => 'function_call',
                'name' => 'check_stock',
                'args' => 'not-an-object',
            ]]],
        ];
    }

    /** Validates: Requirements 8.2, 9.1 */
    public function test_it_uses_the_legacy_fallback_exactly_once_without_catalog_when_disabled(): void
    {
        config(['santi.function_calling_enabled' => false]);
        $serverDraftRequestId = '448f5aa4-8d1a-4c27-aadb-7f6e5703ca15';
        $prompts = [];
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateResponse')->once()->andReturnUsing(
            function (string $prompt) use (&$prompts): string {
                $prompts[] = $prompt;

                return 'Claro, dime qué producto estás buscando.';
            },
        );
        $gemini->shouldNotReceive('generateContent');

        $result = (new SantiAgentService($gemini, new ToolExecutor()))->handle(
            'Busco un cargador.',
            null,
            $serverDraftRequestId,
        );

        $this->assertSame(AgentResult::RESULT_TYPE_OK, $result->resultType);
        $this->assertSame('Claro, dime qué producto estás buscando.', $result->reply);
        $this->assertSame($serverDraftRequestId, $result->draftRequestId);
        $this->assertCount(1, $prompts);
        $this->assertStringContainsString('Santi', $prompts[0]);
        $this->assertStringNotContainsString('INVENTARIO REAL ACTUAL', $prompts[0]);
        $this->assertStringNotContainsString('Cable USB-C', $prompts[0]);
        $this->assertStringNotContainsString($serverDraftRequestId, $prompts[0]);
    }

    /** @return array{ToolExecutor, AgentTestTool, AgentTestTool, AgentTestTool} */
    private function executor(): array
    {
        $stock = new AgentTestTool(
            name: 'check_stock',
            parameters: [
                'type' => 'object',
                'properties' => ['product_identifier' => ['type' => 'string']],
            ],
            rules: ['product_identifier' => 'required|string|max:255'],
            responseSchema: [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'slug' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'is_active' => ['type' => 'boolean'],
                    'stock' => ['type' => 'integer'],
                ],
            ],
            resultData: [
                'id' => 1,
                'slug' => 'cable-usb-c',
                'name' => 'Cable USB-C',
                'is_active' => true,
                'stock' => 10,
            ],
        );
        $search = new AgentTestTool(
            name: 'search_products',
            parameters: [
                'type' => 'object',
                'properties' => ['query' => ['type' => 'string']],
            ],
            rules: ['query' => 'required|string|min:1|max:100'],
            responseSchema: [
                'type' => 'object',
                'properties' => ['results' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => []]]],
            ],
            resultData: ['results' => []],
        );
        $draft = new AgentTestTool(
            name: 'create_draft_order',
            parameters: [
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
            rules: [
                'items' => 'required|array|min:1|max:20',
                'items.*.product_identifier' => 'required|string|max:255',
                'items.*.quantity' => 'required|integer|min:1|max:99',
            ],
            responseSchema: [
                'type' => 'object',
                'properties' => [
                    'order_number' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'subtotal_clp' => ['type' => 'integer'],
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'quantity' => ['type' => 'integer'],
                                'unit_price_clp' => ['type' => 'integer'],
                                'subtotal_clp' => ['type' => 'integer'],
                            ],
                        ],
                    ],
                    'expires_at' => ['type' => 'string'],
                    'requires_human_confirmation' => ['type' => 'boolean'],
                ],
            ],
            resultData: [
                'order_number' => 'IST-TEST-0001',
                'status' => 'draft',
                'subtotal_clp' => 15980,
                'items' => [[
                    'name' => 'Cable USB-C',
                    'quantity' => 2,
                    'unit_price_clp' => 7990,
                    'subtotal_clp' => 15980,
                ]],
                'expires_at' => '2026-05-16T18:00:00Z',
                'requires_human_confirmation' => true,
                'draft_request_id' => '00000000-0000-4000-8000-000000000099',
                'internal_note' => 'not for Gemini or customers',
            ],
            requiresAuth: true,
        );

        return [new ToolExecutor([$stock, $search, $draft]), $stock, $search, $draft];
    }
}

final class AgentTestTool implements ToolContract
{
    public int $calls = 0;

    /** @var list<ToolContext> */
    public array $contexts = [];

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, string|array> $rules
     * @param array<string, mixed> $responseSchema
     * @param array<string, mixed> $resultData
     */
    public function __construct(
        private readonly string $name,
        private readonly array $parameters,
        private readonly array $rules,
        private readonly array $responseSchema,
        private readonly array $resultData,
        private readonly bool $requiresAuth = false,
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
            'parameters' => $this->parameters,
        ];
    }

    /** @return array<string, mixed> */
    public function responseSchema(): array
    {
        return $this->responseSchema;
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
        $this->contexts[] = $ctx;

        return ToolResult::ok($this->resultData);
    }
}
