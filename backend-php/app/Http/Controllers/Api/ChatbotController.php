<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Chatbot\AgentResult;
use App\Services\Chatbot\SantiAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ChatbotController extends Controller
{
    public function __construct(
        private readonly SantiAgentService $santiAgent,
    ) {
    }

    /**
     * Processes a chat message through the Santi agent.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:500',
            'draft_request_id' => 'nullable|uuid',
        ]);
        $correlationId = (string) Str::uuid();

        try {
            $result = $this->santiAgent->handle(
                $validated['message'],
                $request->user(),
                $validated['draft_request_id'] ?? null,
            );
        } catch (Throwable $exception) {
            Log::error('santi.agent.unexpected_failure', [
                'correlation_id' => $correlationId,
                'outcome' => 'UNEXPECTED_ERROR',
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'reply' => 'Perdona, tuve un problema técnico. ¿Lo intentamos de nuevo?',
                'result_type' => AgentResult::RESULT_TYPE_SAFE_RETRY,
            ], 500);
        }

        return response()->json($result->toArray());
    }
}
