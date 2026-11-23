<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Aeat;

use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Transport\Aeat\AeatFormat;
use IntrAgile\EInvoicing\Transport\Aeat\SoapEnvelope;
use IntrAgile\EInvoicing\Transport\Aeat\SoapFault;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class ResponseFixturesTest extends TestCase
{
    private const DIR = __DIR__.'/../../fixtures/spain/aeat';

    private const PAYLOADS = [
        'correcto.xml',
        'parcialmente-correcto.xml',
        'incorrecto.xml',
        'duplicado-correcta.xml',
        'duplicado-anulada.xml',
        'consulta-con-datos.xml',
        'consulta-sin-datos.xml',
    ];

    private const FAULTS = [
        'fault-client-4104.xml' => ['Client', 4104],
        'fault-client-4112.xml' => ['Client', 4112],
        'fault-server.xml' => ['Server', null],
    ];

    #[Group('verifactu-official')]
    public function test_every_response_fixture_is_schema_valid(): void
    {
        $schemas = Fixtures::verifactuSchemas();
        $validated = [];

        foreach ($this->xmlFixtures() as $name) {
            $opened = SoapEnvelope::open((string) file_get_contents(self::DIR.'/'.$name));
            if ($opened instanceof SoapFault) {
                continue;
            }
            $xsd = match ($opened->namespaceURI) {
                AeatFormat::RESPONSE_NS => $schemas->responseXsd(),
                AeatFormat::QUERY_RESPONSE_NS => $schemas->queryResponseXsd(),
                default => self::fail("{$name}: unexpected payload namespace {$opened->namespaceURI}"),
            };
            $document = new DOMDocument;
            $document->appendChild($document->importNode($opened, true));

            $errors = (new SchemaValidator)->validate($document, $xsd, $schemas->localPrefixes());

            $this->assertSame([], array_map(static fn ($error): string => $error->message, $errors), $name);
            $validated[] = $name;
        }

        $this->assertSame(self::PAYLOADS, array_values(array_intersect(self::PAYLOADS, $validated)));
        $this->assertCount(count(self::PAYLOADS), $validated);
    }

    public function test_the_fixture_set_is_complete(): void
    {
        $expected = [...self::PAYLOADS, ...array_keys(self::FAULTS)];
        sort($expected);

        $this->assertSame($expected, $this->xmlFixtures());
        $this->assertFileExists(self::DIR.'/html-502.html');
        $this->assertFileExists(self::DIR.'/README.md');
    }

    /**
     * @param  array{string, ?int}  $expected
     */
    #[DataProvider('faults')]
    public function test_fault_fixtures_open_as_faults(string $name, array $expected): void
    {
        $fault = SoapEnvelope::open((string) file_get_contents(self::DIR.'/'.$name));

        $this->assertInstanceOf(SoapFault::class, $fault);
        $this->assertSame($expected, [$fault->faultCode, $fault->code]);
    }

    /** @return iterable<string, array{string, array{string, ?int}}> */
    public static function faults(): iterable
    {
        foreach (self::FAULTS as $name => $expected) {
            yield $name => [$name, $expected];
        }
    }

    public function test_html_error_page_is_not_an_envelope(): void
    {
        // A gateway error page is not well-formed XML; it must not be mistaken for a SOAP response.
        $this->expectException(UnsupportedDocument::class);

        SoapEnvelope::open((string) file_get_contents(self::DIR.'/html-502.html'));
    }

    public function test_payload_fixtures_are_not_faults(): void
    {
        foreach (self::PAYLOADS as $name) {
            $this->assertInstanceOf(DOMElement::class, SoapEnvelope::open((string) file_get_contents(self::DIR.'/'.$name)), $name);
        }
    }

    public function test_readme_carries_the_maintainer_line_under_the_title_and_as_footer(): void
    {
        $lines = file(self::DIR.'/README.md', FILE_IGNORE_NEW_LINES) ?: [];
        $credit = 'Developed and maintained by [IntrAgile Kft.](https://intragile.eu)';

        $this->assertStringStartsWith('# ', $lines[0] ?? '');
        $this->assertSame($credit, $lines[2] ?? null);
        $this->assertSame($credit, end($lines));
        $this->assertStringContainsString('not AEAT output', implode("\n", $lines));
    }

    /** @return list<string> */
    private function xmlFixtures(): array
    {
        $names = array_map('basename', glob(self::DIR.'/*.xml') ?: []);
        sort($names);

        return $names;
    }
}
