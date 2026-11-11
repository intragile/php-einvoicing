<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

/**
 * Payment card information (BG-18).
 */
final readonly class PaymentCard
{
    public function __construct(
        /** Payment card primary account number (BT-87); EN 16931 allows only the last 4-6 digits. */
        public string $primaryAccountNumber,
        /** Payment card holder name (BT-88). */
        public ?string $holderName = null,
    ) {}
}
