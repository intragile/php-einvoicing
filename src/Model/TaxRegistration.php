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
         * Tax scheme qualifier as found in UBL TaxScheme/ID. EN 16931 defines no scheme for BT-32;
         * it is kept only so a read invoice round-trips. The writer placeholder "TAX" and the CII
         * binding's fixed schemeID "FC" read as null.
         */
        public ?string $taxScheme = null,
    ) {}
}
