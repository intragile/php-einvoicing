<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Cii;

use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\ReadResult;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\HostileXml;
use IntrAgile\EInvoicing\Tests\Support\InvoiceSnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CiiReaderTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../fixtures/cii';

    private const UBL_FIXTURES = __DIR__.'/../../fixtures/ubl';

    private const RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

    private const RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    /**
     * What the official examples hold that the model cannot. In these lines the gross price base
     * quantity repeats the gross price amount (e.g. 1498 for a price of 1498) and contradicts the
     * net price base quantity; EN 16931 has one base quantity (BT-149) for both prices, so the
     * conflicting one is reported.
     *
     * @var array<string, list<string>>
     */
    private const KNOWN_IGNORED = [
        'CII_business_example_01.xml' => [
            '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem[1]/ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity',
            '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem[3]/ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity',
        ],
        'CII_example2.xml' => [
            '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem[1]/ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity',
            '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem[3]/ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity',
        ],
        'CII_example5.xml' => [
            '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem[1]/ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity',
        ],
    ];

    /** A listed free text that differs only in whitespace. */
    private const LAYOUT_ONLY = 'layout only';

    /** A listed free text that differs in content. */
    private const DIFFERENT_TEXT = 'different text';

    /**
     * Differences that follow from the two syntax bindings themselves, as snapshot path => [UBL, CII].
     * Everything else in the model of the same invoice must be equal.
     *
     * @var array<string, array{scalar|null, scalar|null}>
     */
    private const ALL_TERMS_SYNTAX_DIFFERENCES = [
        // CII carries BT-29 without a scheme in ram:ID, which the schema places before every
        // ram:GlobalID; UBL keeps the order of cac:PartyIdentification as written.
        'seller.identifiers.0.value' => ['5790000435968', 'LOCAL-29'],
        'seller.identifiers.0.scheme' => ['0088', null],
        'seller.identifiers.1.value' => ['LOCAL-29', '5790000435968'],
        'seller.identifiers.1.scheme' => [null, '0088'],
        // BT-32 has no scheme in EN 16931; the model keeps the syntax qualifier (UBL TaxScheme/ID,
        // CII schemeID "FC").
        'seller.taxRegistration.taxScheme' => ['LOC', 'FC'],
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function hostile(): array
    {
        return HostileXml::cases('CrossIndustryInvoice', self::RSM);
    }

    #[DataProvider('hostile')]
    public function test_refuses_hostile_xml(string $xml): void
    {
        $started = hrtime(true);

        try {
            (new CiiReader)->read($xml);
            self::fail('UnsafeXml was not thrown.');
        } catch (UnsafeXml) {
            self::assertLessThan(100, (hrtime(true) - $started) / 1e6);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function officialExamples(): array
    {
        $cases = [];
        foreach (Fixtures::official('cii') as $file) {
            $cases[basename($file)] = [$file];
        }

        return $cases;
    }

    #[DataProvider('officialExamples')]
    public function test_reads_every_official_example(string $file): void
    {
        $xml = self::contents($file);
        $result = (new CiiReader)->read($xml);

        $document = new DOMDocument;
        $document->loadXML($xml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('rsm', self::RSM);
        $xpath->registerNamespace('ram', self::RAM);

        self::assertSame($xpath->evaluate('string(/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:ID)'), $result->invoice->number);
        $lineCount = $xpath->evaluate('count(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem)');
        self::assertIsFloat($lineCount);
        self::assertSame((int) $lineCount, count($result->invoice->lines));
        self::assertSame(self::KNOWN_IGNORED[basename($file)] ?? [], $result->ignored, 'Every other element of an official example maps to a business term.');
    }

    public function test_guide_example1_values(): void
    {
        $invoice = $this->readOfficial('CII_example1.xml')->invoice;

        self::assertSame('12115118', $invoice->number);
        self::assertSame('2015-01-09', $invoice->issueDate->format('Y-m-d'));
        self::assertSame('EUR', $invoice->currency->value);
        self::assertSame('250.33', (string) $invoice->totals?->totalWithVat);
        self::assertSame('De Koksmaat', $invoice->seller->name);
        self::assertCount(20, $invoice->lines);

        $return = $invoice->lines[19];
        self::assertSame('6', (string) $return->quantity);
        self::assertSame('-109.98', (string) $return->netAmount);
    }

    public function test_xrechnung_example_reads_case_insensitive_and_empty_values(): void
    {
        $invoice = $this->readOfficial('XRechnung-O.xml')->invoice;

        self::assertSame('VATEX-EU-132-1A', $invoice->vatBreakdown[0]->exemptionReasonCode?->value, 'Lower-case VATEX is valid in the official artefacts.');
        self::assertNull($invoice->buyer->legalRegistration, 'An empty ram:ID is absent.');
        self::assertNull($invoice->buyer->electronicAddress, 'An empty ram:URIID is absent.');
        self::assertSame('50028642477', $invoice->lines[0]->objectIdentifier?->value, 'A line reference without type code is BT-128, as in UBL.');
    }

    public function test_unknown_and_extension_elements_are_ignored_and_reported(): void
    {
        $xml = self::contents(self::officialPath('CII_example1.xml'));
        $xml = str_replace(
            '<rsm:ExchangedDocument>',
            '<x:Extension xmlns:x="urn:example"><x:Any>1</x:Any></x:Extension><rsm:ExchangedDocument>',
            $xml,
        );
        $xml = str_replace('<ram:InvoiceCurrencyCode>', '<ram:FutureElement>new</ram:FutureElement><ram:InvoiceCurrencyCode>', $xml);
        $xml = str_replace('<ram:LineID>1</ram:LineID>', '<ram:LineID>1</ram:LineID><ram:LineStatusCode>ZZ</ram:LineStatusCode>', $xml);

        $result = (new CiiReader)->read($xml);

        self::assertSame('12115118', $result->invoice->number);
        self::assertSame([
            '/rsm:CrossIndustryInvoice/x:Extension',
            '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem[1]/ram:AssociatedDocumentLineDocument/ram:LineStatusCode',
            '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:FutureElement',
        ], $result->ignored);
    }

    public function test_maps_every_business_term_of_the_binding(): void
    {
        $result = (new CiiReader)->read(self::contents(self::FIXTURES.'/all-terms-invoice.xml'));
        $invoice = $result->invoice;

        self::assertSame([], $result->ignored);
        self::assertSame('INV-2026-001', $invoice->number);
        self::assertSame('35', $invoice->taxPointDateCode, 'CII due date type code 29 is UNTDID 2005 code 35.');
        self::assertSame('PRJ-11', $invoice->projectReference);
        self::assertSame('TENDER-17', $invoice->tenderOrLotReference);
        self::assertSame('OBJ-18', $invoice->invoicedObjectIdentifier?->value);
        self::assertSame('AAA', $invoice->invoicedObjectIdentifier->scheme);
        self::assertSame('AAI', $invoice->notes[0]->subjectCode?->value);
        self::assertSame("  Plain note\nsecond line  ", $invoice->notes[1]->text);
        self::assertSame('DE98ZZZ09999999999', $invoice->payment?->directDebit?->creditorIdentifier);
        self::assertSame('MANDATE-89', $invoice->payment->directDebit->mandateReference);
        self::assertSame('10260', (string) $invoice->totals?->totalVatInAccountingCurrency);
        self::assertSame('LINE-OBJ-128', $invoice->lines[0]->objectIdentifier?->value);
        self::assertSame('ABZ', $invoice->lines[0]->objectIdentifier->scheme);
        self::assertSame('5.00', (string) $invoice->lines[0]->price->discount);
        self::assertSame('45.00', (string) $invoice->lines[0]->price->grossPrice);
    }

    /**
     * The all-terms fixtures hold the same invoice in both syntaxes; only the differences that the
     * bindings impose may remain.
     */
    public function test_all_terms_invoice_is_the_same_in_both_syntaxes(): void
    {
        $ubl = (new UblReader)->read(self::contents(self::UBL_FIXTURES.'/all-terms-invoice.xml'))->invoice;
        $cii = (new CiiReader)->read(self::contents(self::FIXTURES.'/all-terms-invoice.xml'))->invoice;

        self::assertSameApartFrom(self::ALL_TERMS_SYNTAX_DIFFERENCES, InvoiceSnapshot::of($ubl), InvoiceSnapshot::of($cii));
    }

    /**
     * The CEN UBL and CII examples 1 to 9 were written separately and none of them is the same
     * invoice in both syntaxes. These pairs differ only in a few values, all of them in the files
     * themselves (listed below with the reason); no difference comes from the bindings. Pairs 2, 3
     * and 5 differ in substance (line count, prices, base quantities, documents, payment means)
     * and are not compared; the all-terms test above covers every business term instead.
     *
     * A pattern key (starting with "#") applies to every matching path.
     *
     * @return array<string, array{string, string, array<string, array{scalar|null, scalar|null}|string>}>
     */
    public static function examplePairs(): array
    {
        // The UBL files use unit code EA ("each"), the CII files C62 ("one") or H87 ("piece").
        $eachVersusOne = ['#^lines\.\d+\.unit$#' => ['EA', 'C62']];
        $eachVersusPiece = ['#^lines\.\d+\.unit$#' => ['EA', 'H87']];

        return [
            'example 1' => ['ubl-tc434-example1.xml', 'CII_example1.xml', [
                ...$eachVersusPiece,
                // Item names typed differently.
                'lines.1.item.name' => ['PKAAS 50PL. JONG BEL. 1KG', 'KAAS 50PL. JONG BEL. 1KG'],
                'lines.12.item.name' => ['PK CHOCOLADEMEL', 'PK CHOCOLADEMELK '],
                'lines.14.item.name' => ['STATIEGELD', 'STATIEGELD '],
                // The UBL note ends in "##Delivery terms" instead of a "#AAR#" prefix; the CII
                // note carries subject code AAR and a final full stop.
                'notes.0.subjectCode' => [null, 'AAR'],
                'notes.0.text' => self::DIFFERENT_TEXT,
                // The second UBL account is another IBAN; CII repeats the first one.
                'payment.creditTransfers.1.accountIdentifier' => ['NL03 INGB 0004489902', 'NL57 RABO 0107307510'],
            ]],
            'example 4' => ['ubl-tc434-example4.xml', 'CII_example4.xml', [
                ...$eachVersusOne,
                'seller.contact.email' => ['antonio@SubscriptionsSeller.dk', 'Anthon@SellerCompany.dk'],
                // CII repeats the payment means with the same account.
                'payment.creditTransfers.1.accountIdentifier' => [null, 'DK1212341234123412'],
            ]],
            'example 6' => ['ubl-tc434-example6.xml', 'CII_example6.xml', $eachVersusOne],
            'example 7' => ['ubl-tc434-example7.xml', 'CII_example7.xml', [
                ...$eachVersusOne,
                'buyer.contact.name' => ['A3150bdn', '3150bdn'],
                'issueDate' => ['2013-03-11', '2013-05-13'],
                // CII repeats the payment means with the same account.
                'payment.creditTransfers.1.accountIdentifier' => [null, 'SE1212341234123412'],
                // The CII file has no ram:TaxTotalAmount; UBL states 0.00.
                'totals.totalVat' => ['0', null],
            ]],
            'example 8' => ['ubl-tc434-example8.xml', 'CII_example8.xml', [
                // UBL: supporting document "ATS" without a type code; CII: type code 130 (BT-18).
                'additionalDocuments.0.description' => ['ATS', null],
                'additionalDocuments.0.reference' => ['871694831000290806', null],
                'invoicedObjectIdentifier.value' => [null, '871694831000290806'],
                // The CII file gives price base quantities that differ from the UBL ones, and
                // kilowatt as KWT where UBL has KW.
                'lines.0.price.baseQuantity' => ['1', '0.0088'],
                'lines.1.price.baseQuantity' => ['1', '0.00101'],
                'lines.2.price.baseQuantity' => ['12', '15.24'],
                'lines.2.price.baseQuantityUnit' => ['KW', 'KWT'],
                'lines.2.unit' => ['KW', 'KWT'],
                'lines.3.price.baseQuantity' => ['1', '1.53'],
                'lines.3.price.baseQuantityUnit' => ['KW', 'KWT'],
                'lines.3.unit' => ['KW', 'KWT'],
                'lines.4.price.baseQuantity' => ['12', '441'],
                'lines.5.price.baseQuantity' => ['12', '678'],
                'lines.6.price.baseQuantity' => ['1', '83.34'],
                'lines.7.price.baseQuantity' => ['1', '190.31'],
                'lines.8.price.baseQuantity' => ['1', '64.21'],
                'lines.9.price.baseQuantity' => ['1', '64.46'],
                // Free text indented differently.
                'notes.0.text' => self::LAYOUT_ONLY,
                'paymentTerms' => self::LAYOUT_ONLY,
                // CII repeats the payment means with the same account.
                'payment.creditTransfers.1.accountIdentifier' => [null, 'NL28RBOS0420242228'],
                'taxPointDate' => ['2013-06-30', null],
            ]],
            'example 9' => ['ubl-tc434-example9.xml', 'CII_example9.xml', [
                // UBL has an invoicing period, CII a one-day line period instead.
                'delivery.invoicingPeriod.end' => ['2016-06-30', null],
                'delivery.invoicingPeriod.start' => ['2016-04-01', null],
                'lines.0.period.end' => [null, '2016-04-01'],
                'lines.0.period.start' => [null, '2016-04-01'],
                'dueDate' => ['2015-04-14', '2015-04-15'],
                'lines.0.price.baseQuantity' => ['1', '49'],
                // Free text indented differently.
                'notes.0.text' => self::LAYOUT_ONLY,
                'seller.contact.phone' => self::LAYOUT_ONLY,
                // CII repeats the payment means with the same account.
                'payment.creditTransfers.1.accountIdentifier' => [null, 'NL13RABO0377815500'],
            ]],
        ];
    }

    /**
     * Where CEN ships the same example in UBL and CII, both readers produce the same model apart
     * from the listed differences of the files themselves.
     *
     * Amounts and quantities are compared by value, so "19.9" equals "19.90".
     *
     * @param  array<string, array{scalar|null, scalar|null}|string>  $differences
     */
    #[DataProvider('examplePairs')]
    public function test_same_invoice_from_both_syntaxes(string $ublFile, string $ciiFile, array $differences): void
    {
        $ubl = (new UblReader)->read(self::contents(self::officialPath($ublFile, 'ubl')));
        $cii = (new CiiReader)->read(self::contents(self::officialPath($ciiFile)));

        self::assertSame([], $ubl->ignored);
        self::assertSame([], $cii->ignored);
        self::assertSameApartFrom($differences, InvoiceSnapshot::of($ubl->invoice), InvoiceSnapshot::of($cii->invoice));
    }

    public function test_tax_point_date_codes_are_translated_to_untdid_2005(): void
    {
        foreach (['5' => '3', '29' => '35', '72' => '432'] as $cii => $model) {
            $xml = str_replace('<ram:DueDateTypeCode>29</ram:DueDateTypeCode>', "<ram:DueDateTypeCode>{$cii}</ram:DueDateTypeCode>", self::contents(self::FIXTURES.'/all-terms-invoice.xml'));

            $result = (new CiiReader)->read($xml);

            self::assertSame($model, $result->invoice->taxPointDateCode);
            self::assertSame([], $result->ignored);
        }
    }

    public function test_conflicting_tax_point_date_codes_keep_the_first_and_report_the_rest(): void
    {
        $xml = preg_replace('#<ram:DueDateTypeCode>29</ram:DueDateTypeCode>(\s*<ram:RateApplicablePercent>0<)#', '<ram:DueDateTypeCode>5</ram:DueDateTypeCode>$1', self::contents(self::FIXTURES.'/all-terms-invoice.xml'));
        self::assertIsString($xml);

        $result = (new CiiReader)->read($xml);

        self::assertSame('35', $result->invoice->taxPointDateCode);
        self::assertSame(['/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax[2]/ram:DueDateTypeCode'], $result->ignored);
    }

    public function test_invalid_optional_codes_become_null_and_are_reported(): void
    {
        $xml = strtr(self::contents(self::FIXTURES.'/all-terms-invoice.xml'), [
            '<ram:SubjectCode>AAI</ram:SubjectCode>' => '<ram:SubjectCode>XYZ</ram:SubjectCode>',
            'schemeID="9920"' => 'schemeID="XX99"',
            '<ram:ReasonCode>95</ram:ReasonCode>
                <ram:Reason>Discount</ram:Reason>' => '<ram:ReasonCode>ZZ</ram:ReasonCode>
                <ram:Reason>Discount</ram:Reason>',
            'VATEX-EU-132' => 'VATEX-NOPE',
            '<ram:TaxCurrencyCode>HUF</ram:TaxCurrencyCode>' => '<ram:TaxCurrencyCode>XXQ</ram:TaxCurrencyCode>',
            '<ram:BasisQuantity unitCode="H87">1</ram:BasisQuantity>
                </ram:NetPriceProductTradePrice>' => '<ram:BasisQuantity unitCode="NOPE">1</ram:BasisQuantity>
                </ram:NetPriceProductTradePrice>',
            'mimeCode="text/csv"' => 'mimeCode="application/x-evil"',
            '<ram:ID>DE</ram:ID>' => '<ram:ID>QQ</ram:ID>',
            '<ram:DueDateTypeCode>29</ram:DueDateTypeCode>' => '<ram:DueDateTypeCode>35</ram:DueDateTypeCode>',
        ]);

        $result = (new CiiReader)->read($xml);
        $invoice = $result->invoice;

        self::assertNull($invoice->notes[0]->subjectCode);
        self::assertSame('Árvíztűrő tükörfúrógép & co', $invoice->notes[0]->text);
        self::assertNull($invoice->seller->electronicAddress);
        self::assertNull($invoice->allowances[0]->reasonCode);
        self::assertNull($invoice->vatBreakdown[1]->exemptionReasonCode);
        self::assertNull($invoice->taxCurrency);
        self::assertNull($invoice->lines[0]->price->baseQuantityUnit);
        self::assertNull($invoice->additionalDocuments[0]->attachment);
        self::assertNull($invoice->lines[0]->item->originCountry);
        self::assertNull($invoice->taxPointDateCode);

        $settlement = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement';
        $agreement = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement';
        $line = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem';
        foreach ([
            "/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IncludedNote[1]/ram:SubjectCode (invalid code 'XYZ')",
            "{$agreement}/ram:SellerTradeParty/ram:URIUniversalCommunication/ram:URIID/@schemeID (invalid code 'XX99')",
            "{$settlement}/ram:SpecifiedTradeAllowanceCharge[1]/ram:ReasonCode (invalid code 'ZZ')",
            "{$settlement}/ram:ApplicableTradeTax[2]/ram:ExemptionReasonCode (invalid code 'VATEX-NOPE')",
            "{$settlement}/ram:TaxCurrencyCode (invalid code 'XXQ')",
            "{$line}/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity/@unitCode (invalid code 'NOPE')",
            "{$agreement}/ram:AdditionalReferencedDocument[2]/ram:AttachmentBinaryObject/@mimeCode (invalid code 'application/x-evil')",
            "{$line}/ram:SpecifiedTradeProduct/ram:OriginTradeCountry/ram:ID (invalid code 'QQ')",
            "{$settlement}/ram:ApplicableTradeTax[1]/ram:DueDateTypeCode (invalid code '35')",
        ] as $expected) {
            self::assertContains($expected, $result->ignored);
        }
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidRequiredCodes(): array
    {
        return [
            'BT-3' => [
                '<ram:TypeCode>380</ram:TypeCode>',
                '<ram:TypeCode>999</ram:TypeCode>',
                "Invalid code '999' for required BT-3 at /rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode.",
            ],
            'BT-5' => [
                '<ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>',
                '<ram:InvoiceCurrencyCode>EURO</ram:InvoiceCurrencyCode>',
                "Invalid code 'EURO' for required BT-5 at /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode.",
            ],
            'BT-130' => [
                'unitCode="H87">2.5000',
                'unitCode="BOGUS">2.5000',
                "Invalid code 'BOGUS' for required BT-130 at /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeDelivery/ram:BilledQuantity/@unitCode.",
            ],
            'BT-151' => [
                '<ram:CategoryCode>S</ram:CategoryCode>
                    <ram:RateApplicablePercent>27</ram:RateApplicablePercent>
                </ram:ApplicableTradeTax>
                <ram:BillingSpecifiedPeriod>',
                '<ram:CategoryCode>Q</ram:CategoryCode>
                    <ram:RateApplicablePercent>27</ram:RateApplicablePercent>
                </ram:ApplicableTradeTax>
                <ram:BillingSpecifiedPeriod>',
                "Invalid code 'Q' for required BT-151 at /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode.",
            ],
            'BT-3 missing' => [
                '<ram:TypeCode>380</ram:TypeCode>',
                '',
                'Missing required BT-3 (rsm:ExchangedDocument/ram:TypeCode) in /rsm:CrossIndustryInvoice.',
            ],
        ];
    }

    #[DataProvider('invalidRequiredCodes')]
    public function test_invalid_or_missing_required_code_is_unsupported(string $search, string $replace, string $message): void
    {
        $xml = self::contents(self::FIXTURES.'/all-terms-invoice.xml');
        self::assertStringContainsString($search, $xml);

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage($message);

        (new CiiReader)->read(str_replace($search, $replace, $xml));
    }

    public function test_date_in_another_format_is_reported(): void
    {
        $xml = str_replace(
            '<udt:DateTimeString format="102">20261015</udt:DateTimeString>',
            '<udt:DateTimeString format="203">202610151200</udt:DateTimeString>',
            self::contents(self::FIXTURES.'/all-terms-invoice.xml'),
        );

        $result = (new CiiReader)->read($xml);

        self::assertNull($result->invoice->delivery?->actualDeliveryDate);
        self::assertSame(
            ["/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString (invalid date '202610151200' in format '203')"],
            $result->ignored,
        );
    }

    public function test_invalid_issue_date_is_unsupported(): void
    {
        $xml = str_replace('format="102">20261102<', 'format="102">20261302<', self::contents(self::FIXTURES.'/all-terms-invoice.xml'));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage("Invalid date '20261302' for BT-2 at /rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString.");

        (new CiiReader)->read($xml);
    }

    public function test_credit_note_type_code_reads_as_credit_note(): void
    {
        $xml = str_replace('<ram:TypeCode>380</ram:TypeCode>', '<ram:TypeCode>381</ram:TypeCode>', self::contents(self::FIXTURES.'/all-terms-invoice.xml'));

        $result = (new CiiReader)->read($xml);

        self::assertTrue($result->invoice->type->isCreditNote());
        self::assertSame('2026-12-02', $result->invoice->dueDate?->format('Y-m-d'));
        self::assertSame([], $result->ignored);
    }

    public function test_further_payment_means_add_credit_transfers_or_are_reported(): void
    {
        $extra = '<ram:SpecifiedTradeSettlementPaymentMeans><ram:TypeCode>59</ram:TypeCode><ram:PayeePartyCreditorFinancialAccount><ram:ProprietaryID>SECOND-ACCOUNT</ram:ProprietaryID></ram:PayeePartyCreditorFinancialAccount></ram:SpecifiedTradeSettlementPaymentMeans>'
            .'<ram:SpecifiedTradeSettlementPaymentMeans><ram:TypeCode>31</ram:TypeCode><ram:PayeePartyCreditorFinancialAccount><ram:IBANID>OTHER-MEANS</ram:IBANID></ram:PayeePartyCreditorFinancialAccount></ram:SpecifiedTradeSettlementPaymentMeans>';
        $xml = str_replace('<ram:ApplicableTradeTax>
                <ram:CalculatedAmount>25.65', $extra.'<ram:ApplicableTradeTax>
                <ram:CalculatedAmount>25.65', self::contents(self::FIXTURES.'/all-terms-invoice.xml'));

        $result = (new CiiReader)->read($xml);
        $transfers = $result->invoice->payment?->creditTransfers;

        self::assertNotNull($transfers);
        self::assertCount(2, $transfers);
        self::assertSame('SECOND-ACCOUNT', $transfers[1]->accountIdentifier);
        self::assertSame(['/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[3]'], $result->ignored);
    }

    public function test_gross_price_base_quantity_is_used_when_the_net_price_has_none(): void
    {
        $xml = str_replace('<ram:ChargeAmount>40.00</ram:ChargeAmount>
                    <ram:BasisQuantity unitCode="H87">1</ram:BasisQuantity>', '<ram:ChargeAmount>40.00</ram:ChargeAmount>', self::contents(self::FIXTURES.'/all-terms-invoice.xml'));

        $result = (new CiiReader)->read($xml);

        self::assertSame('1', (string) $result->invoice->lines[0]->price->baseQuantity);
        self::assertSame('H87', $result->invoice->lines[0]->price->baseQuantityUnit?->value);
        self::assertSame([], $result->ignored);
    }

    public function test_conflicting_gross_price_base_quantity_is_reported(): void
    {
        $xml = str_replace('<ram:BasisQuantity unitCode="H87">1</ram:BasisQuantity>
                    <ram:AppliedTradeAllowanceCharge>', '<ram:BasisQuantity unitCode="H87">10</ram:BasisQuantity>
                    <ram:AppliedTradeAllowanceCharge>', self::contents(self::FIXTURES.'/all-terms-invoice.xml'));

        $result = (new CiiReader)->read($xml);

        self::assertSame('1', (string) $result->invoice->lines[0]->price->baseQuantity);
        self::assertSame(['/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice/ram:BasisQuantity'], $result->ignored);
    }

    public function test_reads_large_invoice_in_linear_time(): void
    {
        $reader = new CiiReader;
        $reader->read(self::invoiceWithLines(100));

        $started = hrtime(true);
        $result = $reader->read(self::invoiceWithLines(5000));
        $fiveThousand = (hrtime(true) - $started) / 1e9;

        $started = hrtime(true);
        $reader->read(self::invoiceWithLines(10000));
        $tenThousand = (hrtime(true) - $started) / 1e9;

        self::assertCount(5000, $result->invoice->lines);
        self::assertLessThan(2.0, $fiveThousand, sprintf('5,000 lines took %.2f s.', $fiveThousand));
        self::assertLessThan(3 * $fiveThousand, $tenThousand, sprintf(
            'Doubling the lines grew the time from %.2f s to %.2f s.',
            $fiveThousand,
            $tenThousand,
        ));
    }

    public function test_unknown_root_is_unsupported(): void
    {
        $this->expectException(UnsupportedDocument::class);

        (new CiiReader)->read('<rsm:CrossIndustryOrder xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryOrder:100"/>');
    }

    public function test_ubl_document_is_unsupported(): void
    {
        $this->expectException(UnsupportedDocument::class);

        (new CiiReader)->read(self::contents(self::UBL_FIXTURES.'/all-terms-invoice.xml'));
    }

    public function test_missing_invoice_number_is_unsupported(): void
    {
        $xml = str_replace('<ram:ID>INV-2026-001</ram:ID>', '', self::contents(self::FIXTURES.'/all-terms-invoice.xml'));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('Missing required BT-1 (rsm:ExchangedDocument/ram:ID) in /rsm:CrossIndustryInvoice.');

        (new CiiReader)->read($xml);
    }

    /**
     * Asserts that the snapshots are equal once the listed differences are taken out; every listed
     * difference must still be there, so the list cannot go stale.
     *
     * @param  array<string, array{scalar|null, scalar|null}|string>  $differences
     * @param  array<string, scalar>  $ubl
     * @param  array<string, scalar>  $cii
     */
    private static function assertSameApartFrom(array $differences, array $ubl, array $cii): void
    {
        foreach ($differences as $key => $expected) {
            $paths = str_starts_with($key, '#')
                ? array_values(array_filter(array_keys($ubl + $cii), static fn (string $path): bool => preg_match($key, $path) === 1))
                : [$key];
            self::assertNotSame([], $paths, "Listed difference {$key} matches nothing.");

            foreach ($paths as $path) {
                $ublValue = $ubl[$path] ?? null;
                $ciiValue = $cii[$path] ?? null;
                if ($expected === self::LAYOUT_ONLY) {
                    self::assertIsString($ublValue);
                    self::assertIsString($ciiValue);
                    self::assertNotSame($ublValue, $ciiValue, "Listed difference {$path} is gone.");
                    self::assertSame(self::collapse($ublValue), self::collapse($ciiValue), "{$path} differs in more than layout.");
                } elseif ($expected === self::DIFFERENT_TEXT) {
                    self::assertNotSame($ublValue, $ciiValue, "Listed difference {$path} is gone.");
                } else {
                    self::assertSame($expected, [$ublValue, $ciiValue], "Listed difference {$path}.");
                }
                unset($ubl[$path], $cii[$path]);
            }
        }

        self::assertSame($ubl, $cii);
    }

    private static function collapse(string $text): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($text));
    }

    private function readOfficial(string $name): ReadResult
    {
        return (new CiiReader)->read(self::contents(self::officialPath($name)));
    }

    /**
     * CII_example1 with its first line repeated to the given count, built in memory.
     */
    private static function invoiceWithLines(int $count): string
    {
        $xml = self::contents(self::officialPath('CII_example1.xml'));
        $open = '<ram:IncludedSupplyChainTradeLineItem>';
        $close = '</ram:IncludedSupplyChainTradeLineItem>';
        $start = strpos($xml, $open);
        $end = strpos($xml, $close);
        $last = strrpos($xml, $close);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        self::assertNotFalse($last);
        $line = substr($xml, $start, $end + strlen($close) - $start);

        return substr($xml, 0, $start).str_repeat($line, $count).substr($xml, $last + strlen($close));
    }

    private static function officialPath(string $name, string $syntax = 'cii'): string
    {
        foreach (Fixtures::official($syntax) as $file) {
            if (basename($file) === $name) {
                return $file;
            }
        }

        self::fail("Official example {$name} not found.");
    }

    private static function contents(string $file): string
    {
        $contents = file_get_contents($file);
        self::assertIsString($contents);

        return $contents;
    }
}
