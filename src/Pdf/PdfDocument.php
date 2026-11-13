<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

use IntrAgile\EInvoicing\Pdf\Exception\EncryptedPdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnsupportedPdfFeature;

/**
 * A parsed PDF, read from untrusted bytes without any PDF library.
 *
 * It reads classic cross-reference tables, xref streams, hybrid files, object streams and
 * incremental updates, and rebuilds the index by scanning when the file's own is damaged
 * (reconstructed() then says so). Objects are parsed on first access. Damaged or hostile input
 * raises UnreadablePdf, never a PHP warning; see PdfParser for the safety limits.
 */
final class PdfDocument
{
    private function __construct(private readonly PdfParser $parser, private readonly string $bytes) {}

    /**
     * @throws EncryptedPdf when the trailer has /Encrypt
     * @throws UnreadablePdf when the bytes are not a readable PDF
     */
    public static function parse(string $bytes): self
    {
        return PdfParser::guarded(static fn (): self => new self(new PdfParser($bytes), $bytes));
    }

    /** The version in the %PDF- header, such as "1.7". */
    public function version(): string
    {
        return $this->parser->version();
    }

    /** The trailer, merged over the /Prev chain with the newest value of each key winning. */
    public function trailer(): PdfDictionary
    {
        return $this->parser->xref()->trailer;
    }

    public function catalog(): PdfDictionary
    {
        return $this->parser->catalog();
    }

    /** Follows PdfRef values to a direct value; a missing or free object resolves to null. */
    public function resolve(mixed $value): mixed
    {
        return PdfParser::guarded(fn (): mixed => $this->parser->resolve($value));
    }

    /** The value of object $number in its newest revision, or null when there is none. */
    public function object(int $number): mixed
    {
        return PdfParser::guarded(fn (): mixed => $this->parser->object($number));
    }

    /**
     * The stream's data with its filters applied.
     *
     * @throws UnsupportedPdfFeature for a filter other than FlateDecode, and for image streams,
     *                               which this layer never decodes
     */
    public function decodedStream(PdfStream $stream): string
    {
        if ($stream->dictionary->isName('Subtype', 'Image')) {
            throw new UnsupportedPdfFeature('Image streams are not decoded.');
        }

        return PdfParser::guarded(fn (): string => $this->parser->decode($stream));
    }

    /**
     * The byte offset of the newest cross-reference section: the /Prev of an appended update.
     * After reconstruction it is the file's own (unusable) startxref value, or 0 when it has none.
     */
    public function startXref(): int
    {
        return $this->parser->xref()->startXref;
    }

    /** True when the newest cross-reference section is an xref stream. */
    public function usesXrefStream(): bool
    {
        return $this->parser->xref()->xrefStream;
    }

    /**
     * The next free object number: the trailer /Size, or one more than the highest object
     * number the index lists when that is larger (some producers write a /Size that is too small,
     * and allocating from it would overwrite existing objects). After reconstruction it is always
     * one more than the highest object number found: the trailer of a damaged file is not trusted.
     */
    public function size(): int
    {
        $listed = $this->parser->xref()->highest() + 1;
        if ($this->reconstructed()) {
            return $listed;
        }
        $size = $this->trailer()->get('Size');

        return is_int($size) ? max($size, $listed) : $listed;
    }

    /**
     * The generation of object $number in the newest index entry: what an update that replaces
     * the object writes. Objects inside object streams, and unlisted objects, have generation 0.
     */
    public function generation(int $number): int
    {
        $entry = $this->parser->xref()->entry($number);

        return $entry !== null && $entry[0] === CrossReference::IN_FILE ? $entry[2] : 0;
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    /** True when the cross-reference index was rebuilt by scanning, because the file's own was unusable. */
    public function reconstructed(): bool
    {
        return $this->parser->xref()->reconstructed;
    }
}
