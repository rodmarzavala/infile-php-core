<?php

declare(strict_types=1);

namespace InfilePhp\Core\Sat;

use InfilePhp\Core\InfilePhp;

/**
 * SAT RTU (Registro Tributario Unificado) lookup gateway.
 *
 * @example
 *   $taxpayer = Rtu::lookupNit('12345678');
 *   $person   = Rtu::lookupCui('1234567890101');
 */
final class Rtu
{
    /**
     * Look up a taxpayer by NIT via the Infile NIT consultation service.
     *
     * Endpoint: POST https://consultareceptores.feel.com.gt/rest/action
     * Body (JSON): { emisor_codigo, emisor_clave, nit_consulta }
     * Response:    { nit, nombre, mensaje }
     *
     * Per "MANUAL SERVICE DE CONSULTA DE NIT.pdf" (02/07/2021).
     *
     * @throws \InfilePhp\Core\Exceptions\InfileServiceUnavailableException
     */
    public static function lookupNit(string $nit): TaxpayerData
    {
        $config = InfilePhp::config();
        $http = InfilePhp::httpClient();
        $requestFactory = InfilePhp::requestFactory();
        $streamFactory = InfilePhp::streamFactory();

        $body = json_encode([
            'emisor_codigo' => $config->signUser,
            'emisor_clave'  => $config->apiKey,
            'nit_consulta'  => $nit,
        ], JSON_THROW_ON_ERROR);

        $request = $requestFactory->createRequest('POST', $config->endpointNit)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream($body));

        try {
            $response = $http->sendRequest($request);
            
            if ($response->getStatusCode() >= 400) {
                throw new \InfilePhp\Core\Exceptions\InfileServiceUnavailableException(
                    message: "NIT lookup endpoint returned {$response->getStatusCode()}",
                    endpoint: $config->endpointNit,
                    statusCode: $response->getStatusCode(),
                );
            }
        } catch (\Psr\Http\Client\ClientExceptionInterface $e) {
            throw new \InfilePhp\Core\Exceptions\InfileServiceUnavailableException(
                message: "NIT lookup endpoint unreachable: {$e->getMessage()}",
                endpoint: $config->endpointNit,
                statusCode: 0,
                previous: $e,
            );
        }

        $decoded = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $data */
        $data = is_array($decoded) ? $decoded : [];

        $rawName = $data['nombre'] ?? null;
        $rawMsg  = $data['mensaje'] ?? null;

        return new TaxpayerData(
            nit: $nit,
            name: is_string($rawName) ? trim($rawName) : '',
            message: is_string($rawMsg) ? trim($rawMsg) : '',
        );
    }

    /**
     * Look up a person by CUI (national ID). JWT token is managed internally.
     *
     * @throws \InfilePhp\Core\Exceptions\InfileAuthException
     * @throws \InfilePhp\Core\Exceptions\InfileServiceUnavailableException
     */
    public static function lookupCui(string $cui): PersonData
    {
        return InfilePhp::cuiClient()->lookupCui($cui);
    }
}
