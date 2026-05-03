<?php

declare(strict_types=1);

namespace InfilePhp\Core\Exceptions;

/**
 * Errors returned by the Infile API (sign, certify, cancel).
 */
class InfileException extends InfilePhpException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
