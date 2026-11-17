<?php

declare(strict_types=1);

// Recipes for fr-extended-ctc-fr-cii; the format is documented on NegativeCorpusTest.

$transaction = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction';
$agreement = "{$transaction}/ram:ApplicableHeaderTradeAgreement";
$settlement = "{$transaction}/ram:ApplicableHeaderTradeSettlement";
$totals = "{$settlement}/ram:SpecifiedTradeSettlementHeaderMonetarySummation";
// Lines of the base: 1 GROUP, 2 and 3 DETAIL, 4 INFORMATION.
$line1 = "{$transaction}/ram:IncludedSupplyChainTradeLineItem[1]";
$line2 = "{$transaction}/ram:IncludedSupplyChainTradeLineItem[2]";
$line3 = "{$transaction}/ram:IncludedSupplyChainTradeLineItem[3]";
$product2 = "{$line2}/ram:SpecifiedTradeProduct";
$reference2 = "{$line2}/ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument[1]";

$recipe = static fn (string $rule, array $edits): array => [
    'set' => 'extended-ctc-fr-cii',
    'rule' => $rule,
    'base' => 'writer:extendedCtcFr:cii',
    'edits' => $edits,
];

$flag = static fn (string $value): string => "<udt:Indicator>{$value}</udt:Indicator>";
$charge = static fn (string $indicator, string $actual, string $reason, string $category = 'S', string $rate = '20'): string => '<ram:SpecifiedTradeAllowanceCharge>'
    ."<ram:ChargeIndicator>{$flag($indicator)}</ram:ChargeIndicator><ram:ActualAmount>{$actual}</ram:ActualAmount>{$reason}"
    ."<ram:CategoryTradeTax><ram:TypeCode>VAT</ram:TypeCode><ram:CategoryCode>{$category}</ram:CategoryCode><ram:RateApplicablePercent>{$rate}</ram:RateApplicablePercent></ram:CategoryTradeTax>"
    .'</ram:SpecifiedTradeAllowanceCharge>';
$beforeTerms = static fn (string $fragment): array => ["{$settlement}/ram:SpecifiedTradePaymentTerms", 'before', $fragment];
$beforeBasis = static fn (string $fragment): array => ["{$totals}/ram:TaxBasisTotalAmount", 'before', $fragment];
// A VAT breakdown line of one category that no invoice line uses, with a taxable amount no line adds up to.
$orphanBreakdown = static fn (string $category, string $rate, string $basis): string => '<ram:ApplicableTradeTax><ram:CalculatedAmount>0.00</ram:CalculatedAmount><ram:TypeCode>VAT</ram:TypeCode>'
    ."<ram:BasisAmount>{$basis}</ram:BasisAmount><ram:CategoryCode>{$category}</ram:CategoryCode>"
    .($rate === '' ? '' : "<ram:RateApplicablePercent>{$rate}</ram:RateApplicablePercent>").'</ram:ApplicableTradeTax>';
$characteristic = static fn (string $content): string => "<ram:ApplicableProductCharacteristic>{$content}</ram:ApplicableProductCharacteristic>";

$recipes = [
    // Header charges, allowances and totals.
    $recipe('BR-FREXT-BR-38', [$beforeTerms($charge('true', '10.00', ''))]),
    $recipe('BR-FREXT-CO-10', [["{$totals}/ram:LineTotalAmount", 'set', '501.00']]),
    $recipe('BR-FREXT-CO-11', [$beforeBasis('<ram:AllowanceTotalAmount>5.00</ram:AllowanceTotalAmount>')]),
    $recipe('BR-FREXT-CO-12', [$beforeBasis('<ram:ChargeTotalAmount>5.00</ram:ChargeTotalAmount>')]),
    $recipe('BR-FREXT-CO-13', [["{$totals}/ram:TaxBasisTotalAmount", 'set', '600.00']]),
    $recipe('BR-FREXT-CO-15', [["{$totals}/ram:GrandTotalAmount", 'set', '999.00']]),
    $recipe('BR-CO-25', [["{$settlement}/ram:SpecifiedTradePaymentTerms", 'remove']]),

    // Line checks.
    $recipe('BR-FREXT-BR-22', [["{$line2}/ram:SpecifiedLineTradeDelivery/ram:BilledQuantity", 'remove']]),
    $recipe('BR-FREXT-BR-23', [["{$line2}/ram:SpecifiedLineTradeDelivery/ram:BilledQuantity/@unitCode", 'remove']]),
    $recipe('BR-FREXT-BR-24', [["{$line2}/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount", 'remove']]),
    $recipe('BR-FREXT-BR-26', [["{$line2}/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice", 'remove']]),
    $recipe('BR-FREXT-CO-04', [["{$line3}/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax", 'remove']]),
    $recipe('BR-FREXT-BR-44', [["{$line2}/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation", 'before', '<ram:SpecifiedTradeAllowanceCharge><ram:ChargeIndicator><udt:Indicator>true</udt:Indicator></ram:ChargeIndicator><ram:ActualAmount>1.00</ram:ActualAmount></ram:SpecifiedTradeAllowanceCharge>']]),

    // Product characteristics.
    $recipe('BR-FREXT-54-1', [[$product2, 'append', $characteristic('<ram:Value>ROUGE</ram:Value>')]]),
    $recipe('BR-FREXT-54-2', [[$product2, 'append', $characteristic('<ram:Description>COULEUR</ram:Description>')]]),
    $recipe('BR-FXEXT-04', [[$product2, 'append', $characteristic('<ram:TypeCode>QQQQ</ram:TypeCode><ram:Description>COULEUR</ram:Description><ram:Value>ROUGE</ram:Value>')]]),

    // Sub-lines.
    $recipe('BR-FREXT-05', [["{$line2}/ram:AssociatedDocumentLineDocument/ram:LineStatusReasonCode", 'set', 'SUBTOTAL']]),
    $recipe('BR-FREXT-06', [["{$line2}/ram:AssociatedDocumentLineDocument/ram:LineStatusReasonCode", 'remove']]),
    $recipe('BR-FREXT-08', [["{$line1}/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount", 'set', '501.00']]),
    $recipe('BR-FREXT-11', [["{$line2}/ram:AssociatedDocumentLineDocument/ram:ParentLineID", 'set', '99']]),
    $recipe('BR-FREXT-12', [
        ["{$line2}/ram:AssociatedDocumentLineDocument/ram:LineStatusReasonCode", 'set', 'GROUP'],
        ["{$line2}/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount", 'remove'],
    ]),
];

// VAT categories: -01 (the breakdown names a category no line uses) and -08 / -08ini / -08rev (the
// taxable amount of that breakdown is not the sum of the lines). Category letters as the rule set
// writes them: IC is K, AF is L, AG is M.
$categories = ['Z' => ['Z', '0'], 'E' => ['E', '0'], 'AE' => ['AE', '0'], 'IC' => ['K', '0'], 'G' => ['G', '0'], 'O' => ['O', ''], 'AF' => ['L', '7'], 'AG' => ['M', '7']];
foreach ($categories as $name => [$code, $rate]) {
    $edit = [["{$settlement}/ram:SpecifiedTradePaymentTerms", 'before', $orphanBreakdown($code, $rate, '50.00')]];
    $recipes[] = $recipe("BR-FREXT-{$name}-01", $edit);
    foreach (['', 'ini', 'rev'] as $suffix) {
        $recipes[] = $recipe("BR-FXEXT-{$name}-08{$suffix}", $edit);
    }
}
// Standard rated: the lines are S, so make the breakdown disagree with them, and let the lines stop being S for -01.
$wrongBasis = [["{$settlement}/ram:ApplicableTradeTax[1]/ram:BasisAmount", 'set', '250.00']];
$recipes[] = $recipe('BR-FREXT-S-01', [
    ["{$line2}/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode", 'set', 'Z'],
    ["{$line3}/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode", 'set', 'Z'],
]);
foreach (['', 'ini', 'rev'] as $suffix) {
    $recipes[] = $recipe("BR-FXEXT-S-08{$suffix}", $wrongBasis);
}
$recipes[] = $recipe('BR-FXEXT-S-09', $wrongBasis);

// CII syntax and data type rules.
array_push(
    $recipes,
    $recipe('CII-FREXT-SR-069', [[$product2, 'append', $characteristic('<ram:Description>A</ram:Description><ram:Description>B</ram:Description>')]]),
    $recipe('CII-FREXT-SR-072', [[$product2, 'append', $characteristic('<ram:Description>A</ram:Description><ram:Value>1</ram:Value><ram:Value>2</ram:Value>')]]),
    $recipe('CII-FREXT-SR-106', [["{$line2}/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice", 'before', '<ram:ApplicableTradeDeliveryTerms><ram:DeliveryTypeCode>FOB</ram:DeliveryTypeCode><ram:Description>FRANCO</ram:Description></ram:ApplicableTradeDeliveryTerms>']]),
    $recipe('CII-FREXT-SR-293', [[$agreement, 'append', '<ram:ApplicableTradeDeliveryTerms><ram:DeliveryTypeCode>FOB</ram:DeliveryTypeCode><ram:Description>FRANCO</ram:Description></ram:ApplicableTradeDeliveryTerms>']]),
    $recipe('CII-FREXT-SR-439', [["{$line2}/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice", 'append', '<ram:ChargeAmount>10.00</ram:ChargeAmount>']]),
    $recipe('CII-FREXT-SR-366', [["{$settlement}/ram:ApplicableTradeTax[1]", 'before', '<ram:TaxApplicableTradeCurrencyExchange><ram:SourceCurrencyCode>EUR</ram:SourceCurrencyCode><ram:SourceUnitBasisNumeric>1</ram:SourceUnitBasisNumeric><ram:TargetCurrencyCode>USD</ram:TargetCurrencyCode><ram:ConversionRate>1.1</ram:ConversionRate><ram:ConversionRateDateTime><udt:DateTimeString format="102">20261102</udt:DateTimeString></ram:ConversionRateDateTime></ram:TaxApplicableTradeCurrencyExchange>']]),
    $recipe('CII-EXT-DT-018', [["{$reference2}/ram:TypeCode", 'set', '380']]),
    $recipe('CII-FREXT-DT-024', [["{$reference2}/ram:TypeCode", 'set', '916']]),
    $recipe('CII-FREXTDT-027', [[$reference2, 'append', '<ram:FormattedIssueDateTime><qdt:DateTimeString format="102">20261101</qdt:DateTimeString></ram:FormattedIssueDateTime>']]),
);

// Code lists.
array_push(
    $recipes,
    $recipe('BR-FREXT-CL-10', [["{$agreement}/ram:SellerTradeParty/ram:GlobalID/@schemeID", 'set', '9999']]),
    $recipe('BR-FREXT-CL-19-GP', [["{$line2}/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice", 'before', '<ram:GrossPriceProductTradePrice><ram:ChargeAmount>10.00</ram:ChargeAmount><ram:AppliedTradeAllowanceCharge><ram:ChargeIndicator><udt:Indicator>false</udt:Indicator></ram:ChargeIndicator><ram:ActualAmount>0.00</ram:ActualAmount><ram:ReasonCode>99</ram:ReasonCode></ram:AppliedTradeAllowanceCharge></ram:GrossPriceProductTradePrice>']]),
    $recipe('BR-FREXT-CL-20', [$beforeTerms($charge('true', '10.00', '<ram:ReasonCode>QQ</ram:ReasonCode><ram:Reason>FRAIS</ram:Reason>'))]),
    $recipe('BR-FREXT-CL-20-TAX', [$beforeTerms($charge('true', '10.00', '<ram:ReasonCode listID="5153">QQQ</ram:ReasonCode><ram:Reason>FRAIS</ram:Reason>'))]),
    $recipe('BR-FREXT-CL-20-LISTID', [$beforeTerms($charge('true', '10.00', '<ram:ReasonCode listID="9999">AA</ram:ReasonCode><ram:Reason>FRAIS</ram:Reason>'))]),
    $recipe('BR-FREXT-CL-24', [[$agreement, 'append', '<ram:AdditionalReferencedDocument><ram:IssuerAssignedID>PJ-1</ram:IssuerAssignedID><ram:TypeCode>916</ram:TypeCode><ram:Name>Pièce</ram:Name><ram:AttachmentBinaryObject mimeCode="text/plain" filename="a.txt">SGVsbG8=</ram:AttachmentBinaryObject></ram:AdditionalReferencedDocument>']]),
    $recipe('BR-FREXT-CL-27', [[$agreement, 'append', '<ram:ApplicableTradeDeliveryTerms><ram:DeliveryTypeCode>ZZZ</ram:DeliveryTypeCode></ram:ApplicableTradeDeliveryTerms>']]),
);

return $recipes;
