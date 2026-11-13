<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

use Closure;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;

/**
 * Reads PDF objects (ISO 32000-1 §7.2–7.3) from a byte string: direct values, and indirect
 * objects with their stream data. Every read is bounds-checked, so damaged input raises
 * UnreadablePdf rather than a PHP warning.
 *
 * @internal
 */
final class Lexer
{
    public const string WHITESPACE = "\0\t\n\f\r ";

    private const string DELIMITERS = '()<>[]{}/%';

    private const string HEADER = '/\G[\0\t\n\f\r ]*(\d{1,10})[\0\t\n\f\r ]+(\d{1,5})[\0\t\n\f\r ]+obj(?![^\0\t\n\f\r ()<>\[\]{}\/%])/';

    private readonly int $length;

    public function __construct(private readonly string $data)
    {
        $this->length = strlen($data);
    }

    public function data(): string
    {
        return $this->data;
    }

    /** The position of the next token at or after $pos, past whitespace and comments. */
    public function skip(int $pos): int
    {
        while ($pos < $this->length) {
            $pos += strspn($this->data, self::WHITESPACE, $pos);
            if ($pos < $this->length && $this->data[$pos] === '%') {
                $pos += strcspn($this->data, "\r\n", $pos);

                continue;
            }
            break;
        }

        return $pos;
    }

    /** True when the keyword $keyword stands at $pos as a whole token. */
    public function keywordAt(int $pos, string $keyword): bool
    {
        $end = $pos + strlen($keyword);

        return substr($this->data, $pos, strlen($keyword)) === $keyword
            && ($end >= $this->length || ! $this->isRegular($this->data[$end]));
    }

    /**
     * The object number and generation of an "N G obj" header at $pos (after optional
     * whitespace), with the offset just past it; null when there is none.
     *
     * @return array{int, int, int}|null
     */
    public function header(int $pos): ?array
    {
        if ($pos < 0 || $pos >= $this->length || preg_match(self::HEADER, $this->data, $m, 0, $pos) !== 1) {
            return null;
        }

        return [(int) $m[1], (int) $m[2], $pos + strlen($m[0])];
    }

    /**
     * Opens an object stream (ISO 32000-1 §7.5.7) from its dictionary and decoded data: a lexer
     * over the data, the offset of the first object, and the object number and relative offset of
     * each member.
     *
     * @return array{self, int, list<array{int, int}>}
     */
    public static function objectStream(PdfStream $stream, string $decoded): array
    {
        $count = $stream->dictionary->get('N');
        $first = $stream->dictionary->get('First');
        $length = strlen($decoded);
        // Each member needs at least four header bytes ("1 0 "), which bounds a hostile /N.
        if (! is_int($count) || ! is_int($first) || $count < 0 || $first < 0 || $first > $length || $count * 4 > $first) {
            throw UnreadablePdf::malformed('Invalid object stream /N or /First.');
        }
        $lexer = new self($decoded);
        $members = [];
        $pos = 0;
        for ($i = 0; $i < $count; $i++) {
            $number = $lexer->value($pos);
            $offset = $lexer->value($pos);
            if (! is_int($number) || ! is_int($offset) || $number < 0 || $offset < 0 || $first + $offset > $length) {
                throw UnreadablePdf::malformed('Invalid object stream header.');
            }
            $members[] = [$number, $offset];
        }

        return [$lexer, $first, $members];
    }

    /**
     * Reads the direct value at $pos and moves $pos past it.
     */
    public function value(int &$pos, int $depth = 0): mixed
    {
        $pos = $this->skip($pos);
        if ($pos >= $this->length) {
            throw UnreadablePdf::malformed('Unexpected end of PDF data at offset '.$pos.'.');
        }
        $c = $this->data[$pos];

        return match (true) {
            $c === '/' => $this->name($pos),
            $c === '(' => $this->literalString($pos),
            $c === '<' => ($this->data[$pos + 1] ?? '') === '<' ? $this->dictionary($pos, $depth) : $this->hexString($pos),
            $c === '[' => $this->array($pos, $depth),
            ($c >= '0' && $c <= '9') || $c === '+' || $c === '-' || $c === '.' => $this->number($pos),
            default => $this->keyword($pos),
        };
    }

    /**
     * Reads the indirect object whose header is at $offset. A stream's data is located with its
     * /Length when that is right (an indirect /Length goes through $length, which may return
     * null), and otherwise by searching for "endstream".
     *
     * @param  (Closure(PdfRef): mixed)|null  $length
     * @return array{int, int, mixed, int} number, generation, value, offset after the object
     */
    public function indirectObject(int $offset, ?Closure $length = null): array
    {
        $header = $this->header($offset);
        if ($header === null) {
            throw UnreadablePdf::malformed('No object header at offset '.$offset.'.');
        }
        [$number, $generation, $pos] = $header;
        $value = $this->value($pos);
        $after = $this->skip($pos);
        if ($value instanceof PdfDictionary && $this->keywordAt($after, 'stream')) {
            [$raw, $pos] = $this->streamData($number, $value, $after + 6, $length);
            $value = new PdfStream($value, $raw);
        }

        return [$number, $generation, $value, $pos];
    }

    /**
     * @param  (Closure(PdfRef): mixed)|null  $resolveLength
     * @return array{string, int}
     */
    private function streamData(int $number, PdfDictionary $dictionary, int $pos, ?Closure $resolveLength): array
    {
        // The keyword is followed by CRLF or LF; a lone CR is tolerated.
        if (($this->data[$pos] ?? '') === "\r") {
            $pos++;
        }
        if (($this->data[$pos] ?? '') === "\n") {
            $pos++;
        }
        $length = $dictionary->get('Length');
        if ($length instanceof PdfRef) {
            $length = $resolveLength === null ? null : $resolveLength($length);
        }
        if (is_int($length) && $length >= 0 && $pos + $length <= $this->length) {
            $end = $pos + $length;
            $next = $end + strspn($this->data, self::WHITESPACE, $end);
            if ($this->keywordAt($next, 'endstream')) {
                return [substr($this->data, $pos, $length), $next + 9];
            }
            if ($this->keywordAt($next, 'endobj')) {
                // The /Length is right but the writer left out "endstream".
                return [substr($this->data, $pos, $length), $next];
            }
        }
        $found = strpos($this->data, 'endstream', $pos);
        if ($found === false) {
            throw UnreadablePdf::malformed('The stream of object '.$number.' has no endstream.');
        }
        $end = $found;
        if ($end > $pos && $this->data[$end - 1] === "\n") {
            $end--;
        }
        if ($end > $pos && $this->data[$end - 1] === "\r") {
            $end--;
        }

        return [substr($this->data, $pos, $end - $pos), $found + 9];
    }

    private function name(int &$pos): PdfName
    {
        $start = $pos + 1;
        $length = $start < $this->length ? strcspn($this->data, self::WHITESPACE.self::DELIMITERS, $start) : 0;
        $pos = $start + $length;
        $name = substr($this->data, $start, $length);
        if (str_contains($name, '#')) {
            $name = (string) preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $m): string => chr(((int) hexdec($m[1])) & 0xFF), $name);
        }

        return new PdfName($name);
    }

    private function literalString(int &$pos): PdfString
    {
        $p = $pos + 1;
        $depth = 1;
        $out = '';
        while (true) {
            if ($p >= $this->length) {
                throw UnreadablePdf::malformed('Unterminated string at offset '.$pos.'.');
            }
            $run = strcspn($this->data, "()\\\r", $p);
            $out .= substr($this->data, $p, $run);
            $p += $run;
            if ($p >= $this->length) {
                continue;
            }
            $c = $this->data[$p++];
            if ($c === '(') {
                $depth++;
                $out .= '(';
            } elseif ($c === ')') {
                if (--$depth === 0) {
                    break;
                }
                $out .= ')';
            } elseif ($c === "\r") {
                // An unescaped end-of-line is read as a single line feed.
                $out .= "\n";
                if (($this->data[$p] ?? '') === "\n") {
                    $p++;
                }
            } else {
                $out .= $this->escape($p);
            }
        }
        $pos = $p;

        return new PdfString($out);
    }

    /** Decodes the escape sequence after a backslash at $p and moves $p past it. */
    private function escape(int &$p): string
    {
        $e = $this->data[$p] ?? '';
        if ($e === '') {
            return '';
        }
        $p++;
        $simple = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C"];
        if (isset($simple[$e])) {
            return $simple[$e];
        }
        if ($e >= '0' && $e <= '7') {
            $digits = $e;
            while (strlen($digits) < 3 && ($d = $this->data[$p] ?? '') !== '' && $d >= '0' && $d <= '7') {
                $digits .= $d;
                $p++;
            }

            return chr(((int) octdec($digits)) & 0xFF);
        }
        if ($e === "\r") {
            // A backslash before an end-of-line continues the string on the next line.
            if (($this->data[$p] ?? '') === "\n") {
                $p++;
            }

            return '';
        }

        return $e === "\n" ? '' : $e;
    }

    private function hexString(int &$pos): PdfString
    {
        $end = strpos($this->data, '>', $pos + 1);
        if ($end === false) {
            throw UnreadablePdf::malformed('Unterminated hex string at offset '.$pos.'.');
        }
        $hex = str_replace(str_split(self::WHITESPACE), '', substr($this->data, $pos + 1, $end - $pos - 1));
        if (preg_match('/[^0-9A-Fa-f]/', $hex) === 1) {
            throw UnreadablePdf::malformed('Invalid hex string at offset '.$pos.'.');
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }
        $pos = $end + 1;

        return new PdfString((string) hex2bin($hex), true);
    }

    /** @return list<mixed> */
    private function array(int &$pos, int $depth): array
    {
        $this->guardDepth($depth + 1, $pos);
        $pos++;
        $items = [];
        while (true) {
            $pos = $this->skip($pos);
            if ($pos >= $this->length) {
                throw UnreadablePdf::malformed('Unterminated array.');
            }
            if ($this->data[$pos] === ']') {
                $pos++;

                return $items;
            }
            $items[] = $this->value($pos, $depth + 1);
        }
    }

    private function dictionary(int &$pos, int $depth): PdfDictionary
    {
        $this->guardDepth($depth + 1, $pos);
        $pos += 2;
        $entries = [];
        while (true) {
            $pos = $this->skip($pos);
            if ($pos >= $this->length) {
                throw UnreadablePdf::malformed('Unterminated dictionary.');
            }
            if ($this->data[$pos] === '>' && ($this->data[$pos + 1] ?? '') === '>') {
                $pos += 2;

                return new PdfDictionary($entries);
            }
            if ($this->data[$pos] !== '/') {
                throw UnreadablePdf::malformed('Dictionary key expected at offset '.$pos.'.');
            }
            $key = $this->name($pos)->name;
            $pos = $this->skip($pos);
            // A key directly followed by the end of the dictionary has no value; read it as null.
            $entries[$key] = substr($this->data, $pos, 2) === '>>' ? null : $this->value($pos, $depth + 1);
        }
    }

    private function number(int &$pos): int|float|PdfRef
    {
        $start = $pos;
        $p = $pos;
        if ($this->data[$p] === '+' || $this->data[$p] === '-') {
            $p++;
        }
        $digits = strspn($this->data, '0123456789', $p);
        $p += $digits;
        $isReal = ($this->data[$p] ?? '') === '.';
        if ($isReal) {
            $p++;
            $p += strspn($this->data, '0123456789', $p);
        }
        $pos = $p;
        $text = substr($this->data, $start, $p - $start);
        if ($isReal) {
            return (float) $text;
        }
        if ($digits === 0) {
            // A lone sign is read as zero, as common readers do.
            return 0;
        }
        if ($digits > 18) {
            return (float) $text;
        }
        $int = (int) $text;
        if ($int >= 0 && ($ref = $this->reference($int, $pos)) !== null) {
            return $ref;
        }

        return $int;
    }

    /** Recognises "G R" after an object number ending at $p; moves $pos past it. */
    private function reference(int $number, int &$pos): ?PdfRef
    {
        $p = $pos + strspn($this->data, self::WHITESPACE, $pos);
        $digits = strspn($this->data, '0123456789', $p);
        if ($digits === 0 || $digits > 5) {
            return null;
        }
        $generation = (int) substr($this->data, $p, $digits);
        $p += $digits;
        $p += strspn($this->data, self::WHITESPACE, $p);
        if (! $this->keywordAt($p, 'R')) {
            return null;
        }
        $pos = $p + 1;

        return new PdfRef($number, $generation);
    }

    private function keyword(int &$pos): ?bool
    {
        $length = strcspn($this->data, self::WHITESPACE.self::DELIMITERS, $pos);
        $word = substr($this->data, $pos, $length);
        if ($word === 'true' || $word === 'false' || $word === 'null') {
            $pos += $length;

            return match ($word) {
                'true' => true,
                'false' => false,
                default => null,
            };
        }

        throw UnreadablePdf::malformed(sprintf('Unexpected token "%s" at offset %d.', substr($word === '' ? $this->data[$pos] : $word, 0, 20), $pos));
    }

    private function guardDepth(int $depth, int $pos): void
    {
        if ($depth > PdfParser::MAX_DEPTH) {
            throw UnreadablePdf::refused('Arrays and dictionaries nest deeper than '.PdfParser::MAX_DEPTH.' levels at offset '.$pos.'.');
        }
    }

    private function isRegular(string $c): bool
    {
        return ! str_contains(self::WHITESPACE.self::DELIMITERS, $c);
    }
}
