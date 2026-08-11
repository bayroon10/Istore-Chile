<?php

namespace Tests\Feature\Services;

use App\Services\GeminiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    public function test_generate_content_normalizes_text_and_function_calls(): void
    {
        config([
            'services.gemini.api_key' => 'testing-api-key',
            'services.gemini.model' => 'gemini-test-model',
            'services.gemini.timeout' => 15,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [
                            ['text' => 'Hola'],
                            ['functionCall' => [
                                'name' => 'search_products',
                                'args' => ['query' => 'cable'],
                            ]],
                        ],
                    ],
                ]],
            ]),
        ]);

        $contents = [['role' => 'user', 'parts' => [['text' => 'Busca un cable']]]];
        $tools = [['functionDeclarations' => [['name' => 'search_products']]]];
        $toolConfig = ['functionCallingConfig' => ['mode' => 'AUTO']];

        $parts = app(GeminiService::class)->generateContent($contents, $tools, $toolConfig);

        $this->assertSame([
            ['type' => 'text', 'text' => 'Hola'],
            [
                'type' => 'function_call',
                'name' => 'search_products',
                'args' => ['query' => 'cable'],
            ],
        ], $parts);

        Http::assertSent(function (Request $request) use ($contents, $tools, $toolConfig): bool {
            return str_contains($request->url(), '/models/gemini-test-model:generateContent')
                && $request['contents'] === $contents
                && $request['tools'] === $tools
                && $request['toolConfig'] === $toolConfig;
        });
    }

    public function test_generate_content_returns_a_sanitized_dependency_error_for_failed_requests(): void
    {
        config(['services.gemini.api_key' => 'testing-api-key']);

        $responseBody = '{"error":"sensitive response body"}';
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response($responseBody, 503),
        ]);
        Log::spy();

        $result = app(GeminiService::class)->generateContent([
            ['role' => 'user', 'parts' => [['text' => 'private prompt']]],
        ]);

        $this->assertSame(['error' => 'DEPENDENCY_ERROR'], $result);
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Gemini API request failed.', [
                'status' => 503,
                'response_size' => strlen($responseBody),
                'response_body' => $responseBody,
            ]);
    }
}
