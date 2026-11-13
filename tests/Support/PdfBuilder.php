<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use LogicException;

/**
 * Writes minimal PDF files in code, so parser tests need no binary fixtures.
 *
 * Objects are given as raw PDF syntax. Each call to classic(), xrefStream() or hybrid() closes
 * one revision: it appends the pending objects and a cross-reference section of that kind, with
 * /Prev pointing at the previous section, and returns the whole file so far. Further objects then
 * start the next revision as an incremental update.
 */
final class PdfBuilder
{
    private string $bytes;

    private ?int $lastStartXref = null;

    private int $size = 1;

    /** @var array<int, array{string, int}> object number => [body, generation] */
    private array $objects = [];

    /** @var array<int, array<int, string>> stream number => [object number => body] */
    private array $objectStreams = [];

    /** @var array<int, int> object number => next generation */
    private array $freed = [];

    private string $trailer = '';

    public function __construct(string $version = '1.7', private readonly string $eol = "\n")
    {
        $this->bytes = '%PDF-'.$version.$this->eol."%\xE2\xE3\xCF\xD3".$this->eol;
    }

    public function object(int $number, string $body, int $generation = 0): self
    {
        $this->objects[$number] = [$body, $generation];

        return $this;
    }

    /**
     * A stream object. $entries are extra dictionary entries in PDF syntax. $length overrides the
     * /Length value written (an int, or raw syntax such as "9 0 R").
     */
    public function stream(int $number, string $entries, string $data, bool $flate = false, int|string|null $length = null): self
    {
        if ($flate) {
            $data = (string) gzcompress($data);
            $entries .= ' /Filter /FlateDecode';
        }
        $length ??= strlen($data);
        $body = '<< '.$entries.' /Length '.$length.' >>'.$this->eol.'stream'.$this->eol.$data.$this->eol.'endstream';

        return $this->object($number, $body);
    }

    /**
     * An object stream holding the given objects; written only by xrefStream() and hybrid().
     *
     * @param  array<int, string>  $objects  object number => body
     */
    public function objectStream(int $number, array $objects): self
    {
        $this->objectStreams[$number] = $objects;

        return $this;
    }

    public function free(int $number, int $nextGeneration): self
    {
        $this->freed[$number] = $nextGeneration;

        return $this;
    }

    /** Extra trailer entries in PDF syntax, such as "/Root 1 0 R". */
    public function trailer(string $entries): self
    {
        $this->trailer = $entries;

        return $this;
    }

    /** Closes the revision with a classic cross-reference table. */
    public function classic(): string
    {
        if ($this->objectStreams !== []) {
            throw new LogicException('Object streams need an xref stream.');
        }
        $offsets = $this->writeObjects();
        $start = strlen($this->bytes);
        $this->bytes .= $this->classicTable($offsets, $this->freedEntries());
        $this->bytes .= 'trailer'.$this->eol.'<< /Size '.$this->size.$this->prev().' '.$this->trailer.' >>'.$this->eol;

        return $this->finish($start);
    }

    /**
     * Closes the revision with a cross-reference stream (W [1 4 2], Flate with a PNG predictor
     * whose row filters cycle through None, Sub, Up, Average and Paeth).
     */
    public function xrefStream(): string
    {
        $offsets = $this->writeObjects();
        $rows = [];
        foreach ($offsets as $number => [$offset, $generation]) {
            $rows[$number] = [1, $offset, $generation];
        }
        foreach ($this->objectStreams as $streamNumber => $members) {
            $index = 0;
            foreach (array_keys($members) as $member) {
                $rows[$member] = [2, $streamNumber, $index++];
            }
        }
        foreach ($this->freedEntries() as $number => $generation) {
            $rows[$number] = [0, 0, $generation];
        }
        $number = $this->size;
        $start = strlen($this->bytes);
        $rows[$number] = [1, $start, 0];
        $this->size = $number + 1;
        $this->bytes .= $this->xrefStreamObject($number, $rows, '/Size '.$this->size.$this->prev().' '.$this->trailer);

        return $this->finish($start);
    }

    /**
     * Closes the revision with a classic table for the direct objects and a trailer /XRefStm
     * pointing at a cross-reference stream for the objects inside object streams.
     */
    public function hybrid(): string
    {
        $offsets = $this->writeObjects();
        $rows = [];
        foreach ($this->objectStreams as $streamNumber => $members) {
            $index = 0;
            foreach (array_keys($members) as $member) {
                $rows[$member] = [2, $streamNumber, $index++];
            }
        }
        $number = $this->size;
        $streamOffset = strlen($this->bytes);
        $rows[$number] = [1, $streamOffset, 0];
        $this->size = $number + 1;
        $this->bytes .= $this->xrefStreamObject($number, $rows, '/Size '.$this->size);
        $offsets[$number] = [$streamOffset, 0];
        ksort($offsets);
        $start = strlen($this->bytes);
        $this->bytes .= $this->classicTable($offsets, $this->freedEntries());
        $this->bytes .= 'trailer'.$this->eol.'<< /Size '.$this->size.$this->prev().' /XRefStm '.$streamOffset.' '.$this->trailer.' >>'.$this->eol;

        return $this->finish($start);
    }

    /** @return array<int, array{int, int}> object number => [offset, generation] */
    private function writeObjects(): array
    {
        $offsets = [];
        ksort($this->objects);
        foreach ($this->objects as $number => [$body, $generation]) {
            $offsets[$number] = [strlen($this->bytes), $generation];
            $this->bytes .= $number.' '.$generation.' obj'.$this->eol.$body.$this->eol.'endobj'.$this->eol;
            $this->size = max($this->size, $number + 1);
        }
        foreach ($this->objectStreams as $streamNumber => $members) {
            $header = '';
            $content = '';
            foreach ($members as $member => $body) {
                $header .= $member.' '.strlen($content).' ';
                $content .= $body.' ';
                $this->size = max($this->size, $member + 1);
            }
            $data = (string) gzcompress($header.$content);
            $offsets[$streamNumber] = [strlen($this->bytes), 0];
            $this->bytes .= $streamNumber.' 0 obj'.$this->eol
                .'<< /Type /ObjStm /N '.count($members).' /First '.strlen($header).' /Filter /FlateDecode /Length '.strlen($data).' >>'.$this->eol
                .'stream'.$this->eol.$data.$this->eol.'endstream'.$this->eol.'endobj'.$this->eol;
            $this->size = max($this->size, $streamNumber + 1);
        }
        ksort($offsets);

        return $offsets;
    }

    /** @return array<int, int> */
    private function freedEntries(): array
    {
        foreach (array_keys($this->freed) as $number) {
            $this->size = max($this->size, $number + 1);
        }

        return $this->freed;
    }

    /**
     * @param  array<int, array{int, int}>  $offsets
     * @param  array<int, int>  $freed
     */
    private function classicTable(array $offsets, array $freed): string
    {
        $entries = [];
        if ($this->lastStartXref === null) {
            $entries[0] = sprintf('%010d %05d f', 0, 65535);
        }
        foreach ($offsets as $number => [$offset, $generation]) {
            $entries[$number] = sprintf('%010d %05d n', $offset, $generation);
        }
        foreach ($freed as $number => $generation) {
            $entries[$number] = sprintf('%010d %05d f', 0, $generation);
        }
        ksort($entries);
        // A CR-only file gets 19-byte entries; otherwise the standard 2-byte " \n" ending.
        $entryEnd = $this->eol === "\r" ? "\r" : ' '."\n";
        $table = 'xref'.$this->eol;
        foreach ($entries as $number => $entry) {
            $table .= $number.' 1'.$this->eol.$entry.$entryEnd;
        }

        return $table;
    }

    /** @param array<int, array{int, int, int}> $rows */
    private function xrefStreamObject(int $number, array $rows, string $entries): string
    {
        ksort($rows);
        $index = [];
        $raw = [];
        foreach ($rows as $object => [$type, $field2, $field3]) {
            $index[] = $object.' 1';
            $raw[] = pack('CNn', $type, $field2, $field3);
        }
        $data = (string) gzcompress($this->pngEncode($raw));

        return $number.' 0 obj'.$this->eol
            .'<< /Type /XRef /W [1 4 2] /Index ['.implode(' ', $index).'] '.$entries
            .' /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 7 >> /Length '.strlen($data).' >>'.$this->eol
            .'stream'.$this->eol.$data.$this->eol.'endstream'.$this->eol.'endobj'.$this->eol;
    }

    /**
     * PNG-encodes rows of one byte per pixel, cycling the row filter type.
     *
     * @param  list<string>  $rows
     */
    private function pngEncode(array $rows): string
    {
        $out = '';
        $previous = str_repeat("\0", 7);
        foreach ($rows as $r => $row) {
            $type = $r % 5;
            $encoded = '';
            for ($i = 0; $i < 7; $i++) {
                $x = ord($row[$i]);
                $a = $i > 0 ? ord($row[$i - 1]) : 0;
                $b = ord($previous[$i]);
                $c = $i > 0 ? ord($previous[$i - 1]) : 0;
                $predictor = match ($type) {
                    0 => 0,
                    1 => $a,
                    2 => $b,
                    3 => intdiv($a + $b, 2),
                    default => self::paeth($a, $b, $c),
                };
                $encoded .= chr(($x - $predictor) & 0xFF);
            }
            $out .= chr($type).$encoded;
            $previous = $row;
        }

        return $out;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        return $pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c);
    }

    private function prev(): string
    {
        return $this->lastStartXref === null ? '' : ' /Prev '.$this->lastStartXref;
    }

    private function finish(int $start): string
    {
        $this->bytes .= 'startxref'.$this->eol.$start.$this->eol.'%%EOF'.$this->eol;
        $this->lastStartXref = $start;
        $this->objects = [];
        $this->objectStreams = [];
        $this->freed = [];
        $this->trailer = '';

        return $this->bytes;
    }
}
