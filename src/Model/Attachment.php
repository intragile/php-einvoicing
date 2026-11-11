<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use IntrAgile\EInvoicing\CodeList\MimeCode;

/**
 * Attached document (BT-125) embedded in an additional supporting document.
 */
final readonly class Attachment
{
    public function __construct(
        /** Attached document (BT-125) as raw binary content; writers apply Base64 encoding. */
        public string $content,
        /** Attached document mime code (BT-125-1). */
        public MimeCode $mimeCode,
        /** Attached document filename (BT-125-2). */
        public string $filename,
    ) {}
}
