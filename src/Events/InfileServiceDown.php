<?php

declare(strict_types=1);

namespace InfilePhp\Core\Events;

/**
 * Fired when the Infile service becomes unreachable (first failure detected).
 */
final readonly class InfileServiceDown
{
    public function __construct(
        public readonly string $endpoint,
        public readonly string $errorMessage,
        public readonly \DateTimeImmutable $detectedAt,
    ) {
    }
}
