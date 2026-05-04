<?php

declare(strict_types=1);

use InfilePhp\Core\Dte\Invoice;
use InfilePhp\Core\Dte\Item;
use InfilePhp\Core\Dte\Recipient;
use InfilePhp\Core\Enums\DteType;
use InfilePhp\Core\Exceptions\InvalidDteStructureException;

it('creates an empty invoice builder via static constructor', function () {
    $invoice = Invoice::create();

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->getItems())->toBeEmpty()
        ->and($invoice->getRecipient())->toBeNull()
        ->and($invoice->getIdempotencyKey())->toBeNull();
});

it('returns DteType::Invoice from getType', function () {
    expect(Invoice::create()->getType())->toBe(DteType::Invoice);
});

it('sets a named recipient via for()', function () {
    $recipient = Recipient::withTaxId('12345678')->name('ACME')->address('GT');
    $invoice = Invoice::create()->for($recipient);

    expect($invoice->getRecipient())->toBe($recipient)
        ->and($invoice->getRecipient()?->getTaxId())->toBe('12345678');
});

it('sets final consumer via forFinalConsumer()', function () {
    $invoice = Invoice::create()->forFinalConsumer();

    expect($invoice->getRecipient()?->isFinalConsumer())->toBeTrue()
        ->and($invoice->getRecipient()?->getTaxId())->toBe('CF');
});

it('accumulates multiple items', function () {
    $invoice = Invoice::create()
        ->forFinalConsumer()
        ->add(Item::product('Widget')->quantity(2)->unitPrice(100.00))
        ->add(Item::service('Consulting')->quantity(1)->unitPrice(500.00));

    expect($invoice->getItems())->toHaveCount(2);
});

it('computes grand total as sum of all item totals', function () {
    $invoice = Invoice::create()
        ->forFinalConsumer()
        ->add(Item::product('A')->quantity(2)->unitPrice(100.00))   // 200
        ->add(Item::service('B')->quantity(3)->unitPrice(50.00));    // 150

    expect($invoice->getTotal())->toBe(350.00);
});

it('computes grand total with discount applied per item', function () {
    $invoice = Invoice::create()
        ->forFinalConsumer()
        ->add(Item::product('A')->quantity(1)->unitPrice(200.00)->discount(50.00)); // 150

    expect($invoice->getTotal())->toBe(150.00);
});

it('throws InvalidDteStructureException when validating without a recipient', function () {
    $invoice = Invoice::create()
        ->add(Item::product('Widget')->quantity(1)->unitPrice(10.00));

    expect(fn () => $invoice->validate())
        ->toThrow(InvalidDteStructureException::class, 'recipient');
});

it('throws InvalidDteStructureException when validating without items', function () {
    $invoice = Invoice::create()->forFinalConsumer();

    expect(fn () => $invoice->validate())
        ->toThrow(InvalidDteStructureException::class, 'line item');
});

it('passes validation with recipient and at least one item', function () {
    $invoice = Invoice::create()
        ->forFinalConsumer()
        ->add(Item::product('X')->quantity(1)->unitPrice(10.00));

    expect($invoice->validate())->toBe($invoice); // returns self
});

it('throws when trying to cancel an unissued invoice', function () {
    $invoice = Invoice::create()
        ->forFinalConsumer()
        ->add(Item::product('X')->quantity(1)->unitPrice(10.00));

    expect(fn () => $invoice->cancel('Mistake'))
        ->toThrow(InvalidDteStructureException::class, 'not been issued');
});
