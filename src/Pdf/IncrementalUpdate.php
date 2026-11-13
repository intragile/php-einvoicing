<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;

/**
 * An incremental update (ISO 32000-1, 7.5.6): new and replaced objects appended after the
 * original bytes, which are never changed, followed by a cross-reference section whose /Prev
 * points at the original's newest one.
 *
 * The section is an xref stream when the original's newest section is one, and a classic table
 * otherwise. The output is deterministic for the same objects and file identifier.
 */
final class IncrementalUpdate
{
    private int $next;

    /** @var array<int, mixed> object number => value */
    private array $objects = [];

    /** @var array<int, int> object number => generation */
    private array $generations = [];

    /**
     * @param  string|null  $fileId  the second /ID element (raw bytes, usually 16); null derives
     *                               it from the original bytes and the appended objects
     *
     * @throws UnreadablePdf when the original's cross-reference index had to be reconstructed
     */
    public function __construct(private readonly PdfDocument $base, private readonly ?string $fileId = null)
    {
        if ($base->reconstructed()) {
            throw UnreadablePdf::refused(
                'The cross-reference table of this PDF is damaged and had to be reconstructed, so an incremental '
                .'update would be unsafe: the update would point back at offsets that are wrong. Re-save the PDF '
                .'with a PDF tool (for example qpdf, Ghostscript or a PDF editor) and try again.'
            );
        }
        $this->next = $base->size();
    }

    /** Adds a new object under the next free object number. */
    public function add(mixed $object): PdfRef
    {
        if ($this->next > PdfParser::MAX_OBJECTS) {
            throw UnreadablePdf::refused('The PDF has no free object number left.');
        }
        $number = $this->next++;
        $this->objects[$number] = $object;
        $this->generations[$number] = 0;

        return new PdfRef($number, 0);
    }

    /** Replaces object $number of the original, keeping its generation. */
    public function replace(int $number, mixed $object): void
    {
        $this->objects[$number] = $object;
        $this->generations[$number] = $this->generations[$number] ?? $this->base->generation($number);
        $this->next = max($this->next, $number + 1);
    }

    /**
     * The original bytes with the update appended.
     *
     * The trailer carries /Size, /Root, /Prev, the original /Info and an /ID whose first element
     * is the original's (or the new one when the original has none); $trailerExtra entries are
     * added last and win.
     */
    public function toBytes(PdfDictionary $trailerExtra = new PdfDictionary([])): string
    {
        $original = $this->base->bytes();
        $out = $original;
        if ($out !== '' && ! in_array($out[-1], ["\n", "\r"], true)) {
            $out .= "\n";
        }

        $objects = $this->objects;
        ksort($objects);
        $offsets = [];
        $body = '';
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($out) + strlen($body);
            $body .= Serializer::object($number, $this->generations[$number], $object);
        }
        $out .= $body;

        $trailer = $this->trailer($original, $body, $trailerExtra);
        $start = strlen($out);
        if ($this->base->usesXrefStream()) {
            $number = $this->next;
            $offsets[$number] = $start;
            $out .= $this->xrefStream($number, $offsets, $trailer->with('Size', $number + 1));
        } else {
            $out .= $this->classicSection($offsets, $trailer->with('Size', $this->next));
        }

        return $out.'startxref'."\n".$start."\n".'%%EOF'."\n";
    }

    private function trailer(string $original, string $body, PdfDictionary $extra): PdfDictionary
    {
        $base = $this->base->trailer();
        $root = $base->get('Root');
        if (! $root instanceof PdfRef) {
            throw UnreadablePdf::malformed('The PDF trailer has no /Root reference.');
        }

        $new = new PdfString($this->fileId ?? md5($original.$body, true), true);
        $id = $base->get('ID');
        $first = is_array($id) && ($id[0] ?? null) instanceof PdfString ? $id[0] : $new;

        $entries = ['Size' => 0, 'Root' => $root, 'Prev' => $this->base->startXref()];
        if ($base->has('Info')) {
            $entries['Info'] = $base->get('Info');
        }
        $entries['ID'] = [$first, $new];

        return new PdfDictionary(array_merge($entries, $extra->entries()));
    }

    /** @param array<int, int> $offsets */
    private function classicSection(array $offsets, PdfDictionary $trailer): string
    {
        $section = 'xref'."\n";
        foreach (self::subsections(array_keys($offsets)) as [$first, $numbers]) {
            $section .= $first.' '.count($numbers)."\n";
            foreach ($numbers as $number) {
                $section .= sprintf('%010d %05d n', $offsets[$number], $this->generations[$number])." \n";
            }
        }

        return $section.'trailer'."\n".Serializer::value($trailer)."\n";
    }

    /** @param array<int, int> $offsets */
    private function xrefStream(int $number, array $offsets, PdfDictionary $trailer): string
    {
        ksort($offsets);
        $width = max(1, (int) ceil(strlen(dechex(max([0, ...$offsets]))) / 2));
        $index = [];
        $data = '';
        foreach (self::subsections(array_keys($offsets)) as [$first, $numbers]) {
            $index[] = $first;
            $index[] = count($numbers);
            foreach ($numbers as $object) {
                $data .= "\x01".substr(pack('J', $offsets[$object]), 8 - $width).pack('n', $this->generations[$object] ?? 0);
            }
        }

        $dictionary = new PdfDictionary(array_merge(
            ['Type' => new PdfName('XRef'), 'W' => [1, $width, 2], 'Index' => $index],
            $trailer->entries(),
        ));

        return Serializer::object($number, 0, new PdfStream($dictionary, $data));
    }

    /**
     * Groups object numbers into runs of consecutive numbers.
     *
     * @param  list<int>  $numbers
     * @return list<array{int, non-empty-list<int>}> first number => the run
     */
    private static function subsections(array $numbers): array
    {
        sort($numbers);
        $runs = [];
        foreach ($numbers as $number) {
            $last = array_key_last($runs);
            if ($last !== null && end($runs[$last][1]) === $number - 1) {
                $runs[$last][1][] = $number;
            } else {
                $runs[] = [$number, [$number]];
            }
        }

        return $runs;
    }
}
