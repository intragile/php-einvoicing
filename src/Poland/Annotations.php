<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * The FA(3) annotations block (Adnotacje). Every flag defaults to off, which is what a plain VAT
 * invoice declares.
 */
final readonly class Annotations
{
    public function __construct(
        /** Cash accounting method (P_16). */
        public bool $cashMethod = false,
        /** Self-billing (P_17). */
        public bool $selfBilling = false,
        /** Reverse charge (P_18). */
        public bool $reverseCharge = false,
        /** Split payment mechanism (P_18A). */
        public bool $splitPayment = false,
        /** VAT exemption (P_19 with P_19A, P_19B or P_19C); null writes P_19N. */
        public ?Exemption $exemption = null,
        /** Intra-Community supply of new means of transport (NoweSrodkiTransportu with P_22 = 1), kept verbatim; null writes P_22N. */
        public ?RawFragment $newTransport = null,
        /** Simplified triangular transaction (P_23). */
        public bool $triangular = false,
        /** Margin scheme (PMarzy with its P_PMarzy_* flag); null writes P_PMarzyN. */
        public ?MarginScheme $margin = null,
    ) {}
}
