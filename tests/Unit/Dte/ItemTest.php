<?php

declare(strict_types=1);

use InfilePhp\Core\Dte\Item;

it('calculates the exact total without discount', function () {
    $item = Item::product('Test Product')->quantity(2.5)->unitPrice(100.00);

    expect($item->getDescription())->toBe('Test Product')
        ->and($item->getItemType())->toBe('product')
        ->and($item->getQuantity())->toBe(2.5)
        ->and($item->getUnitPrice())->toBe(100.00)
        ->and($item->getDiscount())->toBe(0.00)
        ->and($item->getTotal())->toBe(250.00); // 2.5 * 100
});

it('calculates the exact total with discount', function () {
    $item = Item::service('Test Service')
        ->quantity(1)
        ->unitPrice(150.00)
        ->discount(25.00);

    expect($item->getItemType())->toBe('service')
        ->and($item->getTotal())->toBe(125.00); // (1 * 150) - 25
});

it('rounds the total to two decimal places to avoid floating point drift', function () {
    // 3 * 33.333 = 99.999 => should round to 100.00
    $item = Item::product('Drift test')
        ->quantity(3)
        ->unitPrice(33.333);

    expect($item->getTotal())->toBe(100.00);
});
