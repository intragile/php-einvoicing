<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

use DateTimeImmutable;
use IntrAgile\EInvoicing\France\CollectedAmount;
use InvalidArgumentException;

/**
 * Payments of one day reported as totals per VAT rate (TG-37), such as B2C collections.
 */
final readonly class DailyPayments
{
    /**
     * @param  DateTimeImmutable  $date  payment date (TT-96)
     * @param  list<CollectedAmount>  $amounts  amounts collected per VAT rate (TG-39), in euros, at
     *                                          least one
     *
     * @throws InvalidArgumentException when there is no amount
     */
    public function __construct(
        public DateTimeImmutable $date,
        public array $amounts,
    ) {
        if ($amounts === []) {
            throw new InvalidArgumentException(sprintf('The payments of %s need at least one collected amount (TG-39).', $date->format('Y-m-d')));
        }
    }
}
