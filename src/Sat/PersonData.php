<?php

declare(strict_types=1);

namespace InfilePhp\Core\Sat;

/**
 * Immutable CUI lookup result from the SAT system.
 */
final readonly class PersonData
{
    public function __construct(
        public readonly string $cui,
        public readonly string $name,
        public readonly bool $deceased,
    ) {
    }
}
