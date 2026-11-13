<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

use Closure;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;

/**
 * The cross-reference index of a PDF (ISO 32000-1 §7.5.4–7.5.8): where each object lives, and
 * the trailer. read() follows the startxref and /Prev chain through classic tables, xref streams
 * and hybrid files; reconstruct() rebuilds the index by scanning the bytes when that chain is
 * unusable.
 *
 * @internal
 */
final class CrossReference
{
    public const int FREE = 0;

    public const int IN_FILE = 1;

    public const int IN_OBJECT_STREAM = 2;

    /** Dictionary keys that describe an xref stream itself rather than the document. */
    private const array STREAM_KEYS = ['Type', 'W', 'Index', 'Filter', 'DecodeParms', 'Length', 'F', 'FFilter', 'FDecodeParms', 'DL', 'XRefStm'];

    private const string OBJECT_PATTERN = '/(?<![0-9])(\d{1,10})[\0\t\n\f\r ]+(\d{1,5})[\0\t\n\f\r ]+obj(?![^\0\t\n\f\r ()<>\[\]{}\/%])/';

    /**
     * @param  array<int, array{int, int, int}>  $entries  object number => [IN_FILE, offset, generation]
     *                                                     | [IN_OBJECT_STREAM, stream number, index] | [FREE, 0, generation]
     */
    private function __construct(
        public readonly array $entries,
        public readonly PdfDictionary $trailer,
        public readonly int $startXref,
        public readonly bool $xrefStream,
        public readonly bool $reconstructed,
    ) {}

    /**
     * Reads the sections from $startXref back along /Prev. Damage throws UnreadablePdf with code
     * MALFORMED; a /Prev loop, an overlong chain or an oversized /Size throws code REFUSED.
     *
     * @param  Closure(PdfStream): string  $decode
     */
    public static function read(Lexer $lexer, int $startXref, Closure $decode): self
    {
        $entries = [];
        $trailers = [];
        $visited = [];
        $newestIsStream = false;
        $offset = $startXref;
        while ($offset !== null) {
            if (isset($visited[$offset])) {
                throw UnreadablePdf::refused('The cross-reference /Prev chain loops back to offset '.$offset.'.');
            }
            if (count($visited) > PdfParser::MAX_PREV_CHAIN) {
                throw UnreadablePdf::refused('The cross-reference /Prev chain is longer than '.PdfParser::MAX_PREV_CHAIN.' sections.');
            }
            $visited[$offset] = true;
            $pos = $lexer->skip($offset);
            if ($lexer->keywordAt($pos, 'xref')) {
                [$section, $trailer] = self::readTable($lexer, $pos + 4);
                $hidden = $trailer->get('XRefStm');
                if (is_int($hidden)) {
                    // Hybrid file: the stream adds the objects that the table leaves out or marks free.
                    [$streamSection] = self::readStream($lexer, $hidden, $decode);
                    foreach ($streamSection as $number => $entry) {
                        if (! isset($section[$number]) || $section[$number][0] === self::FREE) {
                            $section[$number] = $entry;
                        }
                    }
                }
                $isStream = false;
            } else {
                [$section, $trailer] = self::readStream($lexer, $pos, $decode);
                $isStream = true;
            }
            if ($trailers === []) {
                $newestIsStream = $isStream;
            }
            $entries += $section;
            $trailers[] = $trailer;
            $prev = $trailer->get('Prev');
            $offset = is_int($prev) ? $prev : null;
        }

        return new self($entries, self::mergeTrailers($trailers), $startXref, $newestIsStream, false);
    }

    /**
     * Rebuilds the index from "N G obj" headers, object streams and the trailers or xref stream
     * dictionaries found in the file; later definitions win.
     *
     * @param  Closure(PdfStream): string  $decode
     */
    public static function reconstruct(Lexer $lexer, int $startXref, Closure $decode): self
    {
        $bytes = $lexer->data();
        $entries = [];
        $positions = [];
        $trailers = [];
        $objectStreams = [];
        $catalog = null;
        $pos = 0;
        while (preg_match(self::OBJECT_PATTERN, $bytes, $m, PREG_OFFSET_CAPTURE, $pos) === 1) {
            $start = $m[0][1];
            $pos = $start + strlen($m[0][0]);
            $number = (int) $m[1][0];
            if ($number < 1 || $number > PdfParser::MAX_OBJECTS) {
                continue;
            }
            try {
                [, $generation, $value, $end] = $lexer->indirectObject($start);
            } catch (UnreadablePdf $e) {
                if ($e->getCode() !== UnreadablePdf::MALFORMED) {
                    throw $e;
                }

                continue;
            }
            $pos = max($pos, $end);
            $entries[$number] = [self::IN_FILE, $start, $generation];
            $positions[$number] = $start;
            $dictionary = $value instanceof PdfStream ? $value->dictionary : $value;
            if (! $dictionary instanceof PdfDictionary) {
                continue;
            }
            if ($dictionary->isName('Type', 'Catalog')) {
                $catalog = $number;
            } elseif ($value instanceof PdfStream && $dictionary->isName('Type', 'XRef')) {
                $trailers[$start] = [self::stripStreamKeys($dictionary), true];
            } elseif ($value instanceof PdfStream && $dictionary->isName('Type', 'ObjStm')) {
                $objectStreams[$number] = [$start, $value];
            }
        }
        if ($entries === []) {
            throw UnreadablePdf::malformed('The PDF contains no readable objects.');
        }

        foreach ($objectStreams as $streamNumber => [$start, $stream]) {
            try {
                [$streamLexer, $first, $members] = Lexer::objectStream($stream, $decode($stream));
            } catch (UnreadablePdf $e) {
                if ($e->getCode() !== UnreadablePdf::MALFORMED) {
                    throw $e;
                }

                continue;
            }
            foreach ($members as $index => [$number, $relative]) {
                if ($number < 1 || $number > PdfParser::MAX_OBJECTS || $number === $streamNumber
                    || ($positions[$number] ?? -1) >= $start) {
                    continue;
                }
                $entries[$number] = [self::IN_OBJECT_STREAM, $streamNumber, $index];
                $positions[$number] = $start;
                if ($catalog === null) {
                    $at = $first + $relative;
                    try {
                        $member = $streamLexer->value($at);
                    } catch (UnreadablePdf $e) {
                        if ($e->getCode() !== UnreadablePdf::MALFORMED) {
                            throw $e;
                        }

                        continue;
                    }
                    if ($member instanceof PdfDictionary && $member->isName('Type', 'Catalog')) {
                        $catalog = $number;
                    }
                }
            }
        }

        $offset = 0;
        while (preg_match('/(?<![A-Za-z])trailer[\0\t\n\f\r ]*<</', $bytes, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $offset = $m[0][1] + 7;
            $at = $offset;
            try {
                $trailer = $lexer->value($at);
            } catch (UnreadablePdf $e) {
                if ($e->getCode() !== UnreadablePdf::MALFORMED) {
                    throw $e;
                }

                continue;
            }
            if ($trailer instanceof PdfDictionary) {
                $trailers[$m[0][1]] = [$trailer, false];
            }
        }
        krsort($trailers);
        $merged = self::mergeTrailers(array_column($trailers, 0));
        $newestIsStream = $trailers !== [] && reset($trailers)[1];

        if (! $merged->get('Root') instanceof PdfRef && $catalog !== null) {
            $merged = $merged->with('Root', new PdfRef($catalog, 0));
        }
        $size = $merged->get('Size');
        $highest = max(array_keys($entries)) + 1;
        $merged = $merged->with('Size', is_int($size) && $size > $highest ? $size : $highest);

        return new self($entries, $merged, $startXref, $newestIsStream, true);
    }

    /**
     * @return array{array<int, array{int, int, int}>, PdfDictionary}
     */
    private static function readTable(Lexer $lexer, int $pos): array
    {
        $bytes = $lexer->data();
        $entries = [];
        while (true) {
            $pos = $lexer->skip($pos);
            if ($lexer->keywordAt($pos, 'trailer')) {
                $pos += 7;
                $trailer = $lexer->value($pos);
                if (! $trailer instanceof PdfDictionary) {
                    throw UnreadablePdf::malformed('The trailer is not a dictionary.');
                }
                self::guardSize($trailer->get('Size'));

                return [$entries, $trailer];
            }
            if (preg_match('/\G(\d{1,10})[ \t]+(\d{1,10})/', $bytes, $m, 0, $pos) !== 1) {
                throw UnreadablePdf::malformed('Invalid cross-reference subsection at offset '.$pos.'.');
            }
            $first = (int) $m[1];
            $count = (int) $m[2];
            $pos += strlen($m[0]);
            // Each entry takes at least 18 bytes; a count the file cannot hold is damage.
            if ($first + $count > PdfParser::MAX_OBJECTS + 1 || $count * 18 > strlen($bytes) - $pos) {
                throw UnreadablePdf::malformed('Invalid cross-reference subsection size at offset '.$pos.'.');
            }
            for ($i = 0; $i < $count; $i++) {
                if (preg_match('/\G[\0\t\n\f\r ]*(\d{10})[ ]+(\d{5})[ ]+([nf])/', $bytes, $m, 0, $pos) !== 1) {
                    throw UnreadablePdf::malformed('Invalid cross-reference entry at offset '.$pos.'.');
                }
                $pos += strlen($m[0]);
                $number = $first + $i;
                $offset = (int) $m[1];
                $generation = (int) $m[2];
                // Object 0 is always free, and an in-use entry at offset 0 is a writer bug.
                $entries[$number] = $m[3] === 'n' && $number !== 0 && $offset !== 0
                    ? [self::IN_FILE, $offset, $generation]
                    : [self::FREE, 0, $generation];
            }
        }
    }

    /**
     * @param  Closure(PdfStream): string  $decode
     * @return array{array<int, array{int, int, int}>, PdfDictionary}
     */
    private static function readStream(Lexer $lexer, int $offset, Closure $decode): array
    {
        [, , $stream] = $lexer->indirectObject($offset);
        if (! $stream instanceof PdfStream) {
            throw UnreadablePdf::malformed('No cross-reference table or stream at offset '.$offset.'.');
        }
        $dictionary = $stream->dictionary;
        $size = $dictionary->get('Size');
        self::guardSize($size);
        $widths = $dictionary->get('W');
        if (! is_int($size) || ! is_array($widths) || count($widths) !== 3) {
            throw UnreadablePdf::malformed('Invalid cross-reference stream at offset '.$offset.'.');
        }
        $widths = [self::width($widths[0] ?? null, $offset), self::width($widths[1] ?? null, $offset), self::width($widths[2] ?? null, $offset)];
        $index = $dictionary->get('Index') ?? [0, $size];
        if (! is_array($index) || count($index) % 2 !== 0) {
            throw UnreadablePdf::malformed('Invalid /Index in the cross-reference stream at offset '.$offset.'.');
        }
        $data = $decode($stream);
        $rowLength = array_sum($widths);
        $rows = $rowLength === 0 ? 0 : intdiv(strlen($data), $rowLength);
        $entries = [];
        $row = 0;
        for ($i = 0; $i < count($index); $i += 2) {
            $first = $index[$i];
            $count = $index[$i + 1];
            if (! is_int($first) || ! is_int($count) || $first < 0 || $count < 0 || $first + $count > PdfParser::MAX_OBJECTS + 1) {
                throw UnreadablePdf::malformed('Invalid /Index in the cross-reference stream at offset '.$offset.'.');
            }
            // Rows beyond the decoded data are missing; the section simply ends there.
            $count = min($count, $rows - $row);
            for ($j = 0; $j < $count; $j++, $row++) {
                $pos = $row * $rowLength;
                $type = $widths[0] === 0 ? 1 : self::field($data, $pos, $widths[0]);
                $field2 = self::field($data, $pos + $widths[0], $widths[1]);
                $field3 = self::field($data, $pos + $widths[0] + $widths[1], $widths[2]);
                $number = $first + $j;
                $entries[$number] = match ($type) {
                    0 => [self::FREE, 0, $field3],
                    1 => $number === 0 ? [self::FREE, 0, $field3] : [self::IN_FILE, $field2, $field3],
                    2 => [self::IN_OBJECT_STREAM, $field2, $field3],
                    // Unknown entry types are references to the null object.
                    default => [self::FREE, 0, 0],
                };
            }
        }

        return [$entries, self::stripStreamKeys($dictionary)];
    }

    private static function width(mixed $width, int $offset): int
    {
        if (! is_int($width) || $width < 0 || $width > 8) {
            throw UnreadablePdf::malformed('Invalid /W in the cross-reference stream at offset '.$offset.'.');
        }

        return $width;
    }

    private static function field(string $data, int $pos, int $width): int
    {
        $value = 0;
        for ($i = 0; $i < $width; $i++) {
            $value = ($value << 8) | ord($data[$pos + $i]);
        }

        // An 8-byte field with the top bit set would overflow; no valid offset is that large.
        return max(0, $value);
    }

    private static function guardSize(mixed $size): void
    {
        // /Size is the highest object number plus one, and object numbers stop at MAX_OBJECTS.
        if (is_int($size) && ($size < 0 || $size > PdfParser::MAX_OBJECTS + 1) || is_float($size)) {
            throw UnreadablePdf::refused('The trailer /Size exceeds the PDF limit of '.PdfParser::MAX_OBJECTS.' objects.');
        }
    }

    private static function stripStreamKeys(PdfDictionary $dictionary): PdfDictionary
    {
        return new PdfDictionary(array_diff_key($dictionary->entries(), array_flip(self::STREAM_KEYS)));
    }

    /**
     * Merges trailers given newest first: a key of a newer trailer wins.
     *
     * @param  list<PdfDictionary>  $trailers
     */
    private static function mergeTrailers(array $trailers): PdfDictionary
    {
        $entries = [];
        foreach ($trailers as $trailer) {
            $entries += $trailer->entries();
        }

        return new PdfDictionary($entries);
    }
}
