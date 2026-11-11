<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Ubl;

use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Syntax\ReadResult;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\HostileXml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UblReaderTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../fixtures/ubl';

    /**
     * @return array<string, array{string}>
     */
    public static function hostile(): array
    {
        return HostileXml::cases();
    }

    #[DataProvider('hostile')]
    public function test_refuses_hostile_xml(string $xml): void
    {
        $started = hrtime(true);

        try {
            (new UblReader)->read($xml);
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
        foreach (Fixtures::official('ubl') as $file) {
            $cases[basename($file)] = [$file];
        }

        return $cases;
    }

    #[DataProvider('officialExamples')]
    public function test_reads_every_official_example(string $file): void
    {
        $xml = self::contents($file);
        $result = (new UblReader)->read($xml);

        $document = new DOMDocument;
        $document->loadXML($xml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        self::assertSame($xpath->evaluate('string(/*/cbc:ID)'), $result->invoice->number);
        $lineCount = $xpath->evaluate('count(/*/cac:InvoiceLine | /*/cac:CreditNoteLine)');
        self::assertIsFloat($lineCount);
        self::assertSame((int) $lineCount, count($result->invoice->lines));
        self::assertSame([], $result->ignored, 'Every element of an official example maps to a business term.');
    }

    public function test_guide_example1_values(): void
    {
        $invoice = $this->readOfficial('guide-example1.xml')->invoice;

        self::assertSame('12115118', $invoice->number);
        self::assertSame('2015-01-09', $invoice->issueDate->format('Y-m-d'));
        self::assertSame('EUR', $invoice->currency->value);
        self::assertSame('De Koksmaat', $invoice->seller->name);
        self::assertCount(20, $invoice->lines);
        self::assertSame('250.33', (string) $invoice->totals?->totalWithVat);

        $return = $invoice->lines[19];
        self::assertSame('6', (string) $return->quantity);
        self::assertSame('-109.98', (string) $return->netAmount);
    }

    public function test_unknown_and_extension_elements_are_ignored_and_reported(): void
    {
        $xml = self::contents(self::officialPath('guide-example1.xml'));
        $xml = str_replace(
            '<cbc:CustomizationID>',
            '<ext:UBLExtensions xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"><ext:UBLExtension><ext:ExtensionContent><x:Any xmlns:x="urn:example">1</x:Any></ext:ExtensionContent></ext:UBLExtension></ext:UBLExtensions><cbc:CustomizationID>',
            $xml,
        );
        $xml = str_replace('<cbc:DocumentCurrencyCode>', '<cbc:FutureElement>new</cbc:FutureElement><cbc:DocumentCurrencyCode>', $xml);

        $result = (new UblReader)->read($xml);

        self::assertSame('12115118', $result->invoice->number);
        self::assertContains('/Invoice/ext:UBLExtensions', $result->ignored);
        self::assertContains('/Invoice/cbc:FutureElement', $result->ignored);
    }

    public function test_maps_every_business_term_of_the_binding(): void
    {
        $result = (new UblReader)->read(self::contents(self::FIXTURES.'/all-terms-invoice.xml'));
        $invoice = $result->invoice;

        self::assertSame(['/Invoice/cac:PaymentMeans/cac:CardAccount/cbc:NetworkID'], $result->ignored);

        self::assertSame('INV-2026-001', $invoice->number);
        self::assertSame('2026-11-02', $invoice->issueDate->format('Y-m-d'));
        self::assertSame('380', $invoice->type->value);
        self::assertSame('EUR', $invoice->currency->value);
        self::assertSame('HUF', $invoice->taxCurrency?->value);
        self::assertSame('2026-10-31', $invoice->taxPointDate?->format('Y-m-d'));
        self::assertSame('35', $invoice->taxPointDateCode);
        self::assertSame('2026-12-02', $invoice->dueDate?->format('Y-m-d'));
        self::assertSame('BR-10', $invoice->buyerReference);
        self::assertSame('PRJ-11', $invoice->projectReference);
        self::assertSame('CON-12', $invoice->contractReference);
        self::assertSame('PO-13', $invoice->purchaseOrderReference);
        self::assertSame('SO-14', $invoice->salesOrderReference);
        self::assertSame('REC-15', $invoice->receivingAdviceReference);
        self::assertSame('DESP-16', $invoice->despatchAdviceReference);
        self::assertSame('TENDER-17', $invoice->tenderOrLotReference);
        self::assertSame('OBJ-18', $invoice->invoicedObjectIdentifier?->value);
        self::assertSame('AAA', $invoice->invoicedObjectIdentifier->scheme);
        self::assertSame('ACC-19', $invoice->buyerAccountingReference);
        self::assertSame('Net 30', $invoice->paymentTerms);

        self::assertCount(2, $invoice->notes);
        self::assertSame('AAI', $invoice->notes[0]->subjectCode?->value);
        self::assertSame('Árvíztűrő tükörfúrógép & co', $invoice->notes[0]->text);
        self::assertNull($invoice->notes[1]->subjectCode);
        self::assertSame("  Plain note\nsecond line  ", $invoice->notes[1]->text);

        self::assertSame('urn:cen.eu:en16931:2017', $invoice->processControl?->specificationIdentifier);
        self::assertSame('urn:fdc:peppol.eu:2017:poacc:billing:01:1.0', $invoice->processControl->businessProcessType);

        self::assertCount(1, $invoice->precedingInvoices);
        self::assertSame('INV-2026-000', $invoice->precedingInvoices[0]->number);
        self::assertSame('2026-09-30', $invoice->precedingInvoices[0]->issueDate?->format('Y-m-d'));

        $this->assertSeller($invoice);
        $this->assertBuyer($invoice);
        $this->assertOtherParties($invoice);
        $this->assertDelivery($invoice);
        $this->assertPayment($invoice);
        $this->assertDocumentAllowancesAndCharges($invoice);
        $this->assertTotalsAndVat($invoice);
        $this->assertAdditionalDocuments($invoice);
        $this->assertLine($invoice);
    }

    public function test_credit_note_specific_bindings(): void
    {
        $result = (new UblReader)->read(self::contents(self::FIXTURES.'/creditnote-specifics.xml'));
        $invoice = $result->invoice;

        self::assertSame([], $result->ignored);
        self::assertSame('381', $invoice->type->value);
        self::assertTrue($invoice->type->isCreditNote());
        self::assertSame('2026-12-03', $invoice->dueDate?->format('Y-m-d'));
        self::assertSame('PRJ-CN', $invoice->projectReference);
        self::assertSame('OBJ-CN', $invoice->invoicedObjectIdentifier?->value);
        self::assertCount(1, $invoice->additionalDocuments);
        self::assertSame('DOC-CN', $invoice->additionalDocuments[0]->reference);
        self::assertSame('1', (string) $invoice->lines[0]->quantity);
        self::assertSame('C62', $invoice->lines[0]->unit->value);
    }

    public function test_official_credit_note_reads(): void
    {
        $invoice = $this->readOfficial('ubl-tc434-creditnote1.xml')->invoice;

        self::assertSame('018304 / 28865', $invoice->number);
        self::assertTrue($invoice->type->isCreditNote());
    }

    public function test_invoice_project_reference_with_type_50_is_a_supporting_document(): void
    {
        $xml = str_replace(
            '<cac:ProjectReference>',
            '<cac:AdditionalDocumentReference><cbc:ID>FIFTY</cbc:ID><cbc:DocumentTypeCode>50</cbc:DocumentTypeCode></cac:AdditionalDocumentReference><cac:ProjectReference>',
            self::contents(self::FIXTURES.'/all-terms-invoice.xml'),
        );

        $result = (new UblReader)->read($xml);

        self::assertSame('PRJ-11', $result->invoice->projectReference);
        self::assertSame('FIFTY', $result->invoice->additionalDocuments[1]->reference);
        self::assertContains('/Invoice/cac:AdditionalDocumentReference[3]/cbc:DocumentTypeCode', $result->ignored);
    }

    public function test_invalid_optional_codes_become_null_and_are_reported(): void
    {
        $xml = strtr(self::contents(self::FIXTURES.'/all-terms-invoice.xml'), [
            '#AAI#' => '#XYZ#',
            'schemeID="9920"' => 'schemeID="XX99"',
            '<cbc:AllowanceChargeReasonCode>95</cbc:AllowanceChargeReasonCode>
        <cbc:AllowanceChargeReason>Discount</cbc:AllowanceChargeReason>' => '<cbc:AllowanceChargeReasonCode>ZZ</cbc:AllowanceChargeReasonCode>
        <cbc:AllowanceChargeReason>Discount</cbc:AllowanceChargeReason>',
            'VATEX-EU-132' => 'VATEX-NOPE',
            '<cbc:TaxCurrencyCode>HUF</cbc:TaxCurrencyCode>' => '<cbc:TaxCurrencyCode>XXQ</cbc:TaxCurrencyCode>',
            '<cbc:BaseQuantity unitCode="H87">' => '<cbc:BaseQuantity unitCode="NOPE">',
            'mimeCode="text/csv"' => 'mimeCode="application/x-evil"',
            '<cbc:IdentificationCode>DE</cbc:IdentificationCode>' => '<cbc:IdentificationCode>QQ</cbc:IdentificationCode>',
        ]);

        $result = (new UblReader)->read($xml);
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

        foreach ([
            "/Invoice/cbc:Note[1] (invalid code 'XYZ')",
            "/Invoice/cac:AccountingSupplierParty/cac:Party/cbc:EndpointID/@schemeID (invalid code 'XX99')",
            "/Invoice/cac:AllowanceCharge[1]/cbc:AllowanceChargeReasonCode (invalid code 'ZZ')",
            "/Invoice/cac:TaxTotal[1]/cac:TaxSubtotal[2]/cac:TaxCategory/cbc:TaxExemptionReasonCode (invalid code 'VATEX-NOPE')",
            "/Invoice/cbc:TaxCurrencyCode (invalid code 'XXQ')",
            "/Invoice/cac:InvoiceLine/cac:Price/cbc:BaseQuantity/@unitCode (invalid code 'NOPE')",
            "/Invoice/cac:AdditionalDocumentReference[2]/cac:Attachment/cbc:EmbeddedDocumentBinaryObject/@mimeCode (invalid code 'application/x-evil')",
            "/Invoice/cac:InvoiceLine/cac:Item/cac:OriginCountry/cbc:IdentificationCode (invalid code 'QQ')",
        ] as $expected) {
            self::assertContains($expected, $result->ignored);
        }
    }

    public function test_exemption_reason_code_is_case_insensitive(): void
    {
        $xml = str_replace('VATEX-EU-132', 'vatex-eu-132', self::contents(self::FIXTURES.'/all-terms-invoice.xml'));

        $result = (new UblReader)->read($xml);

        self::assertSame('VATEX-EU-132', $result->invoice->vatBreakdown[1]->exemptionReasonCode?->value);
        self::assertSame(['/Invoice/cac:PaymentMeans/cac:CardAccount/cbc:NetworkID'], $result->ignored);
    }

    public function test_empty_identifier_and_endpoint_are_absent_and_not_reported(): void
    {
        $xml = strtr(self::contents(self::FIXTURES.'/all-terms-invoice.xml'), [
            '<cbc:EndpointID schemeID="9920">ESB12345678</cbc:EndpointID>' => '<cbc:EndpointID schemeID="9920"/>',
            '<cbc:CompanyID schemeID="0088">FN123456</cbc:CompanyID>' => '<cbc:CompanyID/>',
        ]);

        $result = (new UblReader)->read($xml);

        self::assertNull($result->invoice->seller->electronicAddress);
        self::assertNull($result->invoice->buyer->legalRegistration);
        self::assertSame(['/Invoice/cac:PaymentMeans/cac:CardAccount/cbc:NetworkID'], $result->ignored);
    }

    public function test_invalid_country_drops_the_address_and_reports_it(): void
    {
        $xml = str_replace(
            '<cbc:IdentificationCode>HU</cbc:IdentificationCode>',
            '<cbc:IdentificationCode>XX</cbc:IdentificationCode>',
            self::contents(self::FIXTURES.'/all-terms-invoice.xml'),
        );

        $result = (new UblReader)->read($xml);

        self::assertNull($result->invoice->seller->address);
        self::assertContains("/Invoice/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode (invalid code 'XX')", $result->ignored);
        self::assertContains('/Invoice/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress (dropped: no valid country code)', $result->ignored);
    }

    public function test_invalid_required_code_is_unsupported(): void
    {
        $xml = str_replace('unitCode="H87">2.5000', 'unitCode="BOGUS">2.5000', self::contents(self::FIXTURES.'/all-terms-invoice.xml'));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage("Invalid code 'BOGUS' for required BT-130 at /Invoice/cac:InvoiceLine/cbc:InvoicedQuantity/@unitCode.");

        (new UblReader)->read($xml);
    }

    public function test_sepa_identifier_on_payee_maps_to_bt_90(): void
    {
        $xml = strtr(self::contents(self::FIXTURES.'/all-terms-invoice.xml'), [
            '<cac:PartyIdentification>
                <cbc:ID schemeID="SEPA">DE98ZZZ09999999999</cbc:ID>
            </cac:PartyIdentification>' => '',
            '<cbc:ID schemeID="0088">5790000435111</cbc:ID>' => '<cbc:ID schemeID="SEPA">DE11ZZZ00000000001</cbc:ID>',
        ]);

        $invoice = (new UblReader)->read($xml)->invoice;

        self::assertSame('DE11ZZZ00000000001', $invoice->payment?->directDebit?->creditorIdentifier);
        self::assertSame([], $invoice->payee?->identifiers);
    }

    public function test_further_payment_means_add_credit_transfers_or_are_reported(): void
    {
        $extra = '<cac:PaymentMeans><cbc:PaymentMeansCode name="SEPA direct debit">59</cbc:PaymentMeansCode><cbc:PaymentID>REMIT-83</cbc:PaymentID><cac:PayeeFinancialAccount><cbc:ID>SECOND-IBAN</cbc:ID></cac:PayeeFinancialAccount></cac:PaymentMeans>'
            .'<cac:PaymentMeans><cbc:PaymentMeansCode>31</cbc:PaymentMeansCode><cac:PayeeFinancialAccount><cbc:ID>OTHER-MEANS</cbc:ID></cac:PayeeFinancialAccount></cac:PaymentMeans>';
        $xml = str_replace('<cac:PaymentTerms>', $extra.'<cac:PaymentTerms>', self::contents(self::FIXTURES.'/all-terms-invoice.xml'));

        $result = (new UblReader)->read($xml);
        $payment = $result->invoice->payment;
        self::assertNotNull($payment);
        $transfers = $payment->creditTransfers;

        self::assertCount(2, $transfers);
        self::assertSame('SECOND-IBAN', $transfers[1]->accountIdentifier);
        self::assertContains('/Invoice/cac:PaymentMeans[3]', $result->ignored);
    }

    public function test_reads_large_invoice_in_linear_time(): void
    {
        $reader = new UblReader;
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

        (new UblReader)->read('<Order xmlns="urn:oasis:names:specification:ubl:schema:xsd:Order-2"/>');
    }

    public function test_missing_invoice_number_is_unsupported(): void
    {
        $xml = str_replace('<cbc:ID>INV-2026-001</cbc:ID>', '', self::contents(self::FIXTURES.'/all-terms-invoice.xml'));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('Missing required BT-1 (cbc:ID) in /Invoice.');

        (new UblReader)->read($xml);
    }

    private function assertSeller(Invoice $invoice): void
    {
        $seller = $invoice->seller;
        self::assertSame('Seller Kft.', $seller->name);
        self::assertSame('Seller Trading', $seller->tradingName);
        self::assertCount(2, $seller->identifiers);
        self::assertSame('5790000435968', $seller->identifiers[0]->value);
        self::assertSame('0088', $seller->identifiers[0]->scheme);
        self::assertSame('LOCAL-29', $seller->identifiers[1]->value);
        self::assertNull($seller->identifiers[1]->scheme);
        self::assertSame('01-09-123456', $seller->legalRegistration?->value);
        self::assertSame('0002', $seller->legalRegistration->scheme);
        self::assertSame('HU12345678', $seller->vatIdentifier);
        self::assertSame('12345678-2-41', $seller->taxRegistration?->identifier);
        self::assertSame('LOC', $seller->taxRegistration->taxScheme);
        self::assertSame('Share capital 3 000 000 HUF', $seller->additionalLegalInformation);
        self::assertSame('ESB12345678', $seller->electronicAddress?->value);
        self::assertSame('9920', $seller->electronicAddress->scheme->value);
        self::assertSame('Main street 1', $seller->address?->line1);
        self::assertSame('Building A', $seller->address->line2);
        self::assertSame('Floor 3', $seller->address->line3);
        self::assertSame('Budapest', $seller->address->city);
        self::assertSame('1011', $seller->address->postCode);
        self::assertSame('Pest', $seller->address->subdivision);
        self::assertSame('HU', $seller->address->country->value);
        self::assertSame('Seller Contact', $seller->contact?->name);
        self::assertSame('+36 1 234 5678', $seller->contact->phone);
        self::assertSame('seller@example.com', $seller->contact->email);
    }

    private function assertBuyer(Invoice $invoice): void
    {
        $buyer = $invoice->buyer;
        self::assertSame('Buyer GmbH', $buyer->name);
        self::assertSame('Buyer Trading', $buyer->tradingName);
        self::assertCount(1, $buyer->identifiers);
        self::assertSame('5790000435999', $buyer->identifiers[0]->value);
        self::assertSame('FN123456', $buyer->legalRegistration?->value);
        self::assertSame('ATU12345678', $buyer->vatIdentifier);
        self::assertSame('5790000435975', $buyer->electronicAddress?->value);
        self::assertSame('0088', $buyer->electronicAddress->scheme->value);
        self::assertSame('Buyer street 2', $buyer->address?->line1);
        self::assertSame('Unit 5', $buyer->address->line2);
        self::assertSame('Door 7', $buyer->address->line3);
        self::assertSame('Wien', $buyer->address->city);
        self::assertSame('1010', $buyer->address->postCode);
        self::assertSame('Wien', $buyer->address->subdivision);
        self::assertSame('AT', $buyer->address->country->value);
        self::assertSame('Buyer Contact', $buyer->contact?->name);
        self::assertSame('+43 1 000', $buyer->contact->phone);
        self::assertSame('buyer@example.com', $buyer->contact->email);
    }

    private function assertOtherParties(Invoice $invoice): void
    {
        $payee = $invoice->payee;
        self::assertSame('Payee Ltd', $payee?->name);
        self::assertSame('5790000435111', $payee->identifiers[0]->value);
        self::assertSame('0088', $payee->identifiers[0]->scheme);
        self::assertSame('01-09-999999', $payee->legalRegistration?->value);
        self::assertSame('0002', $payee->legalRegistration->scheme);

        $representative = $invoice->taxRepresentative;
        self::assertSame('Tax Rep BV', $representative?->name);
        self::assertSame('NL123456789B01', $representative->vatIdentifier);
        self::assertSame('Rep street 3', $representative->address?->line1);
        self::assertSame('Rep building', $representative->address->line2);
        self::assertSame('Rep line 3', $representative->address->line3);
        self::assertSame('Amsterdam', $representative->address->city);
        self::assertSame('1000 AA', $representative->address->postCode);
        self::assertSame('Noord-Holland', $representative->address->subdivision);
        self::assertSame('NL', $representative->address->country->value);
    }

    private function assertDelivery(Invoice $invoice): void
    {
        $delivery = $invoice->delivery;
        self::assertSame('Delivery Party', $delivery?->partyName);
        self::assertSame('5790000435222', $delivery->locationIdentifier?->value);
        self::assertSame('0088', $delivery->locationIdentifier->scheme);
        self::assertSame('2026-10-15', $delivery->actualDeliveryDate?->format('Y-m-d'));
        self::assertSame('2026-10-01', $delivery->invoicingPeriod?->start?->format('Y-m-d'));
        self::assertSame('2026-10-31', $delivery->invoicingPeriod->end?->format('Y-m-d'));
        self::assertSame('Delivery street 4', $delivery->address?->line1);
        self::assertSame('Dock 2', $delivery->address->line2);
        self::assertSame('Gate B', $delivery->address->line3);
        self::assertSame('Graz', $delivery->address->city);
        self::assertSame('8010', $delivery->address->postCode);
        self::assertSame('Steiermark', $delivery->address->subdivision);
        self::assertSame('AT', $delivery->address->country->value);
    }

    private function assertPayment(Invoice $invoice): void
    {
        $payment = $invoice->payment;
        self::assertSame('59', $payment?->meansCode->value);
        self::assertSame('SEPA direct debit', $payment->meansText);
        self::assertSame('REMIT-83', $payment->remittanceInformation);
        self::assertCount(1, $payment->creditTransfers);
        self::assertSame('HU42117730161111101800000000', $payment->creditTransfers[0]->accountIdentifier);
        self::assertSame('Seller account', $payment->creditTransfers[0]->accountName);
        self::assertSame('OTPVHUHB', $payment->creditTransfers[0]->serviceProviderIdentifier);
        self::assertSame('123456', $payment->card?->primaryAccountNumber);
        self::assertSame('Card Holder', $payment->card->holderName);
        self::assertSame('MANDATE-89', $payment->directDebit?->mandateReference);
        self::assertSame('DE98ZZZ09999999999', $payment->directDebit->creditorIdentifier);
        self::assertSame('AT611904300234573201', $payment->directDebit->debitedAccountIdentifier);
    }

    private function assertDocumentAllowancesAndCharges(Invoice $invoice): void
    {
        self::assertCount(1, $invoice->allowances);
        $allowance = $invoice->allowances[0];
        self::assertSame('10.00', (string) $allowance->amount);
        self::assertSame('100.00', (string) $allowance->baseAmount);
        self::assertSame('10', (string) $allowance->percentage);
        self::assertSame('S', $allowance->vatCategory->value);
        self::assertSame('27', (string) $allowance->vatRate);
        self::assertSame('Discount', $allowance->reason);
        self::assertSame('95', $allowance->reasonCode?->value);

        self::assertCount(1, $invoice->charges);
        $charge = $invoice->charges[0];
        self::assertSame('5.00', (string) $charge->amount);
        self::assertSame('100.00', (string) $charge->baseAmount);
        self::assertSame('5', (string) $charge->percentage);
        self::assertSame('S', $charge->vatCategory->value);
        self::assertSame('27', (string) $charge->vatRate);
        self::assertSame('Freight', $charge->reason);
        self::assertSame('FC', $charge->reasonCode?->value);
    }

    private function assertTotalsAndVat(Invoice $invoice): void
    {
        $totals = $invoice->totals;
        self::assertNotNull($totals);
        self::assertSame('100.00', (string) $totals->sumOfLineNetAmounts);
        self::assertSame('10.00', (string) $totals->sumOfAllowances);
        self::assertSame('5.00', (string) $totals->sumOfCharges);
        self::assertSame('95.00', (string) $totals->totalWithoutVat);
        self::assertSame('25.65', (string) $totals->totalVat);
        self::assertSame('10260', (string) $totals->totalVatInAccountingCurrency);
        self::assertSame('120.65', (string) $totals->totalWithVat);
        self::assertSame('20.00', (string) $totals->paidAmount);
        self::assertSame('0.35', (string) $totals->roundingAmount);
        self::assertSame('101.00', (string) $totals->amountDue);

        self::assertCount(2, $invoice->vatBreakdown);
        self::assertSame('95.00', (string) $invoice->vatBreakdown[0]->taxableAmount);
        self::assertSame('25.65', (string) $invoice->vatBreakdown[0]->taxAmount);
        self::assertSame('S', $invoice->vatBreakdown[0]->category->value);
        self::assertSame('27', (string) $invoice->vatBreakdown[0]->rate);
        self::assertSame('E', $invoice->vatBreakdown[1]->category->value);
        self::assertSame('Exempt per Article 132', $invoice->vatBreakdown[1]->exemptionReason);
        self::assertSame('VATEX-EU-132', $invoice->vatBreakdown[1]->exemptionReasonCode?->value);
    }

    private function assertAdditionalDocuments(Invoice $invoice): void
    {
        self::assertCount(1, $invoice->additionalDocuments);
        $document = $invoice->additionalDocuments[0];
        self::assertSame('DOC-122', $document->reference);
        self::assertSame('Timesheet', $document->description);
        self::assertSame('https://example.com/timesheet.csv', $document->externalLocation);
        self::assertSame("hours,12\n", $document->attachment?->content);
        self::assertSame('text/csv', $document->attachment->mimeCode->value);
        self::assertSame('timesheet.csv', $document->attachment->filename);
    }

    private function assertLine(Invoice $invoice): void
    {
        self::assertCount(1, $invoice->lines);
        $line = $invoice->lines[0];
        self::assertSame('1', $line->id);
        self::assertSame('Line note', $line->note);
        self::assertSame('LINE-OBJ-128', $line->objectIdentifier?->value);
        self::assertSame('ABZ', $line->objectIdentifier->scheme);
        self::assertSame('2.5000', (string) $line->quantity);
        self::assertSame('H87', $line->unit->value);
        self::assertSame('100.00', (string) $line->netAmount);
        self::assertSame('OL-132', $line->orderLineReference);
        self::assertSame('LINE-ACC-133', $line->buyerAccountingReference);
        self::assertSame('2026-10-02', $line->period?->start?->format('Y-m-d'));
        self::assertSame('2026-10-30', $line->period->end?->format('Y-m-d'));

        self::assertCount(1, $line->allowances);
        self::assertSame('2.00', (string) $line->allowances[0]->amount);
        self::assertSame('100.00', (string) $line->allowances[0]->baseAmount);
        self::assertSame('2', (string) $line->allowances[0]->percentage);
        self::assertSame('Line discount', $line->allowances[0]->reason);
        self::assertSame('95', $line->allowances[0]->reasonCode?->value);
        self::assertCount(1, $line->charges);
        self::assertSame('2.00', (string) $line->charges[0]->amount);
        self::assertSame('100.00', (string) $line->charges[0]->baseAmount);
        self::assertSame('2', (string) $line->charges[0]->percentage);
        self::assertSame('Packaging', $line->charges[0]->reason);
        self::assertSame('ABL', $line->charges[0]->reasonCode?->value);

        self::assertSame('40.00', (string) $line->price->netPrice);
        self::assertSame('5.00', (string) $line->price->discount);
        self::assertSame('45.00', (string) $line->price->grossPrice);
        self::assertSame('1', (string) $line->price->baseQuantity);
        self::assertSame('H87', $line->price->baseQuantityUnit?->value);

        self::assertSame('S', $line->vat->category->value);
        self::assertSame('27', (string) $line->vat->rate);

        $item = $line->item;
        self::assertSame('Item name', $item->name);
        self::assertSame('Item description', $item->description);
        self::assertSame('SELL-155', $item->sellersIdentifier);
        self::assertSame('BUY-156', $item->buyersIdentifier);
        self::assertSame('05901234123457', $item->standardIdentifier?->value);
        self::assertSame('0160', $item->standardIdentifier->scheme);
        self::assertCount(1, $item->classifications);
        self::assertSame('65434568', $item->classifications[0]->identifier);
        self::assertSame('STI', $item->classifications[0]->listIdentifier);
        self::assertSame('2.1', $item->classifications[0]->listVersion);
        self::assertSame('DE', $item->originCountry?->value);
        self::assertCount(1, $item->attributes);
        self::assertSame('Colour', $item->attributes[0]->name);
        self::assertSame('Blue', $item->attributes[0]->value);
    }

    private function readOfficial(string $name): ReadResult
    {
        return (new UblReader)->read(self::contents(self::officialPath($name)));
    }

    /**
     * guide-example1 with its first line repeated to the given count, built in memory.
     */
    private static function invoiceWithLines(int $count): string
    {
        $xml = self::contents(self::officialPath('guide-example1.xml'));
        $start = strpos($xml, '<cac:InvoiceLine>');
        $end = strpos($xml, '</cac:InvoiceLine>');
        $last = strrpos($xml, '</cac:InvoiceLine>');
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        self::assertNotFalse($last);
        $line = substr($xml, $start, $end + strlen('</cac:InvoiceLine>') - $start);

        return substr($xml, 0, $start).str_repeat($line, $count).substr($xml, $last + strlen('</cac:InvoiceLine>'));
    }

    private static function officialPath(string $name): string
    {
        foreach (Fixtures::official('ubl') as $file) {
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
