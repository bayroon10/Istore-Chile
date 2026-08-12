<?php

namespace App\Services\Chatbot;

use App\Services\Chatbot\Tools\CheckStockTool;
use App\Services\Chatbot\Tools\CreateDraftOrderTool;
use App\Services\Chatbot\Tools\SearchProductsTool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

/**
 * Server-side trust boundary for all Gemini function calls.
 */
final class ToolExecutor
{
    /** @var list<string> */
    private const APPROVED_TOOL_NAMES = [
        'check_stock',
        'search_products',
        'create_draft_order',
    ];

    private const UNKNOWN_TOOL_MESSAGE = 'La acción solicitada no está disponible.';

    private const VALIDATION_ERROR_MESSAGE = 'Los datos de la solicitud no son válidos.';

    private const FORBIDDEN_OPERATION_MESSAGE = 'La operación solicitada no está permitida.';

    private const AUTH_REQUIRED_MESSAGE = 'Debes iniciar sesión para crear una propuesta.';

    private const DEPENDENCY_ERROR_MESSAGE = 'No fue posible completar la solicitud. Inténtalo nuevamente.';

    /** @var array<string, ToolContract> */
    private array $registry = [];

    /**
     * @param list<ToolContract>|null $tools
     */
    public function __construct(?array $tools = null)
    {
        $tools ??= [
            new CheckStockTool(),
            new SearchProductsTool(),
            new CreateDraftOrderTool(),
        ];

        foreach ($tools as $tool) {
            if (! $tool instanceof ToolContract) {
                throw new InvalidArgumentException('Tool registry entries must implement ToolContract.');
            }

            $name = $tool->name();
            if (! in_array($name, self::APPROVED_TOOL_NAMES, true) || isset($this->registry[$name])) {
                throw new InvalidArgumentException('Tool registry must contain each approved tool exactly once.');
            }

            $this->registry[$name] = $tool;
        }

        if (array_keys($this->registry) !== self::APPROVED_TOOL_NAMES) {
            throw new InvalidArgumentException('Tool registry must contain each approved tool exactly once.');
        }
    }

    /** @return list<array{function_declarations: list<array<string, mixed>>}> */
    public function declarations(): array
    {
        return [[
            'function_declarations' => array_values(array_map(
                fn (ToolContract $tool): array => $tool->declaration(),
                $this->registry,
            )),
        ]];
    }

    /**
     * @param array<string, mixed> $args
     */
    public function execute(string $name, array $args, ToolContext $ctx): ToolResult
    {
        $startedAt = hrtime(true);
        $tool = $this->registry[$name] ?? null;

        // Do not record arbitrary model-supplied names in application logs.
        if ($tool === null) {
            $code = $this->isForbiddenToolName($name) ? 'FORBIDDEN_OPERATION' : 'UNKNOWN_TOOL';
            $message = $code === 'FORBIDDEN_OPERATION'
                ? self::FORBIDDEN_OPERATION_MESSAGE
                : self::UNKNOWN_TOOL_MESSAGE;

            return $this->complete($ctx, 'unapproved', ToolResult::error($code, $message), $startedAt, $code);
        }

        $schema = $tool->declaration()['parameters'] ?? [];
        if (! is_array($schema) || $this->hasUndeclaredArguments($args, $schema)) {
            return $this->complete(
                $ctx,
                $tool->name(),
                ToolResult::error('VALIDATION_ERROR', self::VALIDATION_ERROR_MESSAGE),
                $startedAt,
                'VALIDATION_ERROR',
            );
        }

        if ($this->containsDangerousContent($args)) {
            return $this->complete(
                $ctx,
                $tool->name(),
                ToolResult::error('VALIDATION_ERROR', self::VALIDATION_ERROR_MESSAGE),
                $startedAt,
                'DANGEROUS_CONTENT',
            );
        }

        if (Validator::make($args, $tool->rules())->fails()) {
            return $this->complete(
                $ctx,
                $tool->name(),
                ToolResult::error('VALIDATION_ERROR', self::VALIDATION_ERROR_MESSAGE),
                $startedAt,
                'VALIDATION_ERROR',
            );
        }

        if ($tool->requiresAuth() && $ctx->user === null) {
            return $this->complete(
                $ctx,
                $tool->name(),
                ToolResult::error('AUTH_REQUIRED', self::AUTH_REQUIRED_MESSAGE),
                $startedAt,
                'AUTH_REQUIRED',
            );
        }

        try {
            $result = $tool->handle($args, $ctx);
        } catch (Throwable) {
            return $this->complete(
                $ctx,
                $tool->name(),
                ToolResult::error('DEPENDENCY_ERROR', self::DEPENDENCY_ERROR_MESSAGE),
                $startedAt,
                'DEPENDENCY_ERROR',
            );
        }

        $responseSchema = $tool->responseSchema();
        $response = $result->toFunctionResponse($responseSchema);
        $outcome = $response['ok']
            ? 'SUCCESS'
            : (string) $response['error_code'];

        return $this->complete(
            $ctx,
            $tool->name(),
            $result->withResponseSchema($responseSchema),
            $startedAt,
            $outcome,
        );
    }

    /**
     * Rejects extra keys at every object level declared by the function schema.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $schema
     */
    private function hasUndeclaredArguments(array $args, array $schema): bool
    {
        return $this->containsUndeclaredArgument($args, $schema);
    }

    /**
     * @param mixed $value
     * @param array<string, mixed> $schema
     */
    private function containsUndeclaredArgument(mixed $value, array $schema): bool
    {
        if (! is_array($value)) {
            return false;
        }

        $type = $schema['type'] ?? null;
        if ($type === 'array') {
            if (! array_is_list($value)) {
                return true;
            }

            $itemSchema = $schema['items'] ?? null;
            if (! is_array($itemSchema)) {
                return false;
            }

            foreach ($value as $item) {
                if ($this->containsUndeclaredArgument($item, $itemSchema)) {
                    return true;
                }
            }

            return false;
        }

        if ($type !== 'object') {
            return false;
        }

        $properties = $schema['properties'] ?? [];
        if (! is_array($properties)) {
            return true;
        }

        foreach ($value as $key => $item) {
            if (! is_string($key) || ! array_key_exists($key, $properties) || ! is_array($properties[$key])) {
                return true;
            }

            if ($this->containsUndeclaredArgument($item, $properties[$key])) {
                return true;
            }
        }

        return false;
    }

    private function containsDangerousContent(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->containsDangerousContent($item)) {
                    return true;
                }
            }

            return false;
        }

        return is_string($value) && $this->isDangerousString($value);
    }

    private function isDangerousString(string $value): bool
    {
        return preg_match('/<\s*\/?\s*script\b|<\?(?:php|=)?|\bjavascript\s*:/i', $value) === 1
            || preg_match('/\b(?:https?|file):\/\/|\bdata\s*:/i', $value) === 1
            || preg_match(
                '/\b(?:select\s+.+\s+from|insert\s+into|update\s+\S+\s+set|delete\s+from|drop\s+(?:table|database|schema)|alter\s+table|truncate\s+table|create\s+(?:table|database)|grant\s+.+\s+on|revoke\s+.+\s+on|(?:exec|execute)\s*\()/i',
                $value,
            ) === 1;
    }

    private function isForbiddenToolName(string $name): bool
    {
        $normalized = strtolower(str_replace(['_', '-'], ' ', $name));

        return preg_match(
            '/\b(?:payment|pay|refund|cancel(?:lation)?|fulfill(?:ment)?|ship(?:ping)?|stripe|(?:update|modify|change)\s+order)\b/i',
            $normalized,
        ) === 1;
    }

    private function complete(
        ToolContext $ctx,
        string $toolName,
        ToolResult $result,
        int $startedAt,
        string $outcome,
    ): ToolResult {
        $durationMs = (int) floor((hrtime(true) - $startedAt) / 1_000_000);

        try {
            Log::info('santi.tool_execution', [
                'correlation_id' => $ctx->correlationId,
                'tool' => $toolName,
                'outcome' => $outcome,
                'duration_ms' => $durationMs,
            ]);
        } catch (Throwable) {
            // Logging cannot change the bounded outcome returned to the caller.
        }

        return $result;
    }
}
