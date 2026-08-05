<?php

namespace Tests\Unit\Services\Chatbot;

use App\Models\User;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolContract;
use App\Services\Chatbot\ToolResult;
use App\Services\Chatbot\Tools\CheckStockTool;
use PHPUnit\Framework\TestCase;

final class ToolContractsTest extends TestCase
{
    /** Validates: Requirements 6.1, 8.1 */
    public function test_tool_contract_defines_the_required_execution_boundary(): void
    {
        $contract = new \ReflectionClass(ToolContract::class);

        $this->assertTrue($contract->isInterface());
        $this->assertSame(
            ['name', 'declaration', 'responseSchema', 'rules', 'requiresAuth', 'handle'],
            array_map(fn (\ReflectionMethod $method) => $method->getName(), $contract->getMethods()),
        );
        $this->assertSame(ToolResult::class, $contract->getMethod('handle')->getReturnType()?->getName());
    }

    /** Validates: Requirements 6.1 */
    public function test_tool_context_is_immutable_and_preserves_server_supplied_metadata(): void
    {
        $user = new User();
        $context = new ToolContext($user, 'correlation-id', 'draft-request-id');

        $this->assertTrue((new \ReflectionClass(ToolContext::class))->isReadOnly());
        $this->assertSame($user, $context->user);
        $this->assertSame('correlation-id', $context->correlationId);
        $this->assertSame('draft-request-id', $context->draftRequestId);
    }

    /** Validates: Requirements 6.7, 8.1 */
    public function test_tool_result_emits_only_the_bounded_success_or_error_response_shape(): void
    {
        $schema = (new CheckStockTool())->responseSchema();

        $this->assertSame(['ok' => true, 'data' => ['id' => 12]], ToolResult::ok(['id' => 12])->toFunctionResponse($schema));
        $this->assertSame(['ok' => false, 'error_code' => 'PRODUCT_NOT_FOUND', 'message' => 'Producto no encontrado.'], ToolResult::error('PRODUCT_NOT_FOUND', 'Producto no encontrado.')->toFunctionResponse($schema));
    }
}
