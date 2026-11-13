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

    private readonly Lexer $lexer;

    private readonly string $version;

    private CrossReference $xref;

    private PdfDictionary $catalog;

    /** @var array<int, mixed> */
    private array $objects = [];

    /** @var array<int, array{Lexer, int, list<array{int, int}>}> */
    private array $objectStreams = [];

    /** @var array<int, UnreadablePdf> */
    private array $failedObjectStreams = [];

    /** @var array<int, true> */
    private array $loading = [];

    private int $decoded = 0;

    public function __construct(string $bytes)
    {
        $this->lexer = new Lexer($bytes);
        $this->version = self::headerVersion($bytes);
        $startXref = self::startXrefOf($bytes);
        $decodeDirect = fn (PdfStream $stream): string => $this->decodeWith($stream, static fn (mixed $value): mixed => $value instanceof PdfRef ? null : $value);
        try {
            if ($startXref === null) {
                throw UnreadablePdf::malformed('The PDF has no startxref.');
            }
            $this->xref = CrossReference::read($this->lexer, $startXref, $decodeDirect);
            $this->verifyOffsets();
            $this->open();
        } catch (UnreadablePdf $e) {
            if ($e->getCode() !== UnreadablePdf::MALFORMED) {
                throw $e;
            }
            $this->objects = [];
            $this->objectStreams = [];
            $this->failedObjectStreams = [];
            $this->xref = CrossReference::reconstruct($this->lexer, $startXref ?? 0, $decodeDirect);
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
        $entry = $this->xref->entries[$number] ?? null;
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
        try {
            $data = Filters::decode($stream, $resolve, $limit);
        } catch (UnreadablePdf $e) {
            if ($e->getCode() === UnreadablePdf::REFUSED) {
                // The inflation work already done counts, so repeated bombs exhaust the budget.
                $this->decoded += $limit;
            }
            throw $e;
        }
        $this->decoded += strlen($data);

        return $data;
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
        foreach ($this->xref->entries as $number => [$type, $offset]) {
            if ($type !== CrossReference::IN_FILE) {
                continue;
            }
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

    /** An indirect /Length, or null to locate the stream end by its keyword instead. */
    private function streamLength(PdfRef $ref): mixed
    {
        try {
            return $this->resolve($ref);
        } catch (UnreadablePdf) {
            return null;
        }
    }

    private function loadFromObjectStream(int $number, int $streamNumber, int $index): mixed
    {
        [$lexer, $first, $members] = $this->objectStream($streamNumber);
        $member = $members[$index] ?? null;
        if ($member === null || $member[0] !== $number) {
            $member = null;
            foreach ($members as $candidate) {
                if ($candidate[0] === $number) {
                    $member = $candidate;
                    break;
                }
            }
        }
        if ($member === null) {
            throw UnreadablePdf::malformed('Object '.$number.' is missing from object stream '.$streamNumber.'.');
        }
        $pos = $first + $member[1];

        return $lexer->value($pos);
    }

    /** @return array{Lexer, int, list<array{int, int}>} */
    private function objectStream(int $number): array
    {
        if (isset($this->objectStreams[$number])) {
            return $this->objectStreams[$number];
        }
        if (isset($this->failedObjectStreams[$number])) {
            throw $this->failedObjectStreams[$number];
        }
        try {
            $entry = $this->xref->entries[$number] ?? null;
            if ($entry !== null && $entry[0] === CrossReference::IN_OBJECT_STREAM) {
                throw UnreadablePdf::refused('Object stream '.$number.' is itself inside an object stream.');
            }
            $stream = $this->object($number);
            if (! $stream instanceof PdfStream) {
                throw UnreadablePdf::malformed('Object stream '.$number.' is missing.');
            }

            return $this->objectStreams[$number] = Lexer::objectStream($stream, $this->decode($stream));
        } catch (UnreadablePdf $e) {
            $this->failedObjectStreams[$number] = $e;
            throw $e;
        }
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
