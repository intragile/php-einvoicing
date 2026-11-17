<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

/**
 * French invoice extension, attached to an invoice through its extension bag.
 */
final readonly class FrenchInvoice
{
    public function __construct(
        /** Profile the invoice is produced for. */
        public FrenchProfile $profile = FrenchProfile::En16931,
    ) {}
}
