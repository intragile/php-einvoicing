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

    /** The largest second field an entry stores; larger offsets lie beyond any file anyway. */
    private const int FIELD2_MAX = (1 << 37) - 1;

    private const int FIELD3_MAX = 0xFFFFFF;

    /**
     * Entries are packed into one int each (see pack()), which keeps a large index small.
     *
     * @param  array<int, int>  $entries  object number => packed entry
     */
    private function __construct(
        private readonly array $entries,
        public readonly PdfDictionary $trailer,
        public readonly int $startXref,
        public readonly bool $xrefStream,
        public readonly bool $reconstructed,
    ) {}

    /**
     * The entry of object $number as [FREE, 0, generation], [IN_FILE, offset, generation] or
     * [IN_OBJECT_STREAM, stream number, index]; null when the index does not list it.
     *
     * @return array{int, int, int}|null
     */
    public function entry(int $number): ?array
    {
        $packed = $this->entries[$number] ?? null;

        return $packed === null ? null : [$packed & 3, $packed >> 26, ($packed >> 2) & self::FIELD3_MAX];
    }

    /**
     * Object number => offset of every entry that is in the file.
     *
     * @return iterable<int, int>
     */
    public function inFile(): iterable
    {
        foreach ($this->entries as $number => $packed) {
            if (($packed & 3) === self::IN_FILE) {
                yield $number => $packed >> 26;
            }
        }
    }

    /** The highest object number listed, or 0. */
    public function highest(): int
    {
        return $this->entries === [] ? 0 : max(array_keys($this->entries));
    }

    /**
     * Reads the sections from $startXref back along /Prev. Damage throws UnreadablePdf with code
     * MALFORMED; a /Prev loop, an overlong chain, an oversized /Size or more than $maxEntries
     * entries in all throws code REFUSED.
     *
     * @param  Closure(PdfStream): string  $decode
     */
    public static function read(Lexer $lexer, int $startXref, Closure $decode, int $maxEntries): self
    {
        $length = strlen($lexer->data());
        $budget = $maxEntries;
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
            if ($offset < 0 || $offset >= $length) {
                throw UnreadablePdf::malformed('Cross-reference offset '.$offset.' lies outside the file.');
            }
            $pos = $lexer->skip($offset);
            if ($lexer->keywordAt($pos, 'xref')) {
                [$section, $trailer] = self::readTable($lexer, $pos + 4, $budget);
                $hidden = $trailer->get('XRefStm');
                if (is_int($hidden)) {
                    if ($hidden < 0 || $hidden >= $length) {
                        throw UnreadablePdf::malformed('/XRefStm offset '.$hidden.' lies outside the file.');
                    }
                    // Hybrid file: the stream adds the objects that the table leaves out or marks free.
                    [$streamSection] = self::readStream($lexer, $hidden, $decode, $budget);
                    foreach ($streamSection as $number => $packed) {
                        if (! isset($section[$number]) || ($section[$number] & 3) === self::FREE) {
                            $section[$number] = $packed;
                        }
                    }
                }
                $isStream = false;
            } else {
                [$section, $trailer] = self::readStream($lexer, $pos, $decode, $budget);
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
    public static function reconstruct(Lexer $lexer, int $startXref, Closure $decode, int $maxEntries): self
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
            $entries[$number] = self::pack(self::IN_FILE, $start, $generation);
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
                [$streamLexer, $first, $members] = Lexer::objectStream($stream, $decode, $maxEntries - count($entries));
            } catch (UnreadablePdf $e) {
                if ($e->getCode() !== UnreadablePdf::MALFORMED) {
                    throw $e;
                }

                continue;
            }
            for ($k = 0, $n = count($members); $k < $n; $k += 2) {
                $number = $members[$k];
                $relative = $members[$k + 1];
                $index = $k >> 1;
                if ($number < 1 || $number > PdfParser::MAX_OBJECTS || $number === $streamNumber
                    || ($positions[$number] ?? -1) >= $start) {
                    continue;
                }
                $entries[$number] = self::pack(self::IN_OBJECT_STREAM, $streamNumber, $index);
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
     * @return array{array<int, int>, PdfDictionary}
     */
    private static function readTable(Lexer $lexer, int $pos, int &$budget): array
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
            self::spend($budget, $count);
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
                    ? self::pack(self::IN_FILE, $offset, $generation)
                    : self::pack(self::FREE, 0, $generation);
            }
        }
    }

    /**
     * @param  Closure(PdfStream): string  $decode
     * @return array{array<int, int>, PdfDictionary}
     */
    private static function readStream(Lexer $lexer, int $offset, Closure $decode, int &$budget): array
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
        $ranges = [];
        $total = 0;
        for ($i = 0; $i < count($index); $i += 2) {
            $first = $index[$i];
            $count = $index[$i + 1];
            if (! is_int($first) || ! is_int($count) || $first < 0 || $count < 0 || $first + $count > PdfParser::MAX_OBJECTS + 1) {
                throw UnreadablePdf::malformed('Invalid /Index in the cross-reference stream at offset '.$offset.'.');
            }
            // Rows beyond the decoded data are missing; the section simply ends there.
            $count = min($count, $rows - $total);
            $ranges[] = [$first, $count];
            $total += $count;
        }
        // Counted before any row is read, so a tiny stream claiming millions of rows costs nothing.
        self::spend($budget, $total);
        $entries = [];
        $row = 0;
        foreach ($ranges as [$first, $count]) {
            for ($j = 0; $j < $count; $j++, $row++) {
                $pos = $row * $rowLength;
                $type = $widths[0] === 0 ? 1 : self::field($data, $pos, $widths[0]);
                $field2 = self::field($data, $pos + $widths[0], $widths[1]);
                $field3 = self::field($data, $pos + $widths[0] + $widths[1], $widths[2]);
                $number = $first + $j;
                $entries[$number] = match ($type) {
                    0 => self::pack(self::FREE, 0, $field3),
                    1 => $number === 0 ? self::pack(self::FREE, 0, $field3) : self::pack(self::IN_FILE, $field2, $field3),
                    2 => self::pack(self::IN_OBJECT_STREAM, $field2, $field3),
                    // Unknown entry types are references to the null object.
                    default => self::pack(self::FREE, 0, 0),
                };
            }
        }

        return [$entries, self::stripStreamKeys($dictionary)];
    }

    /** Packs an entry: the type in bits 0–1, the third field in bits 2–25, the second above. */
    private static function pack(int $type, int $field2, int $field3): int
    {
        return (min($field2, self::FIELD2_MAX) << 26) | (min($field3, self::FIELD3_MAX) << 2) | $type;
    }

    /**
     * Takes $count entries from the document's entry budget, refusing an index that lists more
     * objects than the file could physically hold.
     */
    private static function spend(int &$budget, int $count): void
    {
        $budget -= $count;
        if ($budget < 0) {
            throw UnreadablePdf::refused('The cross-reference index lists more objects than the PDF can hold.');
        }
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
