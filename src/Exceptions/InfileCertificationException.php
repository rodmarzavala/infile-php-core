<?php

declare(strict_types=1);

namespace InfilePhp\Core\Exceptions;

/**
 * Thrown when Infile returns an error during DTE certification or signing.
 */
final class InfileCertificationException extends InfileException
{
    public function __construct(
        string $message,
        int $statusCode = 0,
        public readonly ?string $infileCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
