<?php

declare(strict_types=1);

use InfilePhp\Core\Dte\DebitNote;
use InfilePhp\Core\Dte\Invoice;
use InfilePhp\Core\Dte\Item;
use InfilePhp\Core\Dte\Recipient;
use InfilePhp\Core\Enums\DteType;
use InfilePhp\Core\Exceptions\InvalidDteStructureException;

/**
 * Returns an Invoice that has a non-null idempotency key,
 * simulating a previously issued invoice without making an HTTP call.
 */
function issuedInvoiceForDebit(): Invoice
{
    $invoice = Invoice::create()
        ->for(Recipient::withTaxId('99999999')->name('Corp SA')->address('Guatemala'))
        ->add(Item::product('Server')->quantity(1)->unitPrice(5000.00));

    $reflection = new ReflectionProperty(Invoice::class, 'idempotencyKey');
    $reflection->setAccessible(true);
    $reflection->setValue($invoice, 'fake-idempotency-key-debit');

    return $invoice;
}

it('returns DteType::DebitNote from getType', function () {
    expect(DebitNote::create()->getType())->toBe(DteType::DebitNote);
});

it('stores the source invoice via for()', function () {
    $invoice = issuedInvoiceForDebit();
    $note = DebitNote::create()->for($invoice);

    expect($note->getSourceInvoice())->toBe($invoice);
});

it('stores the reason', function () {
    $note = DebitNote::create()->reason('Additional shipping charges');

    expect($note->getReason())->toBe('Additional shipping charges');
});

it('delegates getRecipient() to the source invoice', function () {
    $invoice = issuedInvoiceForDebit();
    $note = DebitNote::create()->for($invoice);

    expect($note->getRecipient()?->getTaxId())->toBe('99999999');
});

it('delegates getItems() to the source invoice', function () {
    $invoice = issuedInvoiceForDebit();
    $note = DebitNote::create()->for($invoice);

    expect($note->getItems())->toHaveCount(1);
});

it('throws when validating a debit note without a source invoice', function () {
    expect(fn () => DebitNote::create()->reason('test')->validate())
        ->toThrow(InvalidDteStructureException::class, 'source invoice');
});

it('throws when validating a debit note linked to an unissued invoice', function () {
    $unissued = Invoice::create()
        ->forFinalConsumer()
        ->add(Item::product('X')->quantity(1)->unitPrice(10.00));

    expect(fn () => DebitNote::create()->for($unissued)->reason('test')->validate())
        ->toThrow(InvalidDteStructureException::class, 'not been issued');
});

it('throws when validating a debit note without a reason', function () {
    $invoice = issuedInvoiceForDebit();

    expect(fn () => DebitNote::create()->for($invoice)->validate())
        ->toThrow(InvalidDteStructureException::class, 'reason');
});

it('passes validation with source invoice and reason', function () {
    $invoice = issuedInvoiceForDebit();
    $note = DebitNote::create()->for($invoice)->reason('Extra charges applied');

    expect($note->validate())->toBe($note);
});

it('throws when trying to cancel an unissued debit note', function () {
    $invoice = issuedInvoiceForDebit();
    $note = DebitNote::create()->for($invoice)->reason('test');

    expect(fn () => $note->cancel('Error'))
        ->toThrow(InvalidDteStructureException::class, 'not been issued');
});
