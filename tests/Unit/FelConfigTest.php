<?php

declare(strict_types=1);

use InfilePhp\Core\Enums\DteType;
use InfilePhp\Core\Enums\Environment;
use InfilePhp\Core\Enums\Flow;
use InfilePhp\Core\FelConfig;

it('stores all required credentials', function () {
    $config = new FelConfig(
        nit: '12345678K',
        signUser: 'usuario_firma',
        signKey: 'llave_firma_secreta',
        apiUser: 'usuario_api',
        apiKey: 'llave_api_secreta',
    );

    expect($config->nit)->toBe('12345678K')
        ->and($config->signUser)->toBe('usuario_firma')
        ->and($config->signKey)->toBe('llave_firma_secreta')
        ->and($config->apiUser)->toBe('usuario_api')
        ->and($config->apiKey)->toBe('llave_api_secreta');
});

it('defaults to Sandbox environment', function () {
    $config = new FelConfig(
        nit: '1',
        signUser: 'u',
        signKey: 'k',
        apiUser: 'u',
        apiKey: 'k',
    );

    expect($config->environment)->toBe(Environment::Sandbox);
});

it('defaults to Unified flow', function () {
    $config = new FelConfig(
        nit: '1',
        signUser: 'u',
        signKey: 'k',
        apiUser: 'u',
        apiKey: 'k',
    );

    expect($config->flow)->toBe(Flow::Unified);
});

it('defaults to 3 retry attempts', function () {
    $config = new FelConfig(nit: '1', signUser: 'u', signKey: 'k', apiUser: 'u', apiKey: 'k');

    expect($config->retryTimes)->toBe(3);
});

it('defaults to 2 seconds between retries', function () {
    $config = new FelConfig(nit: '1', signUser: 'u', signKey: 'k', apiUser: 'u', apiKey: 'k');

    expect($config->retrySleep)->toBe(2);
});

it('defaults to fallback enabled', function () {
    $config = new FelConfig(nit: '1', signUser: 'u', signKey: 'k', apiUser: 'u', apiKey: 'k');

    expect($config->fallbackEnabled)->toBeTrue();
});

it('stores the correct production endpoints by default', function () {
    $config = new FelConfig(nit: '1', signUser: 'u', signKey: 'k', apiUser: 'u', apiKey: 'k');

    expect($config->endpointSign)->toContain('signer-emisores.feel.com.gt')
        ->and($config->endpointCertify)->toContain('certificador.feel.com.gt')
        ->and($config->endpointCancel)->toContain('anulacion')
        ->and($config->endpointUnified)->toContain('procesounificado')
        ->and($config->endpointNit)->toContain('consultareceptores.feel.com.gt')
        ->and($config->endpointCui)->toContain('servicios/externos/cui')
        ->and($config->endpointCuiAuth)->toContain('servicios/externos/login');
});

it('allows overriding individual endpoints', function () {
    $config = new FelConfig(
        nit: '1',
        signUser: 'u',
        signKey: 'k',
        apiUser: 'u',
        apiKey: 'k',
        endpointUnified: 'https://sandbox.example.com/unified',
    );

    expect($config->endpointUnified)->toBe('https://sandbox.example.com/unified');
});

it('can be configured with Production environment', function () {
    $config = new FelConfig(
        nit: '1',
        signUser: 'u',
        signKey: 'k',
        apiUser: 'u',
        apiKey: 'k',
        environment: Environment::Production,
    );

    expect($config->environment)->toBe(Environment::Production);
});

it('can be configured with Separate flow', function () {
    $config = new FelConfig(
        nit: '1',
        signUser: 'u',
        signKey: 'k',
        apiUser: 'u',
        apiKey: 'k',
        flow: Flow::Separate,
    );

    expect($config->flow)->toBe(Flow::Separate);
});
