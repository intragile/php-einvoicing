<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

/**
 * Category of aggregated transactions (TT-81).
 */
enum TransactionCategory: string
{
    /** Supplies of goods subject to French VAT. */
    case Goods = 'TLB1';

    /** Supplies of services subject to French VAT. */
    case Services = 'TPS1';

    /** Supplies not taxed in France. */
    case NotTaxedInFrance = 'TNT1';

    /** Supplies under the margin scheme. */
    case MarginScheme = 'TMA1';
}
