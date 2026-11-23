<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Aeat;

use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use IntrAgile\EInvoicing\Transport\Aeat\AeatFormat;
use IntrAgile\EInvoicing\Transport\Aeat\LookupRequest;
use IntrAgile\EInvoicing\Transport\Aeat\LookupWriter;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class LookupWriterTest extends TestCase
{
    #[Group('verifactu-official')]
    public function test_lookup_request_is_schema_valid(): void
    {
        $schemas = Fixtures::verifactuSchemas();
        $xml = (new LookupWriter($schemas))->write(self::request('12345678/G33', '01-01-2024'));

        $errors = (new SchemaValidator)->validate(SafeXml::load($xml), $schemas->queryXsd(), $schemas->localPrefixes());

        $this->assertSame([], array_map(static fn ($error): string => $error->message, $errors));
        $this->assertSame(AeatFormat::QUERY_NS, SafeXml::load($xml)->documentElement?->namespaceURI);
        $this->assertStringContainsString('<sum1:Ejercicio>2024</sum1:Ejercicio>', $xml);
        $this->assertStringContainsString('<sum1:Periodo>01</sum1:Periodo>', $xml);
        $this->assertStringContainsString('<con:NumSerieFactura>12345678/G33</con:NumSerieFactura>', $xml);
        $this->assertStringContainsString('<sum1:FechaExpedicionFactura>01-01-2024</sum1:FechaExpedicionFactura>', $xml);
        $this->assertStringContainsString('<sum1:IDVersion>1.0</sum1:IDVersion>', $xml);
        $this->assertStringContainsString('<sum1:NIF>89890001K</sum1:NIF>', $xml);
    }

    #[Group('verifactu-official')]
    public function test_lookup_period_is_zero_padded(): void
    {
        $xml = (new LookupWriter(Fixtures::verifactuSchemas()))->write(self::request('A-1', '23-11-2026'));

        $this->assertStringContainsString('<sum1:Ejercicio>2026</sum1:Ejercicio>', $xml);
        $this->assertStringContainsString('<sum1:Periodo>11</sum1:Periodo>', $xml);

        $xml = (new LookupWriter(Fixtures::verifactuSchemas()))->write(self::request('A-1', '05-03-2026'));
        $this->assertStringContainsString('<sum1:Periodo>03</sum1:Periodo>', $xml);
    }

    private static function request(string $number, string $date): LookupRequest
    {
        return new LookupRequest(
            Nif::parse(VerifactuSamples::ISSUER_NIF),
            'Test Issuer S.L.',
            VerifactuSamples::invoiceId($number, $date),
        );
    }
}
