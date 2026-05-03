<?php

declare(strict_types=1);

namespace InfilePhp\Core\Contracts;

use InfilePhp\Core\Enums\DteType;
use InfilePhp\Core\Http\CertificationResponse;

/**
 * Contract that all DTE (Documento Tributario Electrónico) types must implement.
 */
interface DteContract
{
    /**
     * Issue the DTE: validate → generate idempotency key → sign → certify → fire event.
     *
     * Returns the CertificationResponse containing uuid, serie, numero, and the
     * certified XML. This is the primary way to access the issued document data.
     */
    public function issue(): CertificationResponse;

    /**
     * Cancel a previously issued DTE.
     *
     * @return static
     */
    public function cancel(string $reason): static;

    /**
     * Validate the DTE structure against its XSD schema without consuming a transaction.
     *
     * @return static
     */
    public function validate(): static;

    /**
     * Return the DTE type for this document.
     */
    public function getType(): DteType;

    /**
     * Return the recipient of this DTE, or null if not yet set.
     */
    public function getRecipient(): ?\InfilePhp\Core\Dte\Recipient;

    /**
     * Return the line items for this DTE.
     *
     * @return \InfilePhp\Core\Dte\Item[]
     */
    public function getItems(): array;
}
