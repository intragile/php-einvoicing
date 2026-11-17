<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

use DateTimeImmutable;
use IntrAgile\EInvoicing\France\CollectedAmount;
use InvalidArgumentException;

/**
 * A payment collected for one invoice (TG-34), split by VAT rate.
 */
final readonly class InvoicePayment
{
    /**
     * @param  string  $invoiceNumber  invoice number (TT-91)
     * @param  DateTimeImmutable  $invoiceDate  invoice issue date (TT-102)
     * @param  DateTimeImmutable  $paidOn  payment date (TT-92)
     * @param  list<CollectedAmount>  $amounts  amounts collected per VAT rate (TG-36), in euros, at
     *                                          least one
     *
     * @throws InvalidArgumentException when there is no amount
     */
    public function __construct(
        public string $invoiceNumber,
        public DateTimeImmutable $invoiceDate,
        public DateTimeImmutable $paidOn,
        public array $amounts,
    ) {
        if ($amounts === []) {
            throw new InvalidArgumentException(sprintf('The payment of invoice %s needs at least one collected amount (TG-36).', $invoiceNumber));
        }
    }
}
