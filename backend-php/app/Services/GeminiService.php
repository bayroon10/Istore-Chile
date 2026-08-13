<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent";

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    /**
     * Genera una respuesta de texto utilizando Gemini AI.
     *
     * @param string $prompt
     * @return string
     */
    public function generateResponse(string $prompt): string
    {
        if (!$this->apiKey) {
            Log::warning("Gemini API Key no configurada.");
            return "¡Hola! Estoy en modo de mantenimiento por ahora. ¿En qué puedo ayudarte?";
        }

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 1024,
                    ]
                ]);

            if ($response->failed()) {
                Log::error("Error de Gemini API: " . $response->body());
                return "Parece que mi cerebro está un poco lento hoy. ¡Inténtalo de nuevo en un momento!";
            }

            $data = $response->json();
            
            return $data['candidates'][0]['content']['parts'][0]['text'] 
                ?? "No pude procesar tu solicitud, pero estoy aprendiendo rápido. ¿Puedes repetir eso?";

        } catch (\Exception $e) {
            Log::error("Excepción en GeminiService: " . $e->getMessage());
            return "¡Ups! Algo salió mal en mi red neuronal. Vuelve a intentarlo pronto.";
        }
    }

    /**
     * Envía contenido estructurado a Gemini y normaliza las partes de la respuesta.
     *
     * @param array<int, array<string, mixed>> $contents
     * @param array<int, array<string, mixed>> $tools
     * @param array<string, mixed> $toolConfig
     * @return array<int, array<string, mixed>>|array{error: string}
     */
    public function generateContent(array $contents, array $tools = [], array $toolConfig = []): array
    {
        if (!$this->apiKey) {
            Log::error('Gemini API request failed.', [
                'status' => null,
                'response_size' => 0,
            ]);

            return ['error' => 'DEPENDENCY_ERROR'];
        }

        $payload = ['contents' => $contents];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        if ($toolConfig !== []) {
            $payload['toolConfig'] = $toolConfig;
        }

        $model = config('services.gemini.model');
        $timeout = (int) config('services.gemini.timeout');
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode((string) $model),
            rawurlencode($this->apiKey),
        );

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout($timeout)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::error('Gemini API request failed.', [
                    'status' => $response->status(),
                    'response_size' => strlen($response->body()),
                    'response_body' => $response->body(),
                ]);

                return ['error' => 'DEPENDENCY_ERROR'];
            }

            $parts = $response->json('candidates.0.content.parts', []);

            if (!is_array($parts)) {
                return [];
            }

            return array_values(array_filter(array_map(function (array $part): ?array {
                if (array_key_exists('text', $part)) {
                    return [
                        'type' => 'text',
                        'text' => (string) $part['text'],
                    ];
                }

                if (isset($part['functionCall']) && is_array($part['functionCall'])) {
                    return [
                        'type' => 'function_call',
                        'name' => (string) ($part['functionCall']['name'] ?? ''),
                        'args' => (array) ($part['functionCall']['args'] ?? []),
                    ];
                }

                return null;
            }, $parts)));
        } catch (\Throwable $e) {
            Log::error('Gemini API request failed.', [
                'status' => null,
                'response_size' => 0,
                'exception_message' => $e->getMessage(),
            ]);

            return ['error' => 'DEPENDENCY_ERROR'];
        }
    }
}
