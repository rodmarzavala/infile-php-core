<?php

declare(strict_types=1);

namespace InfilePhp\Core\Events;

use InfilePhp\Core\Enums\DteType;

/**
 * Fired when the contingency (CAFE) mode is activated because Infile is unreachable.
 */
final readonly class FallbackActivated
{
    public function __construct(
        public readonly DteType $dteType,
        public readonly string $idempotencyKey,
        public readonly string $cafe,
        public readonly string $reason,
    ) {
    }
}
