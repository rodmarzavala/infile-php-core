<?php

declare(strict_types=1);

namespace InfilePhp\Core\Http;

use InfilePhp\Core\Contracts\DteContract;
use InfilePhp\Core\Dte\Recipient;
use InfilePhp\Core\Enums\DteType;
use InfilePhp\Core\Enums\Flow;
use InfilePhp\Core\Exceptions\DailyLimitExceededException;
use InfilePhp\Core\Exceptions\InfileAuthException;
use InfilePhp\Core\Exceptions\InfileCertificationException;
use InfilePhp\Core\Exceptions\InfileServiceUnavailableException;
use InfilePhp\Core\FelConfig;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * HTTP client for all Infile sign, certify, and cancel operations.
 * Supports both the Unified and Separate certification flows.
 *
 * XML format follows SAT Guatemala FEL schema 0.2.0:
 *   https://www.sat.gob.gt/dte/fel/0.2.0
 *
 * Unified flow endpoint per Manual V Cloud (01/07/2021):
 *   POST /fel/procesounificado/transaccion/v2/xml
 *   Headers: UsuarioFirma, LlaveFirma, UsuarioApi, LlaveApi, identificador
 *   Body: FEL XML document (application/xml)
 */
class InfileClient
{
    /** SAT FEL XML namespaces. */
    private const NS_DTE = 'http://www.sat.gob.gt/dte/fel/0.2.0';

    /** IVA divisor for extracting tax from IVA-included prices (1 + 0.12). */
    private const IVA_DIVISOR = 1.12;

    public function __construct(
        private readonly FelConfig $config,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * Certify a DTE document using the configured flow (Unified or Separate).
     *
     * @throws InfileAuthException
     * @throws InfileCertificationException
     * @throws DailyLimitExceededException
     * @throws InfileServiceUnavailableException
     */
    public function certify(DteContract $dte, string $idempotencyKey): CertificationResponse
    {
        if ($this->config->flow === Flow::Unified) {
            return $this->certifyUnified($dte, $idempotencyKey);
        }

        return $this->certifySeparate($dte, $idempotencyKey);
    }

    /**
     * Cancel a previously certified DTE.
     *
     * @throws InfileCertificationException
     * @throws InfileServiceUnavailableException
     */
    public function cancel(
        string $uuid,
        DteType $dteType,
        string $reason,
        string $issuedAt,
        string $idempotencyKey,
    ): void {
        $xmlPayload = $this->buildCancelXml(
            uuid: $uuid,
            reason: $reason,
            issuedAt: $issuedAt,
        );

        $response = $this->sendRequest(
            method: 'POST',
            url: $this->config->endpointUnified,
            body: $xmlPayload,
            headers: $this->buildUnifiedHeaders(idempotencyKey: "ANUL-{$idempotencyKey}"),
        );

        $data = $this->decodeJson((string) $response->getBody());
        $this->assertUnifiedSuccess($data);
    }

    /**
     * Check the health of the Infile certification endpoint.
     * Returns the response time in milliseconds, or -1 if unreachable.
     */
    public function ping(): int
    {
        $start = hrtime(true);

        try {
            $this->sendRequest(
                method: 'POST',
                url: $this->config->endpointNit,
                body: json_encode([
                    'emisor_codigo' => $this->config->signUser,
                    'emisor_clave'  => $this->config->apiKey,
                    'nit_consulta'  => '0',
                ], JSON_THROW_ON_ERROR),
                headers: ['Content-Type' => 'application/json'],
            );
        } catch (ClientExceptionInterface) {
            // A network exception means unreachable, but we still return timing for now,
            // or perhaps -1. The docblock says -1.
            return -1;
        }

        return (int) round((hrtime(true) - $start) / 1_000_000);
    }

    /**
     * Generate the raw, unsigned XML for a given DTE.
     * Useful for previewing or XSD validation before sending.
     */
    public function getUnsignedXml(DteContract $dte): string
    {
        return $this->buildDteXml($dte);
    }

    // -----------------------------------------------------------------------
    // Private — certification flows
    // -----------------------------------------------------------------------

    private function certifyUnified(DteContract $dte, string $idempotencyKey): CertificationResponse
    {
        $xml = $this->buildDteXml($dte);

        $response = $this->sendRequest(
            method: 'POST',
            url: $this->config->endpointUnified,
            body: $xml,
            headers: $this->buildUnifiedHeaders(idempotencyKey: $idempotencyKey),
        );

        $data = $this->decodeJson((string) $response->getBody());
        $this->assertUnifiedSuccess($data);

        return $this->buildResponse($data);
    }

    private function certifySeparate(DteContract $dte, string $idempotencyKey): CertificationResponse
    {
        // Step 1: Sign
        $xml    = $this->buildDteXml($dte);
        $signed = $this->sign($xml);

        // Step 2: Certify
        $body = json_encode([
            'nit_emisor'   => $this->config->nit,
            'correo_copia' => $this->config->emailCopy,
            'xml_dte'      => base64_encode($signed),
        ], JSON_THROW_ON_ERROR);

        $response = $this->sendRequest(
            method: 'POST',
            url: $this->config->endpointCertify,
            body: $body,
            headers: $this->buildSeparateCertifyHeaders(idempotencyKey: $idempotencyKey),
        );

        $data = $this->decodeJson((string) $response->getBody());
        $this->assertNoInfileError($data);

        return $this->buildResponse($data);
    }

    /**
     * Sign an XML payload using the Infile signing service (separate flow only).
     *
     * @throws InfileCertificationException
     * @throws InfileServiceUnavailableException
     */
    private function sign(string $xml): string
    {
        $body = json_encode([
            'llave'        => $this->config->signKey,
            'archivo'      => base64_encode($xml),
            'codigo'       => $this->config->apiKey,
            'alias'        => $this->config->signUser,
            'es_anulacion' => 'N',
        ], JSON_THROW_ON_ERROR);

        $response = $this->sendRequest(
            method: 'POST',
            url: $this->config->endpointSign,
            body: $body,
            headers: ['Content-Type' => 'application/json'],
        );

        $data = $this->decodeJson((string) $response->getBody());
        $this->assertNoInfileError($data);

        /** @var string $signed */
        $signed = $data['xml_firmado'] ?? '';

        return base64_decode($signed, true) ?: $signed;
    }

    // -----------------------------------------------------------------------
    // Private — HTTP wrapper
    // -----------------------------------------------------------------------

    /**
     * @param array<string, string> $headers
     * @throws InfileServiceUnavailableException
     */
    private function sendRequest(string $method, string $url, string $body, array $headers): \Psr\Http\Message\ResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $request = $request->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->http->sendRequest($request);

            // Guzzle throws on 4xx/5xx, PSR-18 does not. We emulate Guzzle's behavior for 5xx.
            if ($response->getStatusCode() >= 500) {
                throw new InfileServiceUnavailableException(
                    message: "Infile endpoint returned {$response->getStatusCode()}",
                    endpoint: $url,
                    statusCode: $response->getStatusCode(),
                );
            }

            return $response;
        } catch (ClientExceptionInterface $e) {
            throw new InfileServiceUnavailableException(
                message: "Infile endpoint unreachable: {$e->getMessage()}",
                endpoint: $url,
                statusCode: 0,
                previous: $e,
            );
        }
    }

    // -----------------------------------------------------------------------
    // Private — XML builders
    // -----------------------------------------------------------------------

    /**
     * Build the complete FEL XML document per SAT Guatemala schema 0.2.0.
     *
     * Reference: SAT Guatemala — Documentación Técnica FEL
     * Provider: InFile WS Unificado V Cloud (01/07/2021)
     */
    private function buildDteXml(DteContract $dte): string
    {
        $recipient = $dte->getRecipient() ?? Recipient::finalConsumer();
        $items     = $dte->getItems();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        // GTDocumento
        $root = $dom->createElement('dte:GTDocumento');
        $root->setAttribute('xmlns:dte', self::NS_DTE);
        $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttribute('Version', '0.1');
        $root->setAttribute('xsi:schemaLocation', self::NS_DTE);
        $dom->appendChild($root);

        // SAT
        $sat = $dom->createElement('dte:SAT');
        $sat->setAttribute('ClaseDocumento', 'dte');
        $root->appendChild($sat);

        // DTE
        $dteNode = $dom->createElement('dte:DTE');
        $dteNode->setAttribute('ID', 'DatosCertificados');
        $sat->appendChild($dteNode);

        // DatosEmision
        $datosEmision = $dom->createElement('dte:DatosEmision');
        $datosEmision->setAttribute('ID', 'DatosEmision');
        $dteNode->appendChild($datosEmision);

        // DatosGenerales
        $datosGenerales = $dom->createElement('dte:DatosGenerales');
        $datosGenerales->setAttribute('CodigoMoneda', 'GTQ');
        $datosGenerales->setAttribute('FechaHoraEmision', $this->nowGt());
        $datosGenerales->setAttribute('Tipo', $dte->getType()->value);
        $datosEmision->appendChild($datosGenerales);

        // Emisor
        $emisor = $dom->createElement('dte:Emisor');
        $emisor->setAttribute('AfiliacionIVA', 'GEN');
        $emisor->setAttribute('CodigoEstablecimiento', '1');
        $emisor->setAttribute('NITEmisor', $this->config->nit);
        $emisor->setAttribute('NombreComercial', $this->config->signUser);
        $emisor->setAttribute('NombreEmisor', $this->config->signUser);
        if ($this->config->emailCopy !== '') {
            $emisor->setAttribute('CorreoEmisor', $this->config->emailCopy);
        }

        $dirEmisor = $dom->createElement('dte:DireccionEmisor');
        $emisor->appendChild($dirEmisor);
        $this->appendDireccion($dom, $dirEmisor);
        $datosEmision->appendChild($emisor);

        // Receptor
        $receptor = $dom->createElement('dte:Receptor');
        $receptor->setAttribute('IDReceptor', $recipient->getTaxId());
        $receptor->setAttribute('NombreReceptor', $recipient->getName());
        $dirReceptor = $dom->createElement('dte:DireccionReceptor');
        $receptor->appendChild($dirReceptor);
        $this->appendDireccion($dom, $dirReceptor, $recipient->getAddress());
        $datosEmision->appendChild($receptor);

        // Frases (IVA regime — required by SAT)
        $frases = $dom->createElement('dte:Frases');
        $frase  = $dom->createElement('dte:Frase');
        $frase->setAttribute('CodigoEscenario', '1');
        $frase->setAttribute('TipoFrase', '1');
        $frases->appendChild($frase);
        $datosEmision->appendChild($frases);

        // Items
        $itemsNode  = $dom->createElement('dte:Items');
        $grandTotal = 0.0;

        foreach ($items as $lineNum => $item) {
            $grossPrice    = round($item->getQuantity() * $item->getUnitPrice(), 2);
            $discount      = round($item->getDiscount(), 2);
            $lineTotal     = round($grossPrice - $discount, 2);
            $montoGravable = round($lineTotal / self::IVA_DIVISOR, 2);
            $montoIva      = round($lineTotal - $montoGravable, 2);
            $grandTotal   += $lineTotal;

            $itemNode = $dom->createElement('dte:Item');
            $itemNode->setAttribute('BienOServicio', $item->getItemType() === 'service' ? 'S' : 'B');
            $itemNode->setAttribute('NumeroLinea', (string) ($lineNum + 1));
            $itemsNode->appendChild($itemNode);

            $this->appendTextNode($dom, $itemNode, 'dte:Cantidad', (string) $item->getQuantity());
            $this->appendTextNode($dom, $itemNode, 'dte:UnidadMedida', 'UNI');
            $this->appendTextNode($dom, $itemNode, 'dte:Descripcion', $item->getDescription());
            $this->appendTextNode($dom, $itemNode, 'dte:PrecioUnitario', (string) $item->getUnitPrice());
            $this->appendTextNode($dom, $itemNode, 'dte:Precio', (string) $grossPrice);
            $this->appendTextNode($dom, $itemNode, 'dte:Descuento', (string) $discount);

            $impuestos = $dom->createElement('dte:Impuestos');
            $impuesto  = $dom->createElement('dte:Impuesto');
            $this->appendTextNode($dom, $impuesto, 'dte:NombreCorto', 'IVA');
            $this->appendTextNode($dom, $impuesto, 'dte:CodigoUnidadGravable', '1');
            $this->appendTextNode($dom, $impuesto, 'dte:MontoGravable', (string) $montoGravable);
            $this->appendTextNode($dom, $impuesto, 'dte:MontoImpuesto', (string) $montoIva);
            $impuestos->appendChild($impuesto);
            $itemNode->appendChild($impuestos);

            $this->appendTextNode($dom, $itemNode, 'dte:Total', (string) $lineTotal);
        }

        $datosEmision->appendChild($itemsNode);

        // Totales
        $grandTotal     = round($grandTotal, 2);
        $totalIvaAmount = round($grandTotal - $grandTotal / self::IVA_DIVISOR, 2);

        $totales       = $dom->createElement('dte:Totales');
        $totalImpuestos = $dom->createElement('dte:TotalImpuestos');
        $totalImpuesto  = $dom->createElement('dte:TotalImpuesto');
        $totalImpuesto->setAttribute('NombreCorto', 'IVA');
        $totalImpuesto->setAttribute('TotalMontoImpuesto', (string) $totalIvaAmount);
        $totalImpuestos->appendChild($totalImpuesto);
        $totales->appendChild($totalImpuestos);
        $this->appendTextNode($dom, $totales, 'dte:GranTotal', (string) $grandTotal);
        $datosEmision->appendChild($totales);

        $xml = $dom->saveXML();

        return $xml !== false ? $xml : '';
    }

    /**
     * Build the cancellation XML per SAT FEL schema.
     *
     * @param string $issuedAt ISO-8601 datetime of the original DTE emission
     */
    private function buildCancelXml(
        string $uuid,
        string $reason,
        string $issuedAt,
    ): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElement('dte:GTAnulacionDocumento');
        $root->setAttribute('xmlns:dte', self::NS_DTE);
        $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttribute('Version', '0.1');
        $root->setAttribute('xsi:schemaLocation', self::NS_DTE);
        $dom->appendChild($root);

        $sat      = $dom->createElement('dte:SAT');
        $anulacion = $dom->createElement('dte:AnulacionDTE');
        $anulacion->setAttribute('ID', 'DatosAnulacion');
        $sat->appendChild($anulacion);
        $root->appendChild($sat);

        $datos = $dom->createElement('dte:DatosGenerales');
        $datos->setAttribute('ID', 'DatosAnulacion');
        $datos->setAttribute('NumeroDocumentoAAnular', $uuid);
        $datos->setAttribute('NITEmisor', $this->config->nit);
        $datos->setAttribute('FechaEmisionDocumentoAnular', $issuedAt);
        $datos->setAttribute('FechaHoraAnulacion', $this->nowGt());
        $datos->setAttribute('MotivoAnulacion', $reason);
        $anulacion->appendChild($datos);

        $xml = $dom->saveXML();

        return $xml !== false ? $xml : '';
    }

    // -----------------------------------------------------------------------
    // Private — header builders
    // -----------------------------------------------------------------------

    /**
     * Build authentication headers for the Unified flow.
     * Per Manual V Cloud (01/07/2021), Table 1.
     *
     * @return array<string, string>
     */
    private function buildUnifiedHeaders(string $idempotencyKey): array
    {
        return [
            'Content-Type' => 'application/xml',
            'UsuarioFirma' => $this->config->signUser,
            'LlaveFirma'   => $this->config->signKey,
            'UsuarioApi'   => $this->config->apiUser,
            'LlaveApi'     => $this->config->apiKey,
            'identificador' => $idempotencyKey,
        ];
    }

    /**
     * Build authentication headers for the Separate certify/cancel endpoints.
     *
     * @return array<string, string>
     */
    private function buildSeparateCertifyHeaders(string $idempotencyKey): array
    {
        return [
            'Content-Type'  => 'application/json',
            'usuario'       => $this->config->apiUser,
            'llave'         => $this->config->apiKey,
            'identificador' => $idempotencyKey,
        ];
    }

    // -----------------------------------------------------------------------
    // Private — response parsing
    // -----------------------------------------------------------------------

    /**
     * Assert the Unified flow response indicates success (`resultado: true`).
     *
     * @param array<string, mixed> $data
     *
     * @throws InfileAuthException
     * @throws DailyLimitExceededException
     * @throws InfileCertificationException
     */
    private function assertUnifiedSuccess(array $data): void
    {
        // Primary indicator for the unified endpoint.
        $resultado = $data['resultado'] ?? null;
        if ($resultado === true) {
            return;
        }

        // Map structured errors.
        $errores = $data['descripcion_errores'] ?? [];
        if (is_array($errores) && $errores !== []) {
            $msgs = [];
            foreach ($errores as $err) {
                if (is_array($err)) {
                    $msg = $err['mensaje_error'] ?? '';
                    if (is_string($msg) && $msg !== '') {
                        $msgs[] = $msg;
                    }
                }
            }
            if ($msgs !== []) {
                throw new InfileCertificationException(
                    message: implode('; ', $msgs),
                    statusCode: 0,
                    infileCode: 'FEL_ERROR',
                );
            }
        }

        // Fallback description.
        $rawDesc = $data['descripcion'] ?? null;
        $desc    = is_string($rawDesc) ? $rawDesc : 'Infile certification failed (resultado: false).';

        throw new InfileCertificationException(
            message: $desc,
            statusCode: 0,
            infileCode: 'FEL_ERROR',
        );
    }

    /**
     * Assert the Separate flow response contains no error code.
     *
     * @param array<string, mixed> $data
     *
     * @throws InfileAuthException
     * @throws DailyLimitExceededException
     * @throws InfileCertificationException
     */
    private function assertNoInfileError(array $data): void
    {
        $rawCodigo = $data['codigo'] ?? $data['codigo_error'] ?? '';
        $codigo    = is_string($rawCodigo) ? $rawCodigo : '';

        if ($codigo === '' || $codigo === '0') {
            return;
        }

        $rawMsg  = $data['mensaje'] ?? null;
        $mensaje = is_string($rawMsg) ? $rawMsg : 'Authentication rejected by Infile.';

        if (in_array($codigo, ['ERROR_401', 'ERROR_403'], strict: true)) {
            throw new InfileAuthException(
                message: $mensaje,
                statusCode: 401,
            );
        }

        if ($codigo === 'ERROR_429') {
            throw new DailyLimitExceededException(dailyLimit: 2_000);
        }

        throw new InfileCertificationException(
            message: $mensaje,
            statusCode: 0,
            infileCode: $codigo,
        );
    }

    /**
     * Decode a JSON string into an associative array.
     *
     * @return array<string, mixed>
     */
    private function decodeJson(string $body): array
    {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, associative: true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build a CertificationResponse from the Infile API response data.
     *
     * @param array<string, mixed> $data
     */
    private function buildResponse(array $data): CertificationResponse
    {
        $uuid    = $data['uuid'] ?? null;
        $serie   = $data['serie'] ?? null;
        $numero  = $data['numero'] ?? null;
        $xmlCert = $data['xml_certificado'] ?? null;
        $credits = $data['creditos_disponibles'] ?? null;
        $fecha   = $data['fecha'] ?? null;

        return new CertificationResponse(
            uuid: is_string($uuid) ? $uuid : '',
            serie: is_string($serie) ? $serie : '',
            numero: is_string($numero) ? $numero : '',
            xmlCertified: is_string($xmlCert) ? $xmlCert : '',
            remainingCreditsVal: is_int($credits) ? $credits : 0,
            issuedAt: is_string($fecha) ? $fecha : $this->nowGt(),
        );
    }

    // -----------------------------------------------------------------------
    // Private — DOM helpers
    // -----------------------------------------------------------------------

    private function appendTextNode(\DOMDocument $dom, \DOMElement $parent, string $tag, string $text): void
    {
        $node = $dom->createElement($tag);
        $node->appendChild($dom->createTextNode($text));
        $parent->appendChild($node);
    }

    private function appendDireccion(
        \DOMDocument $dom,
        \DOMElement $parent,
        string $direccion = 'Ciudad',
    ): void {
        $this->appendTextNode($dom, $parent, 'dte:Direccion', $direccion);
        $this->appendTextNode($dom, $parent, 'dte:CodigoPostal', '01001');
        $this->appendTextNode($dom, $parent, 'dte:Municipio', 'Guatemala');
        $this->appendTextNode($dom, $parent, 'dte:Departamento', 'Guatemala');
        $this->appendTextNode($dom, $parent, 'dte:Pais', 'GT');
    }

    /**
     * Return the current datetime in Guatemala timezone (UTC-6) formatted as ISO-8601.
     */
    private function nowGt(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Guatemala')))
            ->format('Y-m-d\TH:i:sP');
    }
}
