<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use DateTimeImmutable;

/**
 * Invoicing period (BG-14) or invoice line period (BG-26).
 *
 * Only the date part of each value is significant; any time of day is ignored by writers.
 */
final readonly class Period
{
    public function __construct(
        /** Invoicing period start date (BT-73) / invoice line period start date (BT-134). */
        public ?DateTimeImmutable $start = null,
        /** Invoicing period end date (BT-74) / invoice line period end date (BT-135). */
        public ?DateTimeImmutable $end = null,
    ) {}
}
