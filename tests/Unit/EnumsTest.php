<?php

declare(strict_types=1);

use InfilePhp\Core\Enums\DteType;
use InfilePhp\Core\Enums\Environment;
use InfilePhp\Core\Enums\Flow;

// ──────────────────────────────────────────────
// DteType
// ──────────────────────────────────────────────

it('DteType::Invoice has value FACT', function () {
    expect(DteType::Invoice->value)->toBe('FACT');
});

it('DteType::CreditNote has value NCRE', function () {
    expect(DteType::CreditNote->value)->toBe('NCRE');
});

it('DteType::DebitNote has value NDEB', function () {
    expect(DteType::DebitNote->value)->toBe('NDEB');
});

it('DteType::SmallTaxpayer has value FPEQ', function () {
    expect(DteType::SmallTaxpayer->value)->toBe('FPEQ');
});

it('DteType can be retrieved from its string value', function () {
    expect(DteType::from('FACT'))->toBe(DteType::Invoice)
        ->and(DteType::from('NCRE'))->toBe(DteType::CreditNote)
        ->and(DteType::from('NDEB'))->toBe(DteType::DebitNote)
        ->and(DteType::from('FPEQ'))->toBe(DteType::SmallTaxpayer);
});

// ──────────────────────────────────────────────
// Environment
// ──────────────────────────────────────────────

it('Environment::Sandbox and Environment::Production are distinct', function () {
    expect(Environment::Sandbox)->not->toBe(Environment::Production);
});

it('Environment can be retrieved from string value', function () {
    expect(Environment::from('sandbox'))->toBe(Environment::Sandbox);
    expect(Environment::from('production'))->toBe(Environment::Production);
});

// ──────────────────────────────────────────────
// Flow
// ──────────────────────────────────────────────

it('Flow::Unified and Flow::Separate are distinct', function () {
    expect(Flow::Unified)->not->toBe(Flow::Separate);
});

it('Flow can be retrieved from string value', function () {
    expect(Flow::from('unified'))->toBe(Flow::Unified);
    expect(Flow::from('separate'))->toBe(Flow::Separate);
});
