<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

use IntrAgile\EInvoicing\Transport\DeliveryStatus;

/**
 * A Spanish invoice issued through B2Brouter, as B2Brouter reports it.
 *
 * The invoice state is B2Brouter's delivery of the invoice to the customer, not its VERI*FACTU
 * registration: the status is Failed when B2Brouter put the invoice in error, and Pending
 * otherwise, because the registration outcome is read from the invoice's tax report.
 */
final readonly class IssuedInvoice
{
    /**
     * @param  string  $invoiceId  the B2Brouter invoice id (digits)
     * @param  string|null  $number  the invoice number, when B2Brouter reports one
     * @param  string  $state  B2Brouter's invoice state as received, for example "sending"
     * @param  list<string>  $taxReportIds  the ids of the invoice's tax reports (digits), as listed
     * @param  DeliveryStatus  $status  the delivery status, keyed by the invoice id
     */
    public function __construct(
        public string $invoiceId,
        public ?string $number,
        public string $state,
        public array $taxReportIds,
        public DeliveryStatus $status,
    ) {}

    /**
     * The id of the invoice's current tax report, the highest numeric id listed, or null while
     * B2Brouter lists none.
     */
    public function latestTaxReportId(): ?string
    {
        $latest = null;
        foreach ($this->taxReportIds as $id) {
            if ($latest === null || self::compare($id, $latest) > 0) {
                $latest = $id;
            }
        }

        return $latest;
    }

    /**
     * Compares two digit strings by numeric value, without integer overflow.
     */
    private static function compare(string $a, string $b): int
    {
        $a = ltrim($a, '0');
        $b = ltrim($b, '0');

        return strlen($a) <=> strlen($b) ?: strcmp($a, $b) <=> 0;
    }
}
