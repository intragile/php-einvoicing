<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

/**
 * The AEAT VERI*FACTU environments: the pre-production (test) and the production service.
 */
enum VerifactuEnvironment: string
{
    case Test = 'test';
    case Production = 'production';

    private const SOAP_PATH = '/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

    /**
     * Base URL of the QR verification service, without query string.
     */
    public function qrBaseUrl(): string
    {
        return match ($this) {
            self::Test => 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR',
            self::Production => 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR',
        };
    }

    /**
     * URL of the VERI*FACTU SOAP service; personal and seal certificates use different hosts.
     */
    public function soapEndpoint(CertificateKind $kind): string
    {
        $host = match ($this) {
            self::Test => match ($kind) {
                CertificateKind::Personal => 'https://prewww1.aeat.es',
                CertificateKind::Seal => 'https://prewww10.aeat.es',
            },
            self::Production => match ($kind) {
                CertificateKind::Personal => 'https://www1.agenciatributaria.gob.es',
                CertificateKind::Seal => 'https://www10.agenciatributaria.gob.es',
            },
        };

        return $host.self::SOAP_PATH;
    }
}
