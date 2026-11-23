<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use DOMElement;
use IntrAgile\EInvoicing\Spain\CorrectionType;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\Verifactu\Fingerprint;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxBreakdownLine;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Verifactu\RecordReader;
use IntrAgile\EInvoicing\Syntax\Verifactu\VerifactuFormat;
use IntrAgile\EInvoicing\Tests\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Records written by other software hash their literal text: B2Brouter writes "100.0" and
 * "21.00", AEAT's example "41.4" and "4". The fingerprint is recomputed from that text as found.
 */
final class LiteralRecomputeTest extends TestCase
{
    /** Huella of each B2Brouter sandbox record, measured from the captured files. */
    private const SPIKE_HASHES = [
        'record-1064490.xml' => '1E823150439412AB8A572FBFD66D676CA366074F933949010EC70FDDC1D5FC73',
        'record-1064493.xml' => 'D60F9FD2DDDD19313359612C52222CC74E0B7C6E3C0D64AFBC797F2CE54EF812',
        'record-1064500.xml' => '1FC6D6D59975C2CFC2E1FE74F3E77643D47B6CB352E291B83AFC825AD7FA9928',
        'record-1064501.xml' => '3AAB5BE8A33CDFF9DE5FD4BCF0601F169D5CFAE546611CC5EEA5CA9248D84C19',
    ];

    /** The Huella AEAT publishes for its example record (hash vector 4 with the literal 41.4). */
    private const AEAT_EXAMPLE_HASH = 'FF954378B64ED331A9B2366AD317D86E9DEC1716B12DD0ACCB172A6DC4C105AA';

    /** @return iterable<string, array{string, string}> */
    public static function spikeRecords(): iterable
    {
        foreach (self::SPIKE_HASHES as $file => $hash) {
            yield $file => [$file, $hash];
        }
    }

    #[DataProvider('spikeRecords')]
    public function test_spike_records_recompute_their_huella(string $file, string $expected): void
    {
        $xml = Fixtures::verifactuB2Brouter()[$file];
        $root = self::root($xml);

        $this->assertSame($expected, Fingerprint::recompute($root));
        $this->assertSame($expected, self::huella($root));
        $this->assertSame($expected, (new RecordReader)->read($xml)->fingerprint());
    }

    public function test_spike_records_form_one_chain(): void
    {
        $records = array_values(array_map(
            static fn (string $xml) => (new RecordReader)->read($xml),
            Fixtures::verifactuB2Brouter(),
        ));

        $this->assertCount(4, $records);
        $this->assertTrue($records[0]->chainLink()->isFirst());
        $this->assertSame('', $records[0]->chainLink()->previousFingerprint());
        for ($i = 1; $i < count($records); $i++) {
            $link = $records[$i]->chainLink();
            $previous = $link->previous();
            $this->assertNotNull($previous, "record {$i}");
            $this->assertTrue($previous->equals($records[$i - 1]->invoiceId()), "record {$i}");
            $this->assertSame($records[$i - 1]->fingerprint(), $link->previousFingerprint(), "record {$i}");
        }
    }

    #[Group('verifactu-official')]
    public function test_aeat_example_recomputes_vector_four(): void
    {
        $xml = (string) file_get_contents(Fixtures::verifactuExample());

        $this->assertSame(self::AEAT_EXAMPLE_HASH, Fingerprint::recompute(self::root($xml)));

        $record = (new RecordReader)->read($xml);
        $this->assertInstanceOf(RegistrationRecord::class, $record);
        $this->assertSame(self::AEAT_EXAMPLE_HASH, $record->fingerprint());
        $this->assertSame(self::AEAT_EXAMPLE_HASH, Fingerprint::of($record));
        $this->assertSame('41.4', $record->draft->totalTax->text);
        $this->assertSame('241.4', $record->draft->total->text);
        $this->assertSame(InvoiceType::R3, $record->draft->type);
        $this->assertSame(CorrectionType::Difference, $record->draft->correctionType);
        $this->assertSame(
            ['4', '21', '10'],
            array_map(static fn (TaxBreakdownLine $line): ?string => $line->rate?->text, $record->draft->breakdown),
        );
    }

    private static function root(string $xml): DOMElement
    {
        $root = SafeXml::load($xml)->documentElement;
        self::assertInstanceOf(DOMElement::class, $root);

        return $root;
    }

    private static function huella(DOMElement $root): string
    {
        $value = null;
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === VerifactuFormat::SF_NS && $child->localName === 'Huella') {
                $value = trim($child->textContent);
            }
        }

        return (string) $value;
    }
}
