<?php

declare(strict_types=1);

// Recipes for fr-flux2-cii; the format is documented on NegativeCorpusTest.

$domestic = 'writer:domesticB2b:cii';
$extended = 'writer:extendedCtcFr:cii';
$creditNote = 'writer:creditNoteWithTwoPrecedingInvoices:cii';

$context = '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext';
$framework = "{$context}/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID";
$document = '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument';
$typeCode = "{$document}/ram:TypeCode";
$transaction = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction';
$agreement = "{$transaction}/ram:ApplicableHeaderTradeAgreement";
$seller = "{$agreement}/ram:SellerTradeParty";
$buyer = "{$agreement}/ram:BuyerTradeParty";
$delivery = "{$transaction}/ram:ApplicableHeaderTradeDelivery";
$settlement = "{$transaction}/ram:ApplicableHeaderTradeSettlement";
$headerTax = "{$settlement}/ram:ApplicableTradeTax";
$terms = "{$settlement}/ram:SpecifiedTradePaymentTerms";
$totals = "{$settlement}/ram:SpecifiedTradeSettlementHeaderMonetarySummation";
$headerReference = "{$settlement}/ram:InvoiceReferencedDocument[1]";
$line = "{$transaction}/ram:IncludedSupplyChainTradeLineItem[1]";
$lineSettlement = "{$line}/ram:SpecifiedLineTradeSettlement";
// Lines of the EXTENDED-CTC-FR base: 1 GROUP, 2 and 3 DETAIL (3 with a preceding invoice and a
// delivery), 4 INFORMATION.
$group = "{$transaction}/ram:IncludedSupplyChainTradeLineItem[1]";
$detail2 = "{$transaction}/ram:IncludedSupplyChainTradeLineItem[2]";
$detail3 = "{$transaction}/ram:IncludedSupplyChainTradeLineItem[3]";
$information = "{$transaction}/ram:IncludedSupplyChainTradeLineItem[4]";

$recipe = static fn (string $rule, array $edits, string $base = 'writer:domesticB2b:cii'): array => [
    'set' => 'flux2-cii',
    'rule' => $rule,
    'base' => $base,
    'edits' => $edits,
];
$note = static fn (string $subject, string $content = 'MENTION'): string => "<ram:IncludedNote><ram:Content>{$content}</ram:Content><ram:SubjectCode>{$subject}</ram:SubjectCode></ram:IncludedNote>";
// A party of one role: identifiers go before its name, addresses after it.
$party = static fn (string $name, string $identifiers, string $addresses): string => "<ram:{$name}>{$identifiers}<ram:Name>TIERS</ram:Name>{$addresses}</ram:{$name}>";
$siret = '<ram:GlobalID schemeID="0009">123</ram:GlobalID>';
$uri = static fn (string $value): string => "<ram:URIUniversalCommunication><ram:URIID schemeID=\"0225\">{$value}</ram:URIID></ram:URIUniversalCommunication>";
$long = str_repeat('A', 126);
$allowance = static fn (string $category = 'S', string $rate = '20', string $actual = '10.00', string $basis = ''): string => '<ram:SpecifiedTradeAllowanceCharge><ram:ChargeIndicator><udt:Indicator>false</udt:Indicator></ram:ChargeIndicator>'
    .($basis === '' ? '' : "<ram:BasisAmount>{$basis}</ram:BasisAmount>")
    ."<ram:ActualAmount>{$actual}</ram:ActualAmount><ram:Reason>REMISE</ram:Reason>"
    ."<ram:CategoryTradeTax><ram:TypeCode>VAT</ram:TypeCode><ram:CategoryCode>{$category}</ram:CategoryCode><ram:RateApplicablePercent>{$rate}</ram:RateApplicablePercent></ram:CategoryTradeTax>"
    .'</ram:SpecifiedTradeAllowanceCharge>';
$lineAllowance = static fn (string $actual, string $basis = ''): string => '<ram:SpecifiedTradeAllowanceCharge><ram:ChargeIndicator><udt:Indicator>false</udt:Indicator></ram:ChargeIndicator>'
    .($basis === '' ? '' : "<ram:BasisAmount>{$basis}</ram:BasisAmount>")
    ."<ram:ActualAmount>{$actual}</ram:ActualAmount></ram:SpecifiedTradeAllowanceCharge>";
$document916 = static fn (string $name): string => "<ram:AdditionalReferencedDocument><ram:IssuerAssignedID>PJ-{$name}</ram:IssuerAssignedID><ram:TypeCode>916</ram:TypeCode><ram:Name>{$name}</ram:Name></ram:AdditionalReferencedDocument>";
$reference130 = static fn (string $type, string $id, string $typeCode = '130'): string => "<ram:AdditionalReferencedDocument><ram:IssuerAssignedID>{$id}</ram:IssuerAssignedID><ram:TypeCode>{$typeCode}</ram:TypeCode><ram:ReferenceTypeCode>{$type}</ram:ReferenceTypeCode></ram:AdditionalReferencedDocument>";
$characteristic = static fn (string $content): string => "<ram:ApplicableProductCharacteristic>{$content}</ram:ApplicableProductCharacteristic>";
$date = static fn (string $element, string $value, string $type = 'udt'): string => "<ram:{$element}><{$type}:DateTimeString format=\"102\">{$value}</{$type}:DateTimeString></ram:{$element}>";

$b8 = [$framework, 'set', 'B8'];
$b9 = [$framework, 'set', 'B9'];

// Parties the rules check, with where an extra party of that role goes in the schema order.
$parties = [
    'BT-60' => ['PayeeTradeParty', [$headerTax, 'before']],
    'EXT-FR-FE-06' => ['BuyerAgentTradeParty', [$agreement, 'append']],
    'EXT-FR-FE-46' => ['PayerTradeParty', [$headerTax, 'before']],
    'EXT-FR-FE-69' => ['SalesAgentTradeParty', [$agreement, 'append']],
    'EXT-FR-FE-92' => ['InvoiceeTradeParty', [$headerTax, 'before']],
    'EXT-FR-FE-115' => ['InvoicerTradeParty', [$headerTax, 'before']],
    'BT-71' => ['ShipToTradeParty', [$delivery, 'append']],
    'EXT-FR-FE-146' => ['ShipToTradeParty', ["{$line}/ram:SpecifiedLineTradeDelivery", 'append']],
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
$addParty = static function (string $id, string $identifiers, string $addresses = '') use ($parties, $party): array {
    [$name, [$where, $operation]] = $parties[$id];

    return [$where, $operation, $party($name, $identifiers, $addresses)];
};

$recipes = [
    // BR-FR-01 / BR-FR-02: identifier length and characters.
    $recipe('BR-FR-01_BT-1', [["{$document}/ram:ID", 'set', str_repeat('F', 36)]]),
    $recipe('BR-FR-02_BT-1', [["{$document}/ram:ID", 'set', 'F2026#3']]),
    $recipe('BR-FR-01_BT-25', [["{$headerReference}/ram:IssuerAssignedID", 'set', str_repeat('F', 36)]], $creditNote),
    $recipe('BR-FR-02_BT-25', [["{$headerReference}/ram:IssuerAssignedID", 'set', 'F2026#1']], $creditNote),
    $recipe('BR-FR-01_EXT-FR-FE-136', [["{$detail3}/ram:SpecifiedLineTradeSettlement/ram:InvoiceReferencedDocument/ram:IssuerAssignedID", 'set', str_repeat('F', 36)]], $extended),
    $recipe('BR-FR-02_EXT-FR-FE-136', [["{$detail3}/ram:SpecifiedLineTradeSettlement/ram:InvoiceReferencedDocument/ram:IssuerAssignedID", 'set', 'F2025#99']], $extended),

    // BR-FR-03: dates in 2000-2099 as CCYYMMDD.
    $recipe('BR-FR-03_BT-2', [["{$document}/ram:IssueDateTime/udt:DateTimeString", 'set', '19991102']]),
    $recipe('BR-FR-03_BT-7', [["{$headerTax}/ram:RateApplicablePercent", 'before', '<ram:TaxPointDate><udt:DateString format="102">19991231</udt:DateString></ram:TaxPointDate>']]),
    $recipe('BR-FR-03_BT-9', [["{$terms}/ram:DueDateDateTime/udt:DateTimeString", 'set', '20261302']]),
    $recipe('BR-FR-03_BT-26', [["{$headerReference}/ram:FormattedIssueDateTime/qdt:DateTimeString", 'set', '19991001']], $creditNote),
    $recipe('BR-FR-03_BT-72', [[$delivery, 'append', '<ram:ActualDeliverySupplyChainEvent>'.$date('OccurrenceDateTime', '19991031').'</ram:ActualDeliverySupplyChainEvent>']]),
    $recipe('BR-FR-03_BT-73', [[$terms, 'before', '<ram:BillingSpecifiedPeriod>'.$date('StartDateTime', '19991001').'</ram:BillingSpecifiedPeriod>']]),
    $recipe('BR-FR-03_BT-74', [[$terms, 'before', '<ram:BillingSpecifiedPeriod>'.$date('EndDateTime', '20261032').'</ram:BillingSpecifiedPeriod>']]),
    $recipe('BR-FR-03_EXT-FR-FE-138', [["{$detail3}/ram:SpecifiedLineTradeSettlement/ram:InvoiceReferencedDocument/ram:FormattedIssueDateTime/qdt:DateTimeString", 'set', '19991215']], $extended),
    $recipe('BR-FR-03_EXT-FR-FE-158', [["{$detail3}/ram:SpecifiedLineTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString", 'set', '20250230']], $extended),
    $recipe('BR-FR-03_EXT-FR-FE-134', [["{$detail2}/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString", 'set', '19991001']], $extended),
    $recipe('BR-FR-03_EXT-FR-FE-135', [["{$detail2}/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString", 'set', '20261131']], $extended),

    // BR-FR-04: document type codes.
    $recipe('BR-FR-04_BT-3', [[$typeCode, 'set', '326']]),
    $recipe('BR-FR-04_EXT-FR-FE-02', [["{$headerReference}/ram:FormattedIssueDateTime", 'before', '<ram:TypeCode>326</ram:TypeCode>']], $creditNote),
    $recipe('BR-FR-04_EXT-FR-FE-137', [["{$detail3}/ram:SpecifiedLineTradeSettlement/ram:InvoiceReferencedDocument/ram:FormattedIssueDateTime", 'before', '<ram:TypeCode>326</ram:TypeCode>']], $extended),

    // BR-FR-05 / BR-FR-06: mandatory notes, each at most once.
    $recipe('BR-FR-05_BT-22_PMT', [["{$document}/ram:IncludedNote[ram:SubjectCode = 'PMT']", 'remove']]),
    $recipe('BR-FR-05_BT-22_PMD', [["{$document}/ram:IncludedNote[ram:SubjectCode = 'PMD']", 'remove']]),
    $recipe('BR-FR-05_BT-22_AAB', [["{$document}/ram:IncludedNote[ram:SubjectCode = 'AAB']", 'remove']]),
    $recipe('BR-FR-06_BT-21_PMT', [[$document, 'append', $note('PMT')]]),
    $recipe('BR-FR-06_BT-21_PMD', [[$document, 'append', $note('PMD')]]),
    $recipe('BR-FR-06_BT-21_AAB', [[$document, 'append', $note('AAB')]]),
    $recipe('BR-FR-06_BT-21_TXD', [[$document, 'append', $note('TXD').$note('TXD')]]),

    // BR-FR-08: billing framework.
    $recipe('BR-FR-08_BT-23', [[$framework, 'set', 'B3']]),

    // BR-FR-09: a SIRET is 14 digits and starts with the SIREN.
    $recipe('BR-FR-09_BT-29', [["{$seller}/ram:GlobalID[@schemeID = '0009']", 'set', '20000000800017']]),
    $recipe('BR-FR-09_BT-46', [["{$buyer}/ram:Name", 'before', '<ram:GlobalID schemeID="0009">10000000900017</ram:GlobalID>']]),
    $recipe('BR-FR-09_BT-60', [$addParty('BT-60', $siret)]),
    $recipe('BR-FR-09_EXT-FR-FE-06', [$addParty('EXT-FR-FE-06', $siret)]),
    $recipe('BR-FR-09_EXT-FR-FE-46', [$addParty('EXT-FR-FE-46', $siret)]),
    $recipe('BR-FR-09_EXT-FR-FE-69', [$addParty('EXT-FR-FE-69', $siret)]),
    $recipe('BR-FR-09_EXT-FR-FE-92', [$addParty('EXT-FR-FE-92', $siret)]),
    $recipe('BR-FR-09_EXT-FR-FE-115', [$addParty('EXT-FR-FE-115', $siret)]),
    $recipe('BR-FR-09_BT-71', [$addParty('BT-71', $siret)]),
    $recipe('BR-FR-09_EXT-FR-FE-146', [$addParty('EXT-FR-FE-146', $siret)]),

    // BR-FR-10 to BR-FR-13: SIRENs and electronic addresses.
    $recipe('BR-FR-10_BT-30', [["{$seller}/ram:SpecifiedLegalOrganization/ram:ID", 'set', '10000000']]),
    $recipe('BR-FR-11_BT-47', [["{$buyer}/ram:SpecifiedLegalOrganization/ram:ID", 'set', '20000000A']]),
    $recipe('BR-FR-12_BT-49', [["{$buyer}/ram:URIUniversalCommunication", 'remove']]),
    $recipe('BR-FR-13_BT-34', [["{$seller}/ram:URIUniversalCommunication", 'remove']]),

    // BR-FR-15 / BR-FR-16: VAT categories and rates.
    $recipe('BR-FR-15_BT-95_BT-102', [[$terms, 'before', $allowance('B')]]),
    $recipe('BR-FR-15_BT-95_BT-102_LM', [[$terms, 'before', $allowance('L')]]),
    $recipe('BR-FR-15_BT-95_BT-118', [["{$headerTax}/ram:CategoryCode", 'set', 'B']]),
    $recipe('BR-FR-15_BT-95_BT-118_LM', [["{$headerTax}/ram:CategoryCode", 'set', 'M']]),
    $recipe('BR-FR-15_BT-95_BT-151', [["{$lineSettlement}/ram:ApplicableTradeTax/ram:CategoryCode", 'set', 'B']]),
    $recipe('BR-FR-15_BT-95_BT-151_LM', [["{$lineSettlement}/ram:ApplicableTradeTax/ram:CategoryCode", 'set', 'L']]),
    $recipe('BR-FR-16_BT-96_BT-103', [[$terms, 'before', $allowance('S', '21')]]),
    $recipe('BR-FR-16_BT-119', [["{$headerTax}/ram:RateApplicablePercent", 'set', '21']]),
    $recipe('BR-FR-16_BT-152', [["{$lineSettlement}/ram:ApplicableTradeTax/ram:RateApplicablePercent", 'set', '21']]),

    // BR-FR-17 / BR-FR-18: attachment descriptions.
    $recipe('BR-FR-17_BT-123', [[$agreement, 'append', $document916('INCONNU')]]),
    $recipe('BR-FR-18_BT-123', [[$agreement, 'append', $document916('LISIBLE').$document916('LISIBLE')]]),

    // BR-FR-20 to BR-FR-22: treatment note and the routing addresses it requires.
    $recipe('BR-FR-20_BT-21', [["{$document}/ram:IncludedNote[ram:SubjectCode = 'BAR']/ram:Content", 'set', 'B2G']]),
    $recipe('BR-FR-21_BT-49', [["{$buyer}/ram:URIUniversalCommunication/ram:URIID/@schemeID", 'set', '0088']]),
    $recipe('BR-FR-22_BT-34', [[$typeCode, 'set', '389'], ["{$seller}/ram:URIUniversalCommunication/ram:URIID/@schemeID", 'set', '0088']]),

    // BR-FR-23 to BR-FR-26: format and length of electronic addresses and private identifiers.
    $recipe('BR-FR-23_BT-34', [["{$seller}/ram:URIUniversalCommunication/ram:URIID", 'set', '100000009/FACTURES']]),
    $recipe('BR-FR-23_BT-49', [["{$buyer}/ram:URIUniversalCommunication/ram:URIID", 'set', '200000008/FACTURES']]),
    $recipe('BR-FR-24_BT-29', [["{$seller}/ram:Name", 'before', '<ram:GlobalID schemeID="0224">CLIENT/42</ram:GlobalID>']]),
    $recipe('BR-FR-24_BT-46', [["{$buyer}/ram:Name", 'before', '<ram:GlobalID schemeID="0224">CLIENT/42</ram:GlobalID>']]),
    $recipe('BR-FR-25_BT-34', [["{$seller}/ram:URIUniversalCommunication/ram:URIID", 'set', $long]]),
    $recipe('BR-FR-25_BT-49', [["{$buyer}/ram:URIUniversalCommunication/ram:URIID", 'set', $long]]),
    $recipe('BR-FR-26_BT-29', [["{$seller}/ram:Name", 'before', '<ram:GlobalID schemeID="0224">'.str_repeat('A', 101).'</ram:GlobalID>']]),
    $recipe('BR-FR-26_BT-46', [["{$buyer}/ram:Name", 'before', '<ram:GlobalID schemeID="0224">'.str_repeat('A', 101).'</ram:GlobalID>']]),

    // BR-FR-27 / BR-FR-28: item attributes.
    $recipe('BR-FR-27_BG-32', [["{$line}/ram:SpecifiedTradeProduct", 'append', $characteristic('<ram:Value>ROUGE</ram:Value>')]]),
    $recipe('BR-FR-27_BT-160', [["{$line}/ram:SpecifiedTradeProduct", 'append', $characteristic('<ram:Description> </ram:Description><ram:Value>ROUGE</ram:Value>')]]),
    $recipe('BR-FR-27_EXT-FR-FE-159', [["{$line}/ram:SpecifiedTradeProduct", 'append', $characteristic('<ram:TypeCode> </ram:TypeCode><ram:Value>ROUGE</ram:Value>')]]),
    $recipe('BR-FR-28-Value', [["{$line}/ram:SpecifiedTradeProduct", 'append', $characteristic('<ram:Description>COULEUR</ram:Description>')]]),

    // BR-FR-29: invoiced object references at document level.
    $recipe('BR-FR-29_BT-18', [[$agreement, 'append', $reference130('AFL', 'SF1').$reference130('AFL', 'SF2')]]),
    $recipe('BR-FR-29_AFL', [[$agreement, 'append', $reference130('AFL', ' ')]]),
    $recipe('BR-FR-29_AVV', [[$agreement, 'append', $reference130('AVV', ' ')]]),

    // BR-FR-31 / BR-FR-32: one treatment note; SIREN identifiers are 9 digits.
    $recipe('BR-FR-31_BT-21', [[$document, 'append', $note('BAR', 'B2C')]]),
    $recipe('BR-FR-32-LEGALID', [["{$buyer}/ram:SpecifiedLegalOrganization/ram:ID", 'set', '2000000080']]),
    $recipe('BR-FR-32-GLOBALID', [["{$buyer}/ram:Name", 'before', '<ram:GlobalID schemeID="0002">20000000</ram:GlobalID>']]),

    // BR-FR-CO-03 to BR-FR-CO-09: document type, framework and payment coherence.
    $recipe('BR-FR-CO-03_BT-3', [[$typeCode, 'set', '262']]),
    $recipe('BR-FR-CO-04_BT-4', [[$typeCode, 'set', '384']]),
    $recipe('BR-FR-CO-05_BT-3', [[$typeCode, 'set', '381']]),
    $recipe('BR-FR-CO-07_BT-9', [["{$terms}/ram:DueDateDateTime/udt:DateTimeString", 'set', '20261101']]),
    $recipe('BR-FR-CO-08_BT-23', [[$framework, 'set', 'B4'], [$typeCode, 'set', '386']]),
    $recipe('BR-FR-CO-09_BT-23-1', [[$framework, 'set', 'B2']]),
    $recipe('BR-FR-CO-09_BT-23-2', [[$framework, 'set', 'B2']]),
    $recipe('BR-FR-CO-09_BT-23-3', [[$framework, 'set', 'B2'], ["{$terms}/ram:DueDateDateTime", 'remove']]),

    // BR-FR-CO-10: every global identifier has a scheme, each scheme once per party.
    $recipe('BR-FR-CO-10_BT-29-1', [["{$seller}/ram:GlobalID/@schemeID", 'remove']]),
    $recipe('BR-FR-CO-10_BT-29-2', [["{$seller}/ram:Name", 'before', '<ram:GlobalID schemeID="0009">10000000900025</ram:GlobalID>']]),
    $recipe('BR-FR-CO-10_BT-46-1', [["{$buyer}/ram:Name", 'before', '<ram:GlobalID>200000008</ram:GlobalID>']]),
    $recipe('BR-FR-CO-10_BT-46-2', [["{$buyer}/ram:Name", 'before', '<ram:GlobalID schemeID="0088">3000000000017</ram:GlobalID><ram:GlobalID schemeID="0088">3000000000024</ram:GlobalID>']]),
];

foreach (['BT-60', 'EXT-FR-FE-06', 'EXT-FR-FE-46', 'EXT-FR-FE-69', 'EXT-FR-FE-92', 'EXT-FR-FE-115', 'BT-71', 'EXT-FR-FE-146'] as $id) {
    $recipes[] = $recipe("BR-FR-CO-10_{$id}-1", [$addParty($id, '<ram:GlobalID>3000000000017</ram:GlobalID>')]);
    $recipes[] = $recipe("BR-FR-CO-10_{$id}-2", [$addParty($id, '<ram:GlobalID schemeID="0088">3000000000017</ram:GlobalID><ram:GlobalID schemeID="0088">3000000000024</ram:GlobalID>')]);
}
foreach ($addressParties as $addressId => $partyId) {
    $recipes[] = $recipe("BR-FR-23_{$addressId}", [$addParty($partyId, '', $uri('300000000/FACTURES'))]);
    $recipes[] = $recipe("BR-FR-25_{$addressId}", [$addParty($partyId, '', $uri($long))]);
}

return [
    ...$recipes,

    // BR-FR-CO-12 to BR-FR-CO-15: foreign currency and single VAT taxable persons.
    $recipe('BR-FR-CO-12_BT-5', [["{$settlement}/ram:InvoiceCurrencyCode", 'set', 'USD']]),
    $recipe('BR-FR-CO-14_BT-29-1', [["{$seller}/ram:Name", 'before', '<ram:GlobalID schemeID="0231">100000009</ram:GlobalID>']]),
    $recipe('BR-FR-CO-15_BT-29-1', [["{$seller}/ram:Name", 'before', '<ram:GlobalID schemeID="0231">100000009</ram:GlobalID>']]),

    // BR-FR-DEC-01: amounts have at most 2 decimals.
    $recipe('BR-FR-DEC-01_BT-92_BT-99', [[$terms, 'before', $allowance('S', '20', '10.001')]]),
    $recipe('BR-FR-DEC-01_BT-93_BT-100', [[$terms, 'before', $allowance('S', '20', '10.00', '100.001')]]),
    $recipe('BR-FR-DEC-01_BT-106', [["{$totals}/ram:LineTotalAmount", 'set', '10000.001']]),
    $recipe('BR-FR-DEC-01_BT-107', [["{$totals}/ram:TaxBasisTotalAmount", 'before', '<ram:AllowanceTotalAmount>0.001</ram:AllowanceTotalAmount>']]),
    $recipe('BR-FR-DEC-01_BT-108', [["{$totals}/ram:TaxBasisTotalAmount", 'before', '<ram:ChargeTotalAmount>0.001</ram:ChargeTotalAmount>']]),
    $recipe('BR-FR-DEC-01_BT-109', [["{$totals}/ram:TaxBasisTotalAmount", 'set', '10000.001']]),
    $recipe('BR-FR-DEC-01_BT-110', [["{$totals}/ram:TaxTotalAmount", 'set', '2000.001']]),
    $recipe('BR-FR-DEC-01_BT-112', [["{$totals}/ram:GrandTotalAmount", 'set', '12000.001']]),
    $recipe('BR-FR-DEC-01_BT-113', [["{$totals}/ram:DuePayableAmount", 'before', '<ram:TotalPrepaidAmount>0.001</ram:TotalPrepaidAmount>']]),
    $recipe('BR-FR-DEC-01_BT-114', [["{$totals}/ram:GrandTotalAmount", 'before', '<ram:RoundingAmount>0.001</ram:RoundingAmount>']]),
    $recipe('BR-FR-DEC-01_BT-115', [["{$totals}/ram:DuePayableAmount", 'set', '12000.001']]),
    $recipe('BR-FR-DEC-01_BT-116', [["{$headerTax}/ram:BasisAmount", 'set', '10000.001']]),
    $recipe('BR-FR-DEC-01_BT-117', [["{$headerTax}/ram:CalculatedAmount", 'set', '2000.001']]),
    $recipe('BR-FR-DEC-01_BT-131', [["{$lineSettlement}/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount", 'set', '8000.001']]),
    $recipe('BR-FR-DEC-01_BT-136_BT-141', [["{$lineSettlement}/ram:SpecifiedTradeSettlementLineMonetarySummation", 'before', $lineAllowance('1.001')]]),
    $recipe('BR-FR-DEC-01_BT-137_BT-142', [["{$lineSettlement}/ram:SpecifiedTradeSettlementLineMonetarySummation", 'before', $lineAllowance('1.00', '100.001')]]),

    // BR-FR-DEC-02 to BR-FR-DEC-04: quantities, unit prices and VAT rates.
    $recipe('BR-FR-DEC-02_BT-129', [["{$line}/ram:SpecifiedLineTradeDelivery/ram:BilledQuantity", 'set', '200.00001']]),
    $recipe('BR-FR-DEC-02_BT-149', [["{$line}/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice", 'append', '<ram:BasisQuantity unitCode="HUR">1.00001</ram:BasisQuantity>']]),
    $recipe('BR-FR-DEC-03_BT-146', [["{$line}/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount", 'set', '40.0000001']]),
    $recipe('BR-FR-DEC-03_BT-147', [["{$line}/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice", 'before', '<ram:GrossPriceProductTradePrice><ram:ChargeAmount>41.00</ram:ChargeAmount><ram:AppliedTradeAllowanceCharge><ram:ChargeIndicator><udt:Indicator>false</udt:Indicator></ram:ChargeIndicator><ram:ActualAmount>-1.00</ram:ActualAmount></ram:AppliedTradeAllowanceCharge></ram:GrossPriceProductTradePrice>']]),
    $recipe('BR-FR-DEC-03_BT-148', [["{$line}/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice", 'before', '<ram:GrossPriceProductTradePrice><ram:ChargeAmount>-40.00</ram:ChargeAmount></ram:GrossPriceProductTradePrice>']]),
    $recipe('BR-FR-DEC-04_BT-96_BT-103', [[$terms, 'before', $allowance('S', '20.001')]]),
    $recipe('BR-FR-DEC-04_BT-119', [["{$headerTax}/ram:RateApplicablePercent", 'set', '20.001']]),
    $recipe('BR-FR-DEC-04_BT-152', [["{$lineSettlement}/ram:ApplicableTradeTax/ram:RateApplicablePercent", 'set', '20.001']]),

    // BR-FR-MV / BR-FR-BD: multi-vendor (B8, S8, M8) and bi-directional (B9, S9, M9) invoices.
    $recipe('BR-FR-MV-01_EXT-FR-FE-163', [$b8, ["{$information}/ram:AssociatedDocumentLineDocument/ram:LineStatusReasonCode", 'set', '']], $extended),
    $recipe('BR-FR-MV-02_EXT-FR-FE-163', [$b8]),
    $recipe('BR-FR-BD-02_EXT-FR-FE-163', [$b9], $extended),
    $recipe('BR-FR-BD-02_BT-31', [$b9], $extended),
    $recipe('BR-FR-BD-02_BT-47', [$b9], $extended),
    $recipe('BR-FR-MV-03_EXT-FR-FE-164', [$b8], $extended),
    $recipe('BR-FR-MV-03_EXT-FR-FE-167', [$b8], $extended),
    $recipe('BR-FR-MV-03_EXT-FR-FE-177', [$b8], $extended),
    $recipe('BR-FR-MV-03_EXT-FR-FE-181', [$b8], $extended),
    $recipe('BR-FR-MV-03_EXT-FR-FE-182', [$b8, ["{$settlement}/ram:InvoiceCurrencyCode", 'before', '<ram:TaxCurrencyCode>EUR</ram:TaxCurrencyCode>']], $extended),
    $recipe('BR-FR-MV-03_BT-128_AFL', [$b8, ["{$group}/ram:SpecifiedLineTradeSettlement", 'append', $reference130('AFL', 'SF1', '916')]], $extended),
    $recipe('BR-FR-MV-03_BT-128_AVV', [$b8, ["{$group}/ram:SpecifiedLineTradeSettlement", 'append', $reference130('AVV', 'B8')]], $extended),
    $recipe('BR-FR-MV-05_EXT-FR-FE-BG-12', [$b8, ["{$group}/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount", 'set', '600.00']], $extended),
    $recipe('BR-FR-MV-06_EXT-FR-FE-167', [$b8], $extended),
    $recipe('BR-FR-MV-07_BT-128', [$b8], $extended),
    $recipe('BR-FR-MV-08_BT-128', [$b8], $extended),
    $recipe('BR-FR-MV-09_EXT-FR-FE-181', [$b8], $extended),
    $recipe('BR-FR-MV-10_EXT-FR-FE-184', [$b8, ["{$group}/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation", 'append', '<ram:GrandTotalAmount>999.00</ram:GrandTotalAmount>']], $extended),
    $recipe('BR-FR-MV-11_BT-128', [$b8, ["{$group}/ram:SpecifiedLineTradeAgreement", 'append', '<ram:ItemSellerTradeParty><ram:Name>LE VENDEUR</ram:Name><ram:SpecifiedLegalOrganization><ram:ID schemeID="0002">100000009</ram:ID></ram:SpecifiedLegalOrganization></ram:ItemSellerTradeParty>']], $extended),
    $recipe('BR-FR-MV-12_BT-128', [$b8, ["{$group}/ram:SpecifiedLineTradeSettlement", 'append', $reference130('AFL', 'SF1').$reference130('AFL', 'SF1')]], $extended),
    $recipe('BR-FR-MV-13_BT-3', [$b8, [$typeCode, 'set', '389']], $extended),
    $recipe('BR-FR-BD-13_BT-3', [$b9], $extended),
    $recipe('BR-FR-MV-14-EXT-FR-FE-136', [$b8, [$typeCode, 'set', '381']], $extended),
    $recipe('BR-FR-MV-14-EXT-FR-FE-138', [$b8, [$typeCode, 'set', '381']], $extended),
    $recipe('BR-FR-BD-14-EXT-FR-FE-136', [$b9, [$typeCode, 'set', '261']], $extended),
    $recipe('BR-FR-BD-14-EXT-FR-FE-138', [$b9, [$typeCode, 'set', '261']], $extended),
];
