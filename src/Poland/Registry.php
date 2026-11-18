<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * Registry data of the seller printed in the invoice footer (Rejestry).
 */
final readonly class Registry
{
    public function __construct(
        /** Full name (PelnaNazwa). */
        public ?string $fullName = null,
        /** National Court Register number (KRS). */
        public ?string $krs = null,
        /** Statistical number (REGON). */
        public ?string $regon = null,
        /** Waste database number (BDO). */
        public ?string $bdo = null,
    ) {}
}
