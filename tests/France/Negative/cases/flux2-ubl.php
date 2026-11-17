<?php

declare(strict_types=1);

// Recipes for fr-flux2-ubl; the format is documented on NegativeCorpusTest.

$domestic = 'writer:domesticB2b:ubl';
$extended = 'writer:extendedCtcFrUbl:ubl';
$creditNote = 'writer:creditNoteWithTwoPrecedingInvoices:ubl';

$invoice = '/ubl:Invoice';
$credit = '/cn:CreditNote';
$profile = "{$invoice}/cbc:ProfileID";
$typeCode = "{$invoice}/cbc:InvoiceTypeCode";
$currencyCode = "{$invoice}/cbc:DocumentCurrencyCode";
$supplier = "{$invoice}/cac:AccountingSupplierParty/cac:Party";
$customer = "{$invoice}/cac:AccountingCustomerParty/cac:Party";
$terms = "{$invoice}/cac:PaymentTerms";
$taxTotal = "{$invoice}/cac:TaxTotal";
$subtotal = "{$taxTotal}/cac:TaxSubtotal";
$totals = "{$invoice}/cac:LegalMonetaryTotal";
$line = "{$invoice}/cac:InvoiceLine[1]";
$headerReference = "{$credit}/cac:BillingReference[1]/cac:InvoiceDocumentReference";
// Lines of the EXTENDED-CTC-FR base: 1 GROUP, 2 and 3 DETAIL (3 with a preceding invoice and a
// delivery), 4 INFORMATION.
$group = "{$invoice}/cac:InvoiceLine[1]";
$detail2 = "{$invoice}/cac:InvoiceLine[2]";
$detail3 = "{$invoice}/cac:InvoiceLine[3]";
$information = "{$invoice}/cac:InvoiceLine[4]";
$lineReference = "{$detail3}/cac:BillingReference[2]/cac:InvoiceDocumentReference";

$recipe = static fn (string $rule, array $edits, string $base = 'writer:domesticB2b:ubl'): array => [
    'set' => 'flux2-ubl',
    'rule' => $rule,
    'base' => $base,
    'edits' => $edits,
];
$note = static fn (string $subject, string $content = 'MENTION'): string => "<cbc:Note>#{$subject}#{$content}</cbc:Note>";
$id = static fn (string $scheme, string $value): string => '<cac:PartyIdentification><cbc:ID'.($scheme === '' ? '' : " schemeID=\"{$scheme}\"").">{$value}</cbc:ID></cac:PartyIdentification>";
$endpoint = static fn (string $value, string $scheme = '0225'): string => "<cbc:EndpointID schemeID=\"{$scheme}\">{$value}</cbc:EndpointID>";
$name = '<cac:PartyName><cbc:Name>TIERS</cbc:Name></cac:PartyName>';
$legal = static fn (string $siren): string => "<cac:PartyLegalEntity><cbc:RegistrationName>TIERS</cbc:RegistrationName><cbc:CompanyID schemeID=\"0002\">{$siren}</cbc:CompanyID></cac:PartyLegalEntity>";
$long = str_repeat('A', 126);
$vat = static fn (string $element, string $category, string $rate): string => "<cac:{$element}><cbc:ID>{$category}</cbc:ID><cbc:Percent>{$rate}</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:{$element}>";
$allowance = static fn (string $category = 'S', string $rate = '20', string $amount = '10.00', string $basis = ''): string => '<cac:AllowanceCharge><cbc:ChargeIndicator>false</cbc:ChargeIndicator><cbc:AllowanceChargeReason>REMISE</cbc:AllowanceChargeReason>'
    ."<cbc:Amount currencyID=\"EUR\">{$amount}</cbc:Amount>"
    .($basis === '' ? '' : "<cbc:BaseAmount currencyID=\"EUR\">{$basis}</cbc:BaseAmount>")
    .$vat('TaxCategory', $category, $rate)
    .'</cac:AllowanceCharge>';
$lineAllowance = static fn (string $amount, string $basis = ''): string => '<cac:AllowanceCharge><cbc:ChargeIndicator>false</cbc:ChargeIndicator>'
    ."<cbc:Amount currencyID=\"EUR\">{$amount}</cbc:Amount>"
    .($basis === '' ? '' : "<cbc:BaseAmount currencyID=\"EUR\">{$basis}</cbc:BaseAmount>")
    .'</cac:AllowanceCharge>';
$document916 = static fn (string $description, string $id = 'PJ'): string => "<cac:AdditionalDocumentReference><cbc:ID>{$id}</cbc:ID><cbc:DocumentTypeCode>916</cbc:DocumentTypeCode><cbc:DocumentDescription>{$description}</cbc:DocumentDescription></cac:AdditionalDocumentReference>";
$reference130 = static fn (string $type, string $value, string $element = 'AdditionalDocumentReference'): string => "<cac:{$element}><cbc:ID schemeID=\"{$type}\">{$value}</cbc:ID><cbc:DocumentTypeCode>130</cbc:DocumentTypeCode></cac:{$element}>";

$b8 = [$profile, 'set', 'B8'];
$b9 = [$profile, 'set', 'B9'];

// Extra parties of each role the rules check: where the party goes in the schema order and how it
// is wrapped.
$parties = [
    'BT-60' => [[$terms, 'before'], 'PayeeParty', ''],
    'EXT-FR-FE-06' => [[$customer, 'append'], 'AgentParty', ''],
    'EXT-FR-FE-46' => [[$terms, 'before'], 'PayerParty', '<cac:PaymentMeans><cbc:PaymentMeansCode>1</cbc:PaymentMeansCode><cac:PaymentMandate>%s</cac:PaymentMandate></cac:PaymentMeans>'],
    'EXT-FR-FE-69' => [[$supplier, 'append'], 'AgentParty', ''],
    'EXT-FR-FE-92' => [[$customer, 'append'], 'Party', '<cac:ServiceProviderParty>%s</cac:ServiceProviderParty>'],
    'EXT-FR-FE-115' => [[$supplier, 'append'], 'Party', '<cac:ServiceProviderParty>%s</cac:ServiceProviderParty>'],
];
// The electronic address rules name these parties by their address id.
$addressParties = [
    'EXT-FR-FE-12' => 'EXT-FR-FE-06',
    'EXT-FR-FE-29' => 'BT-60',
    'EXT-FR-FE-52' => 'EXT-FR-FE-46',
    'EXT-FR-FE-75' => 'EXT-FR-FE-69',
    'EXT-FR-FE-98' => 'EXT-FR-FE-92',
    'EXT-FR-FE-121' => 'EXT-FR-FE-115',
];
$addParty = static function (string $partyId, string $content) use ($parties): array {
    [[$where, $operation], $element, $wrapper] = $parties[$partyId];
    $xml = "<cac:{$element}>{$content}</cac:{$element}>";
    $xml = $wrapper === '' ? $xml : sprintf($wrapper, $xml);

    return [$where, $operation, $xml];
};
$mismatch = $id('0009', '20000000800017').$name.$legal('100000009');

$recipes = [
    // BR-FR-01 / BR-FR-02: identifier length and characters.
    $recipe('BR-FR-01_BT-1-1', [["{$invoice}/cbc:ID", 'set', str_repeat('F', 36)]]),
    $recipe('BR-FR-01_BT-1-2', [["{$invoice}/cbc:ID", 'set', 'F2026#3']]),
    $recipe('BR-FR-02_BT-1', [["{$invoice}/cbc:ID", 'set', 'F2026#3']]),
    $recipe('BR-FR-01_BT-25-1', [["{$headerReference}/cbc:ID", 'set', str_repeat('F', 36)]], $creditNote),
    $recipe('BR-FR-01_BT-25-2', [["{$headerReference}/cbc:ID", 'set', 'F2026#1']], $creditNote),
    $recipe('BR-FR-02_BT-25', [["{$headerReference}/cbc:ID", 'set', 'F2026#1']], $creditNote),
    $recipe('BR-FR-01_EXT-FR-FE-136-1', [["{$lineReference}/cbc:ID", 'set', str_repeat('F', 36)]], $extended),
    $recipe('BR-FR-01_EXT-FR-FE-136-2', [["{$lineReference}/cbc:ID", 'set', 'F2025#99']], $extended),
    $recipe('BR-FR-02_EXT-FR-FE-136', [["{$lineReference}/cbc:ID", 'set', 'F2025#99']], $extended),

    // BR-FR-03: dates in 2000-2099.
    $recipe('BR-FR-03_BT-2', [["{$invoice}/cbc:IssueDate", 'set', '1999-11-02']]),
    $recipe('BR-FR-03_BT-7', [[$currencyCode, 'before', '<cbc:TaxPointDate>1999-12-31</cbc:TaxPointDate>']]),
    $recipe('BR-FR-03_BT-9', [["{$invoice}/cbc:DueDate", 'set', '2100-12-02']]),
    $recipe('BR-FR-03_BT-26', [["{$headerReference}/cbc:IssueDate", 'set', '1999-10-01']], $creditNote),
    $recipe('BR-FR-03_BT-72', [[$terms, 'before', '<cac:Delivery><cbc:ActualDeliveryDate>1999-10-31</cbc:ActualDeliveryDate></cac:Delivery>']]),
    $recipe('BR-FR-03_BT-73', [["{$invoice}/cac:AccountingSupplierParty", 'before', '<cac:InvoicePeriod><cbc:StartDate>1999-10-01</cbc:StartDate></cac:InvoicePeriod>']]),
    $recipe('BR-FR-03_BT-74', [["{$invoice}/cac:AccountingSupplierParty", 'before', '<cac:InvoicePeriod><cbc:EndDate>2100-10-31</cbc:EndDate></cac:InvoicePeriod>']]),
    $recipe('BR-FR-03_EXT-FR-FE-138', [["{$lineReference}/cbc:IssueDate", 'set', '1999-12-15']], $extended),
    $recipe('BR-FR-03_EXT-FR-FE-158', [["{$detail3}/cac:Delivery/cbc:ActualDeliveryDate", 'set', '1999-12-20']], $extended),
    $recipe('BR-FR-03_BT-134', [["{$detail2}/cac:InvoicePeriod/cbc:StartDate", 'set', '1999-10-01']], $extended),
    $recipe('BR-FR-03_BT-135', [["{$detail2}/cac:InvoicePeriod/cbc:EndDate", 'set', '2100-10-31']], $extended),

    // BR-FR-04: document type codes.
    $recipe('BR-FR-04_BT-3', [[$typeCode, 'set', '326']]),
    $recipe('BR-FR-04_EXT-FR-FE-02', [[$headerReference, 'append', '<cbc:DocumentTypeCode>326</cbc:DocumentTypeCode>']], $creditNote),
    $recipe('BR-FR-04_EXT-FR-FE-137', [[$lineReference, 'append', '<cbc:DocumentTypeCode>326</cbc:DocumentTypeCode>']], $extended),

    // BR-FR-05 / BR-FR-06: mandatory notes, each at most once.
    $recipe('BR-FR-05_BT-22-1', [["{$invoice}/cbc:Note[starts-with(., '#PMT#')]", 'remove']]),
    $recipe('BR-FR-05_BT-22-2', [["{$invoice}/cbc:Note[starts-with(., '#PMD#')]", 'remove']]),
    $recipe('BR-FR-05_BT-22-3', [["{$invoice}/cbc:Note[starts-with(., '#AAB#')]", 'remove']]),
    $recipe('BR-FR-06_BT-21-1', [[$currencyCode, 'before', $note('PMT')]]),
    $recipe('BR-FR-06_BT-21-2', [[$currencyCode, 'before', $note('PMD')]]),
    $recipe('BR-FR-06_BT-21-3', [[$currencyCode, 'before', $note('AAB')]]),
    $recipe('BR-FR-06_BT-21-4', [[$currencyCode, 'before', $note('TXD').$note('TXD')]]),

    // BR-FR-08: billing framework.
    $recipe('BR-FR-08_BT-23', [[$profile, 'set', 'B3']]),

    // BR-FR-09: a SIRET is 14 digits and starts with the SIREN.
    $recipe('BR-FR-09_BT-29', [["{$supplier}/cac:PartyIdentification/cbc:ID[@schemeID = '0009']", 'set', '20000000800017']]),
    $recipe('BR-FR-09_BT-46', [["{$customer}/cac:PostalAddress", 'before', $id('0009', '10000000900017')]]),
    $recipe('BR-FR-09_BT-60', [$addParty('BT-60', $mismatch)]),
    $recipe('BR-FR-09_EXT-FR-FE-06', [$addParty('EXT-FR-FE-06', $mismatch)]),
    $recipe('BR-FR-09_EXT-FR-FE-46', [$addParty('EXT-FR-FE-46', $mismatch)]),
    $recipe('BR-FR-09_EXT-FR-FE-69', [$addParty('EXT-FR-FE-69', $mismatch)]),
    $recipe('BR-FR-09_EXT-FR-FE-92', [$addParty('EXT-FR-FE-92', $mismatch)]),
    $recipe('BR-FR-09_EXT-FR-FE-115', [$addParty('EXT-FR-FE-115', $mismatch)]),
    $recipe('BR-FR-09_BT-71', [[$terms, 'before', '<cac:Delivery><cac:DeliveryLocation><cbc:ID schemeID="0009">20000000800017</cbc:ID></cac:DeliveryLocation><cac:DeliveryParty>'.$name.$legal('100000009').'</cac:DeliveryParty></cac:Delivery>']]),

    // BR-FR-10 to BR-FR-13: SIRENs and electronic addresses.
    $recipe('BR-FR-10_BT-30', [["{$supplier}/cac:PartyLegalEntity/cbc:CompanyID", 'set', '10000000']]),
    $recipe('BR-FR-11_BT-47', [["{$customer}/cac:PartyLegalEntity/cbc:CompanyID", 'set', '20000000A']]),
    $recipe('BR-FR-12_BT-49', [["{$customer}/cbc:EndpointID", 'remove']]),
    $recipe('BR-FR-13_BT-34', [["{$supplier}/cbc:EndpointID", 'remove']]),

    // BR-FR-15 / BR-FR-16: VAT categories and rates.
    $recipe('BR-FR-15_BT-95_BT-102-1', [[$taxTotal, 'before', $allowance('B')]]),
    $recipe('BR-FR-15_BT-95_BT-102-2', [[$taxTotal, 'before', $allowance('L')]]),
    $recipe('BR-FR-15_BT-118-1', [["{$subtotal}/cac:TaxCategory/cbc:ID", 'set', 'B']]),
    $recipe('BR-FR-15_BT-118-2', [["{$subtotal}/cac:TaxCategory/cbc:ID", 'set', 'M']]),
    $recipe('BR-FR-15_BT-151-1', [["{$line}/cac:Item/cac:ClassifiedTaxCategory/cbc:ID", 'set', 'B']]),
    $recipe('BR-FR-08_BT-151-2', [["{$line}/cac:Item/cac:ClassifiedTaxCategory/cbc:ID", 'set', 'L']]),
    $recipe('BR-FR-16_BT-96', [[$taxTotal, 'before', $allowance('S', '21')]]),
    $recipe('BR-FR-16_BT-119', [["{$subtotal}/cac:TaxCategory/cbc:Percent", 'set', '21']]),
    $recipe('BR-FR-16_BT-152', [["{$line}/cac:Item/cac:ClassifiedTaxCategory/cbc:Percent", 'set', '21']]),

    // BR-FR-17 / BR-FR-18: attachment descriptions.
    $recipe('BR-FR-17_BT-123', [["{$invoice}/cac:AccountingSupplierParty", 'before', $document916('INCONNU')]]),
    $recipe('BR-FR-18_BT-123', [["{$invoice}/cac:AccountingSupplierParty", 'before', $document916('LISIBLE', 'PJ1').$document916('LISIBLE', 'PJ2')]]),

    // BR-FR-20 to BR-FR-22: treatment note and the routing addresses it requires.
    $recipe('BR-FR-20_BT-21', [["{$invoice}/cbc:Note[starts-with(., '#BAR#')]", 'set', '#BAR#B2G']]),
    $recipe('BR-FR-21_BT-49', [["{$customer}/cbc:EndpointID/@schemeID", 'set', '0088']]),
    $recipe('BR-FR-22_BT-34', [[$typeCode, 'set', '389'], ["{$supplier}/cbc:EndpointID/@schemeID", 'set', '0088']]),

    // BR-FR-23 to BR-FR-26: format and length of electronic addresses and private identifiers.
    $recipe('BR-FR-23_BT-34', [["{$supplier}/cbc:EndpointID", 'set', '100000009/FACTURES']]),
    $recipe('BR-FR-23_BT-49', [["{$customer}/cbc:EndpointID", 'set', '200000008/FACTURES']]),
    $recipe('BR-FR-24_BT-29', [["{$supplier}/cac:PostalAddress", 'before', $id('0224', 'CLIENT/42')]]),
    $recipe('BR-FR-24_BT-46', [["{$customer}/cac:PostalAddress", 'before', $id('0224', 'CLIENT/42')]]),
    $recipe('BR-FR-25_BT-34', [["{$supplier}/cbc:EndpointID", 'set', $long]]),
    $recipe('BR-FR-25_BT-49', [["{$customer}/cbc:EndpointID", 'set', $long]]),
    $recipe('BR-FR-26_BT-29', [["{$supplier}/cac:PostalAddress", 'before', $id('0224', str_repeat('A', 101))]]),
    $recipe('BR-FR-26_BT-46', [["{$customer}/cac:PostalAddress", 'before', $id('0224', str_repeat('A', 101))]]),

    // BR-FR-28: item attributes carry a value or a quantity with its unit.
    $recipe('BR-FR-28-Value', [["{$line}/cac:Item", 'append', '<cac:AdditionalItemProperty><cbc:Name>COULEUR</cbc:Name></cac:AdditionalItemProperty>']]),

    // BR-FR-29 / BR-FR-30: invoiced object references.
    $recipe('BR-FR-29_AFL', [["{$invoice}/cac:AccountingSupplierParty", 'before', $reference130('AFL', ' ')]]),
    $recipe('BR-FR-29_AVV', [["{$invoice}/cac:AccountingSupplierParty", 'before', $reference130('AVV', ' ')]]),
    $recipe('BR-FR-30_AFL', [["{$line}/cac:Item", 'before', $reference130('AFL', ' ', 'DocumentReference')]]),
    $recipe('BR-FR-30_AVV', [["{$line}/cac:Item", 'before', $reference130('AVV', ' ', 'DocumentReference')]]),

    // BR-FR-31 / BR-FR-32: one treatment note; SIREN identifiers are 9 digits.
    $recipe('BR-FR-30_BT-21', [[$currencyCode, 'before', $note('BAR', 'B2C')]]),
    $recipe('BR-FR-32-LEGALID', [["{$customer}/cac:PartyLegalEntity/cbc:CompanyID", 'set', '2000000080']]),
    $recipe('BR-FR-32-ID', [["{$customer}/cac:PostalAddress", 'before', $id('0002', '20000000')]]),

    // BR-FR-CO-03 to BR-FR-CO-09: document type, framework and payment coherence.
    $recipe('BR-FR-CO-03_BT-3', [["{$credit}/cbc:CreditNoteTypeCode", 'set', '262']], $creditNote),
    $recipe('BR-FR-CO-04_BT-3', [[$typeCode, 'set', '384']]),
    $recipe('BR-FR-CO-05_BT-3', [["{$credit}/cac:BillingReference", 'remove']], $creditNote),
    $recipe('BR-FR-CO-07_BT-9', [["{$invoice}/cbc:DueDate", 'set', '2026-11-01']]),
    $recipe('BR-FR-CO-08_BT-23', [[$profile, 'set', 'B4'], [$typeCode, 'set', '386']]),
    $recipe('BR-FR-CO-09_BT-23-1', [[$profile, 'set', 'B2']]),
    $recipe('BR-FR-CO-09_BT-23-2', [[$profile, 'set', 'B2']]),
    $recipe('BR-FR-CO-09_BT-23-3', [[$profile, 'set', 'B2'], ["{$invoice}/cbc:DueDate", 'remove']]),

    // BR-FR-CO-10: every party identifier has a scheme, each scheme once per party.
    $recipe('BR-FR-CO-10_BT-29-1', [["{$supplier}/cac:PartyIdentification/cbc:ID/@schemeID", 'remove']]),
    $recipe('BR-FR-CO-10_BT-29-2', [["{$supplier}/cac:PostalAddress", 'before', $id('0009', '10000000900025')]]),
    $recipe('BR-FR-CO-10_BT-46-1', [["{$customer}/cac:PostalAddress", 'before', $id('', '200000008')]]),
    $recipe('BR-FR-CO-10_BT-46-2', [["{$customer}/cac:PostalAddress", 'before', $id('0088', '3000000000017').$id('0088', '3000000000024')]]),
    $recipe('BR-FR-CO-10_BT-71-1', [[$terms, 'before', '<cac:Delivery><cac:DeliveryLocation><cbc:ID>3000000000017</cbc:ID></cac:DeliveryLocation></cac:Delivery>']]),
    $recipe('BR-FR-CO-10_EXT-FR-FE-146-1', [["{$detail3}/cac:Delivery/cac:DeliveryLocation/cbc:Name", 'before', '<cbc:ID>3000000000017</cbc:ID>']], $extended),
];

foreach (['BT-60', 'EXT-FR-FE-06', 'EXT-FR-FE-46', 'EXT-FR-FE-69', 'EXT-FR-FE-92', 'EXT-FR-FE-115'] as $partyId) {
    $recipes[] = $recipe("BR-FR-CO-10_{$partyId}-1", [$addParty($partyId, $id('', '3000000000017').$name)]);
    $recipes[] = $recipe("BR-FR-CO-10_{$partyId}-2", [$addParty($partyId, $id('0088', '3000000000017').$id('0088', '3000000000024').$name)]);
}
foreach ($addressParties as $addressId => $partyId) {
    $recipes[] = $recipe("BR-FR-23_{$addressId}", [$addParty($partyId, $endpoint('300000000/FACTURES').$name)]);
    $recipes[] = $recipe("BR-FR-25_{$addressId}", [$addParty($partyId, $endpoint($long).$name)]);
}

return [
    ...$recipes,

    // BR-FR-CO-12 to BR-FR-CO-15: foreign currency and single VAT taxable persons.
    $recipe('BR-FR-CO-12_BT-5', [[$currencyCode, 'set', 'USD']]),
    $recipe('BR-FR-CO-14_BT-29-1', [["{$supplier}/cac:PostalAddress", 'before', $id('0231', '100000009')]]),
    $recipe('BR-FR-CO-15_BT-29-1', [["{$supplier}/cac:PostalAddress", 'before', $id('0231', '100000009')]]),

    // BR-FR-DEC-01: amounts have at most 2 decimals.
    $recipe('BR-FR-DEC-01_BT-92_BT-99', [[$taxTotal, 'before', $allowance('S', '20', '10.001')]]),
    $recipe('BR-FR-DEC-01_BT-93_BT-100', [[$taxTotal, 'before', $allowance('S', '20', '10.00', '100.001')]]),
    $recipe('BR-FR-DEC-01_BT-106', [["{$totals}/cbc:LineExtensionAmount", 'set', '10000.001']]),
    $recipe('BR-FR-DEC-01_BT-107', [["{$totals}/cbc:PayableAmount", 'before', '<cbc:AllowanceTotalAmount currencyID="EUR">0.001</cbc:AllowanceTotalAmount>']]),
    $recipe('BR-FR-DEC-01_BT-108', [["{$totals}/cbc:PayableAmount", 'before', '<cbc:ChargeTotalAmount currencyID="EUR">0.001</cbc:ChargeTotalAmount>']]),
    $recipe('BR-FR-DEC-01_BT-109', [["{$totals}/cbc:TaxExclusiveAmount", 'set', '10000.001']]),
    $recipe('BR-FR-DEC-01_BT-110', [["{$taxTotal}/cbc:TaxAmount", 'set', '2000.001']]),
    $recipe('BR-FR-DEC-01_BT-112', [["{$totals}/cbc:TaxInclusiveAmount", 'set', '12000.001']]),
    $recipe('BR-FR-DEC-01_BT-113', [["{$totals}/cbc:PayableAmount", 'before', '<cbc:PrepaidAmount currencyID="EUR">0.001</cbc:PrepaidAmount>']]),
    $recipe('BR-FR-DEC-01_BT-114', [["{$totals}/cbc:PayableAmount", 'before', '<cbc:PayableRoundingAmount currencyID="EUR">0.001</cbc:PayableRoundingAmount>']]),
    $recipe('BR-FR-DEC-01_BT-115', [["{$totals}/cbc:PayableAmount", 'set', '12000.001']]),
    $recipe('BR-FR-DEC-01_BT-116', [["{$subtotal}/cbc:TaxableAmount", 'set', '10000.001']]),
    $recipe('BR-FR-DEC-01_BT-117', [["{$subtotal}/cbc:TaxAmount", 'set', '2000.001']]),
    $recipe('BR-FR-DEC-01_BT-131', [["{$line}/cbc:LineExtensionAmount", 'set', '8000.001']]),
    $recipe('BR-FR-DEC-01_BT-136_BT-141', [["{$line}/cac:Item", 'before', $lineAllowance('1.001')]]),
    $recipe('BR-FR-DEC-01_BT-137_BT-142', [["{$line}/cac:Item", 'before', $lineAllowance('1.00', '100.001')]]),

    // BR-FR-DEC-02 to BR-FR-DEC-04: quantities, unit prices and VAT rates.
    $recipe('BR-FR-DEC-02_BT-129', [["{$line}/cbc:InvoicedQuantity", 'set', '200.00001']]),
    $recipe('BR-FR-DEC-02_BT-149', [["{$line}/cac:Price", 'append', '<cbc:BaseQuantity unitCode="HUR">1.00001</cbc:BaseQuantity>']]),
    $recipe('BR-FR-DEC-03_BT-146', [["{$line}/cac:Price/cbc:PriceAmount", 'set', '40.0000001']]),
    $recipe('BR-FR-DEC-03_BT-147', [["{$line}/cac:Price", 'append', $lineAllowance('-1.00')]]),
    $recipe('BR-FR-DEC-03_BT-148', [["{$line}/cac:Price", 'append', $lineAllowance('1.00', '-40.00')]]),
    $recipe('BR-FR-DEC-04_BT-96_BT-103', [[$taxTotal, 'before', $allowance('S', '20.001')]]),
    $recipe('BR-FR-DEC-04_BT-119', [["{$subtotal}/cac:TaxCategory/cbc:Percent", 'set', '20.001']]),
    $recipe('BR-FR-DEC-04_BT-152', [["{$line}/cac:Item/cac:ClassifiedTaxCategory/cbc:Percent", 'set', '20.001']]),

    // BR-FR-MV / BR-FR-BD: multi-vendor (B8, S8, M8) and bi-directional (B9, S9, M9) invoices.
    $recipe('BR-FR-MV-01_EXT-FR-FE-163', [$b8, ["{$information}/cac:BillingReference", 'remove']], $extended),
    $recipe('BR-FR-MV-02_EXT-FR-FE-163', [$b8]),
    $recipe('BR-FR-BD-02_EXT-FR-FE-163', [$b9], $extended),
    $recipe('BR-FR-BD-02_BT-30', [$b9], $extended),
    $recipe('BR-FR-BD-02_BT-47', [$b9], $extended),
    $recipe('BR-FR-MV-03_EXT-FR-FE-164', [$b8], $extended),
    $recipe('BR-FR-MV-03_EXT-FR-FE-167', [$b8], $extended),
    $recipe('BR-FR-MV-03_EXT-FR-FE-177', [$b8], $extended),
    $recipe('BR-FR-MV-03_EXT-FR-FE-181', [$b8], $extended),
    $recipe('BR-FR-MV-03_EXT-FR-FE-182', [$b8, ["{$invoice}/cbc:BuyerReference", 'before', '<cbc:TaxCurrencyCode>EUR</cbc:TaxCurrencyCode>']], $extended),
    $recipe('BR-FR-MV-03_BT-128_AFL', [$b8], $extended),
    $recipe('BR-FR-MV-03_BT-128_AVV', [$b8], $extended),
    $recipe('BR-FR-MV-05_EXT-FR-FE-BG-12', [$b8, ["{$group}/cbc:LineExtensionAmount", 'set', '600.00']], $extended),
    $recipe('BR-FR-MV-06_EXT-FR-FE-167', [$b8], $extended),
    $recipe('BR-FR-MV-07_BT-128', [$b8], $extended),
    $recipe('BR-FR-MV-08_BT-128', [$b8], $extended),
    $recipe('BR-FR-MV-09_EXT-FR-FE-181', [$b8], $extended),
    $recipe('BR-FR-MV-11_BT-128', [$b8, ["{$group}/cac:Item", 'append', '<cac:ManufacturerParty><cac:PartyLegalEntity><cbc:RegistrationName>LE VENDEUR</cbc:RegistrationName><cbc:CompanyID schemeID="0002">100000009</cbc:CompanyID></cac:PartyLegalEntity></cac:ManufacturerParty>']], $extended),
    $recipe('BR-FR-MV-12_BT-128', [$b8, ["{$group}/cac:Item", 'before', $reference130('AFL', 'SF1', 'DocumentReference').$reference130('AFL', 'SF1', 'DocumentReference')]], $extended),
    $recipe('BR-FR-MV-13_BT-3', [$b8, [$typeCode, 'set', '389']], $extended),
    $recipe('BR-FR-MV-BD_BT-3', [$b9], $extended),
    $recipe('BR-FR-MV-14-EXT-FR-FE-136', [$b8, [$typeCode, 'set', '384']], $extended),
    $recipe('BR-FR-MV-14-EXT-FR-FE-138', [$b8, [$typeCode, 'set', '384']], $extended),
    $recipe('BR-FR-BD-14-EXT-FR-FE-136', [$b9, [$typeCode, 'set', '261']], $extended),
    $recipe('BR-FR-BD-14-EXT-FR-FE-138', [$b9, [$typeCode, 'set', '261']], $extended),
];
