<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf\Exception;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;

/**
 * Thrown when bytes cannot be read as a PDF: they are not a PDF, they are damaged beyond
 * recovery, or they breach one of the safety limits of PdfParser.
 */
class UnreadablePdf extends UnsupportedDocument
{
    /** Exception code for damage that rebuilding the cross-reference index may get past. */
    public const int MALFORMED = 1;

    /** Exception code for a breached safety limit or a hostile structure; never recovered. */
    public const int REFUSED = 2;

    public static function malformed(string $message): self
    {
        return new self($message, self::MALFORMED);
    }

    public static function refused(string $message): self
    {
        return new self($message, self::REFUSED);
    }
}
