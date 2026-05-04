<?php

declare(strict_types=1);

use InfilePhp\Core\Support\IdempotencyKey;

it('generates a string', function () {
    expect(IdempotencyKey::generate())->toBeString();
});

it('generates a valid UUID4 format', function () {
    $key = IdempotencyKey::generate();

    // UUID4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
    expect($key)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
});

it('generates a unique key on every call', function () {
    $keys = array_map(
        static fn (): string => IdempotencyKey::generate(),
        range(1, 50)
    );

    // All 50 generated keys must be unique
    expect(array_unique($keys))->toHaveCount(50);
});

it('generates a key of exactly 36 characters', function () {
    expect(strlen(IdempotencyKey::generate()))->toBe(36);
});
