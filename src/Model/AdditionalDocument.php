<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

/**
 * Additional supporting document (BG-24).
 */
final readonly class AdditionalDocument
{
    public function __construct(
        /** Supporting document reference (BT-122). */
        public string $reference,
        /** Supporting document description (BT-123). */
        public ?string $description = null,
        /** External document location (BT-124). */
        public ?string $externalLocation = null,
        /** Attached document (BT-125, BT-125-1, BT-125-2). */
        public ?Attachment $attachment = null,
    ) {}
}
