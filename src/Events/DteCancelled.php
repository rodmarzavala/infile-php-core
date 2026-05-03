<?php

declare(strict_types=1);

namespace InfilePhp\Core\Events;

use InfilePhp\Core\Enums\DteType;

/**
 * Fired after a DTE is successfully cancelled with Infile.
 */
final readonly class DteCancelled
{
    public function __construct(
        public readonly string $uuid,
        public readonly DteType $dteType,
        public readonly string $reason,
    ) {
    }
}
