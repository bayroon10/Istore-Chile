<?php

namespace App\Services\Chatbot;

use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Support\Str;
use Throwable;

final class SantiAgentService
{
    private const SAFE_RETRY_REPLY = 'Perdona, no pude completar la solicitud en este momento. ¿Lo intentamos de nuevo?';

    private const SYSTEM_INSTRUCTION = <<<'TEXT'
Eres Santi, el asistente de iStore Chile. Responde de forma concisa, profesional y en español chileno.

Para cualquier dato de catálogo —producto, categoría, precio o stock— usa exclusivamente los resultados exitosos de las herramientas. No inventes datos ni asumas que tienes un catálogo precargado.

Una propuesta o borrador no es una compra, pago ni confirmación: requiere confirmación humana posterior. Nunca confirmes pagos, cobros, cancelaciones, reembolsos, despachos ni cambios de órdenes.

Entrega como máximo tres párrafos y comunica los errores de herramientas sin afirmar que una operación se realizó.
TEXT;

    /** @var list<string> */
    private const LEGACY_ERROR_RESPONSES = [
        '¡Hola! Estoy en modo de mantenimiento por ahora. ¿En qué puedo ayudarte?',
        'Parece que mi cerebro está un poco lento hoy. ¡Inténtalo de nuevo en un momento!',
        'No pude procesar tu solicitud, pero estoy aprendiendo rápido. ¿Puedes repetir eso?',
        '¡Ups! Algo salió mal en mi red neuronal. Vuelve a intentarlo pronto.',
    ];

    public function __construct(
        private readonly GeminiService $gemini,
        private readonly ToolExecutor $executor,
    ) {
    }

    public function handle(string $message, ?User $user, ?string $draftRequestId): AgentResult
    {
        $correlationId = (string) Str::uuid();
        $resolvedDraftRequestId = $this->resolveDraftRequestId($draftRequestId);

        try {
            if (! (bool) config('santi.function_calling_enabled', true)) {
                return $this->handleLegacy($message, $resolvedDraftRequestId);
            }

            return $this->handleFunctionCalling(
                $message,
                new ToolContext($user, $correlationId, $resolvedDraftRequestId),
            );
        } catch (Throwable) {
            return $this->safeRetry($resolvedDraftRequestId);
        }
    }

    private function handleLegacy(string $message, string $draftRequestId): AgentResult
    {
        $reply = $this->usableReply($this->gemini->generateResponse($this->promptFor($message)));

        if ($reply === null || in_array($reply, self::LEGACY_ERROR_RESPONSES, true)) {
            return $this->safeRetry($draftRequestId);
        }

        return new AgentResult($reply, AgentResult::RESULT_TYPE_OK, draftRequestId: $draftRequestId);
    }

    private function handleFunctionCalling(string $message, ToolContext $context): AgentResult
    {
        $contents = [[
            'role' => 'user',
            'parts' => [['text' => $this->promptFor($message)]],
        ]];
        $maxRounds = max(0, (int) config('santi.max_tool_rounds', 3));
        $maxCalls = max(0, (int) config('santi.max_tool_calls', 6));
        $rounds = 0;
        $calls = 0;
        $draft = null;

        while (true) {
            $parts = $this->gemini->generateContent(
                $contents,
                $this->executor->declarations(),
                ['function_calling_config' => ['mode' => 'AUTO']],
            );

            $response = $this->parseModelResponse($parts);
            if ($response === null) {
                return $this->safeRetry($context->draftRequestId);
            }

            if ($response['calls'] === []) {
                $reply = $this->usableReply(implode("\n", $response['texts']));

                return $reply === null
                    ? $this->safeRetry($context->draftRequestId)
                    : new AgentResult($reply, AgentResult::RESULT_TYPE_OK, $draft, $context->draftRequestId);
            }

            if ($rounds >= $maxRounds || $calls + count($response['calls']) > $maxCalls) {
                return $this->safeRetry($context->draftRequestId);
            }

            $rounds++;
            $modelCallParts = [];
            $functionResponseParts = [];

            foreach ($response['calls'] as $call) {
                $functionResponse = $this->executeToolCall($call, $context);
                if ($functionResponse === null) {
                    return $this->safeRetry($context->draftRequestId);
                }

                if (($functionResponse['error_code'] ?? null) === 'DEPENDENCY_ERROR') {
                    return $this->safeRetry($context->draftRequestId);
                }

                if ($call['name'] === 'create_draft_order' && ($functionResponse['ok'] ?? false) === true) {
                    $draft = $functionResponse['data'];
                }

                $calls++;
                $modelCallParts[] = [
                    'functionCall' => [
                        'name' => $call['name'],
                        'args' => $this->withoutSensitiveIds($call['args']),
                    ],
                ];
                $functionResponseParts[] = [
                    'functionResponse' => [
                        'name' => $call['name'],
                        'response' => $functionResponse,
                    ],
                ];
            }

            $contents[] = ['role' => 'model', 'parts' => $modelCallParts];
            $contents[] = ['role' => 'function', 'parts' => $functionResponseParts];
        }
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>|null
     */
    private function executeToolCall(array $call, ToolContext $context): ?array
    {
        try {
            $response = $this->executor
                ->execute($call['name'], $call['args'], $context)
                ->toFunctionResponse();
        } catch (Throwable) {
            return null;
        }

        if (! is_array($response) || ! array_key_exists('ok', $response) || ! is_bool($response['ok'])) {
            return null;
        }

        if ($response['ok'] === true) {
            return isset($response['data']) && is_array($response['data']) ? $response : null;
        }

        return isset($response['error_code'], $response['message'])
            && is_string($response['error_code'])
            && is_string($response['message'])
            ? $response
            : null;
    }

    /**
     * @param mixed $parts
     * @return array{calls: list<array{name: string, args: array<string, mixed>}>, texts: list<string>}|null
     */
    private function parseModelResponse(mixed $parts): ?array
    {
        if (! is_array($parts) || ! array_is_list($parts) || $parts === [] || array_key_exists('error', $parts)) {
            return null;
        }

        $calls = [];
        $texts = [];

        foreach ($parts as $part) {
            if (! is_array($part) || ! isset($part['type']) || ! is_string($part['type'])) {
                return null;
            }

            if ($part['type'] === 'text' && isset($part['text']) && is_string($part['text'])) {
                $texts[] = $part['text'];
                continue;
            }

            if (
                $part['type'] === 'function_call'
                && isset($part['name'])
                && is_string($part['name'])
                && $part['name'] !== ''
                && isset($part['args'])
                && is_array($part['args'])
            ) {
                $calls[] = ['name' => $part['name'], 'args' => $part['args']];
                continue;
            }

            return null;
        }

        return ['calls' => $calls, 'texts' => $texts];
    }

    private function promptFor(string $message): string
    {
        return self::SYSTEM_INSTRUCTION."\n\nMensaje del cliente:\n".$message;
    }

    private function resolveDraftRequestId(?string $draftRequestId): string
    {
        return $draftRequestId !== null && Str::isUuid($draftRequestId)
            ? $draftRequestId
            : (string) Str::uuid();
    }

    private function safeRetry(string $draftRequestId): AgentResult
    {
        return new AgentResult(self::SAFE_RETRY_REPLY, AgentResult::RESULT_TYPE_SAFE_RETRY, draftRequestId: $draftRequestId);
    }

    private function usableReply(string $reply): ?string
    {
        $paragraphs = preg_split('/(?:\R\s*){2,}/u', trim($reply), -1, PREG_SPLIT_NO_EMPTY);
        $reply = implode("\n\n", array_slice(array_map('trim', $paragraphs ?: []), 0, 3));

        return $reply === '' ? null : $reply;
    }

    /** @param array<string, mixed> $args */
    private function withoutSensitiveIds(array $args): array
    {
        $sanitized = [];

        foreach ($args as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), ['draft_request_id', 'correlation_id'], true)) {
                continue;
            }

            $sanitized[$key] = is_array($value) ? $this->withoutSensitiveIds($value) : $value;
        }

        return $sanitized;
    }
}
