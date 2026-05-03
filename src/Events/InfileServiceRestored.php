<?php

declare(strict_types=1);

namespace InfilePhp\Core\Events;

/**
 * Fired when the Infile service becomes reachable again after a downtime period.
 */
final readonly class InfileServiceRestored
{
    public function __construct(
        public readonly string $endpoint,
        public readonly \DateTimeImmutable $restoredAt,
        public readonly int $downtimeSeconds,
    ) {
    }
}
