<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

/**
 * Direct debit (BG-19).
 */
final readonly class DirectDebit
{
    public function __construct(
        /** Mandate reference identifier (BT-89). */
        public ?string $mandateReference = null,
        /**
         * Bank assigned creditor identifier (BT-90), e.g. a SEPA creditor identifier.
         * EN 16931 gives this term no scheme; UBL writers emit it with schemeID "SEPA".
         */
        public ?string $creditorIdentifier = null,
        /** Debited account identifier (BT-91). */
        public ?string $debitedAccountIdentifier = null,
    ) {}
}
