<?php

declare(strict_types=1);

// Recipes for fr-facturx-extended; the format is documented on NegativeCorpusTest.
//
// Every rule in scope is an assert the FNFE fork rewrote; each recipe makes the fork and the
// rules-facturx EXTENDED rule set disagree. All twelve go the 'silent' way: the fork accepts a
// document that rules-facturx EXTENDED rejects.
//
// - BR-FXEXT-CO-10, -CO-12, -CO-13: the fork sums the amounts as xs:decimal (".../xs:decimal(.)"),
//   rules-facturx sums the untyped nodes, which XPath 2.0 turns into xs:double. 200.045 + 300.00 is
//   500.04499999999996 as a double, so round(x * 100) div 100 gives 500.04 upstream and 500.05 in
//   the fork (1.005 likewise gives 1.00 against 1.01). The stated total is then set exactly at the
//   fork's tolerance and one cent beyond the rules-facturx one.
// - BR-FXEXT-{S,Z,E,AE,IC,G,O,AF,AG}-01: the fork filters invoice lines by
//   "../ram:AssociatedDocumentLineDocument/ram:LineStatusReasonCode" (the line's subtype, BT-X-8),
//   rules-facturx by "ram:AssociatedDocumentLineDocument/..." below SpecifiedLineTradeSettlement,
//   which never exists, so it counts GROUP and INFORMATION lines as well. A VAT category that only
//   a GROUP line carries, with no VAT breakdown for it, fails rules-facturx and passes the fork.

$base = 'writer:facturXExtended:facturx-extended';

$transaction = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction';
// Lines of the base: 1 GROUP, 2 and 3 DETAIL (net 200.00 and 300.00), 4 INFORMATION.
$groupSettlement = "{$transaction}/ram:IncludedSupplyChainTradeLineItem[1]/ram:SpecifiedLineTradeSettlement";
$detail2Amount = "{$transaction}/ram:IncludedSupplyChainTradeLineItem[2]/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount";
$settlement = "{$transaction}/ram:ApplicableHeaderTradeSettlement";
$totals = "{$settlement}/ram:SpecifiedTradeSettlementHeaderMonetarySummation";

$lineTaxCategory = "{$transaction}/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode";

// Each recipe also carries a 'control': edits to the base alone that make the fork's own assert fire
// on a schema-valid document, so a silent recipe cannot hide a fork assert that never fires.
$recipe = static fn (string $rule, array $edits, array $control): array => [
    'set' => 'facturx-extended',
    'rule' => $rule,
    'base' => $base,
    'edits' => $edits,
    'fork' => 'silent',
    'control' => $control,
];
$groupTax = static fn (string $category, string $rate = '0'): array => [
    "{$groupSettlement}/ram:SpecifiedTradeSettlementLineMonetarySummation",
    'before',
    "<ram:ApplicableTradeTax><ram:TypeCode>VAT</ram:TypeCode><ram:CategoryCode>{$category}</ram:CategoryCode><ram:RateApplicablePercent>{$rate}</ram:RateApplicablePercent></ram:ApplicableTradeTax>",
];

$recipes = [
    // Σ BT-131 of DETAIL lines: 500.05 in the fork, 500.04 upstream; tolerance 4 lines × 0.01.
    $recipe('BR-FXEXT-CO-10', [
        [$detail2Amount, 'set', '200.045'],
        ["{$totals}/ram:LineTotalAmount", 'set', '500.09'],
    ], [
        // BT-106 one euro above Σ BT-131.
        ["{$totals}/ram:LineTotalAmount", 'set', '501.00'],
    ]),
    // Σ BT-99: 1.01 in the fork, 1.00 upstream; tolerance 1 charge × 0.01.
    $recipe('BR-FXEXT-CO-12', [
        ["{$settlement}/ram:SpecifiedTradePaymentTerms", 'before', '<ram:SpecifiedTradeAllowanceCharge><ram:ChargeIndicator><udt:Indicator>true</udt:Indicator></ram:ChargeIndicator><ram:ActualAmount>1.005</ram:ActualAmount><ram:Reason>FRAIS</ram:Reason><ram:CategoryTradeTax><ram:TypeCode>VAT</ram:TypeCode><ram:CategoryCode>S</ram:CategoryCode><ram:RateApplicablePercent>20</ram:RateApplicablePercent></ram:CategoryTradeTax></ram:SpecifiedTradeAllowanceCharge>'],
        ["{$totals}/ram:TaxBasisTotalAmount", 'before', '<ram:ChargeTotalAmount>1.02</ram:ChargeTotalAmount>'],
    ], [
        // BT-108 one euro above Σ BT-99.
        ["{$settlement}/ram:SpecifiedTradePaymentTerms", 'before', '<ram:SpecifiedTradeAllowanceCharge><ram:ChargeIndicator><udt:Indicator>true</udt:Indicator></ram:ChargeIndicator><ram:ActualAmount>1.00</ram:ActualAmount><ram:Reason>FRAIS</ram:Reason><ram:CategoryTradeTax><ram:TypeCode>VAT</ram:TypeCode><ram:CategoryCode>S</ram:CategoryCode><ram:RateApplicablePercent>20</ram:RateApplicablePercent></ram:CategoryTradeTax></ram:SpecifiedTradeAllowanceCharge>'],
        ["{$totals}/ram:TaxBasisTotalAmount", 'before', '<ram:ChargeTotalAmount>2.00</ram:ChargeTotalAmount>'],
    ]),
    // BT-109 against Σ BT-131: 500.05 in the fork, 500.04 upstream; tolerance 2 DETAIL lines × 0.01.
    $recipe('BR-FXEXT-CO-13', [
        [$detail2Amount, 'set', '200.045'],
        ["{$totals}/ram:TaxBasisTotalAmount", 'set', '500.07'],
    ], [
        // BT-109 one euro above Σ BT-131.
        ["{$totals}/ram:TaxBasisTotalAmount", 'set', '501.00'],
    ]),
    // The base is all "S": move every S to Z, then give S to the GROUP line only.
    $recipe('BR-FXEXT-S-01', [
        ["{$transaction}//ram:CategoryCode[. = 'S']", 'set', 'Z'],
        $groupTax('S', '20'),
    ], [
        // The S breakdown stays, but no line uses S any more.
        [$lineTaxCategory, 'set', 'Z'],
    ]),
];
foreach (['Z' => 'Z', 'E' => 'E', 'AE' => 'AE', 'IC' => 'K', 'G' => 'G', 'O' => 'O', 'AF' => 'L', 'AG' => 'M'] as $id => $category) {
    // The control gives the DETAIL lines a category the VAT breakdown does not list.
    $recipes[] = $recipe("BR-FXEXT-{$id}-01", [$groupTax($category)], [[$lineTaxCategory, 'set', $category]]);
}

return $recipes;
