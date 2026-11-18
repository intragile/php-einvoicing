<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

/**
 * A Flux 10 e-reporting file deposited with B2Brouter, as a B2Brouter ledger.
 */
final readonly class EReportDeposit
{
    public function __construct(
        /** The B2Brouter ledger id; B2Brouter also writes it into the file as ReportDocument/Id. */
        public string $ledgerId,
        public LedgerState $state,
        /**
         * Whether B2Brouter had imported the file before, under the same idempotency key, and
         * returned that ledger instead of creating another; always true for a ledger that was read.
         */
        public bool $alreadyExisted,
        /** The error code the DGFiP or the transport reported, for example "303" for a refused file. */
        public ?string $errorCode = null,
        /** The error detail the DGFiP or the transport reported. */
        public ?string $errorDescription = null,
    ) {}
}
