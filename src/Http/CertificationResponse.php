<?php

declare(strict_types=1);

namespace InfilePhp\Core\Http;

/**
 * Immutable response from the Infile certification API.
 */
final readonly class CertificationResponse
{
    /**
     * @param string $uuid                The SAT-issued UUID for the certified DTE
     * @param string $serie               Serie assigned by Infile
     * @param string $numero              Document number assigned by Infile
     * @param string $xmlCertified        Base64-encoded certified XML
     * @param int    $remainingCreditsVal Remaining API credits for today
     * @param string $issuedAt            ISO-8601 datetime of certification (from API 'fecha' field)
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $serie,
        public readonly string $numero,
        public readonly string $xmlCertified,
        private readonly int $remainingCreditsVal,
        public readonly string $issuedAt = '',
    ) {
    }

    /**
     * Return remaining API credits for today.
     * Expose this so consumers can react before hitting the daily limit.
     */
    public function remainingCredits(): int
    {
        return $this->remainingCreditsVal;
    }
}
