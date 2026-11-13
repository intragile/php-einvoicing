<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

/**
 * A PDF string with its escapes resolved. $hex records whether it was written as <...>, so a
 * writer can keep the original form.
 */
final readonly class PdfString
{
    public function __construct(public string $bytes, public bool $hex = false) {}
}
