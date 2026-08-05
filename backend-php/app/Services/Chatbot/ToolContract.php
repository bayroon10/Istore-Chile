<?php

namespace App\Services\Chatbot;

interface ToolContract
{
    public function name(): string;

    /** @return array<string, mixed> */
    public function declaration(): array;

    /** @return array<string, mixed> */
    public function responseSchema(): array;

    /** @return array<string, string|array> */
    public function rules(): array;

    public function requiresAuth(): bool;

    /** @param array<string, mixed> $args */
    public function handle(array $args, ToolContext $ctx): ToolResult;
}
