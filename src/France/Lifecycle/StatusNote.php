<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

use IntrAgile\EInvoicing\CodeList\TextSubjectCode;

/**
 * Free-text note attached to a status, such as the comment explaining a refusal or a suspension.
 */
final readonly class StatusNote
{
    public function __construct(
        /** Note content (MDT-126). */
        public string $content,
        /** Note subject (MDT-127). */
        public ?TextSubjectCode $subject = null,
        /** Note content code. */
        public ?string $contentCode = null,
    ) {}
}
