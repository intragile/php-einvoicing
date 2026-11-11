<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use DateTimeImmutable;

/**
 * Preceding invoice reference (BG-3).
 */
final readonly class PrecedingInvoiceReference
{
    public function __construct(
        /** Preceding invoice reference (BT-25). */
        public string $number,
        /** Preceding invoice issue date (BT-26); only the date part is significant. */
        public ?DateTimeImmutable $issueDate = null,
    ) {}
}
