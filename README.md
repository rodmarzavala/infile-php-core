# Infile PHP Core SDK

[![Packagist Version](https://img.shields.io/packagist/v/rodmarzavala/infile-php-core)](https://packagist.org/packages/rodmarzavala/infile-php-core)
[![PHP Version Require](https://img.shields.io/packagist/php-v/rodmarzavala/infile-php-core)](https://packagist.org/packages/rodmarzavala/infile-php-core)
[![License](https://img.shields.io/packagist/l/rodmarzavala/infile-php-core)](https://packagist.org/packages/rodmarzavala/infile-php-core)

Un SDK open-source para PHP 8.2+ de facturación electrónica en línea (FEL) de Guatemala, utilizando a Infile S.A. como certificador. Este paquete es agnóstico y no depende de ningún framework.

> **Nota:** Este repositorio es una división de solo lectura (read-only split) del monorepo principal `infile-php`. Por favor, envía tus *issues* y *pull requests* al [repositorio principal](https://github.com/rodmarzavala/infile-php).

## Instalación

```bash
composer require rodmarzavala/infile-php-core
```

## Documentación

Para acceder a la documentación completa, guías de inicio rápido y referencia de la API, por favor visita nuestro sitio:

**👉 [Documentación del SDK (rodmarzavala.github.io/infile-php)](https://rodmarzavala.github.io/infile-php/)**

## Ejemplo de Uso

```php
use InfilePhp\Core\Dte\Invoice;
use InfilePhp\Core\Dte\Recipient;
use InfilePhp\Core\Dte\Item;

$response = Invoice::create()
    ->for(Recipient::withTaxId('12345678')->name('Juan Pérez')->address('Ciudad'))
    ->add(Item::product('Laptop')->quantity(1)->unitPrice(8500.00))
    ->issue();

echo $response->uuid();
```
