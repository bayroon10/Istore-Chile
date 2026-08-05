<?php

namespace App\Services\Chatbot;

use App\Models\User;

/**
 * Trusted request metadata supplied exclusively by the server.
 */
final readonly class ToolContext
{
    public function __construct(
        public ?User $user,
        public string $correlationId,
        public string $draftRequestId,
    ) {
    }
}
