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
 * Standard FEL invoice (FACT).
 *
 * @example
 *   $response = Invoice::create()
 *       ->for(Recipient::withTaxId('12345678')->name('ACME Corp')->address('Guatemala'))
 *       ->add(Item::product('Widget')->quantity(2)->unitPrice(100.00))
 *       ->issue();
 *
 *   echo $response->uuid();
 */
final class Invoice implements DteContract
{
    private ?Recipient $recipient = null;

    /** @var Item[] */
    private array $items = [];

    private ?string $idempotencyKey = null;

    /** Stored after a successful `issue()` call to support future `cancel()`. */
    private ?CertificationResponse $certificationResponse = null;

    private function __construct()
    {
    }

    /**
     * Create a new invoice builder.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set the recipient for this invoice.
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
     * Add a line item to the invoice.
     */
    public function add(Item $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Issue the invoice: validate → generate idempotency key → certify → fire event.
     *
     * Returns the CertificationResponse so callers can access uuid(), serie(), numero()
     * immediately without calling getters on this object.
     *
     * @throws InvalidDteStructureException
     * @throws \InfilePhp\Core\Exceptions\InfileAuthException
     * @throws \InfilePhp\Core\Exceptions\InfileCertificationException
     * @throws \InfilePhp\Core\Exceptions\DailyLimitExceededException
     * @throws \InfilePhp\Core\Exceptions\InfileServiceUnavailableException
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
                xmlCertified: $response->xmlCertified,
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
     * Cancel this invoice. Must have been issued first.
     *
     * @throws InvalidDteStructureException
     * @throws \InfilePhp\Core\Exceptions\InfileCertificationException
     * @throws \InfilePhp\Core\Exceptions\InfileServiceUnavailableException
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
     * Validate the DTE structure without consuming a transaction.
     *
     * @throws InvalidDteStructureException
     */
    public function validate(): static
    {
        if ($this->recipient === null) {
            throw new InvalidDteStructureException(
                message: 'An invoice requires a recipient. Call ->for() or ->forFinalConsumer().',
                field: 'recipient',
            );
        }

        if ($this->items === []) {
            throw new InvalidDteStructureException(
                message: 'An invoice requires at least one line item. Call ->add().',
                field: 'items',
            );
        }

        return $this;
    }

    public function getType(): DteType
    {
        return DteType::Invoice;
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

    /**
     * Compute the invoice grand total (sum of all line totals).
     */
    public function getTotal(): float
    {
        return round(array_sum(array_map(
            static fn (Item $item): float => $item->getTotal(),
            $this->items,
        )), 2);
    }
}
