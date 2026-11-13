<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Pdf;

use Closure;
use ErrorException;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;
use IntrAgile\EInvoicing\Pdf\PdfDocument;
use IntrAgile\EInvoicing\Pdf\PdfParser;
use IntrAgile\EInvoicing\Pdf\PdfStream;
use IntrAgile\EInvoicing\Tests\Support\PdfBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Inputs built to exhaust time or memory. Each must end in UnreadablePdf within a second, without
 * a PHP warning.
 */
final class HostilePdfTest extends TestCase
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

    public function test_flate_bomb_is_refused(): void
    {
        $bomb = self::flateBomb(PdfParser::MAX_STREAM_BYTES + 1024 * 1024);
        self::assertLessThan(1024, strlen($bomb));
        $pdf = (new PdfBuilder)
            ->object(1, self::CATALOG)
            ->object(2, self::PAGES)
            ->object(3, '<< /Type /EmbeddedFile /Filter [/FlateDecode /FlateDecode] /Length '.strlen($bomb)." >>\nstream\n".$bomb."\nendstream")
            ->trailer('/Root 1 0 R')
            ->classic();
        $document = PdfDocument::parse($pdf);
        $stream = $document->object(3);
        self::assertInstanceOf(PdfStream::class, $stream);

        $this->assertRefusedQuickly(static fn () => $document->decodedStream($stream), 'exceeds');
    }

    public function test_flate_bomb_in_object_stream_is_refused(): void
    {
        $bomb = self::flateBomb(PdfParser::MAX_STREAM_BYTES + 1024 * 1024);
        $pdf = (new PdfBuilder('1.5'))
            ->objectStream(5, [1 => self::CATALOG, 2 => self::PAGES])
            ->trailer('/Root 1 0 R')
            ->xrefStream();
        // Swap the object stream's data for the bomb, keeping every offset before it valid.
        $start = strpos($pdf, '5 0 obj') ?: 0;
        $end = strpos($pdf, 'endobj', $start) ?: 0;
        $object = "5 0 obj\n<< /Type /ObjStm /N 2 /First 8 /Filter [/FlateDecode /FlateDecode] /Length ".strlen($bomb)." >>\nstream\n".$bomb."\nendstream\n";
        $broken = substr($pdf, 0, $start).$object.substr($pdf, $end);

        $this->assertRefusedQuickly(static fn () => PdfDocument::parse($broken));
    }

    public function test_prev_loop_is_refused(): void
    {
        // A section whose /Prev is itself.
        $pdf = (new PdfBuilder)
            ->object(1, self::CATALOG)
            ->object(2, self::PAGES)
            ->trailer('/Root 1 0 R /Prev 0000000000')
            ->classic();
        $start = (int) strrpos($pdf, "\nxref") + 1;
        $self = str_replace('/Prev 0000000000', sprintf('/Prev %010d', $start), $pdf);
        $this->assertRefusedQuickly(static fn () => PdfDocument::parse($self), '/Prev');

        // Two sections pointing at each other.
        $builder = (new PdfBuilder)
            ->object(1, self::CATALOG)
            ->object(2, self::PAGES)
            ->trailer('/Root 1 0 R /Prev 0000000000');
        $builder->classic();
        $builder->object(3, '(second)')->trailer('/Root 1 0 R');
        $pdf = $builder->xrefStream();
        preg_match('/startxref\n(\d+)\n%%EOF\n$/', $pdf, $m);
        $cycle = str_replace('/Prev 0000000000', sprintf('/Prev %010d', (int) ($m[1] ?? 0)), $pdf);
        $this->assertRefusedQuickly(static fn () => PdfDocument::parse($cycle), '/Prev');
    }

    public function test_long_prev_chain_is_refused(): void
    {
        $builder = (new PdfBuilder)->object(1, self::CATALOG)->object(2, self::PAGES)->trailer('/Root 1 0 R');
        $builder->classic();
        for ($i = 0; $i <= PdfParser::MAX_PREV_CHAIN; $i++) {
            $builder->object(3, '('.$i.')')->trailer('/Root 1 0 R');
            $pdf = $builder->classic();
        }

        $this->assertRefusedQuickly(static fn () => PdfDocument::parse($pdf), '/Prev');
    }

    public function test_deep_nesting_is_refused(): void
    {
        $deep = str_repeat('[', 10000).str_repeat(']', 10000);
        $pdf = (new PdfBuilder)
            ->object(1, '<< /Type /Catalog /Pages 2 0 R /Deep '.$deep.' >>')
            ->object(2, self::PAGES)
            ->trailer('/Root 1 0 R')
            ->classic();
        $this->assertRefusedQuickly(static fn () => PdfDocument::parse($pdf), 'nest');

        $dictionaries = str_repeat('<< /A ', 10000).str_repeat('>> ', 10000);
        $pdf = (new PdfBuilder)
            ->object(1, self::CATALOG)
            ->object(2, self::PAGES)
            ->object(3, $dictionaries)
            ->trailer('/Root 1 0 R')
            ->classic();
        $document = PdfDocument::parse($pdf);
        $this->assertRefusedQuickly(static fn () => $document->object(3), 'nest');
    }

    public function test_self_referencing_object_stream_is_refused(): void
    {
        // Object stream 5 lists itself as one of its members.
        $pdf = (new PdfBuilder('1.5'))
            ->objectStream(5, [1 => self::CATALOG, 2 => self::PAGES, 5 => '0'])
            ->trailer('/Root 1 0 R')
            ->xrefStream();
        $this->assertRefusedQuickly(static fn () => PdfDocument::parse($pdf), 'object stream');

        // Object streams 5 and 6 each live inside the other.
        $pdf = (new PdfBuilder('1.5'))
            ->objectStream(5, [1 => self::CATALOG, 6 => '0'])
            ->objectStream(6, [2 => self::PAGES, 5 => '0'])
            ->trailer('/Root 1 0 R')
            ->xrefStream();
        $this->assertRefusedQuickly(static fn () => PdfDocument::parse($pdf), 'object stream');
    }

    public function test_reference_cycle_is_refused(): void
    {
        $pdf = (new PdfBuilder)
            ->object(1, self::CATALOG)
            ->object(2, self::PAGES)
            ->object(3, '4 0 R')
            ->object(4, '3 0 R')
            ->trailer('/Root 1 0 R')
            ->classic();
        $document = PdfDocument::parse($pdf);

        $this->assertRefusedQuickly(static fn () => $document->resolve($document->object(3)), 'cycle');
    }

    public function test_huge_size_is_refused(): void
    {
        $classic = (new PdfBuilder)->object(1, self::CATALOG)->object(2, self::PAGES)->trailer('/Root 1 0 R')->classic();
        $this->assertRefusedQuickly(static fn () => PdfDocument::parse(str_replace('/Size 3', '/Size 99999999999', $classic)), '/Size');
        $this->assertRefusedQuickly(static fn () => PdfDocument::parse(str_replace('/Size 3', '/Size 8388609', $classic)), '/Size');

        $stream = (new PdfBuilder('1.5'))->objectStream(5, [1 => self::CATALOG, 2 => self::PAGES])->trailer('/Root 1 0 R')->xrefStream();
        $huge = (string) preg_replace('/\/Index \[[^\]]*\] \/Size 7/', '/Size 4294967296', $stream);
        self::assertNotSame($stream, $huge);
        $this->assertRefusedQuickly(static fn () => PdfDocument::parse($huge), '/Size');
    }

    /**
     * Two Flate layers: zeros deflated once give about 1/1000 of their size, and that highly
     * repetitive output deflates again to a few hundred bytes.
     */
    private static function flateBomb(int $size): string
    {
        $context = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        self::assertNotFalse($context);
        $chunk = str_repeat("\0", 1024 * 1024);
        $inner = '';
        for ($written = 0; $written < $size; $written += strlen($chunk)) {
            $inner .= deflate_add($context, $chunk, ZLIB_NO_FLUSH);
        }
        $inner .= deflate_add($context, '', ZLIB_FINISH);

        return (string) gzcompress($inner, 9);
    }

    /** @param Closure(): mixed $action */
    private function assertRefusedQuickly(Closure $action, ?string $message = null): void
    {
        $started = hrtime(true);
        try {
            $action();
            self::fail('Expected UnreadablePdf');
        } catch (UnreadablePdf $e) {
            if ($message !== null) {
                self::assertStringContainsStringIgnoringCase($message, $e->getMessage());
            }
        }
        $seconds = (hrtime(true) - $started) / 1e9;
        self::assertLessThan(1.0, $seconds, sprintf('Took %.3f s', $seconds));
    }
}
