<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

/**
 * A stream object: its dictionary and its still-encoded data. PdfDocument::decodedStream()
 * applies the filters.
 */
final readonly class PdfStream
{
    public function __construct(public PdfDictionary $dictionary, public string $raw) {}
}
