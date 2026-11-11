<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use IntrAgile\EInvoicing\CodeList\CountryCode;

/**
 * Postal address of a party or delivery location (BG-5, BG-8, BG-12, BG-15).
 *
 * Term ids are listed in the order seller (BG-5) / buyer (BG-8) / tax representative (BG-12) /
 * deliver to (BG-15).
 */
final readonly class PostalAddress
{
    public function __construct(
        /** Country code (BT-40 / BT-55 / BT-69 / BT-80). */
        public CountryCode $country,
        /** Address line 1 (BT-35 / BT-50 / BT-64 / BT-75). */
        public ?string $line1 = null,
        /** Address line 2 (BT-36 / BT-51 / BT-65 / BT-76). */
        public ?string $line2 = null,
        /** Address line 3 (BT-162 / BT-163 / BT-164 / BT-165). */
        public ?string $line3 = null,
        /** City (BT-37 / BT-52 / BT-66 / BT-77). */
        public ?string $city = null,
        /** Post code (BT-38 / BT-53 / BT-67 / BT-78). */
        public ?string $postCode = null,
        /** Country subdivision (BT-39 / BT-54 / BT-68 / BT-79). */
        public ?string $subdivision = null,
    ) {}
}
