<?php

declare(strict_types=1);

namespace InfilePhp\Core\Exceptions;

/**
 * Thrown when the DTE XML fails validation against the SAT XSD schema.
 */
final class XsdValidationException extends DteValidationException
{
    /**
     * @param string[] $errors List of XSD validation errors
     */
    public function __construct(
        public readonly array $errors,
        ?\Throwable $previous = null,
    ) {
        $summary = implode('; ', $errors);

        parent::__construct(
            message: "XSD validation failed: {$summary}",
            field: 'xml',
            previous: $previous,
        );
    }
}
