<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

/**
 * Seller tax registration identifier (BT-32): a local tax number that is not the VAT identifier,
 * e.g. the Hungarian "adószám".
 */
final readonly class TaxRegistration
{
    public function __construct(
        /** Seller tax registration identifier (BT-32). */
        public string $identifier,
        /**
         * Tax scheme qualifier as found in the source syntax (UBL TaxScheme/ID, CII schemeID "FC").
         * EN 16931 defines no scheme for BT-32; it is kept only so a read invoice round-trips.
         */
        public ?string $taxScheme = null,
    ) {}
}
