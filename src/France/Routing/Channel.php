<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Routing;

/**
 * How the transaction data of an invoice reaches the tax administration.
 */
enum Channel
{
    /** Structured e-invoice exchanged through an approved platform (CGI art. 289 bis). */
    case EInvoice;

    /** Per-invoice transaction e-report (Flux 10.1, CGI art. 290). */
    case TransactionReport;

    /** Daily aggregated transaction e-report (Flux 10.3, CGI art. 290). */
    case AggregatedTransactionReport;

    /** Nothing to transmit. */
    case None;
}
