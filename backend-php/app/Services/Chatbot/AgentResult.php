<?php

namespace App\Services\Chatbot;

use InvalidArgumentException;

/**
 * Customer-facing outcome produced by the Santi agent.
 */
final readonly class AgentResult
{
    public const RESULT_TYPE_OK = 'OK';

    public const RESULT_TYPE_SAFE_RETRY = 'SAFE_RETRY';

    /**
     * @param array<string, mixed>|null $draft
     */
    public function __construct(
        public string $reply,
        public string $resultType,
        public ?array $draft = null,
        public ?string $draftRequestId = null,
    ) {
        if (! in_array($this->resultType, [self::RESULT_TYPE_OK, self::RESULT_TYPE_SAFE_RETRY], true)) {
            throw new InvalidArgumentException('Unsupported agent result type.');
        }
    }

    /**
     * @return array{reply: string, result_type: 'OK'|'SAFE_RETRY', draft?: array<string, mixed>, draft_request_id?: string}
     */
    public function toArray(): array
    {
        $result = [
            'reply' => $this->reply,
            'result_type' => $this->resultType,
        ];

        if ($this->draft !== null) {
            $result['draft'] = $this->draft;
        }

        if ($this->draftRequestId !== null) {
            $result['draft_request_id'] = $this->draftRequestId;
        }

        return $result;
    }
}
