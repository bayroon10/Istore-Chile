<?php

namespace App\Services\Chatbot\Exceptions;

use DomainException;
use InvalidArgumentException;

final class DraftLimitException extends DomainException
{
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const ITEM_LIMIT_EXCEEDED = 'ITEM_LIMIT_EXCEEDED';
    public const SUBTOTAL_LIMIT_EXCEEDED = 'SUBTOTAL_LIMIT_EXCEEDED';

    private const MESSAGES = [
        self::VALIDATION_ERROR => 'Los productos solicitados no son válidos.',
        self::ITEM_LIMIT_EXCEEDED => 'La propuesta supera el máximo de productos permitidos.',
        self::SUBTOTAL_LIMIT_EXCEEDED => 'La propuesta supera el monto máximo permitido.',
    ];

    public function __construct(private readonly string $errorCode)
    {
        if (! array_key_exists($errorCode, self::MESSAGES)) {
            throw new InvalidArgumentException('Unsupported draft limit error code.');
        }

        parent::__construct(self::MESSAGES[$errorCode]);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
