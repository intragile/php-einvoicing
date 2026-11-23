<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain;

use IntrAgile\EInvoicing\Spain\CertificateKind;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use PHPUnit\Framework\TestCase;

final class VerifactuEnvironmentTest extends TestCase
{
    public function test_qr_and_soap_hosts(): void
    {
        $path = '/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

        self::assertSame('https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR', VerifactuEnvironment::Test->qrBaseUrl());
        self::assertSame('https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR', VerifactuEnvironment::Production->qrBaseUrl());
        self::assertSame('https://prewww1.aeat.es'.$path, VerifactuEnvironment::Test->soapEndpoint(CertificateKind::Personal));
        self::assertSame('https://prewww10.aeat.es'.$path, VerifactuEnvironment::Test->soapEndpoint(CertificateKind::Seal));
        self::assertSame('https://www1.agenciatributaria.gob.es'.$path, VerifactuEnvironment::Production->soapEndpoint(CertificateKind::Personal));
        self::assertSame('https://www10.agenciatributaria.gob.es'.$path, VerifactuEnvironment::Production->soapEndpoint(CertificateKind::Seal));
    }
}
