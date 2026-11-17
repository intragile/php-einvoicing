<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

use IntrAgile\EInvoicing\France\Lifecycle\LifecycleStatus;
use IntrAgile\EInvoicing\France\Lifecycle\StatusReason;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;

/**
 * The state of an invoice at B2Brouter, acting as plateforme agréée, at the time it was queried.
 */
final readonly class PlatformStatus
{
    public function __construct(
        /** The B2Brouter invoice id that was queried. */
        public string $documentId,
        /** B2Brouter's invoice state as sent, for example "registered"; kept for states this library does not know. */
        public string $platformState,
        /**
         * The lifecycle status the state stands for, or null when the state stands for none (for
         * example "new" or "sending") or cannot tell which.
         */
        public ?LifecycleStatus $lifecycle,
        /** The delivery state derived from the same B2Brouter state, as the Peppol transport reports it. */
        public DeliveryStatus $delivery,
        /** The reason given with a refusal, when B2Brouter reports one from the closed French list. */
        public ?StatusReason $refusalReason,
        /**
         * The ids of the B2Brouter tax reports (e-reporting flux 1) that carry this invoice.
         *
         * @var list<string>
         */
        public array $taxReportIds,
        /** Whether the invoice was received by the account rather than issued by it. */
        public bool $received,
    ) {}
}
