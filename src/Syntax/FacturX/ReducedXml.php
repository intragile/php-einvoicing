<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

/**
 * A CII document reduced to what a profile schema allows, with every node the reduction removed.
 */
final readonly class ReducedXml
{
    public function __construct(
        public string $xml,
        /**
         * Paths of the removed elements and attributes, without sibling positions, de-duplicated
         * and sorted, e.g. "/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/
         * ram:IncludedSupplyChainTradeLineItem". A removed element's descendants are not listed.
         *
         * @var list<string>
         */
        public array $removed,
    ) {}
}
