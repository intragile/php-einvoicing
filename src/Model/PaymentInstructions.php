<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;

/**
 * Payment instructions (BG-16).
 */
final readonly class PaymentInstructions
{
    public function __construct(
        /** Payment means type code (BT-81). */
        public PaymentMeansCode $meansCode,
        /** Payment means text (BT-82). */
        public ?string $meansText = null,
        /** Remittance information (BT-83). */
        public ?string $remittanceInformation = null,
        /** @var list<CreditTransfer> Credit transfers (BG-17). */
        public array $creditTransfers = [],
        /** Payment card information (BG-18). */
        public ?PaymentCard $card = null,
        /** Direct debit (BG-19). */
        public ?DirectDebit $directDebit = null,
    ) {}
}
