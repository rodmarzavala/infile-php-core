<?php

declare(strict_types=1);

namespace InfilePhp\Core;

use InfilePhp\Core\Http\CuiClient;
use InfilePhp\Core\Http\InfileClient;
use InfilePhp\Core\Support\NullEventDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Static SDK entry point.
 *
 * Call InfilePhp::configure() once during application bootstrap (e.g. in a ServiceProvider).
 * Framework adapters handle this automatically.
 *
 * @example
 *   InfilePhp::configure(new FelConfig(nit: '12345678', ...));
 */
final class InfilePhp
{
    private static ?FelConfig $config = null;

    private static ?InfileClient $client = null;

    private static ?CuiClient $cuiClient = null;

    private static ?EventDispatcherInterface $dispatcher = null;

    /**
     * Configure the SDK with credentials and settings.
     * Optionally provide a PSR-14 EventDispatcher (framework adapters inject their own).
     */
    public static function configure(
        FelConfig $config,
        ?EventDispatcherInterface $dispatcher = null,
    ): void {
        self::$config = $config;
        self::$client = new InfileClient($config);
        self::$cuiClient = new CuiClient($config);
        self::$dispatcher = $dispatcher;
    }

    /**
     * Return the configured Infile HTTP client.
     *
     * @throws \RuntimeException if configure() has not been called
     */
    public static function client(): InfileClient
    {
        if (self::$client === null) {
            throw new \RuntimeException(
                'InfilePhp has not been configured. Call InfilePhp::configure() first.'
            );
        }

        return self::$client;
    }

    /**
     * Return the configured CUI client.
     *
     * @throws \RuntimeException if configure() has not been called
     */
    public static function cuiClient(): CuiClient
    {
        if (self::$cuiClient === null) {
            throw new \RuntimeException(
                'InfilePhp has not been configured. Call InfilePhp::configure() first.'
            );
        }

        return self::$cuiClient;
    }

    /**
     * Return the active event dispatcher.
     * Falls back to a no-op dispatcher when none is configured.
     */
    public static function dispatcher(): EventDispatcherInterface
    {
        return self::$dispatcher ?? new NullEventDispatcher();
    }

    /**
     * Return the active configuration.
     *
     * @throws \RuntimeException if configure() has not been called
     */
    public static function config(): FelConfig
    {
        if (self::$config === null) {
            throw new \RuntimeException(
                'InfilePhp has not been configured. Call InfilePhp::configure() first.'
            );
        }

        return self::$config;
    }

    /**
     * Reset the SDK state (useful in tests).
     */
    public static function reset(): void
    {
        self::$config = null;
        self::$client = null;
        self::$cuiClient = null;
        self::$dispatcher = null;
    }
}
