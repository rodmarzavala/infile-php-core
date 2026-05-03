<?php

declare(strict_types=1);

namespace InfilePhp\Core\Exceptions;

/**
 * Thrown when the provided NIT (tax ID) is invalid or not registered in the RTU.
 */
final class InvalidTaxIdException extends DteValidationException
{
    public function __construct(
        public readonly string $taxId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: "The tax ID '{$taxId}' is invalid or not registered in the SAT RTU.",
            field: 'taxId',
            previous: $previous,
        );
    }
}
