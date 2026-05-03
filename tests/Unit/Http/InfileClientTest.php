<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InfilePhp\Core\Dte\Invoice;
use InfilePhp\Core\Dte\Item;
use InfilePhp\Core\Enums\Flow;
use InfilePhp\Core\Exceptions\DailyLimitExceededException;
use InfilePhp\Core\Exceptions\InfileAuthException;
use InfilePhp\Core\Exceptions\InfileCertificationException;
use InfilePhp\Core\Exceptions\InfileServiceUnavailableException;
use InfilePhp\Core\FelConfig;
use InfilePhp\Core\Http\InfileClient;

function createClientWithMock(array $responses, FelConfig $config = null): InfileClient
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);
    $guzzleClient = new Client(['handler' => $handlerStack]);

    $config ??= new FelConfig(
        signUser: 'TEST',
        signKey: '123',
        apiUser: 'TEST',
        apiKey: '123',
        nit: '99999999K',
    );

    $httpFactory = new \GuzzleHttp\Psr7\HttpFactory();
    return new InfileClient($config, $guzzleClient, $httpFactory, $httpFactory);
}

it('throws InfileServiceUnavailableException on connection timeout', function () {
    $client = createClientWithMock([
        new RequestException('cURL error 28: Connection timed out', new Request('POST', 'test')),
    ]);

    $invoice = Invoice::create()->forFinalConsumer()->add(Item::product('Test')->quantity(1)->unitPrice(10));

    expect(fn () => $client->certify($invoice, '123'))
        ->toThrow(InfileServiceUnavailableException::class);
});

it('throws InfileCertificationException when unified endpoint returns false', function () {
    $client = createClientWithMock([
        new Response(200, [], (string) json_encode([
            'resultado' => false,
            'descripcion_errores' => [
                ['mensaje_error' => 'XML no valido']
            ]
        ])),
    ]);

    $invoice = Invoice::create()->forFinalConsumer()->add(Item::product('Test')->quantity(1)->unitPrice(10));

    expect(fn () => $client->certify($invoice, '123'))
        ->toThrow(InfileCertificationException::class, 'XML no valido');
});

it('parses a successful unified response', function () {
    $client = createClientWithMock([
        new Response(200, [], (string) json_encode([
            'resultado' => true,
            'uuid' => 'C9E42C0A-90BB-42ED-B457-1BEB34F7AC7C',
            'serie' => '12345678',
            'numero' => '1',
            'xml_certificado' => 'PGNlcnRpZmljYWRvLz4=',
            'fecha' => '2023-10-15T10:00:00-06:00',
        ])),
    ]);

    $invoice = Invoice::create()->forFinalConsumer()->add(Item::product('Test')->quantity(1)->unitPrice(10));

    $response = $client->certify($invoice, '123');

    expect($response->uuid)->toBe('C9E42C0A-90BB-42ED-B457-1BEB34F7AC7C')
        ->and($response->serie)->toBe('12345678')
        ->and($response->numero)->toBe('1')
        ->and($response->xmlCertified)->toBe('PGNlcnRpZmljYWRvLz4=')
        ->and($response->issuedAt)->toBe('2023-10-15T10:00:00-06:00');
});

it('throws InfileAuthException on ERROR_401 in separate flow', function () {
    $config = new FelConfig(
        signUser: 'TEST',
        signKey: '123',
        apiUser: 'TEST',
        apiKey: '123',
        nit: '99999999K',
        flow: Flow::Separate
    );

    // Separate flow requires two HTTP calls: sign, then certify.
    // Let's fail the sign step with a 401 response mapped by Infile API.
    $client = createClientWithMock([
        new Response(200, [], (string) json_encode([
            'codigo_error' => 'ERROR_401',
            'mensaje' => 'Invalid credentials'
        ])),
    ], $config);

    $invoice = Invoice::create()->forFinalConsumer()->add(Item::product('Test')->quantity(1)->unitPrice(10));

    expect(fn () => $client->certify($invoice, '123'))
        ->toThrow(InfileAuthException::class, 'Invalid credentials');
});

it('throws DailyLimitExceededException on ERROR_429 in separate flow', function () {
    $config = new FelConfig(
        signUser: 'TEST',
        signKey: '123',
        apiUser: 'TEST',
        apiKey: '123',
        nit: '99999999K',
        flow: Flow::Separate
    );

    // Mock successful sign, then fail certify with ERROR_429.
    $client = createClientWithMock([
        new Response(200, [], (string) json_encode([
            'codigo' => '0',
            'xml_firmado' => base64_encode('<test/>')
        ])),
        new Response(200, [], (string) json_encode([
            'codigo' => 'ERROR_429',
            'mensaje' => 'Limite excedido'
        ])),
    ], $config);

    $invoice = Invoice::create()->forFinalConsumer()->add(Item::product('Test')->quantity(1)->unitPrice(10));

    expect(fn () => $client->certify($invoice, '123'))
        ->toThrow(DailyLimitExceededException::class);
});
