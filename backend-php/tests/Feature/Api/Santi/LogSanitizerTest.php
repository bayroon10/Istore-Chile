<?php

namespace Tests\Feature\Api\Santi;

use App\Models\User;
use App\Services\Chatbot\SantiAgentService;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolExecutor;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LogSanitizerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that application logs never record sensitive user data (emails, phones, credentials) or raw prompts in plain text.
     *
     * **Validates: Requirements 10.1, 10.2, 10.5**
     */
    public function test_logs_never_contain_sensitive_user_data_or_plain_text_prompts(): void
    {
        $loggedContexts = [];

        Log::listen(static function (MessageLogged $event) use (&$loggedContexts): void {
            if (is_array($event->context) && ! empty($event->context)) {
                $loggedContexts[] = $event->context;
            }
        });

        $user = User::factory()->create([
            'email' => 'sensitive_client_99@labstock.cl',
        ]);

        $executor = app(ToolExecutor::class);
        $context = new ToolContext(
            user: $user,
            correlationId: (string) Str::uuid(),
            draftRequestId: (string) Str::uuid(),
        );

        $sensitiveMessage = 'Mi correo es sensitive_client_99@labstock.cl y mi clave es Pass1234! mi fono es +56912345678';

        // Test 1: Tool Execution Log
        $executor->execute('search_products', [
            'query' => 'Mouse',
            'sensitive_payload' => $sensitiveMessage,
        ], $context);

        // Test 2: Agent Handle Execution Log
        $geminiMock = $this->createMock(GeminiService::class);
        $geminiMock->method('generateContent')->willReturn([
            ['type' => 'text', 'text' => 'Hola, ¿en qué te puedo ayudar?'],
        ]);

        $agent = new SantiAgentService($geminiMock, $executor);
        $agent->handle($sensitiveMessage, $user, (string) Str::uuid());

        $this->assertNotEmpty($loggedContexts, 'Logs must record structured execution metrics.');

        foreach ($loggedContexts as $loggedContext) {
            $serialized = json_encode($loggedContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $this->assertStringNotContainsString(
                'sensitive_client_99@labstock.cl',
                $serialized,
                'User email must NEVER appear in plain text in log contexts.',
            );
            $this->assertStringNotContainsString(
                'Pass1234!',
                $serialized,
                'User credentials must NEVER appear in log contexts.',
            );
            $this->assertStringNotContainsString(
                '+56912345678',
                $serialized,
                'User phone number must NEVER appear in log contexts.',
            );
            $this->assertStringNotContainsString(
                'Mi correo es',
                $serialized,
                'Raw user prompt text must NEVER be stored in application logs.',
            );
        }
    }

    /**
     * Test that raw Gemini API response structures containing system instructions or keys are not dumped into logs.
     *
     * **Validates: Requirements 10.3, 10.5**
     */
    public function test_logs_do_not_contain_raw_gemini_system_instructions_or_keys(): void
    {
        $loggedMessages = [];

        Log::listen(static function (MessageLogged $event) use (&$loggedMessages): void {
            $loggedMessages[] = $event->message . ' ' . json_encode($event->context);
        });

        $user = User::factory()->create();

        $geminiMock = $this->createMock(GeminiService::class);
        $geminiMock->method('generateContent')->willReturn([
            ['type' => 'text', 'text' => 'Buscando productos...'],
        ]);

        $agent = new SantiAgentService($geminiMock, app(ToolExecutor::class));
        $agent->handle('Busca notebooks', $user, (string) Str::uuid());

        $allLogsCombined = implode("\n", $loggedMessages);

        $this->assertStringNotContainsString('SYSTEM_INSTRUCTION', $allLogsCombined);
        $this->assertStringNotContainsString('GEMINI_API_KEY', $allLogsCombined);
    }
}
