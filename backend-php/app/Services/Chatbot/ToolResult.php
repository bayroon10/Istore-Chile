<?php

namespace App\Services\Chatbot;

use LogicException;

/**
 * Bounded payload returned from a tool to the function-calling protocol.
 */
final readonly class ToolResult
{
    /** @param array<string, mixed> $data */
    private function __construct(
        private bool $successful,
        private array $data = [],
        private string $errorCode = '',
        private string $message = '',
        private ?array $responseSchema = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function ok(array $data): self
    {
        return new self(successful: true, data: $data);
    }

    public static function error(string $code, string $message): self
    {
        return new self(
            successful: false,
            errorCode: $code,
            message: $message,
        );
    }

    /** @param array<string, mixed> $schema */
    public function withResponseSchema(array $schema): self
    {
        return new self(
            successful: $this->successful,
            data: $this->data,
            errorCode: $this->errorCode,
            message: $this->message,
            responseSchema: $schema,
        );
    }

    /**
     * @param array<string, mixed>|null $schema
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, error_code: string, message: string}
     */
    public function toFunctionResponse(?array $schema = null): array
    {
        if (! $this->successful) {
            return [
                'ok' => false,
                'error_code' => $this->errorCode,
                'message' => $this->message,
            ];
        }

        $schema ??= $this->responseSchema;

        if ($schema === null) {
            throw new LogicException('A response schema is required for successful tool results.');
        }

        $valid = false;
        $data = $this->filterValue($this->data, $schema, $valid);

        return [
            'ok' => true,
            'data' => $valid && is_array($data) ? $data : [],
        ];
    }

    /** @param array<string, mixed> $schema */
    private function filterValue(mixed $value, array $schema, bool &$valid): mixed
    {
        $types = $schema['type'] ?? null;
        $types = is_string($types) ? [$types] : $types;

        if (! is_array($types) || $types === []) {
            $valid = false;

            return null;
        }

        if ($value === null) {
            $valid = in_array('null', $types, true);

            return null;
        }

        if (in_array('object', $types, true)) {
            return $this->filterObject($value, $schema, $valid);
        }

        if (in_array('array', $types, true)) {
            return $this->filterArray($value, $schema, $valid);
        }

        $valid = (in_array('string', $types, true) && is_string($value))
            || (in_array('integer', $types, true) && is_int($value))
            || (in_array('number', $types, true) && (is_int($value) || is_float($value)))
            || (in_array('boolean', $types, true) && is_bool($value));

        return $value;
    }

    /** @param array<string, mixed> $schema */
    private function filterObject(mixed $value, array $schema, bool &$valid): array
    {
        $properties = $schema['properties'] ?? null;

        if (! is_array($value) || ! is_array($properties) || ($value !== [] && array_is_list($value))) {
            $valid = false;

            return [];
        }

        $filtered = [];

        foreach ($properties as $field => $fieldSchema) {
            if (! is_string($field) || ! is_array($fieldSchema) || ! array_key_exists($field, $value)) {
                continue;
            }

            $fieldValid = false;
            $filteredValue = $this->filterValue($value[$field], $fieldSchema, $fieldValid);

            if ($fieldValid) {
                $filtered[$field] = $filteredValue;
            }
        }

        $valid = true;

        return $filtered;
    }

    /** @param array<string, mixed> $schema */
    private function filterArray(mixed $value, array $schema, bool &$valid): array
    {
        $itemSchema = $schema['items'] ?? null;

        if (! is_array($value) || ! array_is_list($value) || ! is_array($itemSchema)) {
            $valid = false;

            return [];
        }

        $filtered = [];

        foreach ($value as $item) {
            $itemValid = false;
            $filteredItem = $this->filterValue($item, $itemSchema, $itemValid);

            if ($itemValid) {
                $filtered[] = $filteredItem;
            }
        }

        $valid = true;

        return $filtered;
    }
}
