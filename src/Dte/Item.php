<?php

declare(strict_types=1);

namespace InfilePhp\Core\Dte;

/**
 * Represents a line item in a DTE document.
 *
 * Quantities may be fractional (SAT FEL allows decimal quantities, e.g. 2.5 kg).
 * Unit prices are floats; they are rounded to 2 decimal places when serialized
 * to XML to avoid floating-point drift in tax calculations.
 *
 * @example
 *   Item::product('Widget Pro')->quantity(2)->unitPrice(8_500.00)
 *   Item::service('Consulting')->quantity(1)->unitPrice(500.00)
 *   Item::product('Rice (kg)')->quantity(1.5)->unitPrice(8.00)
 */
final class Item
{
    private string $description;

    private string $itemType;

    private float $quantity;

    private float $unitPrice;

    private float $discount;

    private function __construct(string $description, string $itemType)
    {
        $this->description = $description;
        $this->itemType    = $itemType;
        $this->quantity    = 1.0;
        $this->unitPrice   = 0.0;
        $this->discount    = 0.0;
    }

    /**
     * Create a product line item (BienOServicio = "B").
     */
    public static function product(string $description): self
    {
        return new self($description, 'product');
    }

    /**
     * Create a service line item (BienOServicio = "S").
     */
    public static function service(string $description): self
    {
        return new self($description, 'service');
    }

    /**
     * Set the quantity. SAT FEL allows fractional quantities (e.g. 1.5 kg).
     */
    public function quantity(float $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Set the unit price in GTQ.
     */
    public function unitPrice(float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    /**
     * Set the discount amount in GTQ.
     */
    public function discount(float $discount): self
    {
        $this->discount = $discount;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    /**
     * Compute the total line amount ((quantity × unitPrice) - discount), rounded to 2 decimal places.
     */
    public function getTotal(): float
    {
        return round(($this->quantity * $this->unitPrice) - $this->discount, 2);
    }
}
