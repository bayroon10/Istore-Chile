<?php

namespace Tests\Unit\Services\Chatbot;

use App\Services\Chatbot\AgentResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AgentResultTest extends TestCase
{
    /** Validates: Requirements 7.2, 9.1 */
    public function test_it_serializes_a_normal_reply_without_optional_fields(): void
    {
        $result = new AgentResult('Hola, ¿en qué te ayudo?', AgentResult::RESULT_TYPE_OK);

        $this->assertSame([
            'reply' => 'Hola, ¿en qué te ayudo?',
            'result_type' => 'OK',
        ], $result->toArray());
    }

    /** Validates: Requirements 7.2, 9.1 */
    public function test_it_serializes_safe_retry_with_draft_metadata(): void
    {
        $draft = [
            'order_number' => 'IST-20260514-0007',
            'subtotal' => 249980,
            'items_count' => 2,
            'expires_at' => '2026-05-16T18:00:00Z',
        ];

        $result = new AgentResult(
            'No pude completar la solicitud. ¿Lo intentamos de nuevo?',
            AgentResult::RESULT_TYPE_SAFE_RETRY,
            $draft,
            '9f1c6d03-5f85-4fc1-9ae8-d899d2f6318f',
        );

        $this->assertSame([
            'reply' => 'No pude completar la solicitud. ¿Lo intentamos de nuevo?',
            'result_type' => 'SAFE_RETRY',
            'draft' => $draft,
            'draft_request_id' => '9f1c6d03-5f85-4fc1-9ae8-d899d2f6318f',
        ], $result->toArray());
    }

    /** Validates: Requirements 8.2 */
    public function test_it_rejects_an_unsupported_result_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AgentResult('Respuesta', 'FAILED');
    }
}
