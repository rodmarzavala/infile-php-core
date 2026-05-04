<?php

declare(strict_types=1);

namespace InfilePhp\Core\Events;

use InfilePhp\Core\Enums\DteType;

/**
 * Fired after a DTE is successfully certified by Infile.
 */
final readonly class DteIssued
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $serie,
        public readonly string $numero,
        public readonly DteType $dteType,
        public readonly string $recipientTaxId,
        public readonly string $idempotencyKey,
        public readonly string $xmlCertified = '',
    ) {
    }
}
