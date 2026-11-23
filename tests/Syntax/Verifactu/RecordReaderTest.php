<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Verifactu;

use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Spain\Verifactu\AltaOperation;
use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Fingerprint;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Verifactu\RecordReader;
use IntrAgile\EInvoicing\Syntax\Verifactu\RecordWriter;
use IntrAgile\EInvoicing\Syntax\Verifactu\VerifactuFormat;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class RecordReaderTest extends TestCase
{
    /** The hash fields of RegistroAlta and RegistroAnulacion, as paths below the record. */
    private const HASH_FIELDS = [
        'IDFactura/IDEmisorFactura',
        'IDFactura/NumSerieFactura',
        'IDFactura/FechaExpedicionFactura',
        'IDFactura/IDEmisorFacturaAnulada',
        'IDFactura/NumSerieFacturaAnulada',
        'IDFactura/FechaExpedicionFacturaAnulada',
        'TipoFactura',
        'CuotaTotal',
        'ImporteTotal',
        'Encadenamiento/RegistroAnterior/Huella',
        'FechaHoraHusoGenRegistro',
        'Huella',
    ];

    /** @return iterable<string, array{string}> */
    public static function spikeFiles(): iterable
    {
        foreach (array_keys(Fixtures::verifactuB2Brouter()) as $file) {
            yield $file => [$file];
        }
    }

    /** @return iterable<string, array{BillingRecord}> */
    public static function writerSamples(): iterable
    {
        foreach (RecordWriterTest::sampleRecords() as $name => $record) {
            yield $name => [$record];
        }
    }

    #[DataProvider('spikeFiles')]
    public function test_rewrite_keeps_literal_text_and_hash(string $file): void
    {
        $original = Fixtures::verifactuB2Brouter()[$file];
        $this->assertRewriteKeepsHashFields($original);

        $rewritten = (new RecordWriter)->write((new RecordReader)->read($original));
        $this->assertStringContainsString('<sum1:BaseImponibleOimporteNoSujeto>100.0</sum1:BaseImponibleOimporteNoSujeto>', $rewritten);
        $this->assertStringNotContainsString('100.00', $rewritten);
    }

    #[DataProvider('writerSamples')]
    public function test_written_records_read_back_to_the_same_xml(BillingRecord $record): void
    {
        $writer = new RecordWriter;
        $xml = $writer->write($record);

        $read = (new RecordReader)->read($xml);

        $this->assertSame($record::class, $read::class);
        $this->assertSame($xml, $writer->write($read));
        $this->assertSame($record->fingerprint(), $read->fingerprint());
        $this->assertSame(Fingerprint::input($record), Fingerprint::input($read));
    }

    #[Group('verifactu-official')]
    public function test_rewritten_records_are_schema_valid(): void
    {
        $schemas = Fixtures::verifactuSchemas();
        $sources = Fixtures::verifactuB2Brouter();
        $sources['ejemploRegistro.xml'] = (string) file_get_contents(Fixtures::verifactuExample());

        foreach ($sources as $name => $xml) {
            $rewritten = (new RecordWriter($schemas))->write((new RecordReader)->read($xml));
            $errors = (new SchemaValidator)->validate(SafeXml::load($rewritten), $schemas->informationXsd(), $schemas->localPrefixes());

            $this->assertSame([], array_map(static fn ($error): string => $error->message, $errors), $name);
            $this->assertRewriteKeepsHashFields($xml);
        }
    }

    public function test_amount_comparison_is_by_value(): void
    {
        $record = (new RecordReader)->read(Fixtures::verifactuB2Brouter()['record-1064493.xml']);
        $this->assertInstanceOf(RegistrationRecord::class, $record);
        $canonical = Amount::of('121.00');

        $this->assertSame('121.0', $record->draft->total->text);
        $this->assertNotSame($canonical->text, $record->draft->total->text);
        $this->assertTrue($record->draft->total->isEqualTo($canonical));
        $this->assertTrue($record->draft->breakdown[0]->rate?->value->isEqualTo('21'));
    }

    public function test_doctype_is_refused(): void
    {
        $xml = (string) preg_replace('/\?>\n/', "?>\n<!DOCTYPE r [<!ENTITY x \"y\">]>\n", self::spike(), 1);

        $this->expectException(UnsafeXml::class);
        (new RecordReader)->read($xml);
    }

    public function test_foreign_root_is_unsupported(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('RegistroFactura');
        (new RecordReader)->read('<sum1:RegistroFactura xmlns:sum1="'.VerifactuFormat::SF_NS.'"/>');
    }

    public function test_alta_in_another_namespace_is_unsupported(): void
    {
        $xml = str_replace(VerifactuFormat::SF_NS, 'urn:example:other', self::spike());

        $this->expectException(UnsupportedDocument::class);
        (new RecordReader)->read($xml);
    }

    public function test_unknown_child_is_named(): void
    {
        $xml = str_replace(
            '<sum1:CuotaTotal>',
            '<sum1:Desconocido>1</sum1:Desconocido><sum1:CuotaTotal>',
            self::spike(),
        );

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('/sf:RegistroAlta/sf:Desconocido');
        (new RecordReader)->read($xml);
    }

    public function test_unknown_nested_child_is_named(): void
    {
        $xml = str_replace(
            '<sum1:CuotaRepercutida>',
            '<other:Extra xmlns:other="urn:example:other"/><sum1:CuotaRepercutida>',
            self::spike(),
        );

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('/sf:RegistroAlta/sf:Desglose/sf:DetalleDesglose/other:Extra');
        (new RecordReader)->read($xml);
    }

    public function test_repeated_single_element_is_unsupported(): void
    {
        $xml = str_replace('<sum1:CuotaTotal>21.0</sum1:CuotaTotal>', '<sum1:CuotaTotal>21.0</sum1:CuotaTotal><sum1:CuotaTotal>22.0</sum1:CuotaTotal>', self::spike());

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('CuotaTotal');
        (new RecordReader)->read($xml);
    }

    public function test_missing_required_element_is_unsupported(): void
    {
        $xml = (string) preg_replace('#<sum1:ImporteTotal>[^<]*</sum1:ImporteTotal>#', '', self::spike());

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('ImporteTotal');
        (new RecordReader)->read($xml);
    }

    public function test_signature_is_skipped(): void
    {
        $xml = str_replace(
            '</sum1:RegistroAlta>',
            '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo/></ds:Signature></sum1:RegistroAlta>',
            self::spike(),
        );

        $record = (new RecordReader)->read($xml);

        $this->assertSame('1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73', $record->fingerprint());
        $this->assertStringNotContainsString('Signature', (new RecordWriter)->write($record));
    }

    public function test_inadmissible_flag_pair_is_refused(): void
    {
        $xml = str_replace(
            '<sum1:TipoFactura>',
            '<sum1:Subsanacion>N</sum1:Subsanacion><sum1:RechazoPrevio>S</sum1:RechazoPrevio><sum1:TipoFactura>',
            self::spike(),
        );

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessage('Cuadro Operativa Alta');
        (new RecordReader)->read($xml);
    }

    public function test_written_n_flags_read_as_absent(): void
    {
        $xml = str_replace(
            '<sum1:TipoFactura>',
            '<sum1:Subsanacion> N </sum1:Subsanacion><sum1:RechazoPrevio>N</sum1:RechazoPrevio><sum1:TipoFactura>',
            self::spike(),
        );

        $record = (new RecordReader)->read($xml);

        $this->assertInstanceOf(RegistrationRecord::class, $record);
        $this->assertSame(AltaOperation::Initial, $record->draft->operation);
    }

    public function test_values_are_trimmed(): void
    {
        $xml = str_replace(
            ['<sum1:NumSerieFactura>PROBE-ES-0001<', '<sum1:CuotaTotal>21.0<', '>2026-11-23T12:48:37+01:00<', '>1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73<'],
            ["<sum1:NumSerieFactura>\n  PROBE-ES-0001 <", '<sum1:CuotaTotal> 21.0 <', '> 2026-11-23T12:48:37+01:00 <', ">\n1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73\n<"],
            self::spike(),
        );

        $record = (new RecordReader)->read($xml);

        $this->assertSame('PROBE-ES-0001', $record->invoiceId()->number);
        $this->assertSame('1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73', $record->fingerprint());
        $this->assertSame($record->fingerprint(), Fingerprint::of($record));
    }

    public function test_lower_case_huella_is_kept_verbatim(): void
    {
        $lower = '1e823150439412ab8a572fbfd66d676ca366074f933949010ec70fddc1d5fc73';
        $xml = str_replace('1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73', $lower, self::spike());

        $record = (new RecordReader)->read($xml);

        $this->assertSame($lower, $record->fingerprint());
        $this->assertStringContainsString('<sum1:Huella>'.$lower.'</sum1:Huella>', (new RecordWriter)->write($record));
    }

    public function test_huella_of_wrong_length_is_refused_with_error_1262(): void
    {
        $xml = str_replace(
            '1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73',
            '1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC7',
            self::spike(),
        );

        try {
            (new RecordReader)->read($xml);
            $this->fail('A Huella of 63 characters was accepted.');
        } catch (InvalidRecord $e) {
            $this->assertStringContainsString('/sf:RegistroAlta/sf:Huella', $e->getMessage());
            $this->assertStringContainsString('1262', $e->getMessage());
        }
    }

    public function test_previous_huella_of_63_characters_is_kept_verbatim(): void
    {
        $short = substr('D60F9FD2DDDD19313359612C52222CC74E0B7C6E3C0D64AFBC797F2CE54EF812', 0, 63);
        $xml = str_replace(
            'D60F9FD2DDDD19313359612C52222CC74E0B7C6E3C0D64AFBC797F2CE54EF812</sum1:Huella>
    </sum1:RegistroAnterior>',
            $short.'</sum1:Huella>
    </sum1:RegistroAnterior>',
            Fixtures::verifactuB2Brouter()['record-1064500.xml'],
        );

        $record = (new RecordReader)->read($xml);

        $this->assertSame($short, $record->chainLink()->previousFingerprint());
        $this->assertSame('1FC6D6D59975C2CFC2E1FE74F3E77643D47B6CB352E291B83AFC825AD7FA9928', $record->fingerprint());
        $this->assertStringContainsString('<sum1:Huella>'.$short.'</sum1:Huella>', (new RecordWriter)->write($record));
    }

    public function test_previous_huella_longer_than_64_characters_is_refused(): void
    {
        $xml = str_replace(
            'D60F9FD2DDDD19313359612C52222CC74E0B7C6E3C0D64AFBC797F2CE54EF812</sum1:Huella>
    </sum1:RegistroAnterior>',
            'D60F9FD2DDDD19313359612C52222CC74E0B7C6E3C0D64AFBC797F2CE54EF8120</sum1:Huella>
    </sum1:RegistroAnterior>',
            Fixtures::verifactuB2Brouter()['record-1064500.xml'],
        );

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessage('/sf:RegistroAlta/sf:Encadenamiento/sf:RegistroAnterior/sf:Huella');
        (new RecordReader)->read($xml);
    }

    public function test_blank_previous_huella_is_refused(): void
    {
        $xml = str_replace(
            'D60F9FD2DDDD19313359612C52222CC74E0B7C6E3C0D64AFBC797F2CE54EF812</sum1:Huella>
    </sum1:RegistroAnterior>',
            ' </sum1:Huella>
    </sum1:RegistroAnterior>',
            Fixtures::verifactuB2Brouter()['record-1064500.xml'],
        );

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessage('/sf:RegistroAlta/sf:Encadenamiento/sf:RegistroAnterior/sf:Huella');
        (new RecordReader)->read($xml);
    }

    public function test_kept_huella_need_not_match_the_recomputed_one(): void
    {
        $xml = str_replace('1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73', str_repeat('A', 64), self::spike());

        $record = (new RecordReader)->read($xml);

        $this->assertSame(str_repeat('A', 64), $record->fingerprint());
        $this->assertSame('1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73', Fingerprint::of($record));
    }

    public function test_invalid_values_name_their_path(): void
    {
        $cases = [
            'NIF' => ['<sum1:NIF>A58818501</sum1:NIF>', '<sum1:NIF>A58818502</sum1:NIF>', '/sf:RegistroAlta/sf:Destinatarios/sf:IDDestinatario/sf:NIF'],
            'amount' => ['<sum1:CuotaTotal>21.0</sum1:CuotaTotal>', '<sum1:CuotaTotal>21,0</sum1:CuotaTotal>', '/sf:RegistroAlta/sf:CuotaTotal'],
            'code' => ['<sum1:TipoFactura>F1</sum1:TipoFactura>', '<sum1:TipoFactura>F9</sum1:TipoFactura>', '/sf:RegistroAlta/sf:TipoFactura'],
            'date' => ['<sum1:FechaExpedicionFactura>23-11-2026</sum1:FechaExpedicionFactura>', '<sum1:FechaExpedicionFactura>31-02-2026</sum1:FechaExpedicionFactura>', '/sf:RegistroAlta/sf:IDFactura/sf:FechaExpedicionFactura'],
            'yes/no' => ['<sum1:IndicadorMultiplesOT>N</sum1:IndicadorMultiplesOT>', '<sum1:IndicadorMultiplesOT>X</sum1:IndicadorMultiplesOT>', '/sf:RegistroAlta/sf:SistemaInformatico/sf:IndicadorMultiplesOT'],
        ];

        foreach ($cases as $name => [$search, $replace, $path]) {
            try {
                (new RecordReader)->read(str_replace($search, $replace, self::spike()));
                $this->fail("{$name}: no exception");
            } catch (InvalidRecord $e) {
                $this->assertStringContainsString($path, $e->getMessage(), $name);
            }
        }
    }

    public function test_read_element_reads_a_record_inside_a_submission(): void
    {
        $record = RecordWriterTest::sampleRecords()['vector 3 (anulacion)'];
        $document = SafeXml::load('<sum:RegistroFactura xmlns:sum="'.VerifactuFormat::LR_NS.'" xmlns:sum1="'.VerifactuFormat::SF_NS.'"/>');
        $parent = $document->documentElement;
        $this->assertInstanceOf(DOMElement::class, $parent);
        $element = (new RecordWriter)->appendTo($parent, $record);

        $read = (new RecordReader)->readElement($element);

        $this->assertInstanceOf(CancellationRecord::class, $read);
        $this->assertSame($record->fingerprint(), $read->fingerprint());
        $this->assertSame($parent, $element->parentNode);
    }

    private function assertRewriteKeepsHashFields(string $original): void
    {
        $rewritten = (new RecordWriter)->write((new RecordReader)->read($original));
        $before = self::root($original);
        $after = self::root($rewritten);

        foreach (self::HASH_FIELDS as $path) {
            $this->assertSame(self::textAt($before, $path), self::textAt($after, $path), $path);
        }
        $this->assertSame(Fingerprint::inputOf($before), Fingerprint::inputOf($after));
        $this->assertSame(Fingerprint::recompute($before), Fingerprint::recompute($after));
        $this->assertSame((new RecordReader)->read($original)->fingerprint(), (new RecordReader)->read($rewritten)->fingerprint());
    }

    private static function spike(): string
    {
        return Fixtures::verifactuB2Brouter()['record-1064490.xml'];
    }

    private static function root(string $xml): DOMElement
    {
        $root = SafeXml::load($xml)->documentElement;
        self::assertInstanceOf(DOMElement::class, $root);

        return $root;
    }

    private static function textAt(DOMElement $element, string $path): ?string
    {
        foreach (explode('/', $path) as $name) {
            $next = null;
            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMElement && $child->namespaceURI === VerifactuFormat::SF_NS && $child->localName === $name) {
                    $next = $child;
                    break;
                }
            }
            if ($next === null) {
                return null;
            }
            $element = $next;
        }

        return $element->textContent;
    }
}
