<?php

declare(strict_types=1);

namespace InfilePhp\Core\Exceptions;

/**
 * Thrown when the DTE fails local validation before being sent to Infile.
 */
class DteValidationException extends InfilePhpException
{
    public function __construct(
        string $message,
        public readonly string $field,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
