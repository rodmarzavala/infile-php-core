<?php

declare(strict_types=1);

use InfilePhp\Core\Dte\Item;
use InfilePhp\Core\Dte\Recipient;
use InfilePhp\Core\Dte\SmallTaxpayerInvoice;
use InfilePhp\Core\Enums\DteType;
use InfilePhp\Core\Exceptions\InvalidDteStructureException;

it('returns DteType::SmallTaxpayer from getType', function () {
    expect(SmallTaxpayerInvoice::create()->getType())->toBe(DteType::SmallTaxpayer);
});

it('sets a named recipient via for()', function () {
    $recipient = Recipient::withTaxId('12345678')->name('Corner Store')->address('Quetzaltenango');
    $invoice = SmallTaxpayerInvoice::create()->for($recipient);

    expect($invoice->getRecipient()?->getTaxId())->toBe('12345678');
});

it('sets a final consumer via forFinalConsumer()', function () {
    $invoice = SmallTaxpayerInvoice::create()->forFinalConsumer();

    expect($invoice->getRecipient()?->isFinalConsumer())->toBeTrue();
});

it('accumulates multiple items', function () {
    $invoice = SmallTaxpayerInvoice::create()
        ->forFinalConsumer()
        ->add(Item::product('Tortillas')->quantity(10)->unitPrice(2.00))
        ->add(Item::product('Frijol')->quantity(5)->unitPrice(8.00));

    expect($invoice->getItems())->toHaveCount(2);
});

it('starts with no idempotency key before issuance', function () {
    $invoice = SmallTaxpayerInvoice::create()->forFinalConsumer();

    expect($invoice->getIdempotencyKey())->toBeNull();
});

it('throws when validating without a recipient', function () {
    $invoice = SmallTaxpayerInvoice::create()
        ->add(Item::product('Item')->quantity(1)->unitPrice(50.00));

    expect(fn () => $invoice->validate())
        ->toThrow(InvalidDteStructureException::class, 'recipient');
});

it('throws when validating without items', function () {
    $invoice = SmallTaxpayerInvoice::create()->forFinalConsumer();

    expect(fn () => $invoice->validate())
        ->toThrow(InvalidDteStructureException::class, 'line item');
});

it('passes validation with recipient and items', function () {
    $invoice = SmallTaxpayerInvoice::create()
        ->forFinalConsumer()
        ->add(Item::product('Item')->quantity(1)->unitPrice(50.00));

    expect($invoice->validate())->toBe($invoice);
});

it('throws when trying to cancel an unissued small taxpayer invoice', function () {
    $invoice = SmallTaxpayerInvoice::create()
        ->forFinalConsumer()
        ->add(Item::product('Item')->quantity(1)->unitPrice(50.00));

    expect(fn () => $invoice->cancel('Corrección'))
        ->toThrow(InvalidDteStructureException::class, 'not been issued');
});
