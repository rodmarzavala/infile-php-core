<?php

declare(strict_types=1);

namespace InfilePhp\Core\Dte;

/**
 * Represents a DTE recipient (buyer).
 *
 * @example
 *   Recipient::withTaxId('12345678')->name('ACME Corp')->address('Guatemala City')
 *   Recipient::finalConsumer()
 */
final class Recipient
{
    private string $taxId;

    private string $name;

    private string $address;

    private bool $isFinalConsumer;

    private function __construct()
    {
        $this->taxId = 'CF';
        $this->name = 'Consumidor Final';
        $this->address = 'Ciudad';
        $this->isFinalConsumer = false;
    }

    /**
     * Create a recipient identified by a specific NIT (tax ID).
     */
    public static function withTaxId(string $taxId): self
    {
        $instance = new self();
        $instance->taxId = $taxId;
        $instance->isFinalConsumer = false;

        return $instance;
    }

    /**
     * Create a final consumer recipient (no NIT required).
     * The NIT will be set to "CF" as required by SAT Guatemala.
     */
    public static function finalConsumer(): self
    {
        $instance = new self();
        $instance->taxId = 'CF';
        $instance->name = 'Consumidor Final';
        $instance->isFinalConsumer = true;

        return $instance;
    }

    /**
     * Set the recipient display name.
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the recipient address.
     */
    public function address(string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getTaxId(): string
    {
        return $this->taxId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function isFinalConsumer(): bool
    {
        return $this->isFinalConsumer;
    }
}
