<?php

declare(strict_types=1);

use InfilePhp\Core\Dte\Recipient;

it('creates a recipient with a specific tax id', function () {
    $recipient = Recipient::withTaxId('12345678');

    expect($recipient->getTaxId())->toBe('12345678')
        ->and($recipient->isFinalConsumer())->toBeFalse();
});

it('sets the name via the fluent builder', function () {
    $recipient = Recipient::withTaxId('12345678')->name('ACME Corp');

    expect($recipient->getName())->toBe('ACME Corp');
});

it('sets the address via the fluent builder', function () {
    $recipient = Recipient::withTaxId('12345678')->address('Zona 10, Guatemala');

    expect($recipient->getAddress())->toBe('Zona 10, Guatemala');
});

it('creates a final consumer with CF as tax id', function () {
    $recipient = Recipient::finalConsumer();

    expect($recipient->getTaxId())->toBe('CF')
        ->and($recipient->getName())->toBe('Consumidor Final')
        ->and($recipient->isFinalConsumer())->toBeTrue();
});

it('withTaxId does not mark recipient as final consumer', function () {
    $recipient = Recipient::withTaxId('CF'); // edge case: literal CF string

    expect($recipient->isFinalConsumer())->toBeFalse();
});

it('allows full fluent chain on a named recipient', function () {
    $recipient = Recipient::withTaxId('99999999')
        ->name('Tech Solutions S.A.')
        ->address('Boulevard Los Próceres, Guatemala');

    expect($recipient->getTaxId())->toBe('99999999')
        ->and($recipient->getName())->toBe('Tech Solutions S.A.')
        ->and($recipient->getAddress())->toBe('Boulevard Los Próceres, Guatemala')
        ->and($recipient->isFinalConsumer())->toBeFalse();
});
