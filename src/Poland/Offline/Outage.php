<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland\Offline;

use DateTimeImmutable;

/**
 * An announced KSeF outage. A null end means the outage has not ended yet.
 */
final readonly class Outage
{
    public function __construct(
        public OutageCategory $category,
        public DateTimeImmutable $start,
        public ?DateTimeImmutable $end,
        public ?string $id = null,
    ) {}
}
