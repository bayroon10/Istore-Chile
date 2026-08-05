<?php

namespace Tests\Feature\Api;

use App\Services\GeminiService;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class ChatbotControllerTest extends TestCase
{
    /** Validates: Requirements 8.3, 8.4, 8.6, 8.8, 9.1 */
    public function test_it_delegates_a_valid_message_to_the_real_agent_and_preserves_the_response_contract(): void
    {
        config(['santi.function_calling_enabled' => true]);
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateContent')
            ->once()
            ->andReturn([['type' => 'text', 'text' => 'Claro, puedo ayudarte.']]);
        $gemini->shouldNotReceive('generateResponse');
        $this->app->instance(GeminiService::class, $gemini);

        $response = $this->postJson('/api/chatbot', [
            'message' => 'Necesito ayuda con un cargador.',
        ]);

        $response->assertOk()
            ->assertJson([
                'reply' => 'Claro, puedo ayudarte.',
                'result_type' => 'OK',
            ])
            ->assertJsonStructure(['reply', 'result_type', 'draft_request_id']);
        $this->assertTrue(Str::isUuid((string) $response->json('draft_request_id')));
    }

    /** Validates: Requirements 9.1 */
    public function test_it_returns_laravel_validation_errors_before_delegation(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldNotReceive('generateContent');
        $gemini->shouldNotReceive('generateResponse');
        $this->app->instance(GeminiService::class, $gemini);

        $missingMessage = $this->postJson('/api/chatbot', []);
        $malformedDraftRequestId = $this->postJson('/api/chatbot', [
            'message' => 'Hola Santi.',
            'draft_request_id' => 'not-a-uuid',
        ]);

        $missingMessage->assertUnprocessable()->assertJsonValidationErrors('message');
        $malformedDraftRequestId->assertUnprocessable()->assertJsonValidationErrors('draft_request_id');
    }

    /** Validates: Requirements 8.2, 9.1 */
    public function test_it_uses_the_service_legacy_fallback_when_function_calling_is_disabled(): void
    {
        config(['santi.function_calling_enabled' => false]);
        $prompts = [];
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateResponse')
            ->once()
            ->andReturnUsing(function (string $prompt) use (&$prompts): string {
                $prompts[] = $prompt;

                return 'Claro, ¿qué producto estás buscando?';
            });
        $gemini->shouldNotReceive('generateContent');
        $this->app->instance(GeminiService::class, $gemini);

        $response = $this->postJson('/api/chatbot', [
            'message' => 'Busco un cargador.',
        ]);

        $response->assertOk()
            ->assertJson([
                'reply' => 'Claro, ¿qué producto estás buscando?',
                'result_type' => 'OK',
            ])
            ->assertJsonStructure(['reply', 'result_type', 'draft_request_id']);
        $this->assertTrue(Str::isUuid((string) $response->json('draft_request_id')));
        $this->assertCount(1, $prompts);
        $this->assertStringContainsString('Santi', $prompts[0]);
        $this->assertStringNotContainsString('INVENTARIO REAL ACTUAL', $prompts[0]);
    }

    /** Validates: Requirements 3.11, 9.1 */
    public function test_the_controller_contains_no_legacy_catalog_or_direct_gemini_behavior(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Api/ChatbotController.php'));

        $this->assertNotFalse($controller);
        $this->assertStringContainsString('SantiAgentService', $controller);
        $this->assertStringNotContainsString('App\\Models\\Product', $controller);
        $this->assertStringNotContainsString('GeminiService', $controller);
        $this->assertStringNotContainsString('INVENTARIO REAL ACTUAL', $controller);
        $this->assertStringNotContainsString('systemPrompt', $controller);
    }
}
