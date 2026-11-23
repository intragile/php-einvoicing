<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Aeat;

use IntrAgile\EInvoicing\Spain\CertificateKind;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\AeatCredentials;
use IntrAgile\EInvoicing\Spain\Verifactu\IssuerContext;
use IntrAgile\EInvoicing\Spain\Verifactu\IssuerDriver;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Transport\Aeat\AeatConfig;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AeatConfigTest extends TestCase
{
    public function test_endpoint_per_environment_and_port(): void
    {
        $schemas = new VerifactuSchemas(sys_get_temp_dir());
        $path = '/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

        $expected = [
            [VerifactuEnvironment::Test, CertificateKind::Personal, 'https://prewww1.aeat.es'.$path],
            [VerifactuEnvironment::Test, CertificateKind::Seal, 'https://prewww10.aeat.es'.$path],
            [VerifactuEnvironment::Production, CertificateKind::Personal, 'https://www1.agenciatributaria.gob.es'.$path],
            [VerifactuEnvironment::Production, CertificateKind::Seal, 'https://www10.agenciatributaria.gob.es'.$path],
        ];
        foreach ($expected as [$environment, $port, $url]) {
            $config = new AeatConfig($environment, $port, $schemas);
            self::assertSame($url, $config->endpoint());
            self::assertSame(5_000_000, $config->maxResponseBytes);
        }
    }

    public function test_for_issuer_takes_port_from_credentials_and_refuses_b2brouter(): void
    {
        $schemas = new VerifactuSchemas(sys_get_temp_dir());
        $credentials = new AeatCredentials('/certs/seal.p12', 's3cret-pass-123', null, CertificateKind::Seal);
        $issuer = new IssuerContext(Nif::parse('B64829179'), 'Issuer S.L.', IssuerDriver::Aeat, VerifactuEnvironment::Production, $credentials);

        $config = AeatConfig::forIssuer($issuer, $schemas);
        self::assertSame(CertificateKind::Seal, $config->port);
        self::assertSame(VerifactuEnvironment::Production, $config->environment);
        self::assertSame($schemas, $config->schemas);
        self::assertSame('https://www10.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP', $config->endpoint());

        $b2brouter = new IssuerContext(Nif::parse('B64829179'), 'Issuer S.L.', IssuerDriver::B2Brouter, VerifactuEnvironment::Test, $credentials);
        try {
            AeatConfig::forIssuer($b2brouter, $schemas);
            self::fail('A B2Brouter issuer was accepted.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('B2Brouter', $exception->getMessage());
        }

        $withoutCredentials = new IssuerContext(Nif::parse('B64829179'), 'Issuer S.L.', IssuerDriver::B2Brouter, VerifactuEnvironment::Test);
        $this->expectException(InvalidArgumentException::class);
        AeatConfig::forIssuer($withoutCredentials, $schemas);
    }

    public function test_response_cap_below_one_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AeatConfig(VerifactuEnvironment::Test, CertificateKind::Personal, new VerifactuSchemas(sys_get_temp_dir()), 0);
    }
}
