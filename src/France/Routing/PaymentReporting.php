<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Routing;

/**
 * How the collection of an invoice whose VAT is due on collection is reported (CGI art. 290 A).
 */
enum PaymentReporting
{
    /** "Encaissée" lifecycle status (212) on the e-invoice. */
    case CollectedStatus;

    /** Per-invoice payment e-report (Flux 10.2). */
    case PaymentReport;

    /** Daily aggregated payment e-report (Flux 10.4). */
    case AggregatedPaymentReport;

    /** No payment reporting. */
    case None;
}
