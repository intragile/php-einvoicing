<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

/**
 * Values the UBL writer fills into elements that UBL 2.1 requires but EN 16931 does not model.
 * The reader recognises them, so they never come back as business data.
 *
 * @internal
 */
final class Placeholder
{
    /**
     * cac:OrderReference/cbc:ID when only a sales order reference (BT-14) exists: UBL makes the
     * order identifier mandatory. Read as "no BT-13" only next to a cbc:SalesOrderID, as Peppol
     * BIS does.
     */
    public const ORDER_ID = 'NA';

    /**
     * cac:CardAccount/cbc:NetworkID, which UBL makes mandatory but EN 16931 does not model.
     */
    public const CARD_NETWORK = 'NA';

    /**
     * cac:TaxScheme/cbc:ID of a seller tax registration (BT-32) without a scheme: UBL makes
     * cac:TaxScheme mandatory in cac:PartyTaxScheme. "TAX" is the Peppol BIS convention.
     */
    public const TAX_SCHEME = 'TAX';
}
