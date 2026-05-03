# Infile PHP Core SDK

[![Packagist Version](https://img.shields.io/packagist/v/rodmarzavala/infile-php-core)](https://packagist.org/packages/rodmarzavala/infile-php-core)
[![PHP Version Require](https://img.shields.io/packagist/php-v/rodmarzavala/infile-php-core)](https://packagist.org/packages/rodmarzavala/infile-php-core)
[![License](https://img.shields.io/packagist/l/rodmarzavala/infile-php-core)](https://packagist.org/packages/rodmarzavala/infile-php-core)

The official, framework-agnostic PHP 8.2+ SDK for Guatemala's electronic invoicing system (FEL - Factura Electrónica en Línea) using Infile S.A. as the certifying entity.

> **Note:** This repository is a read-only split of the main `infile-php` monorepo. Please submit issues and pull requests to the [main repository](https://github.com/rodmarzavala/infile-php).

## Installation

```bash
composer require rodmarzavala/infile-php-core
```

## Documentation

For full documentation, quickstart guides, and API reference, please visit our official documentation site:

**👉 [Official Documentation (rodmarzavala.github.io/infile-php)](https://rodmarzavala.github.io/infile-php/)**

## Usage Example

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
