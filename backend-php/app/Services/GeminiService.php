<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent";

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
}
