<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

/**
 * FA(3) seller status information (StatusInfoPodatnika, XSD type TStatusInfoPodatnika).
 */
enum TaxpayerStatus: int
{
    case Liquidation = 1;
    case Restructuring = 2;
    case Bankruptcy = 3;
    /** Enterprise in an estate (inheritance administration). */
    case Inheritance = 4;
}
