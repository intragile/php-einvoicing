<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use IntrAgile\EInvoicing\CodeList\TextSubjectCode;

/**
 * Invoice note (BG-1).
 */
final readonly class Note
{
    public function __construct(
        /** Invoice note (BT-22). */
        public string $text,
        /** Invoice note subject code (BT-21). */
        public ?TextSubjectCode $subjectCode = null,
    ) {}
}
