<?php

declare(strict_types=1);

use InfilePhp\Core\Http\CertificationResponse;

it('stores all properties via the constructor', function () {
    $response = new CertificationResponse(
        uuid: 'C9E42C0A-90BB-42ED-B457-1BEB34F7AC7C',
        serie: 'A123',
        numero: '42',
        xmlCertified: 'PGNlcnRpZmljYWRvLz4=',
        remainingCreditsVal: 1850,
        issuedAt: '2024-01-15T10:30:00-06:00',
    );

    expect($response->uuid)->toBe('C9E42C0A-90BB-42ED-B457-1BEB34F7AC7C')
        ->and($response->serie)->toBe('A123')
        ->and($response->numero)->toBe('42')
        ->and($response->xmlCertified)->toBe('PGNlcnRpZmljYWRvLz4=')
        ->and($response->issuedAt)->toBe('2024-01-15T10:30:00-06:00');
});

it('exposes remaining credits via remainingCredits()', function () {
    $response = new CertificationResponse(
        uuid: 'uuid',
        serie: 'serie',
        numero: '1',
        xmlCertified: 'xml',
        remainingCreditsVal: 1234,
    );

    expect($response->remainingCredits())->toBe(1234);
});

it('defaults issuedAt to an empty string when not provided', function () {
    $response = new CertificationResponse(
        uuid: 'uuid',
        serie: 'serie',
        numero: '1',
        xmlCertified: 'xml',
        remainingCreditsVal: 0,
    );

    expect($response->issuedAt)->toBe('');
});

it('can hold zero remaining credits', function () {
    $response = new CertificationResponse(
        uuid: 'uuid',
        serie: 'serie',
        numero: '1',
        xmlCertified: 'xml',
        remainingCreditsVal: 0,
    );

    expect($response->remainingCredits())->toBe(0);
});
