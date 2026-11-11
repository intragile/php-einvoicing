<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;

/**
 * Electronic address of a party (BT-34 seller, BT-49 buyer).
 */
final readonly class ElectronicAddress
{
    public function __construct(
        /** Electronic address (BT-34 / BT-49). */
        public string $value,
        /** Electronic address scheme identifier (BT-34-1 / BT-49-1). */
        public ElectronicAddressScheme $scheme,
    ) {}
}
