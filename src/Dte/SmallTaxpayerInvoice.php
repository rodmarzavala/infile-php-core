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
 * FEL Small Taxpayer Invoice (FPEQ) — for taxpayers under the simplified tax regime.
 *
 * @example
 *   SmallTaxpayerInvoice::create()->forFinalConsumer()->add(Item::product('...')->unitPrice(50))->issue();
 */
final class SmallTaxpayerInvoice implements DteContract
{
    private ?Recipient $recipient = null;

    /** @var Item[] */
    private array $items = [];

    private ?string $idempotencyKey = null;

    private ?CertificationResponse $certificationResponse = null;

    private function __construct()
    {
    }

    /**
     * Create a new small taxpayer invoice builder.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set the recipient.
     */
    public function for(Recipient $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
    }

    /**
     * Set the recipient as a final consumer (CF).
     */
    public function forFinalConsumer(): self
    {
        $this->recipient = Recipient::finalConsumer();

        return $this;
    }

    /**
     * Add a line item.
     */
    public function add(Item $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Issue the small taxpayer invoice.
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
                recipientTaxId: $this->recipient?->getTaxId() ?? 'CF',
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
     * Cancel the invoice.
     */
    public function cancel(string $reason): static
    {
        if ($this->certificationResponse === null) {
            throw new InvalidDteStructureException(
                message: 'Cannot cancel an invoice that has not been issued.',
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
     * Validate the invoice structure.
     */
    public function validate(): static
    {
        if ($this->recipient === null) {
            throw new InvalidDteStructureException(
                message: 'A small taxpayer invoice requires a recipient.',
                field: 'recipient',
            );
        }

        if ($this->items === []) {
            throw new InvalidDteStructureException(
                message: 'A small taxpayer invoice requires at least one line item.',
                field: 'items',
            );
        }

        return $this;
    }

    public function getType(): DteType
    {
        return DteType::SmallTaxpayer;
    }

    public function getRecipient(): ?Recipient
    {
        return $this->recipient;
    }

    /** @return Item[] */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }
}
