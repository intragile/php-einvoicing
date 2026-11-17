<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

/**
 * Payments report of a Flux 10 transmission (TB-3, flows 10.2 and 10.4): payments collected
 * per invoice and days of aggregated payments.
 */
final readonly class PaymentsReport
{
    /**
     * @param  list<InvoicePayment>  $invoices  payments per invoice (TG-34)
     * @param  list<DailyPayments>  $days  aggregated payments (TG-37)
     */
    public function __construct(
        public ReportPeriod $period,
        public array $invoices = [],
        public array $days = [],
    ) {}
}
