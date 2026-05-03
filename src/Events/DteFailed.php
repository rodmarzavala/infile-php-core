<?php

declare(strict_types=1);

namespace InfilePhp\Core\Events;

use InfilePhp\Core\Enums\DteType;

/**
 * Fired when a DTE certification attempt fails.
 */
final readonly class DteFailed
{
    public function __construct(
        public readonly DteType $dteType,
        public readonly ?string $idempotencyKey,
        public readonly string $errorMessage,
        public readonly ?\Throwable $previous,
    ) {
    }
}
