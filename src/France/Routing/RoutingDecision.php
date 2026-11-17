<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Routing;

use IntrAgile\EInvoicing\France\EReporting\DeclarantRole;

/**
 * Outcome of routing one invoice: the transaction channel, the payment reporting, the rule that
 * decided them and, for e-reports, the role in which the caller declares.
 */
final readonly class RoutingDecision
{
    /**
     * @param  bool  $needsDirectoryCheck  the recipient's directory status could not be
     *                                     determined; the e-invoice is kept, but the caller should
     *                                     check the directory before sending
     */
    public function __construct(
        public Channel $channel,
        public PaymentReporting $payment,
        public RoutingRule $rule,
        public ?DeclarantRole $declarant,
        public bool $needsDirectoryCheck = false,
    ) {}
}
