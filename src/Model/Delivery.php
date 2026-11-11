<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use DateTimeImmutable;

/**
 * Delivery information (BG-13), including the invoicing period (BG-14) it contains in EN 16931.
 */
final readonly class Delivery
{
    public function __construct(
        /** Deliver to party name (BT-70). */
        public ?string $partyName = null,
        /** Deliver to location identifier (BT-71) with its scheme (BT-71-1). */
        public ?Identifier $locationIdentifier = null,
        /** Actual delivery date (BT-72); only the date part is significant. */
        public ?DateTimeImmutable $actualDeliveryDate = null,
        /** Invoicing period (BG-14). */
        public ?Period $invoicingPeriod = null,
        /** Deliver to address (BG-15). */
        public ?PostalAddress $address = null,
    ) {}
}
