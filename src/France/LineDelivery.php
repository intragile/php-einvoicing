<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Model\PostalAddress;

/**
 * Delivery of an invoice line (EXTENDED-CTC-FR), for invoices delivered to several places:
 * the line deliver-to location (EXT-FR-FE-BG-10) and the actual delivery (EXT-FR-FE-BG-11).
 */
final readonly class LineDelivery
{
    public function __construct(
        /** Line deliver-to location name (EXT-FR-FE-149). */
        public ?string $name = null,
        /** Line deliver-to address (EXT-FR-FE-150 to EXT-FR-FE-157). */
        public ?PostalAddress $address = null,
        /** Line actual delivery date (EXT-FR-FE-158); only the date part is significant. */
        public ?DateTimeImmutable $actualDeliveryDate = null,
    ) {}
}
