<?php

declare(strict_types=1);

namespace InfilePhp\Core\Http;

use InfilePhp\Core\Exceptions\InfileAuthException;
use InfilePhp\Core\Exceptions\InfileServiceUnavailableException;
use InfilePhp\Core\FelConfig;
use InfilePhp\Core\Sat\PersonData;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * HTTP client for CUI (Código Único de Identificación) lookups.
 *
 * Manages JWT authentication internally. The CUI auth endpoint allows only
 * 50 logins per day — tokens are cached aggressively using the expiry timestamp
 * returned by the API (`fecha_de_vencimiento`).
 *
 * Auth endpoint per "API Consulta de CUI.pdf":
 *   POST /api/v2/servicios/externos/login
 *   Body: form-data { prefijo, llave }
 *
 * Query endpoint:
 *   POST /api/v2/servicios/externos/cui
 *   Body: form-data { cui }
 *   Header: Authorization: Bearer {token}
 */
final class CuiClient
{
    private ?string $jwtToken = null;

    /** Unix timestamp at which the cached token expires. */
    private ?int $tokenExpiresAt = null;

    /** Safety margin in seconds before actual expiry to force re-authentication. */
    private const TOKEN_SAFETY_MARGIN = 60;

    /** Default TTL if the API does not return `fecha_de_vencimiento`. */
    private const TOKEN_DEFAULT_TTL = 7_200;

    public function __construct(
        private readonly FelConfig $config,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * Look up a person by their CUI (national ID number).
     *
     * JWT authentication is managed internally and cached until the token expires.
     *
     * @throws InfileAuthException
     * @throws InfileServiceUnavailableException
     */
    public function lookupCui(string $cui): PersonData
    {
        $token = $this->getToken();

        $boundary = bin2hex(random_bytes(16));
        $body = "--{$boundary}\r\n" .
                "Content-Disposition: form-data; name=\"cui\"\r\n\r\n" .
                "{$cui}\r\n" .
                "--{$boundary}--\r\n";

        $request = $this->requestFactory->createRequest('POST', $this->config->endpointCui)
            ->withHeader('Content-Type', "multipart/form-data; boundary={$boundary}")
            ->withHeader('Authorization', "Bearer {$token}")
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->http->sendRequest($request);

            if ($response->getStatusCode() === 401) {
                $this->invalidateToken();
                $token = $this->getToken();

                $request = $request->withHeader('Authorization', "Bearer {$token}");
                $response = $this->http->sendRequest($request);

                if ($response->getStatusCode() >= 400) {
                    throw new InfileServiceUnavailableException(
                        message: "CUI lookup endpoint returned {$response->getStatusCode()}",
                        endpoint: $this->config->endpointCui,
                        statusCode: $response->getStatusCode(),
                    );
                }
            } elseif ($response->getStatusCode() >= 400) {
                throw new InfileServiceUnavailableException(
                    message: "CUI lookup endpoint returned {$response->getStatusCode()}",
                    endpoint: $this->config->endpointCui,
                    statusCode: $response->getStatusCode(),
                );
            }
        } catch (ClientExceptionInterface $e) {
            throw new InfileServiceUnavailableException(
                message: "CUI lookup endpoint unreachable: {$e->getMessage()}",
                endpoint: $this->config->endpointCui,
                statusCode: 0,
                previous: $e,
            );
        }

        $decoded = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $data */
        $data = is_array($decoded) ? $decoded : [];

        // Response structure: { resultado: bool, cui: { nombre: string, fallecido: "SI"|"NO" }, descripcion: string }
        $cuiData  = is_array($data['cui'] ?? null) ? $data['cui'] : [];
        $rawName  = $cuiData['nombre'] ?? null;
        $rawDead  = $cuiData['fallecido'] ?? null;

        // `fallecido` is returned as the string "SI" or "NO", not a boolean.
        $deceased = strtoupper(trim(is_string($rawDead) ? $rawDead : '')) === 'SI';

        return new PersonData(
            cui: $cui,
            name: is_string($rawName) ? trim($rawName) : '',
            deceased: $deceased,
        );
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Return a valid JWT token, re-authenticating only when the cached one has expired.
     *
     * @throws InfileAuthException
     * @throws InfileServiceUnavailableException
     */
    private function getToken(): string
    {
        if (
            $this->jwtToken !== null
            && $this->tokenExpiresAt !== null
            && time() < ($this->tokenExpiresAt - self::TOKEN_SAFETY_MARGIN)
        ) {
            return $this->jwtToken;
        }

        $this->authenticate();

        return $this->jwtToken ?? throw new InfileAuthException(
            message: 'CUI authentication failed: no token received.',
            statusCode: 401,
        );
    }

    /**
     * Authenticate against the Infile CUI login endpoint and cache the token.
     *
     * Per "API Consulta de CUI.pdf":
     *   POST /api/v2/servicios/externos/login
     *   Body: form-data { prefijo: <signUser>, llave: <apiKey> }
     *   Response: { resultado: bool, token: string, fecha_de_vencimiento: ISO-8601 }
     *
     * @throws InfileAuthException
     * @throws InfileServiceUnavailableException
     */
    private function authenticate(): void
    {
        $boundary = bin2hex(random_bytes(16));
        $body = "--{$boundary}\r\n" .
                "Content-Disposition: form-data; name=\"prefijo\"\r\n\r\n" .
                "{$this->config->signUser}\r\n" .
                "--{$boundary}\r\n" .
                "Content-Disposition: form-data; name=\"llave\"\r\n\r\n" .
                "{$this->config->apiKey}\r\n" .
                "--{$boundary}--\r\n";

        $request = $this->requestFactory->createRequest('POST', $this->config->endpointCuiAuth)
            ->withHeader('Content-Type', "multipart/form-data; boundary={$boundary}")
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->http->sendRequest($request);

            if ($response->getStatusCode() >= 400) {
                throw new InfileServiceUnavailableException(
                    message: "CUI auth endpoint returned {$response->getStatusCode()}",
                    endpoint: $this->config->endpointCuiAuth,
                    statusCode: $response->getStatusCode(),
                );
            }
        } catch (ClientExceptionInterface $e) {
            throw new InfileServiceUnavailableException(
                message: "CUI auth endpoint unreachable: {$e->getMessage()}",
                endpoint: $this->config->endpointCuiAuth,
                statusCode: 0,
                previous: $e,
            );
        }

        $decoded = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $data */
        $data = is_array($decoded) ? $decoded : [];

        $rawToken = $data['token'] ?? null;
        $token    = is_string($rawToken) ? $rawToken : '';

        if ($token === '') {
            throw new InfileAuthException(
                message: 'CUI authentication failed: empty token in response.',
                statusCode: 401,
            );
        }

        $this->jwtToken = $token;

        // Parse expiry from the API response — more reliable than a fixed TTL.
        $expiryStr = $data['fecha_de_vencimiento'] ?? null;
        if (is_string($expiryStr) && $expiryStr !== '') {
            try {
                $expiry              = new \DateTimeImmutable($expiryStr);
                $this->tokenExpiresAt = $expiry->getTimestamp();
            } catch (\Throwable) {
                $this->tokenExpiresAt = time() + self::TOKEN_DEFAULT_TTL;
            }
        } else {
            $this->tokenExpiresAt = time() + self::TOKEN_DEFAULT_TTL;
        }
    }

    private function invalidateToken(): void
    {
        $this->jwtToken      = null;
        $this->tokenExpiresAt = null;
    }
}
