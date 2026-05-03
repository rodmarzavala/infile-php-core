<?php

declare(strict_types=1);

namespace InfilePhp\Core\Exceptions;

/**
 * Thrown when the Infile service is unreachable after all retries are exhausted.
 */
final class InfileServiceUnavailableException extends InfileException
{
    public function __construct(
        string $message,
        public readonly string $endpoint,
        int $statusCode = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
