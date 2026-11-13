<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

use Closure;
use IntrAgile\EInvoicing\Pdf\Exception\EncryptedPdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;

/**
 * Loads the objects of one PDF on demand: it builds the cross-reference index (rebuilding it by
 * scanning when the file's own index is unusable), then parses objects, object streams and
 * stream data only when they are asked for. It holds the safety limits for untrusted input.
 *
 * @internal Use PdfDocument.
 */
final class PdfParser
{
    /** The most bytes one stream may decode to. */
    public const int MAX_STREAM_BYTES = 64 * 1024 * 1024;

    /** The most bytes all streams of one document may decode to together. */
    public const int MAX_TOTAL_DECODED_BYTES = 256 * 1024 * 1024;

    /** The deepest nesting of arrays and dictionaries. */
    public const int MAX_DEPTH = 64;

    /** The most /Prev links followed from the newest cross-reference section. */
    public const int MAX_PREV_CHAIN = 64;

    /** The highest object number (ISO 32000-1 Annex C). */
    public const int MAX_OBJECTS = 8_388_607;

    /**
     * The most bytes all predictor-coded streams of one document may hold together. Predictors
     * run byte by byte in PHP; this keeps their work well under a second.
     */
    public const int MAX_PREDICTOR_BYTES = 4 * 1024 * 1024;

    /**
     * An index may list at most one object per this many bytes of file (or ENTRY_FLOOR objects in
     * a small file). Real files use well over a hundred bytes per object; the bound keeps a tiny
     * file from claiming millions of objects.
     */
    public const int BYTES_PER_ENTRY = 8;

    public const int ENTRY_FLOOR = 4096;

    /** How many indirect /Length values may be followed while one is already being resolved. */
    private const int MAX_LENGTH_DEPTH = 4;

    private readonly Lexer $lexer;

    private readonly string $version;

    private CrossReference $xref;

    private PdfDictionary $catalog;

    /** @var array<int, mixed> */
    private array $objects = [];

    /** @var array<int, array{Lexer, int, list<int>}> */
    private array $objectStreams = [];

    /** @var array<int, UnreadablePdf> */
    private array $failedObjectStreams = [];

    /** @var array<int, true> */
    private array $loading = [];

    private int $decoded = 0;

    private int $predicted = 0;

    private int $lengthDepth = 0;

    private readonly int $maxEntries;

    public function __construct(string $bytes)
    {
        $this->lexer = new Lexer($bytes);
        $this->version = self::headerVersion($bytes);
        $this->maxEntries = min(self::MAX_OBJECTS + 1, max(self::ENTRY_FLOOR, intdiv(strlen($bytes), self::BYTES_PER_ENTRY)));
        $startXref = self::startXrefOf($bytes);
        // Before the index exists nothing can be resolved: an indirect /Filter or /DecodeParms on
        // an xref stream (where the standard forbids them) is damage.
        $decodeDirect = fn (PdfStream $stream): string => $this->decodeWith($stream, static function (mixed $value): mixed {
            if ($value instanceof PdfRef) {
                throw UnreadablePdf::malformed('An xref or object stream refers to its filter indirectly.');
            }

            return $value;
        });
        try {
            if ($startXref === null) {
                throw UnreadablePdf::malformed('The PDF has no startxref.');
            }
            $this->xref = CrossReference::read($this->lexer, $startXref, $decodeDirect, $this->maxEntries);
            $this->verifyOffsets();
            $this->open();
        } catch (UnreadablePdf $e) {
            if ($e->getCode() !== UnreadablePdf::MALFORMED) {
                throw $e;
            }
            $this->objects = [];
            $this->objectStreams = [];
            $this->failedObjectStreams = [];
            $this->xref = CrossReference::reconstruct($this->lexer, $startXref ?? 0, $decodeDirect, $this->maxEntries);
            $this->open();
        }
    }

    public function version(): string
    {
        return $this->version;
    }

    public function xref(): CrossReference
    {
        return $this->xref;
    }

    public function catalog(): PdfDictionary
    {
        return $this->catalog;
    }

    /** The value of object $number, or null when it is free or absent (as PDF defines). */
    public function object(int $number): mixed
    {
        if (array_key_exists($number, $this->objects)) {
            return $this->objects[$number];
        }
        $entry = $this->xref->entry($number);
        if ($entry === null || $entry[0] === CrossReference::FREE) {
            return null;
        }
        if (isset($this->loading[$number])) {
            throw UnreadablePdf::refused('Reference cycle while loading object '.$number.'.');
        }
        $this->loading[$number] = true;
        try {
            $value = $entry[0] === CrossReference::IN_FILE
                ? $this->loadFromFile($number, $entry[1])
                : $this->loadFromObjectStream($number, $entry[1], $entry[2]);
        } finally {
            unset($this->loading[$number]);
        }

        return $this->objects[$number] = $value;
    }

    /** Follows references until a direct value; a chain that comes back on itself is refused. */
    public function resolve(mixed $value): mixed
    {
        $seen = [];
        while ($value instanceof PdfRef) {
            if (isset($seen[$value->number])) {
                throw UnreadablePdf::refused('Reference cycle through object '.$value->number.'.');
            }
            $seen[$value->number] = true;
            $value = $this->object($value->number);
        }

        return $value;
    }

    public function decode(PdfStream $stream): string
    {
        return $this->decodeWith($stream, $this->resolve(...));
    }

    /** @param Closure(mixed): mixed $resolve */
    private function decodeWith(PdfStream $stream, Closure $resolve): string
    {
        if ($stream->dictionary->get('Filter') === null) {
            return $stream->raw;
        }
        $limit = min(self::MAX_STREAM_BYTES, self::MAX_TOTAL_DECODED_BYTES - $this->decoded);
        if ($limit <= 0) {
            throw UnreadablePdf::refused('The decoded stream data of the document exceeds the limit of '.(self::MAX_TOTAL_DECODED_BYTES >> 20).' MiB.');
        }
        // Inflation and predictor work counts even when decoding then fails, so repeated bombs
        // or corrupt streams exhaust the budgets instead of repeating the work.
        $inflated = 0;
        $predicted = 0;
        try {
            return Filters::decode($stream, $resolve, $limit, self::MAX_PREDICTOR_BYTES - $this->predicted, $inflated, $predicted);
        } catch (UnreadablePdf $e) {
            // A fresh exception: the original's trace may hold up to 64 MiB of decoded data.
            throw self::fresh($e);
        } finally {
            $this->decoded += $inflated;
            $this->predicted += $predicted;
        }
    }

    private function open(): void
    {
        if ($this->xref->trailer->has('Encrypt')) {
            throw new EncryptedPdf('The PDF is encrypted; encrypted PDFs are not supported.');
        }
        $catalog = $this->resolve($this->xref->trailer->get('Root'));
        if (! $catalog instanceof PdfDictionary) {
            throw UnreadablePdf::malformed('The PDF has no document catalog (trailer /Root).');
        }
        $this->catalog = $catalog;
    }

    /** Checks that every in-file entry points at its object's header, without parsing objects. */
    private function verifyOffsets(): void
    {
        foreach ($this->xref->inFile() as $number => $offset) {
            $header = $this->lexer->header($offset);
            if ($header === null || $header[0] !== $number) {
                throw UnreadablePdf::malformed('Object '.$number.' is not at its cross-reference offset '.$offset.'.');
            }
        }
    }

    private function loadFromFile(int $number, int $offset): mixed
    {
        [$found, , $value] = $this->lexer->indirectObject($offset, $this->streamLength(...));
        if ($found !== $number) {
            throw UnreadablePdf::malformed('Object '.$number.' is not at offset '.$offset.'.');
        }

        return $value;
    }

    /**
     * An indirect /Length, or null to locate the stream end by its keyword instead. Lengths that
     * lead to further streams with indirect lengths are followed only a few levels deep.
     */
    private function streamLength(PdfRef $ref): mixed
    {
        if ($this->lengthDepth >= self::MAX_LENGTH_DEPTH) {
            return null;
        }
        $this->lengthDepth++;
        try {
            return $this->resolve($ref);
        } catch (UnreadablePdf) {
            return null;
        } finally {
            $this->lengthDepth--;
        }
    }

    private function loadFromObjectStream(int $number, int $streamNumber, int $index): mixed
    {
        [$lexer, $first, $members] = $this->objectStream($streamNumber);
        $at = 2 * $index;
        if (($members[$at] ?? null) !== $number) {
            // The index in the cross-reference entry is wrong; look the number up instead.
            $at = null;
            for ($k = 0, $n = count($members); $k < $n; $k += 2) {
                if ($members[$k] === $number) {
                    $at = $k;
                    break;
                }
            }
        }
        if ($at === null || ! isset($members[$at + 1])) {
            throw UnreadablePdf::malformed('Object '.$number.' is missing from object stream '.$streamNumber.'.');
        }
        $pos = $first + $members[$at + 1];

        return $lexer->value($pos);
    }

    /** @return array{Lexer, int, list<int>} */
    private function objectStream(int $number): array
    {
        if (isset($this->objectStreams[$number])) {
            return $this->objectStreams[$number];
        }
        if (isset($this->failedObjectStreams[$number])) {
            throw $this->failedObjectStreams[$number];
        }
        try {
            $entry = $this->xref->entry($number);
            if ($entry !== null && $entry[0] === CrossReference::IN_OBJECT_STREAM) {
                throw UnreadablePdf::refused('Object stream '.$number.' is itself inside an object stream.');
            }
            $stream = $this->object($number);
            if (! $stream instanceof PdfStream) {
                throw UnreadablePdf::malformed('Object stream '.$number.' is missing.');
            }

            return $this->objectStreams[$number] = Lexer::objectStream($stream, $this->decode(...), $this->maxEntries);
        } catch (UnreadablePdf $e) {
            throw $this->failedObjectStreams[$number] = self::fresh($e);
        }
    }

    /** A copy of $e with the same class, message and code but a trace that holds no large data. */
    private static function fresh(UnreadablePdf $e): UnreadablePdf
    {
        $class = $e::class;

        return new $class($e->getMessage(), $e->getCode());
    }

    private static function headerVersion(string $bytes): string
    {
        if (preg_match('/%PDF-(\d\.\d)/', substr($bytes, 0, 1024), $m) !== 1) {
            throw new UnreadablePdf('The data is not a PDF: there is no %PDF- header in its first 1024 bytes.');
        }

        return $m[1];
    }

    private static function startXrefOf(string $bytes): ?int
    {
        $pos = strrpos($bytes, 'startxref');
        if ($pos === false || preg_match('/\Gstartxref[\0\t\n\f\r ]+(\d{1,15})/', $bytes, $m, 0, $pos) !== 1) {
            return null;
        }

        return (int) $m[1];
    }
}
