<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

/**
 * An indirect reference ("12 0 R"); PdfDocument::resolve() follows it.
 */
final readonly class PdfRef
{
    public function __construct(public int $number, public int $generation) {}
}
