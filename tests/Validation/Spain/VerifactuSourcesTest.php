<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Spain;

use IntrAgile\EInvoicing\Validation\Spain\VerifactuSources;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class VerifactuSourcesTest extends TestCase
{
    private const AEAT = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/';

    public function test_manifest_lists_eight_runtime_and_two_example_files(): void
    {
        self::assertSame('tikeV1.0', VerifactuSources::RELEASE);
        self::assertCount(8, VerifactuSources::files('runtime'));
        self::assertCount(2, VerifactuSources::files('example'));

        foreach (['runtime', 'example'] as $kind) {
            foreach (VerifactuSources::files($kind) as $name => $file) {
                self::assertStringNotContainsString('/', $name);
                self::assertStringNotContainsString('..', $name);
                self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $file['sha256'], $name);
            }
        }
    }

    public function test_manifest_hashes_match_the_research_table(): void
    {
        self::assertSame([
            'SuministroLR.xsd' => ['url' => self::AEAT.'SuministroLR.xsd', 'sha256' => '26bacfc6229d1a314758753244219ba207b2dcc8e2a22f7ab60b8ab6bae877e1'],
            'SuministroInformacion.xsd' => ['url' => self::AEAT.'SuministroInformacion.xsd', 'sha256' => 'ee4c1655175644de44c4c25055ffeb8e5f4bb4bc3834ce8254d4222ef18c8aa1'],
            'RespuestaSuministro.xsd' => ['url' => self::AEAT.'RespuestaSuministro.xsd', 'sha256' => '82acf80f785643caac13087aae66808ed721a13f08ca5218cf8ae81b695549ef'],
            'ConsultaLR.xsd' => ['url' => self::AEAT.'ConsultaLR.xsd', 'sha256' => 'bf2cdb8fc4b95b291757a72b76d8fffca06a6d30d9329122ca2fd6b2d5f8f1b1'],
            'RespuestaConsultaLR.xsd' => ['url' => self::AEAT.'RespuestaConsultaLR.xsd', 'sha256' => 'de35063acb8d9ba0d6ae51acc6b595de9c2b12333250e95e13108ef5f2670d45'],
            'SistemaFacturacion.wsdl' => ['url' => self::AEAT.'SistemaFacturacion.wsdl', 'sha256' => '05919120708ff7650612fa6683c9336eaf919335d9a4db10e86759190af48602'],
            'errores.properties' => ['url' => self::AEAT.'errores.properties', 'sha256' => '06519ceb23422bd6b0ad3bfb659e3007615050da4920781d12cff536481d5902'],
            'xmldsig-core-schema.xsd' => ['url' => 'https://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd', 'sha256' => 'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d'],
        ], VerifactuSources::files('runtime'));

        self::assertSame([
            'AnexosEjemplosFirmaRegFact.zip' => [
                'url' => 'https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Espec-Tecnicas/AnexosEjemplosFirmaRegFact.zip',
                'sha256' => '66fac533bef6c93c041b08704bf538843fe552052a2994899aba0875c2c4b24e',
            ],
            'ejemploRegistro.xml' => ['url' => 'zip:AnexosEjemplosFirmaRegFact.zip', 'sha256' => '328ff6571e8c75614f0fbe6e6456e472b897c2ed20a86ec42f2667782fdcdc8d'],
        ], VerifactuSources::files('example'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedLines(): array
    {
        $sum = str_repeat('a', 64);

        return [
            'plain http URL' => ["{$sum}  runtime  a.xsd  http://www.w3.org/a.xsd"],
            'parent folder in the name' => ["{$sum}  runtime  ../x  https://example.com/x"],
            '63-digit checksum' => [str_repeat('a', 63).'  runtime  a.xsd  https://example.com/a.xsd'],
            'unknown kind' => ["{$sum}  schema  a.xsd  https://example.com/a.xsd"],
            'archive that is not listed' => ["{$sum}  example  a.xml  zip:missing.zip"],
        ];
    }

    #[DataProvider('malformedLines')]
    public function test_malformed_manifest_line_is_refused(string $line): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed line in the VERI*FACTU manifest');

        VerifactuSources::parseManifest($line."\n");
    }

    public function test_unknown_kind_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        VerifactuSources::files('schema');
    }
}
