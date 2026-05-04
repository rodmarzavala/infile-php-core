<?php

declare(strict_types=1);

use InfilePhp\Core\Dte\CreditNote;
use InfilePhp\Core\Dte\Invoice;
use InfilePhp\Core\Dte\Item;
use InfilePhp\Core\Dte\Recipient;
use InfilePhp\Core\Enums\DteType;
use InfilePhp\Core\Exceptions\InvalidDteStructureException;

/**
 * Returns an Invoice that has a non-null idempotency key,
 * simulating a previously issued invoice without making an HTTP call.
 */
function issuedInvoice(): Invoice
{
    $invoice = Invoice::create()
        ->for(Recipient::withTaxId('12345678')->name('ACME')->address('GT'))
        ->add(Item::product('Widget')->quantity(1)->unitPrice(100.00));

    // Inject an idempotency key to simulate a prior issue() call
    $reflection = new ReflectionProperty(Invoice::class, 'idempotencyKey');
    $reflection->setAccessible(true);
    $reflection->setValue($invoice, 'fake-idempotency-key-uuid');

    return $invoice;
}

// ──────────────────────────────────────────────
// CreditNote
// ──────────────────────────────────────────────

it('returns DteType::CreditNote from getType', function () {
    expect(CreditNote::create()->getType())->toBe(DteType::CreditNote);
});

it('stores the source invoice via for()', function () {
    $invoice = issuedInvoice();
    $note = CreditNote::create()->for($invoice);

    expect($note->getSourceInvoice())->toBe($invoice);
});

it('stores the reason', function () {
    $note = CreditNote::create()->reason('Product returned by customer');

    expect($note->getReason())->toBe('Product returned by customer');
});

it('delegates getRecipient() to the source invoice', function () {
    $invoice = issuedInvoice();
    $note = CreditNote::create()->for($invoice);

    expect($note->getRecipient()?->getTaxId())->toBe('12345678');
});

it('delegates getItems() to the source invoice', function () {
    $invoice = issuedInvoice();
    $note = CreditNote::create()->for($invoice);

    expect($note->getItems())->toHaveCount(1);
});

it('throws when validating a credit note without a source invoice', function () {
    expect(fn () => CreditNote::create()->reason('test')->validate())
        ->toThrow(InvalidDteStructureException::class, 'source invoice');
});

it('throws when validating a credit note linked to an unissued invoice', function () {
    $unissued = Invoice::create()
        ->forFinalConsumer()
        ->add(Item::product('X')->quantity(1)->unitPrice(10.00));

    expect(fn () => CreditNote::create()->for($unissued)->reason('test')->validate())
        ->toThrow(InvalidDteStructureException::class, 'not been issued');
});

it('throws when validating a credit note without a reason', function () {
    $invoice = issuedInvoice();

    expect(fn () => CreditNote::create()->for($invoice)->validate())
        ->toThrow(InvalidDteStructureException::class, 'reason');
});

it('passes validation with source invoice and reason', function () {
    $invoice = issuedInvoice();
    $note = CreditNote::create()->for($invoice)->reason('Returned goods');

    expect($note->validate())->toBe($note);
});

it('throws when trying to cancel an unissued credit note', function () {
    $invoice = issuedInvoice();
    $note = CreditNote::create()->for($invoice)->reason('test');

    expect(fn () => $note->cancel('Error'))
        ->toThrow(InvalidDteStructureException::class, 'not been issued');
});
