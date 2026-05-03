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

    private static ?\Psr\Http\Client\ClientInterface $httpClient = null;

    private static ?\Psr\Http\Message\RequestFactoryInterface $requestFactory = null;

    private static ?\Psr\Http\Message\StreamFactoryInterface $streamFactory = null;

    /**
     * Configure the SDK with credentials and settings.
     * Optionally provide a PSR-14 EventDispatcher (framework adapters inject their own).
     */
    public static function configure(
        FelConfig $config,
        \Psr\Http\Client\ClientInterface $httpClient,
        \Psr\Http\Message\RequestFactoryInterface $requestFactory,
        \Psr\Http\Message\StreamFactoryInterface $streamFactory,
        ?EventDispatcherInterface $dispatcher = null,
    ): void {
        self::$config = $config;
        self::$httpClient = $httpClient;
        self::$requestFactory = $requestFactory;
        self::$streamFactory = $streamFactory;
        
        self::$client = new InfileClient($config, $httpClient, $requestFactory, $streamFactory);
        self::$cuiClient = new CuiClient($config, $httpClient, $requestFactory, $streamFactory);
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
        self::$httpClient = null;
        self::$requestFactory = null;
        self::$streamFactory = null;
    }

    public static function httpClient(): \Psr\Http\Client\ClientInterface
    {
        if (self::$httpClient === null) {
            throw new \RuntimeException('InfilePhp has not been configured.');
        }

        return self::$httpClient;
    }

    public static function requestFactory(): \Psr\Http\Message\RequestFactoryInterface
    {
        if (self::$requestFactory === null) {
            throw new \RuntimeException('InfilePhp has not been configured.');
        }

        return self::$requestFactory;
    }

    public static function streamFactory(): \Psr\Http\Message\StreamFactoryInterface
    {
        if (self::$streamFactory === null) {
            throw new \RuntimeException('InfilePhp has not been configured.');
        }

        return self::$streamFactory;
    }
}
