<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

use IntrAgile\EInvoicing\Model\Invoice;

/**
 * Transactions report of a Flux 10 transmission (TB-2, flows 10.1 and 10.3): invoices reported
 * one by one, such as international B2B sales, and days of aggregated transactions, such as
 * B2C sales.
 */
final readonly class TransactionsReport
{
    /**
     * @param  list<Invoice>  $invoices  invoices reported one by one (TG-8)
     * @param  list<DailyTransactions>  $days  aggregated transactions (TG-31)
     */
    public function __construct(
        public ReportPeriod $period,
        public array $invoices = [],
        public array $days = [],
    ) {}
}
