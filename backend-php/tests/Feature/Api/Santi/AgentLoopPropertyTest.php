<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Product;
use App\Models\User;
use App\Services\Chatbot\AgentResult;
use App\Services\Chatbot\SantiAgentService;
use App\Services\Chatbot\ToolExecutor;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AgentLoopPropertyTest extends TestCase
{
    use RefreshDatabase;
    use WithSantiGenerators;

    private const PROPERTY_SEED = 9_200_2026;

    /**
     * Property 13a: max_tool_rounds (3) bounds the execution loop even if Gemini continuously requests tool calls.
     *
     * **Validates: Requirements 8.7, 9.2**
     */
    public function test_max_tool_rounds_bounds_execution_loop(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'is_active' => true]);
        $user = User::factory()->create();

        // Gemini mock that repeatedly requests check_stock tool call
        $toolCallResponse = [
            [
                'type' => 'function_call',
                'name' => 'check_stock',
                'args' => ['product_identifier' => (string) $product->id],
            ],
        ];

        $geminiMock = $this->createMock(GeminiService::class);
        $geminiMock->expects($this->exactly(4)) // Initial prompt + 3 tool rounds = 4 API calls max
            ->method('generateContent')
            ->willReturn($toolCallResponse);

        $agent = new SantiAgentService($geminiMock, app(ToolExecutor::class));

        $result = $agent->handle('Check stock continuously', $user, (string) Str::uuid());

        $this->assertNotEmpty($result->reply);
        $this->assertSame(AgentResult::RESULT_TYPE_SAFE_RETRY, $result->resultType);
    }

    /**
     * Property 13b: max_tool_calls (6) bounds the total cumulative tool calls across rounds.
     *
     * **Validates: Requirements 8.7, 9.3**
     */
    public function test_max_tool_calls_bounds_cumulative_tool_calls(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'is_active' => true]);
        $user = User::factory()->create();

        // Gemini mock returns 3 tool calls per response (3 in round 1, 3 in round 2 => total 6 reached)
        $multipleToolCallsResponse = [
            [
                'type' => 'function_call',
                'name' => 'check_stock',
                'args' => ['product_identifier' => (string) $product->id],
            ],
            [
                'type' => 'function_call',
                'name' => 'search_products',
                'args' => ['query' => 'mouse'],
            ],
            [
                'type' => 'function_call',
                'name' => 'check_stock',
                'args' => ['product_identifier' => (string) $product->id],
            ],
        ];

        // 1. Strict assertion on Gemini API call count:
        // Round 1 (3 calls) -> Round 2 (3 calls, total=6) -> Round 3 (returns 3 calls, but total 6+3 > 6 triggers cutoff at most 3 API calls)
        $geminiMock = $this->createMock(GeminiService::class);
        $geminiMock->expects($this->atMost(3))
            ->method('generateContent')
            ->willReturn($multipleToolCallsResponse);

        // 2. Track real ToolExecutor::execute invocations via Log listener
        $executedToolCallsCount = 0;
        Log::listen(static function (MessageLogged $event) use (&$executedToolCallsCount): void {
            if ($event->message === 'santi.tool_execution') {
                $executedToolCallsCount++;
            }
        });

        $agent = new SantiAgentService($geminiMock, app(ToolExecutor::class));

        $result = $agent->handle('Check multiple tools', $user, (string) Str::uuid());

        // Hard assertions verifying mathematical boundary enforcement
        $this->assertNotEmpty($result->reply);
        $this->assertSame(AgentResult::RESULT_TYPE_SAFE_RETRY, $result->resultType);
        $this->assertLessThanOrEqual(6, $executedToolCallsCount, 'Executed tool calls must NEVER exceed the max_tool_calls limit (6).');
        $this->assertSame(6, $executedToolCallsCount, 'Exactly 6 tool calls must be executed before reaching the max_tool_calls limit.');
    }
}
