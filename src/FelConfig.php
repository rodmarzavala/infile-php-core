<?php

declare(strict_types=1);

namespace InfilePhp\Core;

use InfilePhp\Core\Enums\Environment;
use InfilePhp\Core\Enums\Flow;

/**
 * Immutable SDK configuration object.
 * Pass to InfilePhp::configure() once during application bootstrap.
 */
final readonly class FelConfig
{
    /**
     * @param string      $nit            Emisor NIT registered with SAT
     * @param string      $signUser       UsuarioFirma / alias (prefix)
     * @param string      $signKey        LlaveFirma / Token Signer from SAT
     * @param string      $apiUser        UsuarioApi (same value as sign_user)
     * @param string      $apiKey         LlaveApi provided by Infile
     * @param Environment $environment    Sandbox or Production
     * @param Flow        $flow           Unified or Separate certification flow
     * @param string      $emailCopy      Optional BCC copy for certified DTE
     * @param int         $retryTimes     Number of retry attempts on failure
     * @param int         $retrySleep     Seconds between retries (exponential base)
     * @param bool        $fallbackEnabled Enable CAFE contingency mode
     * @param string      $endpointSign    Sign XML endpoint
     * @param string      $endpointCertify Certify DTE endpoint
     * @param string      $endpointCancel  Cancel DTE endpoint
     * @param string      $endpointUnified Unified sign+certify endpoint
     * @param string      $endpointNit     NIT lookup endpoint
     * @param string      $endpointCui     CUI lookup endpoint
     * @param string      $endpointCuiAuth CUI JWT authentication endpoint
     */
    public function __construct(
        public readonly string $nit,
        public readonly string $signUser,
        public readonly string $signKey,
        public readonly string $apiUser,
        public readonly string $apiKey,
        public readonly Environment $environment = Environment::Sandbox,
        public readonly Flow $flow = Flow::Unified,
        public readonly string $emailCopy = '',
        public readonly int $retryTimes = 3,
        public readonly int $retrySleep = 2,
        public readonly bool $fallbackEnabled = true,
        public readonly string $endpointSign = 'https://signer-emisores.feel.com.gt/sign_solicitud_firmas/firma_xml',
        public readonly string $endpointCertify = 'https://certificador.feel.com.gt/fel/certificacion/v2/dte/',
        public readonly string $endpointCancel = 'https://certificador.feel.com.gt/fel/anulacion/v2/dte/',
        public readonly string $endpointUnified = 'https://certificador.feel.com.gt/fel/procesounificado/transaccion/v2/xml',
        public readonly string $endpointNit = 'https://consultareceptores.feel.com.gt/rest/action',
        public readonly string $endpointCui = 'https://certificador.feel.com.gt/api/v2/servicios/externos/cui',
        public readonly string $endpointCuiAuth = 'https://certificador.feel.com.gt/api/v2/servicios/externos/login',
    ) {
    }
}
