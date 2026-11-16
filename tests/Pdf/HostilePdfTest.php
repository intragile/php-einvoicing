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
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Inputs built to exhaust time or memory. Each must end in UnreadablePdf within a few CPU seconds,
 * without a PHP warning. Time is process CPU time (user + system), not wall clock, so a loaded or
 * slow machine does not fail a healthy run; the budgets sit far below what a quadratic parse costs. Tests marked to run in a separate process lower memory_limit to PHP's stock 128M
 * first, so exceeding it fails the test; the suite itself runs at 512M and may already use more
 * than 128M by the time they run.
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

    #[RunInSeparateProcess]
    public function test_flate_bomb_is_refused(): void
    {
        self::limitMemory();
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

        $this->assertRefusedQuickly(static fn () => $document->decodedStream($stream), 'exceeds', '48 MiB per stream');
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

    #[RunInSeparateProcess]
    public function test_huge_predictor_row_is_clamped_to_the_data(): void
    {
        self::limitMemory();
        // 2^24 columns of 32 colours at 16 bits declare a 1 GiB row for three bytes of data.
        $data = (string) gzcompress("\x02abc");
        $pdf = (new PdfBuilder)
            ->object(1, self::CATALOG)
            ->object(2, self::PAGES)
            ->object(3, '<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Colors 32 /BitsPerComponent 16 /Columns 16777216 >> /Length '.strlen($data)." >>\nstream\n".$data."\nendstream")
            ->trailer('/Root 1 0 R')
            ->classic();
        $before = memory_get_usage();
        memory_reset_peak_usage();
        $started = self::cpuSeconds();

        $document = PdfDocument::parse($pdf);
        $stream = $document->object(3);
        self::assertInstanceOf(PdfStream::class, $stream);

        self::assertSame('abc', $document->decodedStream($stream));
        self::assertLessThan(3.0, $seconds = self::cpuSeconds() - $started, sprintf('Took %.3f s CPU', $seconds));
        self::assertLessThan(8 * 1024 * 1024, memory_get_peak_usage() - $before);
    }

    #[RunInSeparateProcess]
    public function test_predictor_work_is_bounded(): void
    {
        self::limitMemory();
        $data = (string) gzcompress(str_repeat("\x02".str_repeat("\x01", 99), intdiv(PdfParser::MAX_PREDICTOR_BYTES, 100) + 1));
        $pdf = (new PdfBuilder)
            ->object(1, self::CATALOG)
            ->object(2, self::PAGES)
            ->object(3, '<< /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 99 >> /Length '.strlen($data)." >>\nstream\n".$data."\nendstream")
            ->trailer('/Root 1 0 R')
            ->classic();
        $document = PdfDocument::parse($pdf);
        $stream = $document->object(3);
        self::assertInstanceOf(PdfStream::class, $stream);

        $this->assertRefusedQuickly(static fn () => $document->decodedStream($stream), 'Predictor');
    }

    #[RunInSeparateProcess]
    public function test_xref_stream_claiming_millions_of_rows_is_not_read(): void
    {
        self::limitMemory();
        foreach (["\x00", "\x02", "\x03"] as $type) {
            // 8 MiB of one-byte rows deflate to about 8 KiB. The index is not read; reconstruction
            // indexes only the two objects that exist.
            $pdf = self::claimingXrefStream($type, withCatalog: true);
            self::assertLessThan(16 * 1024, strlen($pdf));
            memory_reset_peak_usage();
            $before = memory_get_usage();
            $started = self::cpuSeconds();

            $document = PdfDocument::parse($pdf);

            self::assertLessThan(3.0, $seconds = self::cpuSeconds() - $started, sprintf('Took %.3f s CPU', $seconds));
            self::assertLessThan(32 * 1024 * 1024, memory_get_peak_usage() - $before);
            self::assertTrue($document->reconstructed());
            self::assertTrue($document->catalog()->isName('Type', 'Catalog'));

            // Without a catalog object there is nothing to reconstruct.
            $empty = self::claimingXrefStream($type, withCatalog: false);
            $this->assertRefusedQuickly(static fn () => PdfDocument::parse($empty), 'catalog');
        }
    }

    #[RunInSeparateProcess]
    public function test_object_stream_claiming_millions_of_members_is_refused(): void
    {
        self::limitMemory();
        foreach ([1_000_000, 4_000_000] as $count) {
            $header = str_repeat('1 0 ', $count);
            $data = (string) gzcompress($header.'<< /Type /Catalog >>', 9);
            // No cross-reference section: reconstruction decodes every object stream it finds.
            $pdf = "%PDF-1.7\n2 0 obj\n<< /Type /ObjStm /N ".$count.' /First '.strlen($header).' /Filter /FlateDecode /Length '.strlen($data)." >>\nstream\n"
                .$data."\nendstream\nendobj\n%%EOF\n";
            unset($header);

            $this->assertRefusedQuickly(static fn () => PdfDocument::parse($pdf), 'more than the PDF can hold');
        }
    }

    #[RunInSeparateProcess]
    public function test_indirect_length_chain_is_bounded(): void
    {
        self::limitMemory();
        // Each stream's /Length points at the next stream, 50 000 deep.
        $builder = (new PdfBuilder)->object(1, '<< /Type /Catalog /Pages 2 0 R >>')->object(2, self::PAGES);
        $last = 50_000;
        for ($n = 3; $n < $last; $n++) {
            $builder->object($n, '<< /Length '.($n + 1)." 0 R >>\nstream\nx\nendstream");
        }
        $pdf = $builder->object($last, '1')->trailer('/Root 1 0 R')->classic();
        $before = memory_get_usage();
        memory_reset_peak_usage();
        $started = self::cpuSeconds();

        $stream = PdfDocument::parse($pdf)->object(3);

        self::assertInstanceOf(PdfStream::class, $stream);
        self::assertSame('x', $stream->raw);
        self::assertLessThan(3.0, $seconds = self::cpuSeconds() - $started, sprintf('Took %.3f s CPU', $seconds));
        self::assertLessThan(16 * 1024 * 1024, memory_get_peak_usage() - $before);
    }

    #[RunInSeparateProcess]
    public function test_unterminated_streams_and_strings_do_not_make_reconstruction_quadratic(): void
    {
        self::limitMemory();
        $streams = "%PDF-1.7\n".str_repeat("1 0 obj<<>>stream\n", 80_000);
        $this->assertRefusedQuickly(static fn () => PdfDocument::parse($streams));

        // Doubling the input must roughly double the CPU time (linear ~2, quadratic ~4). The best of
        // three runs per size keeps scheduler and allocator noise out of the ratio.
        $small = min(array_map(static fn () => self::refusedCpuSeconds($streams), range(1, 3)));
        $doubled = "%PDF-1.7\n".str_repeat("1 0 obj<<>>stream\n", 160_000);
        $large = min(array_map(static fn () => self::refusedCpuSeconds($doubled), range(1, 3)));
        unset($doubled);
        self::assertLessThan(3.0, $large / max($small, 0.005), sprintf('2n/n CPU ratio (%.3f s -> %.3f s)', $small, $large));

        $strings = "%PDF-1.7\n".str_repeat('1 0 obj(', 80_000);
        $this->assertRefusedQuickly(static fn () => PdfDocument::parse($strings));

        $hex = "%PDF-1.7\n".str_repeat('1 0 obj<', 80_000);
        $this->assertRefusedQuickly(static fn () => PdfDocument::parse($hex));
    }

    #[RunInSeparateProcess]
    public function test_reconstruction_refuses_more_objects_than_the_index_cap(): void
    {
        self::limitMemory();
        // About 27 MB of tiny objects numbered downwards, with no usable cross-reference index.
        $pdf = self::tinyObjects(1_100_000);
        $before = memory_get_usage();
        memory_reset_peak_usage();
        $started = self::cpuSeconds();
        try {
            PdfDocument::parse($pdf);
            self::fail('Expected UnreadablePdf');
        } catch (UnreadablePdf $e) {
            self::assertSame(UnreadablePdf::REFUSED, $e->getCode());
        }
        self::assertBounded($started, $before, 48);
    }

    #[RunInSeparateProcess]
    public function test_reconstruction_indexes_objects_up_to_the_index_cap(): void
    {
        self::limitMemory();
        $pdf = self::tinyObjects(520_000);
        $before = memory_get_usage();
        memory_reset_peak_usage();
        $started = self::cpuSeconds();

        $document = PdfDocument::parse($pdf);

        self::assertTrue($document->reconstructed());
        self::assertSame(8_000_001, $document->size());
        self::assertBounded($started, $before, 48);
    }

    #[RunInSeparateProcess]
    public function test_xref_stream_listing_more_entries_than_the_index_cap_is_reconstructed(): void
    {
        self::limitMemory();
        // A valid xref stream of 4M rows in a file large enough to pass the per-byte budget.
        $count = 4_000_000;
        $head = "%PDF-1.7\n";
        $catalog = "1 0 obj\n<< /Type /Catalog >>\nendobj\n";
        $rows = "\x00\x00\x00\x00\x00\x00\x01".pack('N', strlen($head))."\x00".str_repeat("\x00", 6 * ($count - 2));
        $data = (string) gzcompress($rows, 9);
        unset($rows);
        $padding = '%'.str_repeat('x', $count * PdfParser::BYTES_PER_ENTRY)."\n";
        $start = strlen($head.$catalog.$padding);
        $pdf = $head.$catalog.$padding.'2 0 obj'."\n".'<< /Type /XRef /Size '.$count.' /W [1 4 1] /Root 1 0 R /Filter /FlateDecode /Length '.strlen($data)." >>\nstream\n"
            .$data."\nendstream\nendobj\nstartxref\n".$start."\n%%EOF\n";
        unset($padding);
        $before = memory_get_usage();
        memory_reset_peak_usage();
        $started = self::cpuSeconds();

        $document = PdfDocument::parse($pdf);

        self::assertTrue($document->reconstructed());
        self::assertTrue($document->catalog()->isName('Type', 'Catalog'));
        self::assertBounded($started, $before, 48);
    }

    #[RunInSeparateProcess]
    public function test_reconstruction_releases_each_decoded_object_stream(): void
    {
        self::limitMemory();
        // 500k objects and one object stream that decodes to 47 MiB: index plus one stream fit.
        $context = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        self::assertNotFalse($context);
        $payload = deflate_add($context, '2 0 ', ZLIB_NO_FLUSH);
        $spaces = str_repeat(' ', 1024 * 1024);
        for ($i = 0; $i < 47; $i++) {
            $payload .= deflate_add($context, $spaces, ZLIB_NO_FLUSH);
        }
        $payload .= deflate_add($context, 'null', ZLIB_FINISH);
        $pdf = self::tinyObjects(500_000, "3 0 obj\n<< /Type /ObjStm /N 1 /First 4 /Filter /FlateDecode /Length ".strlen($payload)." >>\nstream\n".$payload."\nendstream\nendobj\n");
        unset($payload);
        $before = memory_get_usage();
        memory_reset_peak_usage();
        $started = self::cpuSeconds();

        $document = PdfDocument::parse($pdf);

        self::assertTrue($document->reconstructed());
        self::assertTrue($document->catalog()->isName('Type', 'Catalog'));
        self::assertBounded($started, $before, 100);
    }

    public function test_size_after_reconstruction_ignores_hostile_trailer_size(): void
    {
        // A /Size near the object limit would make an update allocate object numbers there.
        $pdf = "%PDF-1.7\n1 0 obj\n".self::CATALOG."\nendobj\n2 0 obj\n".self::PAGES."\nendobj\n7 0 obj\n(x)\nendobj\n"
            .'trailer<< /Root 1 0 R /Size '.PdfParser::MAX_OBJECTS." >>\nstartxref\n0\n%%EOF\n";

        $document = PdfDocument::parse($pdf);

        self::assertTrue($document->reconstructed());
        self::assertSame(8, $document->size());
        self::assertSame(8, $document->trailer()->get('Size'));
    }

    public function test_decode_errors_hold_no_decoded_data_in_their_trace(): void
    {
        // The inner layer decodes to 2 MiB; the second filter then fails on its parameters. The
        // exceptions must not keep that buffer alive through the arguments in their traces.
        $decoded = 2 * 1024 * 1024;
        $inner = (string) gzcompress(str_repeat('a', $decoded), 9);
        $twice = (string) gzcompress($inner, 9);
        $failures = [
            'unsupported predictor' => '/Predictor 3',
            'malformed parameter' => '/Predictor 12 /Colors 99',
        ];
        foreach ($failures as $case => $parameters) {
            $pdf = (new PdfBuilder)
                ->object(1, self::CATALOG)
                ->object(2, self::PAGES)
                ->object(3, '<< /Filter [/FlateDecode /FlateDecode] /DecodeParms [null << '.$parameters.' >>] /Length '.strlen($twice)." >>\nstream\n".$twice."\nendstream")
                ->trailer('/Root 1 0 R')
                ->classic();
            $document = PdfDocument::parse($pdf);
            $stream = $document->object(3);
            self::assertInstanceOf(PdfStream::class, $stream);

            try {
                $document->decodedStream($stream);
                self::fail($case.': the stream was decoded.');
            } catch (UnreadablePdf $e) {
                self::assertLessThan(64 * 1024, self::largestHeldString($e), $case);
            }
        }
    }

    /**
     * The longest string an exception keeps reachable through the arguments in its trace,
     * including exceptions passed as arguments and chained exceptions.
     */
    private static function largestHeldString(Throwable $exception): int
    {
        $largest = 0;
        for ($link = $exception; $link !== null; $link = $link->getPrevious()) {
            foreach ($link->getTrace() as $frame) {
                foreach ($frame['args'] ?? [] as $argument) {
                    $largest = max($largest, match (true) {
                        is_string($argument) => strlen($argument),
                        $argument instanceof Throwable => self::largestHeldString($argument),
                        default => 0,
                    });
                }
            }
        }

        return $largest;
    }

    /** A catalog, $count tiny objects numbered down from 8 000 000, $extra, and no usable index. */
    private static function tinyObjects(int $count, string $extra = ''): string
    {
        $pdf = "%PDF-1.7\n1 0 obj<</Type/Catalog>>endobj\n";
        for ($i = 0; $i < $count; $i++) {
            $pdf .= (8_000_000 - $i)." 0 obj null endobj\n";
        }

        return $pdf.$extra."trailer<</Root 1 0 R>>\nstartxref\n0\n%%EOF\n";
    }

    private static function assertBounded(int|float $started, int $before, int $megabytes): void
    {
        $seconds = self::cpuSeconds() - $started;
        $peak = memory_get_peak_usage() - $before;
        self::assertLessThan(15.0, $seconds, sprintf('Took %.3f s CPU', $seconds));
        self::assertLessThan($megabytes * 1024 * 1024, $peak, sprintf('Peak grew by %.1f MiB', $peak / 1048576));
    }

    /** A file whose xref stream claims MAX_OBJECTS + 1 one-byte rows of the given type. */
    private static function claimingXrefStream(string $type, bool $withCatalog): string
    {
        $data = (string) gzcompress(str_repeat($type, PdfParser::MAX_OBJECTS + 1), 9);
        $pdf = "%PDF-1.7\n".($withCatalog ? "1 0 obj\n<< /Type /Catalog >>\nendobj\n" : '');
        $start = strlen($pdf);

        return $pdf.'2 0 obj'."\n".'<< /Type /XRef /Size '.(PdfParser::MAX_OBJECTS + 1).' /W [1 0 0] /Root 1 0 R /Filter /FlateDecode /Length '.strlen($data)." >>\nstream\n"
            .$data."\nendstream\nendobj\nstartxref\n".$start."\n%%EOF\n";
    }

    /** Process CPU time (user + system) in seconds; unaffected by machine load. */
    private static function cpuSeconds(): float
    {
        $usage = getrusage();
        if ($usage === false) {
            self::fail('getrusage() is not available');
        }

        $field = static fn (string $key): int => is_int($usage[$key] ?? null) ? $usage[$key] : 0;

        return $field('ru_utime.tv_sec') + $field('ru_stime.tv_sec')
            + ($field('ru_utime.tv_usec') + $field('ru_stime.tv_usec')) / 1e6;
    }

    /** CPU seconds PdfDocument::parse() needs to refuse $pdf. */
    private static function refusedCpuSeconds(string $pdf): float
    {
        $started = self::cpuSeconds();
        try {
            PdfDocument::parse($pdf);
            self::fail('Expected UnreadablePdf');
        } catch (UnreadablePdf) {
        }

        return self::cpuSeconds() - $started;
    }

    /** Lowers memory_limit to PHP's default, which only works in a fresh process. */
    private static function limitMemory(): void
    {
        self::assertNotFalse(ini_set('memory_limit', '128M'), 'memory_limit could not be lowered to 128M');
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
    private function assertRefusedQuickly(Closure $action, string ...$messages): void
    {
        $started = self::cpuSeconds();
        try {
            $action();
            self::fail('Expected UnreadablePdf');
        } catch (UnreadablePdf $e) {
            foreach ($messages as $message) {
                self::assertStringContainsStringIgnoringCase($message, $e->getMessage());
            }
        }
        $seconds = self::cpuSeconds() - $started;
        self::assertLessThan(3.0, $seconds, sprintf('Took %.3f s CPU', $seconds));
    }
}
