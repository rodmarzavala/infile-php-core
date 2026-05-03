<?php

declare(strict_types=1);

namespace InfilePhp\Core\Support;

use Ramsey\Uuid\Uuid;

/**
 * Generates a RFC 4122 UUID4 idempotency key per DTE.
 *
 * The generated key must be persisted before the first HTTP call to Infile.
 * Sending the same key within 24 hours returns the original document instead
 * of creating a duplicate — this IS the retry/idempotency system.
 */
final class IdempotencyKey
{
    /**
     * Generate a new UUID4 idempotency key.
     */
    public static function generate(): string
    {
        return Uuid::uuid4()->toString();
    }
}
