<?php

declare(strict_types=1);

// Recipes for fr-extended-ctc-fr-ubl; the format is documented on NegativeCorpusTest.

// Lines of the base: 1 GROUP, 2 and 3 DETAIL (3 with a preceding invoice and a delivery), 4 INFORMATION.
$base = 'writer:extendedCtcFrUbl:ubl';
$invoice = '/ubl:Invoice';
$totals = "{$invoice}/cac:LegalMonetaryTotal";
$headerTax = "{$invoice}/cac:TaxTotal[1]";
$subtotal1 = "{$headerTax}/cac:TaxSubtotal[1]";
$line1 = "{$invoice}/cac:InvoiceLine[1]";
$line2 = "{$invoice}/cac:InvoiceLine[2]";
$line3 = "{$invoice}/cac:InvoiceLine[3]";
$line4 = "{$invoice}/cac:InvoiceLine[4]";

$recipe = static fn (string $rule, array $edits): array => [
    'set' => 'extended-ctc-fr-ubl',
    'rule' => $rule,
    'base' => 'writer:extendedCtcFrUbl:ubl',
    'edits' => $edits,
];

// A VAT breakdown line of a category that no invoice line or allowance uses.
$extraSubtotal = static fn (string $category, string $taxable = '100.00', string $tax = '0.00'): string => "<cac:TaxSubtotal><cbc:TaxableAmount currencyID=\"EUR\">{$taxable}</cbc:TaxableAmount><cbc:TaxAmount currencyID=\"EUR\">{$tax}</cbc:TaxAmount>"
    ."<cac:TaxCategory><cbc:ID>{$category}</cbc:ID><cbc:Percent>0</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:TaxCategory></cac:TaxSubtotal>";
$charge = static fn (string $attributes, string $code): string => "<cac:AllowanceCharge><cbc:ChargeIndicator>true</cbc:ChargeIndicator><cbc:AllowanceChargeReasonCode{$attributes}>{$code}</cbc:AllowanceChargeReasonCode><cbc:Amount currencyID=\"EUR\">1.00</cbc:Amount></cac:AllowanceCharge>";
$itemProperty = static fn (string $content): string => "<cac:AdditionalItemProperty>{$content}</cac:AdditionalItemProperty>";

$recipes = [
    $recipe('BR-CO-25', [["{$invoice}/cbc:DueDate", 'remove'], ["{$invoice}/cac:PaymentTerms", 'remove']]),

    // Totals.
    $recipe('BR-FREXT-CO-10', [["{$totals}/cbc:LineExtensionAmount", 'set', '600.00']]),
    $recipe('BR-FREXT-CO-11', [["{$totals}/cbc:PayableAmount", 'before', '<cbc:AllowanceTotalAmount currencyID="EUR">5.00</cbc:AllowanceTotalAmount>']]),
    $recipe('BR-FREXT-CO-12', [["{$totals}/cbc:PayableAmount", 'before', '<cbc:ChargeTotalAmount currencyID="EUR">5.00</cbc:ChargeTotalAmount>']]),
    $recipe('BR-FREXT-CO-13', [["{$totals}/cbc:TaxExclusiveAmount", 'set', '510.00']]),
    $recipe('BR-FREXT-CO-15', [["{$totals}/cbc:TaxInclusiveAmount", 'set', '590.00']]),

    // A category in the VAT breakdown that no line or allowance uses.
    $recipe('BR-FREXT-S-01', [["{$line2}/cac:Item/cac:ClassifiedTaxCategory/cbc:ID", 'set', 'Z'], ["{$line3}/cac:Item/cac:ClassifiedTaxCategory/cbc:ID", 'set', 'Z']]),
    $recipe('BR-FREXT-Z-01', [[$headerTax, 'append', $extraSubtotal('Z')]]),
    $recipe('BR-FREXT-E-01', [[$headerTax, 'append', $extraSubtotal('E')]]),
    $recipe('BR-FREXT-AE-01', [[$headerTax, 'append', $extraSubtotal('AE')]]),
    $recipe('BR-FREXT-IC-01', [[$headerTax, 'append', $extraSubtotal('K')]]),
    $recipe('BR-FREXT-G-01', [[$headerTax, 'append', $extraSubtotal('G')]]),
    $recipe('BR-FREXT-O-01', [[$headerTax, 'append', $extraSubtotal('O')]]),
    $recipe('BR-FREXT-AF-01', [[$headerTax, 'append', $extraSubtotal('L')]]),
    $recipe('BR-FREXT-AG-01', [[$headerTax, 'append', $extraSubtotal('M')]]),

    // Line content.
    $recipe('BR-FREXT-22', [["{$line2}/cbc:InvoicedQuantity", 'remove']]),
    $recipe('BR-FREXT-23', [["{$line2}/cbc:InvoicedQuantity/@unitCode", 'remove']]),
    $recipe('BR-FREXT-26', [["{$line2}/cac:Price", 'remove']]),
    $recipe('BR-FREXT-CO-04', [["{$line2}/cac:Item/cac:ClassifiedTaxCategory", 'remove']]),
    $recipe('UBL-FREXT-SR-48', [["{$line2}/cac:Item/cac:ClassifiedTaxCategory", 'remove']]),
    $recipe('BR-FREXT-54-2', [["{$line4}/cac:Item", 'append', $itemProperty('<cbc:Name>COULEUR</cbc:Name>')]]),
    $recipe('BR-FREXT-04', [["{$line4}/cac:Item", 'append', $itemProperty('<cbc:Name>COULEUR</cbc:Name><cbc:NameCode>@@</cbc:NameCode><cbc:Value>ROUGE</cbc:Value>')]]),

    // Sub-lines.
    $recipe('BR-FREXT-05', [["{$line2}/cac:BillingReference[1]/cac:InvoiceDocumentReference/cbc:DocumentStatusCode", 'set', 'SUB']]),
    $recipe('BR-FREXT-06', [["{$line2}/cac:BillingReference[1]/cac:InvoiceDocumentReference/cbc:DocumentStatusCode", 'remove']]),
    $recipe('BR-FREXT-08', [["{$line1}/cbc:LineExtensionAmount", 'set', '499.00']]),
    $recipe('BR-FREXT-11', [["{$line2}/cac:BillingReference[1]/cac:BillingReferenceLine/cbc:ID", 'set', '99']]),

    // Code lists.
    $recipe('BR-FREXT-CL-10', [["{$invoice}/cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID/@schemeID", 'set', 'ZZZZ']]),
    $recipe('BR-FREXT-CL-20', [["{$headerTax}", 'before', $charge('', 'ZZ9')]]),
    $recipe('BR-FREXT-CL-20-TAX', [["{$headerTax}", 'before', $charge(' listID="5153"', 'ZZ9')]]),
    $recipe('BR-FREXT-CL-20-LISTID', [["{$headerTax}", 'before', $charge(' listID="9999"', 'AAA')]]),
    $recipe('BR-FREXT-CL-24', [["{$invoice}/cac:AccountingSupplierParty", 'before', '<cac:AdditionalDocumentReference><cbc:ID>PJ1</cbc:ID><cac:Attachment><cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain" filename="a.txt">QUJD</cbc:EmbeddedDocumentBinaryObject></cac:Attachment></cac:AdditionalDocumentReference>']]),
    $recipe('BR-FREXT-CL-27', [["{$invoice}/cac:PaymentTerms", 'before', '<cac:DeliveryTerms><cbc:ID>ZZZ</cbc:ID></cac:DeliveryTerms>']]),

    // Restrictions of the EXTENDED-CTC-FR carrier elements.
    $recipe('UBL-FREXT-CR-410', [["{$invoice}/cac:PaymentTerms", 'before', '<cac:DeliveryTerms><cbc:ID>CIF</cbc:ID><cbc:SpecialTerms>FRANCO</cbc:SpecialTerms></cac:DeliveryTerms>']]),
    $recipe('CII-FREXT-CR-490', [[$headerTax, 'before', '<cac:TaxExchangeRate><cbc:SourceCurrencyCode>EUR</cbc:SourceCurrencyCode><cbc:TargetCurrencyCode>USD</cbc:TargetCurrencyCode><cbc:CalculationRate>1.1</cbc:CalculationRate><cbc:MathematicOperatorCode>Multiply</cbc:MathematicOperatorCode></cac:TaxExchangeRate>']]),
    $recipe('UBL-FREXT-CR-645', [[$line4, 'append', '<cac:DeliveryTerms><cbc:ID>CIF</cbc:ID><cbc:SpecialTerms>FRANCO</cbc:SpecialTerms></cac:DeliveryTerms>']]),
];

// VAT breakdown amounts: a wrong taxable amount trips the three reconciliation asserts of a category.
$taxable = static fn (string $id, string $category, string $value): array => $recipe($id, [[$headerTax, 'append', $extraSubtotal($category, $value)]]);
foreach (['Z' => 'Z', 'E' => 'E', 'AE' => 'AE', 'IC' => 'K', 'G' => 'G', 'O' => 'O', 'AF' => 'L', 'AG' => 'M'] as $rule => $category) {
    foreach (['08', '08ini', '08rev'] as $suffix) {
        $recipes[] = $taxable("BR-FREXT-{$rule}-{$suffix}", $category, '100.00');
    }
}
foreach (['08', '08ini', '08rev'] as $suffix) {
    $recipes[] = $recipe("BR-FREXT-S-{$suffix}", [["{$subtotal1}/cbc:TaxableAmount", 'set', '250.00']]);
}
$recipes[] = $recipe('BR-FREXT-S-09', [["{$subtotal1}/cbc:TaxAmount", 'set', '25.00']]);

return $recipes;
