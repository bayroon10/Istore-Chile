<?php

namespace App\Services\Chatbot\Exceptions;

use DomainException;
use InvalidArgumentException;

final class DraftUnavailableException extends DomainException
{
    public const PRODUCT_NOT_FOUND = 'PRODUCT_NOT_FOUND';
    public const PRODUCT_UNAVAILABLE = 'PRODUCT_UNAVAILABLE';
    public const INSUFFICIENT_STOCK = 'INSUFFICIENT_STOCK';

    private const MESSAGES = [
        self::PRODUCT_NOT_FOUND => 'Uno de los productos solicitados ya no está disponible.',
        self::PRODUCT_UNAVAILABLE => 'Uno de los productos solicitados no está disponible.',
        self::INSUFFICIENT_STOCK => 'No hay stock suficiente para completar la propuesta.',
    ];

    public function __construct(private readonly string $errorCode)
    {
        if (! array_key_exists($errorCode, self::MESSAGES)) {
            throw new InvalidArgumentException('Unsupported draft availability error code.');
        }

        parent::__construct(self::MESSAGES[$errorCode]);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
