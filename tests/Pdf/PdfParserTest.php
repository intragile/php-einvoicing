<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Pdf;

use ErrorException;
use IntrAgile\EInvoicing\Pdf\Exception\EncryptedPdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnsupportedPdfFeature;
use IntrAgile\EInvoicing\Pdf\PdfDictionary;
use IntrAgile\EInvoicing\Pdf\PdfDocument;
use IntrAgile\EInvoicing\Pdf\PdfName;
use IntrAgile\EInvoicing\Pdf\PdfParser;
use IntrAgile\EInvoicing\Pdf\PdfRef;
use IntrAgile\EInvoicing\Pdf\PdfStream;
use IntrAgile\EInvoicing\Pdf\PdfString;
use IntrAgile\EInvoicing\Tests\Support\PdfBuilder;
use PHPUnit\Framework\TestCase;

final class PdfParserTest extends TestCase
{
    private const string CATALOG = '<< /Type /Catalog /Pages 2 0 R >>';

    private const string PAGES = '<< /Type /Pages /Kids [] /Count 0 >>';

    protected function setUp(): void
    {
        // Any PHP notice or warning escaping the parser fails the test.
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function test_classic_xref(): void
    {
        $pdf = $this->minimal(new PdfBuilder('1.4'))
            ->stream(3, '/Type /EmbeddedFile', 'hello world', flate: true)
            ->trailer('/Root 1 0 R')
            ->classic();

        $document = PdfDocument::parse($pdf);

        self::assertSame('1.4', $document->version());
        self::assertFalse($document->reconstructed());
        self::assertFalse($document->usesXrefStream());
        self::assertSame(4, $document->size());
        self::assertSame($pdf, $document->bytes());
        self::assertSame((int) strrpos($pdf, "\nxref") + 1, $document->startXref());
        self::assertEquals(new PdfName('Catalog'), $document->catalog()->get('Type'));
        $pages = $document->resolve($document->catalog()->get('Pages'));
        self::assertInstanceOf(PdfDictionary::class, $pages);
        self::assertSame(0, $pages->get('Count'));
        $stream = $document->object(3);
        self::assertInstanceOf(PdfStream::class, $stream);
        self::assertSame('hello world', $document->decodedStream($stream));
        self::assertNull($document->object(99));
    }

    public function test_xref_stream_with_png_predictor(): void
    {
        $builder = $this->minimal(new PdfBuilder('1.5'));
        for ($n = 3; $n <= 12; $n++) {
            $builder->object($n, '('.str_repeat('x', $n * 37).')');
        }
        $pdf = $builder->trailer('/Root 1 0 R')->xrefStream();

        $document = PdfDocument::parse($pdf);

        self::assertTrue($document->usesXrefStream());
        self::assertFalse($document->reconstructed());
        self::assertSame(14, $document->size());
        for ($n = 3; $n <= 12; $n++) {
            self::assertEquals(new PdfString(str_repeat('x', $n * 37)), $document->object($n));
        }
        $xref = $document->resolve(new PdfRef(13, 0));
        self::assertInstanceOf(PdfStream::class, $xref);
        self::assertEquals(new PdfName('XRef'), $xref->dictionary->get('Type'));
    }

    public function test_object_stream(): void
    {
        $pdf = (new PdfBuilder('1.5'))
            ->objectStream(5, [1 => self::CATALOG, 2 => self::PAGES, 3 => '[1 2.5 (three) /Four 2 0 R]'])
            ->trailer('/Root 1 0 R')
            ->xrefStream();

        $document = PdfDocument::parse($pdf);

        self::assertEquals(new PdfName('Catalog'), $document->catalog()->get('Type'));
        self::assertEquals([1, 2.5, new PdfString('three'), new PdfName('Four'), new PdfRef(2, 0)], $document->object(3));
        self::assertInstanceOf(PdfStream::class, $document->object(5));
    }

    public function test_prev_chain_newest_wins(): void
    {
        $builder = $this->minimal(new PdfBuilder('1.4'))
            ->object(3, '(old)')
            ->object(4, '<< /Producer (first) >>')
            ->trailer('/Root 1 0 R /Info 4 0 R');
        $builder->classic();
        $builder->object(3, '(new)')->trailer('/Root 1 0 R');
        $second = $builder->classic();
        $builder->object(5, '(third)')->trailer('/Root 1 0 R');
        $pdf = $builder->xrefStream();

        $document = PdfDocument::parse($pdf);

        self::assertEquals(new PdfString('new'), $document->object(3));
        self::assertEquals(new PdfString('third'), $document->object(5));
        self::assertEquals(new PdfRef(4, 0), $document->trailer()->get('Info'));
        self::assertTrue($document->usesXrefStream());
        self::assertSame(strlen($second) + strlen('5 0 obj'."\n".'(third)'."\n".'endobj'."\n"), $document->startXref());
        self::assertSame(7, $document->size());
        self::assertFalse($document->reconstructed());
    }

    public function test_hybrid_xrefstm(): void
    {
        $pdf = (new PdfBuilder('1.5'))
            ->object(1, self::CATALOG)
            ->objectStream(6, [2 => self::PAGES, 3 => '(hidden)'])
            ->trailer('/Root 1 0 R')
            ->hybrid();

        $document = PdfDocument::parse($pdf);

        self::assertFalse($document->usesXrefStream());
        self::assertFalse($document->reconstructed());
        self::assertEquals(new PdfString('hidden'), $document->object(3));
        $pages = $document->resolve(new PdfRef(2, 0));
        self::assertInstanceOf(PdfDictionary::class, $pages);
        self::assertSame(0, $pages->get('Count'));
    }

    public function test_free_entries_and_generations(): void
    {
        $builder = $this->minimal(new PdfBuilder('1.4'))
            ->object(3, '(doomed)')
            ->object(4, '(regenerated)', 2)
            ->trailer('/Root 1 0 R');
        $builder->classic();
        $pdf = $builder->free(3, 1)->trailer('/Root 1 0 R')->classic();

        $document = PdfDocument::parse($pdf);

        self::assertNull($document->object(3));
        self::assertNull($document->resolve(new PdfRef(3, 0)));
        self::assertNull($document->object(0));
        self::assertEquals(new PdfString('regenerated'), $document->resolve(new PdfRef(4, 2)));
        self::assertFalse($document->reconstructed());
    }

    public function test_strings_literal_escapes_and_hex(): void
    {
        $pdf = $this->minimal(new PdfBuilder)
            ->object(3, "[(a\\(b\\)c\\\\d\\n\\r\\t\\b\\f\\101\\0618 (nested (twice)) x\\\ny\r\nz\\q) <48 65 6C 6c 6F2> <>]")
            ->trailer('/Root 1 0 R')
            ->classic();

        $value = PdfDocument::parse($pdf)->object(3);

        self::assertEquals([
            new PdfString("a(b)c\\d\n\r\t\x08\x0CA18 (nested (twice)) xy\nzq"),
            new PdfString('Hello ', true),
            new PdfString('', true),
        ], $value);
    }

    public function test_names_with_hash_escapes(): void
    {
        $pdf = $this->minimal(new PdfBuilder)
            ->object(3, '<< /A#20B /Lime#23Green /paired#28#29parentheses#2 true /1 (one) /Empty null >>')
            ->trailer('/Root 1 0 R')
            ->classic();

        $value = PdfDocument::parse($pdf)->object(3);

        self::assertInstanceOf(PdfDictionary::class, $value);
        self::assertEquals(new PdfName('Lime#Green'), $value->get('A B'));
        self::assertTrue($value->get('paired()parentheses#2'));
        self::assertEquals(new PdfString('one'), $value->get('1'));
        self::assertTrue($value->has('Empty'));
        self::assertNull($value->get('Empty'));
        self::assertFalse($value->has('Missing'));
    }

    public function test_dictionary_with_is_immutable(): void
    {
        $original = new PdfDictionary(['Type' => new PdfName('Catalog')]);
        $changed = $original->with('Version', new PdfName('1.7'));

        self::assertFalse($original->has('Version'));
        self::assertEquals(new PdfName('1.7'), $changed->get('Version'));
        self::assertEquals(new PdfName('Catalog'), $changed->get('Type'));
    }

    public function test_wrong_xref_offset_falls_back_to_reconstruction(): void
    {
        $pdf = $this->minimal(new PdfBuilder)->object(3, '(data)')->trailer('/Root 1 0 R')->classic();
        $start = (int) strrpos($pdf, "\nxref") + 1;
        $broken = str_replace('startxref'."\n".$start, 'startxref'."\n".($start + 3), $pdf);

        $document = PdfDocument::parse($broken);

        self::assertTrue($document->reconstructed());
        self::assertEquals(new PdfName('Catalog'), $document->catalog()->get('Type'));
        self::assertEquals(new PdfString('data'), $document->object(3));
    }

    public function test_wrong_object_offset_falls_back_to_reconstruction(): void
    {
        $pdf = $this->minimal(new PdfBuilder)->object(3, '(data)')->trailer('/Root 1 0 R')->classic();
        $offset = strpos($pdf, '3 0 obj') ?: 0;
        $broken = str_replace(sprintf('%010d 00000 n', $offset), sprintf('%010d 00000 n', $offset - 2), $pdf);

        $document = PdfDocument::parse($broken);

        self::assertTrue($document->reconstructed());
        self::assertEquals(new PdfString('data'), $document->object(3));
    }

    public function test_reconstruction_reads_object_streams_and_xref_stream_trailer(): void
    {
        $pdf = (new PdfBuilder('1.5'))
            ->objectStream(5, [1 => self::CATALOG, 2 => self::PAGES, 3 => '(packed)'])
            ->trailer('/Root 1 0 R')
            ->xrefStream();
        $broken = (string) preg_replace('/startxref\n\d+/', "startxref\n1", $pdf);

        $document = PdfDocument::parse($broken);

        self::assertTrue($document->reconstructed());
        self::assertTrue($document->usesXrefStream());
        self::assertEquals(new PdfString('packed'), $document->object(3));
    }

    public function test_offsets_outside_the_file_fall_back_to_reconstruction(): void
    {
        foreach (['/Prev', '/XRefStm'] as $key) {
            $pdf = $this->minimal(new PdfBuilder)->object(3, '(data)')->trailer('/Root 1 0 R '.$key.' -99999999')->classic();

            $document = PdfDocument::parse($pdf);

            self::assertTrue($document->reconstructed(), $key);
            self::assertEquals(new PdfString('data'), $document->object(3));
        }
    }

    public function test_indirect_filter_on_xref_stream_falls_back_to_reconstruction(): void
    {
        $pdf = (new PdfBuilder('1.5'))
            ->objectStream(5, [1 => self::CATALOG, 2 => self::PAGES, 3 => '(packed)'])
            ->trailer('/Root 1 0 R')
            ->xrefStream();
        $start = (int) strrpos($pdf, '/Type /XRef');
        $broken = substr($pdf, 0, $start).str_replace('/Filter /FlateDecode /DecodeParms', '/Filter 3 0 R /DecodeParms', substr($pdf, $start));
        self::assertNotSame($pdf, $broken);

        $document = PdfDocument::parse($broken);

        self::assertTrue($document->reconstructed());
        self::assertEquals(new PdfString('packed'), $document->object(3));
    }

    public function test_sparse_xref_stream_falls_back_to_reconstruction(): void
    {
        // A full save that keeps the original object numbers: a small file whose xref stream lists
        // /Size rows, nearly all free. More rows than the file could hold are not read as an index.
        foreach ([5000, 60000] as $size) {
            $pdf = self::sparseXrefStream($size, padding: 0);
            self::assertLessThan(1024, strlen($pdf));

            $document = PdfDocument::parse($pdf);

            self::assertTrue($document->reconstructed(), 'Size '.$size);
            self::assertTrue($document->catalog()->isName('Type', 'Catalog'));
            $pages = $document->resolve($document->catalog()->get('Pages'));
            self::assertInstanceOf(PdfDictionary::class, $pages);
            self::assertSame(0, $pages->get('Count'));
        }
    }

    public function test_sparse_xref_stream_within_the_entry_budget_is_read(): void
    {
        $document = PdfDocument::parse(self::sparseXrefStream(5000, padding: 5000 * PdfParser::BYTES_PER_ENTRY));

        self::assertFalse($document->reconstructed());
        self::assertSame(5000, $document->size());
        self::assertTrue($document->catalog()->isName('Type', 'Catalog'));
    }

    public function test_predictor_budget_while_indexing_falls_back_to_reconstruction(): void
    {
        // 700 000 seven-byte rows pass the entry budget of the padded file but exceed the
        // predictor budget of 4 MiB; the index is then rebuilt by scanning.
        $rows = intdiv(PdfParser::MAX_PREDICTOR_BYTES, 7) + 100_000;
        $pdf = self::sparseXrefStream($rows, padding: $rows * PdfParser::BYTES_PER_ENTRY, width: 4);
        $started = hrtime(true);

        $document = PdfDocument::parse($pdf);

        self::assertLessThan(1.0, (hrtime(true) - $started) / 1e9);
        self::assertTrue($document->reconstructed());
        self::assertTrue($document->catalog()->isName('Type', 'Catalog'));
    }

    public function test_reconstruction_finds_catalog_without_trailer(): void
    {
        $pdf = $this->minimal(new PdfBuilder)->trailer('/Root 1 0 R')->classic();
        $broken = substr($pdf, 0, (int) strrpos($pdf, "\nxref") + 1);

        $document = PdfDocument::parse($broken);

        self::assertTrue($document->reconstructed());
        self::assertEquals(new PdfName('Catalog'), $document->catalog()->get('Type'));
    }

    public function test_cr_only_line_endings(): void
    {
        $pdf = $this->minimal(new PdfBuilder('1.4', "\r"))
            ->stream(3, '/Type /EmbeddedFile', "line1\nline2", flate: true)
            ->trailer('/Root 1 0 R')
            ->classic();
        self::assertStringStartsWith("%PDF-1.4\r%", $pdf);
        self::assertStringContainsString("\rstream\r", $pdf);

        $document = PdfDocument::parse($pdf);

        self::assertFalse($document->reconstructed());
        $stream = $document->object(3);
        self::assertInstanceOf(PdfStream::class, $stream);
        self::assertSame("line1\nline2", $document->decodedStream($stream));
    }

    public function test_indirect_length(): void
    {
        $data = "binary\x00endstreamish\r\ndata";
        $pdf = $this->minimal(new PdfBuilder)
            ->stream(3, '/Type /EmbeddedFile', $data, length: '4 0 R')
            ->object(4, (string) strlen($data))
            ->trailer('/Root 1 0 R')
            ->classic();

        $document = PdfDocument::parse($pdf);
        $stream = $document->object(3);

        self::assertInstanceOf(PdfStream::class, $stream);
        self::assertSame($data, $stream->raw);
        self::assertFalse($document->reconstructed());
    }

    public function test_indirect_length_inside_object_stream(): void
    {
        $data = 'payload bytes';
        $pdf = (new PdfBuilder('1.5'))
            ->objectStream(6, [1 => self::CATALOG, 2 => self::PAGES, 4 => (string) strlen($data)])
            ->stream(3, '/Type /EmbeddedFile', $data, length: '4 0 R')
            ->trailer('/Root 1 0 R')
            ->xrefStream();

        $stream = PdfDocument::parse($pdf)->object(3);

        self::assertInstanceOf(PdfStream::class, $stream);
        self::assertSame($data, $stream->raw);
    }

    public function test_wrong_length_uses_endstream(): void
    {
        foreach ([3, 500, '9 0 R', '-1'] as $length) {
            $pdf = $this->minimal(new PdfBuilder)
                ->stream(3, '/Type /EmbeddedFile', 'the real stream data', length: $length)
                ->trailer('/Root 1 0 R')
                ->classic();

            $stream = PdfDocument::parse($pdf)->object(3);

            self::assertInstanceOf(PdfStream::class, $stream);
            self::assertSame('the real stream data', $stream->raw, 'Length '.$length);
        }
    }

    public function test_missing_endstream_is_unreadable(): void
    {
        $pdf = $this->minimal(new PdfBuilder)
            ->object(3, "<< /Length 999 >>\nstream\nno end in sight")
            ->trailer('/Root 1 0 R')
            ->classic();
        $document = PdfDocument::parse($pdf);

        $this->expectException(UnreadablePdf::class);
        $this->expectExceptionMessage('endstream');
        $document->object(3);
    }

    public function test_trailing_garbage_after_eof(): void
    {
        $pdf = $this->minimal(new PdfBuilder)->object(3, '(data)')->trailer('/Root 1 0 R')->classic();
        $pdf .= "\x00\xFF garbage % not pdf )>> ]\n".str_repeat("\xAB", 3000)."\n%%EOF trailing";

        $document = PdfDocument::parse($pdf);

        self::assertFalse($document->reconstructed());
        self::assertEquals(new PdfString('data'), $document->object(3));
    }

    public function test_number_too_large_for_a_float_is_unreadable(): void
    {
        foreach ([str_repeat('9', 400), '-'.str_repeat('9', 400), str_repeat('9', 400).'.5'] as $number) {
            $pdf = (new PdfBuilder)
                ->object(1, '<< /Type /Catalog /Pages 2 0 R /Junk '.$number.' >>')
                ->object(2, self::PAGES)
                ->trailer('/Root 1 0 R')
                ->classic();
            try {
                PdfDocument::parse($pdf);
                self::fail('Expected UnreadablePdf for '.substr($number, 0, 5).'...');
            } catch (UnreadablePdf $e) {
                self::assertSame(UnreadablePdf::MALFORMED, $e->getCode());
            }
        }
    }

    public function test_not_a_pdf_is_unreadable(): void
    {
        $this->expectException(UnreadablePdf::class);
        $this->expectExceptionMessage('%PDF-');
        PdfDocument::parse('<?xml version="1.0"?><Invoice/>');
    }

    public function test_encrypted_is_refused(): void
    {
        $pdf = $this->minimal(new PdfBuilder)
            ->object(3, '<< /Filter /Standard /V 2 /R 3 /O <00> /U <00> /P -4 >>')
            ->trailer('/Root 1 0 R /Encrypt 3 0 R /ID [<01> <02>]')
            ->classic();

        $this->expectException(EncryptedPdf::class);
        PdfDocument::parse($pdf);
    }

    public function test_unknown_filter_names_filter(): void
    {
        $pdf = $this->minimal(new PdfBuilder)
            ->object(3, "<< /Filter [/FlateDecode /LZWDecode] /Length 4 >>\nstream\nabcd\nendstream")
            ->trailer('/Root 1 0 R')
            ->classic();
        $document = PdfDocument::parse($pdf);
        $stream = $document->object(3);
        self::assertInstanceOf(PdfStream::class, $stream);

        $this->expectException(UnsupportedPdfFeature::class);
        $this->expectExceptionMessage('LZWDecode');
        $document->decodedStream(new PdfStream($stream->dictionary->with('Filter', new PdfName('LZWDecode')), $stream->raw));
    }

    public function test_image_streams_are_not_decoded(): void
    {
        $pdf = $this->minimal(new PdfBuilder)
            ->stream(3, '/Type /XObject /Subtype /Image', 'pixels', flate: true)
            ->trailer('/Root 1 0 R')
            ->classic();
        $document = PdfDocument::parse($pdf);
        $stream = $document->object(3);
        self::assertInstanceOf(PdfStream::class, $stream);

        $this->expectException(UnsupportedPdfFeature::class);
        $this->expectExceptionMessage('Image');
        $document->decodedStream($stream);
    }

    public function test_tiff_predictor(): void
    {
        $raw = "\x10\x20\x30\x11\x22\x33";
        $encoded = "\x10\x20\x30\x01\x02\x03";
        $pdf = $this->minimal(new PdfBuilder)
            ->stream(3, '/DecodeParms << /Predictor 2 /Colors 3 /Columns 2 >>', $encoded, flate: true)
            ->trailer('/Root 1 0 R')
            ->classic();
        $document = PdfDocument::parse($pdf);
        $stream = $document->object(3);
        self::assertInstanceOf(PdfStream::class, $stream);

        self::assertSame($raw, $document->decodedStream($stream));
    }

    /**
     * A catalog, a page tree and an xref stream of $size rows (W [1 $width 2], PNG Up) whose rows
     * between the page tree and the stream itself are free, after $padding bytes of comment.
     */
    private static function sparseXrefStream(int $size, int $padding, int $width = 2): string
    {
        $pdf = "%PDF-1.7\n".($padding > 0 ? '%'.str_repeat('x', $padding)."\n" : '');
        $catalog = strlen($pdf);
        $pdf .= "1 0 obj\n".self::CATALOG."\nendobj\n";
        $pages = strlen($pdf);
        $pdf .= "2 0 obj\n".self::PAGES."\nendobj\n";
        $start = strlen($pdf);
        $row = static fn (int $type, int $field2, int $field3): string => pack('C', $type).substr(pack('J', $field2), 8 - $width).pack('n', $field3);
        $raw = [$row(0, 0, 65535), $row(1, $catalog, 0), $row(1, $pages, 0), $row(0, 0, 1)];
        $previous = str_repeat("\0", 3 + $width);
        $encoded = '';
        foreach ($raw as $r) {
            $encoded .= "\x02";
            for ($i = 0; $i < 3 + $width; $i++) {
                $encoded .= chr((ord($r[$i]) - ord($previous[$i])) & 0xFF);
            }
            $previous = $r;
        }
        // Further free rows equal the previous one, so Up encodes them as zeros.
        $encoded .= str_repeat("\x02".str_repeat("\0", 3 + $width), $size - 5);
        $last = $row(1, $start, 0);
        $encoded .= "\x02";
        for ($i = 0; $i < 3 + $width; $i++) {
            $encoded .= chr((ord($last[$i]) - ord($previous[$i])) & 0xFF);
        }
        $data = (string) gzcompress($encoded, 9);

        return $pdf.($size - 1)." 0 obj\n<< /Type /XRef /Size ".$size.' /W [1 '.$width.' 2] /Root 1 0 R /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns '.(3 + $width).' >> /Length '.strlen($data)." >>\nstream\n"
            .$data."\nendstream\nendobj\nstartxref\n".$start."\n%%EOF\n";
    }

    private function minimal(PdfBuilder $builder): PdfBuilder
    {
        return $builder->object(1, self::CATALOG)->object(2, self::PAGES);
    }
}
