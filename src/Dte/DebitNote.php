<?php

declare(strict_types=1);

namespace InfilePhp\Core\Dte;

use InfilePhp\Core\Contracts\DteContract;
use InfilePhp\Core\Enums\DteType;
use InfilePhp\Core\Events\DteCancelled;
use InfilePhp\Core\Events\DteFailed;
use InfilePhp\Core\Events\DteIssued;
use InfilePhp\Core\Exceptions\InvalidDteStructureException;
use InfilePhp\Core\Http\CertificationResponse;
use InfilePhp\Core\InfilePhp;
use InfilePhp\Core\Support\IdempotencyKey;

/**
 * FEL Debit Note (NDEB) — issued to add charges or corrections to a previous invoice.
 *
 * @example
 *   DebitNote::create()->for($invoice)->reason('Additional shipping charges')->issue();
 */
final class DebitNote implements DteContract
{
    private ?Invoice $sourceInvoice = null;

    private ?string $reason = null;

    private ?string $idempotencyKey = null;

    private ?CertificationResponse $certificationResponse = null;

    private function __construct()
    {
    }

    /**
     * Create a new debit note builder.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Reference the original invoice.
     */
    public function for(Invoice $invoice): self
    {
        $this->sourceInvoice = $invoice;

        return $this;
    }

    /**
     * Set the reason for the debit note.
     */
    public function reason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Issue the debit note.
     */
    public function issue(): CertificationResponse
    {
        $this->validate();

        $this->idempotencyKey = IdempotencyKey::generate();

        try {
            $response = InfilePhp::client()->certify($this, $this->idempotencyKey);

            $this->certificationResponse = $response;

            InfilePhp::dispatcher()->dispatch(new DteIssued(
                uuid: $response->uuid,
                serie: $response->serie,
                numero: $response->numero,
                dteType: $this->getType(),
                recipientTaxId: $this->sourceInvoice?->getRecipient()?->getTaxId() ?? 'CF',
                idempotencyKey: $this->idempotencyKey,
            ));

            return $response;
        } catch (\Throwable $e) {
            InfilePhp::dispatcher()->dispatch(new DteFailed(
                dteType: $this->getType(),
                idempotencyKey: $this->idempotencyKey,
                errorMessage: $e->getMessage(),
                previous: $e,
            ));

            throw $e;
        }
    }

    /**
     * Cancel the debit note.
     */
    public function cancel(string $reason): static
    {
        if ($this->certificationResponse === null) {
            throw new InvalidDteStructureException(
                message: 'Cannot cancel a debit note that has not been issued.',
                field: 'uuid',
            );
        }

        InfilePhp::client()->cancel(
            uuid: $this->certificationResponse->uuid,
            dteType: $this->getType(),
            reason: $reason,
            issuedAt: $this->certificationResponse->issuedAt,
            idempotencyKey: $this->idempotencyKey ?? '',
        );

        InfilePhp::dispatcher()->dispatch(new DteCancelled(
            uuid: $this->certificationResponse->uuid,
            dteType: $this->getType(),
            reason: $reason,
        ));

        return $this;
    }

    /**
     * Validate the debit note structure.
     */
    public function validate(): static
    {
        if ($this->sourceInvoice === null) {
            throw new InvalidDteStructureException(
                message: 'A debit note requires a source invoice. Call ->for($invoice).',
                field: 'sourceInvoice',
            );
        }

        if ($this->sourceInvoice->getIdempotencyKey() === null) {
            throw new InvalidDteStructureException(
                message: 'The source invoice has not been issued yet.',
                field: 'sourceInvoice.uuid',
            );
        }

        if ($this->reason === null || $this->reason === '') {
            throw new InvalidDteStructureException(
                message: 'A debit note requires a reason. Call ->reason().',
                field: 'reason',
            );
        }

        return $this;
    }

    public function getType(): DteType
    {
        return DteType::DebitNote;
    }

    public function getSourceInvoice(): ?Invoice
    {
        return $this->sourceInvoice;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function getRecipient(): ?Recipient
    {
        return $this->sourceInvoice?->getRecipient();
    }

    /** @return Item[] */
    public function getItems(): array
    {
        return $this->sourceInvoice?->getItems() ?? [];
    }
}
