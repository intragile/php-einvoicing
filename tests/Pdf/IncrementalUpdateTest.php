<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Pdf;

use ErrorException;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;
use IntrAgile\EInvoicing\Pdf\IncrementalUpdate;
use IntrAgile\EInvoicing\Pdf\PdfDictionary;
use IntrAgile\EInvoicing\Pdf\PdfDocument;
use IntrAgile\EInvoicing\Pdf\PdfName;
use IntrAgile\EInvoicing\Pdf\PdfRef;
use IntrAgile\EInvoicing\Pdf\PdfStream;
use IntrAgile\EInvoicing\Pdf\PdfString;
use IntrAgile\EInvoicing\Pdf\Serializer;
use IntrAgile\EInvoicing\Tests\Support\PdfBuilder;
use PHPUnit\Framework\TestCase;

final class IncrementalUpdateTest extends TestCase
{
    private const string CATALOG = '<< /Type /Catalog /Pages 2 0 R >>';

    private const string PAGES = '<< /Type /Pages /Kids [] /Count 0 >>';

    protected function setUp(): void
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function test_original_bytes_are_a_prefix(): void
    {
        foreach ([$this->classicPdf(), $this->xrefStreamPdf(), $this->classicPdf(withId: false)] as $pdf) {
            $update = new IncrementalUpdate(PdfDocument::parse($pdf));
            $update->add(new PdfString('new'));

            $out = $update->toBytes();

            self::assertSame($pdf, substr($out, 0, strlen($pdf)));
            self::assertGreaterThan(strlen($pdf), strlen($out));
        }
    }

    public function test_newline_is_added_when_the_original_does_not_end_in_one(): void
    {
        $pdf = rtrim($this->classicPdf());
        $update = new IncrementalUpdate(PdfDocument::parse($pdf));
        $update->add(new PdfString('new'));

        $out = $update->toBytes();

        self::assertSame($pdf."\n", substr($out, 0, strlen($pdf) + 1));
        self::assertFalse(PdfDocument::parse($out)->reconstructed());
    }

    public function test_update_reparses_and_newest_catalog_wins(): void
    {
        foreach ([$this->classicPdf(), $this->xrefStreamPdf(), $this->objectStreamPdf(), $this->hybridPdf()] as $pdf) {
            $base = PdfDocument::parse($pdf);
            $update = new IncrementalUpdate($base);
            $note = $update->add(new PdfString('added'));
            self::assertSame($base->size(), $note->number);
            $update->replace(1, $base->catalog()->with('Note', $note));

            $out = PdfDocument::parse($update->toBytes());

            self::assertFalse($out->reconstructed());
            self::assertEquals($note, $out->catalog()->get('Note'));
            self::assertEquals(new PdfString('added'), $out->resolve($out->catalog()->get('Note')));
            self::assertEquals(new PdfName('Catalog'), $out->catalog()->get('Type'));
            $pages = $out->resolve($out->catalog()->get('Pages'));
            self::assertInstanceOf(PdfDictionary::class, $pages);
            self::assertSame(0, $pages->get('Count'));
            self::assertSame($base->startXref(), $out->trailer()->get('Prev'));
            self::assertGreaterThan($base->size(), $out->size());
        }
    }

    public function test_classic_input_gets_classic_update(): void
    {
        $pdf = $this->classicPdf();
        $base = PdfDocument::parse($pdf);
        $update = new IncrementalUpdate($base);
        $update->add(new PdfString('a'));
        $update->add(new PdfString('b'));
        $update->replace(1, $base->catalog()->with('Lang', new PdfString('en')));

        $out = $update->toBytes();
        $tail = substr($out, strlen($pdf));
        $document = PdfDocument::parse($out);

        self::assertFalse($document->usesXrefStream());
        self::assertStringContainsString("xref\n1 1\n", $tail);
        self::assertStringContainsString("5 2\n", $tail);
        self::assertStringContainsString("trailer\n<<", $tail);
        self::assertSame(7, $document->size());
        self::assertEquals(new PdfRef(1, 0), $document->trailer()->get('Root'));
        self::assertEquals(new PdfRef(4, 0), $document->trailer()->get('Info'));
        self::assertEquals(new PdfString('a'), $document->object(5));
        self::assertEquals(new PdfString('b'), $document->object(6));
    }

    public function test_xref_stream_input_gets_xref_stream_update(): void
    {
        $pdf = $this->xrefStreamPdf();
        $base = PdfDocument::parse($pdf);
        $update = new IncrementalUpdate($base);
        $added = $update->add(new PdfStream(new PdfDictionary(['Type' => new PdfName('Test')]), 'payload'));

        $out = $update->toBytes();
        $tail = substr($out, strlen($pdf));
        $document = PdfDocument::parse($out);

        self::assertTrue($document->usesXrefStream());
        self::assertStringNotContainsString("\nxref", $tail);
        self::assertStringContainsString('/Type /XRef', $tail);
        self::assertFalse($document->reconstructed());
        $stream = $document->object($added->number);
        self::assertInstanceOf(PdfStream::class, $stream);
        self::assertSame('payload', $document->decodedStream($stream));
        // The xref stream itself takes the next number after the added objects.
        self::assertSame($added->number + 2, $document->size());
    }

    public function test_id_keeps_the_original_first_element(): void
    {
        $base = PdfDocument::parse($this->classicPdf());
        $update = new IncrementalUpdate($base, str_repeat("\x01", 16));
        $update->add(new PdfString('x'));

        $id = PdfDocument::parse($update->toBytes())->trailer()->get('ID');

        self::assertIsArray($id);
        self::assertEquals(new PdfString('ORIGINAL-FIRST-ID', true), $id[0]);
        self::assertEquals(new PdfString(str_repeat("\x01", 16), true), $id[1]);
    }

    public function test_id_is_created_when_the_original_has_none(): void
    {
        $base = PdfDocument::parse($this->classicPdf(withId: false));
        $update = new IncrementalUpdate($base, str_repeat("\x02", 16));
        $update->add(new PdfString('x'));

        $id = PdfDocument::parse($update->toBytes())->trailer()->get('ID');

        self::assertIsArray($id);
        self::assertEquals(new PdfString(str_repeat("\x02", 16), true), $id[0]);
        self::assertEquals(new PdfString(str_repeat("\x02", 16), true), $id[1]);
    }

    public function test_trailer_extra_entries_are_written(): void
    {
        $base = PdfDocument::parse($this->classicPdf());
        $update = new IncrementalUpdate($base);
        $info = $update->add(new PdfDictionary(['Title' => new PdfString('New')]));

        $document = PdfDocument::parse($update->toBytes(new PdfDictionary(['Info' => $info])));

        self::assertEquals($info, $document->trailer()->get('Info'));
    }

    public function test_reconstructed_input_is_refused_for_update(): void
    {
        $pdf = $this->classicPdf();
        $start = (int) strrpos($pdf, "\nxref") + 1;
        $broken = str_replace('startxref'."\n".$start, 'startxref'."\n".($start + 3), $pdf);
        $base = PdfDocument::parse($broken);
        self::assertTrue($base->reconstructed());

        $this->expectException(UnreadablePdf::class);
        $this->expectExceptionMessageMatches('/reconstructed.*incremental update.*re-sav/is');

        new IncrementalUpdate($base);
    }

    public function test_serializer_writes_names_strings_and_numeric_keys(): void
    {
        $dictionary = new PdfDictionary([
            'Subtype' => new PdfName('text/xml'),
            'A B' => new PdfName('x#y'),
        ]);
        // with() takes a string key; PHP stores the numeric-looking "1" as an int key.
        $dictionary = $dictionary
            ->with('1', new PdfString("a(b)\\c\r"))
            ->with('H', new PdfString("\x00\xFF", true))
            ->with('F', 1.5)
            ->with('N', null)
            ->with('T', true)
            ->with('L', [1, new PdfRef(3, 0)]);

        self::assertSame(
            '<< /Subtype /text#2Fxml /A#20B /x#23y /1 (a\\(b\\)\\\\c\\r) /H <00FF> /F 1.5 /N null /T true /L [1 3 0 R] >>',
            Serializer::value($dictionary),
        );
    }

    private function classicPdf(bool $withId = true): string
    {
        return (new PdfBuilder('1.4'))
            ->object(1, self::CATALOG)
            ->object(2, self::PAGES)
            ->object(3, '(unused)')
            ->object(4, '<< /Title (Base) >>')
            ->trailer('/Root 1 0 R /Info 4 0 R'.($withId ? ' /ID [<4F524947494E414C2D46495253542D4944> <00>]' : ''))
            ->classic();
    }

    private function xrefStreamPdf(): string
    {
        return (new PdfBuilder('1.5'))
            ->object(1, self::CATALOG)
            ->object(2, self::PAGES)
            ->trailer('/Root 1 0 R')
            ->xrefStream();
    }

    private function objectStreamPdf(): string
    {
        return (new PdfBuilder('1.5'))
            ->objectStream(3, [1 => self::CATALOG, 2 => self::PAGES])
            ->trailer('/Root 1 0 R')
            ->xrefStream();
    }

    private function hybridPdf(): string
    {
        return (new PdfBuilder('1.5'))
            ->object(2, self::PAGES)
            ->objectStream(3, [1 => self::CATALOG])
            ->trailer('/Root 1 0 R')
            ->hybrid();
    }
}
