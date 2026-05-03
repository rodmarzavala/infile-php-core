<?php

declare(strict_types=1);

use InfilePhp\Core\Dte\Invoice;
use InfilePhp\Core\Dte\Item;
use InfilePhp\Core\Dte\Recipient;
use InfilePhp\Core\FelConfig;
use InfilePhp\Core\Http\InfileClient;

it('generates the correct XML structure and calculates taxes for a standard invoice', function () {
    $config = new FelConfig(
        signUser: 'TEST_SIGN',
        signKey: 'SIGN_KEY',
        apiUser: 'TEST_API',
        apiKey: 'API_KEY',
        nit: '99999999K',
        emailCopy: 'test@example.com',
    );

    $client = new InfileClient($config);

    $invoice = Invoice::create()
        ->for(Recipient::withTaxId('12345678')->name('Juan Perez')->address('Ciudad 123'))
        ->add(Item::product('Product A')->quantity(2)->unitPrice(100.00))
        ->add(Item::service('Service B')->quantity(1)->unitPrice(50.00)->discount(10.00));

    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('buildDteXml');
    $method->setAccessible(true);

    /** @var string $xml */
    $xml = $method->invoke($client, $invoice);

    $dom = new \DOMDocument();
    $dom->loadXML($xml);

    // Document root
    expect($dom->documentElement->tagName)->toBe('dte:GTDocumento')
        ->and($dom->documentElement->getAttribute('xmlns:dte'))->toBe('http://www.sat.gob.gt/dte/fel/0.2.0');

    // Sender
    $emisor = $dom->getElementsByTagName('Emisor')->item(0);
    expect($emisor)->not->toBeNull()
        ->and($emisor->getAttribute('NITEmisor'))->toBe('99999999K')
        ->and($emisor->getAttribute('NombreEmisor'))->toBe('TEST_SIGN')
        ->and($emisor->getAttribute('CorreoEmisor'))->toBe('test@example.com');

    // Recipient
    $receptor = $dom->getElementsByTagName('Receptor')->item(0);
    expect($receptor)->not->toBeNull()
        ->and($receptor->getAttribute('IDReceptor'))->toBe('12345678')
        ->and($receptor->getAttribute('NombreReceptor'))->toBe('Juan Perez');

    // Items
    $items = $dom->getElementsByTagName('Item');
    expect($items->length)->toBe(2);

    $item1 = $items->item(0);
    expect($item1->getAttribute('BienOServicio'))->toBe('B');

    $item2 = $items->item(1);
    expect($item2->getAttribute('BienOServicio'))->toBe('S');

    // Totals
    // Item 1: 2 * 100 = 200
    // Item 2: (1 * 50) - 10 = 40
    // Grand Total = 240
    $granTotal = $dom->getElementsByTagName('GranTotal')->item(0);
    expect($granTotal)->not->toBeNull()
        ->and($granTotal->nodeValue)->toBe('240');

    // IVA Calculation
    // 240 - (240 / 1.12) = 240 - 214.29 = 25.71
    $totalImpuesto = $dom->getElementsByTagName('TotalImpuesto')->item(0);
    expect($totalImpuesto)->not->toBeNull()
        ->and($totalImpuesto->getAttribute('TotalMontoImpuesto'))->toBe('25.71');
});
