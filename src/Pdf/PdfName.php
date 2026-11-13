<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

/**
 * A PDF name object, held decoded and without the leading slash: /A#20B is "A B".
 */
final readonly class PdfName
{
    public function __construct(public string $name) {}
}
