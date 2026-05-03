<?php

declare(strict_types=1);

namespace InfilePhp\Core\Sat;

/**
 * Immutable NIT lookup result from the SAT RTU.
 */
final readonly class TaxpayerData
{
    public function __construct(
        public readonly string $nit,
        public readonly string $name,
        public readonly string $message,
    ) {
    }
}
