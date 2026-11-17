<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France;

use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrenchWritersTest extends TestCase
{
    private const UBL_SELLER = '/*/cac:AccountingSupplierParty/cac:Party';

    private const UBL_BUYER = '/*/cac:AccountingCustomerParty/cac:Party';

    private const CII_AGREEMENT = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement';

    private const CII_SELLER = self::CII_AGREEMENT.'/ram:SellerTradeParty';

    private const CII_BUYER = self::CII_AGREEMENT.'/ram:BuyerTradeParty';

    private const CII_SETTLEMENT = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement';

    public function test_ubl_siret_written_as_scheme_0009_party_identifier(): void
    {
        $x = $this->ubl(FrenchInvoices::domesticB2b());

        self::assertSame([FrenchInvoices::SELLER_SIRET], $this->values($x, self::UBL_SELLER.'/cac:PartyIdentification/cbc:ID[@schemeID="0009"]'));
    }

    public function test_cii_siret_written_as_scheme_0009_party_identifier(): void
    {
        $x = $this->cii(FrenchInvoices::domesticB2b());

        self::assertSame([FrenchInvoices::SELLER_SIRET], $this->values($x, self::CII_SELLER.'/ram:GlobalID[@schemeID="0009"]'));
    }

    public function test_ubl_sirens_written_as_legal_registration_0002(): void
    {
        $x = $this->ubl(FrenchInvoices::domesticB2b());

        self::assertSame([FrenchInvoices::SELLER_SIREN], $this->values($x, self::UBL_SELLER.'/cac:PartyLegalEntity/cbc:CompanyID[@schemeID="0002"]'));
        self::assertSame([FrenchInvoices::BUYER_SIREN], $this->values($x, self::UBL_BUYER.'/cac:PartyLegalEntity/cbc:CompanyID[@schemeID="0002"]'));
    }

    public function test_cii_sirens_written_as_legal_registration_0002(): void
    {
        $x = $this->cii(FrenchInvoices::domesticB2b());

        self::assertSame([FrenchInvoices::SELLER_SIREN], $this->values($x, self::CII_SELLER.'/ram:SpecifiedLegalOrganization/ram:ID[@schemeID="0002"]'));
        self::assertSame([FrenchInvoices::BUYER_SIREN], $this->values($x, self::CII_BUYER.'/ram:SpecifiedLegalOrganization/ram:ID[@schemeID="0002"]'));
    }

    public function test_ubl_endpoints_written_with_scheme_0225(): void
    {
        $x = $this->ubl(FrenchInvoices::domesticB2b());

        self::assertSame([FrenchInvoices::SELLER_SIREN], $this->values($x, self::UBL_SELLER.'/cbc:EndpointID[@schemeID="0225"]'));
        self::assertSame([FrenchInvoices::BUYER_SIREN], $this->values($x, self::UBL_BUYER.'/cbc:EndpointID[@schemeID="0225"]'));
    }

    public function test_cii_endpoints_written_with_scheme_0225(): void
    {
        $x = $this->cii(FrenchInvoices::domesticB2b());

        self::assertSame([FrenchInvoices::SELLER_SIREN], $this->values($x, self::CII_SELLER.'/ram:URIUniversalCommunication/ram:URIID[@schemeID="0225"]'));
        self::assertSame([FrenchInvoices::BUYER_SIREN], $this->values($x, self::CII_BUYER.'/ram:URIUniversalCommunication/ram:URIID[@schemeID="0225"]'));
    }

    public function test_ubl_vat_on_debits_code_per_syntax(): void
    {
        $x = $this->ubl(FrenchInvoices::vatOnDebits());

        self::assertSame(['3'], $this->values($x, '/*/cac:InvoicePeriod/cbc:DescriptionCode'));
    }

    public function test_cii_vat_on_debits_code_per_syntax(): void
    {
        $x = $this->cii(FrenchInvoices::vatOnDebits());

        self::assertSame(['5'], $this->values($x, self::CII_SETTLEMENT.'/ram:ApplicableTradeTax/ram:DueDateTypeCode'));
    }

    public function test_ubl_delivery_address_written(): void
    {
        $x = $this->ubl(FrenchInvoices::withDeliveryAddress());
        $address = '/*/cac:Delivery/cac:DeliveryLocation/cac:Address/';

        self::assertSame(['12 impasse plus loin'], $this->values($x, $address.'cbc:StreetName'));
        self::assertSame(['NICE'], $this->values($x, $address.'cbc:CityName'));
        self::assertSame(['06000'], $this->values($x, $address.'cbc:PostalZone'));
        self::assertSame(['FR'], $this->values($x, $address.'cac:Country/cbc:IdentificationCode'));
    }

    public function test_cii_delivery_address_written(): void
    {
        $x = $this->cii(FrenchInvoices::withDeliveryAddress());
        $address = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress/';

        self::assertSame(['12 impasse plus loin'], $this->values($x, $address.'ram:LineOne'));
        self::assertSame(['NICE'], $this->values($x, $address.'ram:CityName'));
        self::assertSame(['06000'], $this->values($x, $address.'ram:PostcodeCode'));
        self::assertSame(['FR'], $this->values($x, $address.'ram:CountryID'));
    }

    /**
     * FNFE's UBL examples carry the subject code inside cbc:Note as "#CODE#text".
     */
    public function test_ubl_french_notes_written_with_subject_codes(): void
    {
        $x = $this->ubl(FrenchInvoices::domesticB2b());

        self::assertSame([
            '#PMT#'.FrenchInvoices::RECOVERY_FEE,
            '#PMD#'.FrenchInvoices::LATE_PAYMENT,
            '#AAB#'.FrenchInvoices::EARLY_PAYMENT,
            '#BAR#B2B',
        ], $this->values($x, '/*/cbc:Note'));
    }

    public function test_cii_french_notes_written_with_subject_codes(): void
    {
        $x = $this->cii(FrenchInvoices::domesticB2b());
        $notes = '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IncludedNote/';

        self::assertSame(
            [FrenchInvoices::RECOVERY_FEE, FrenchInvoices::LATE_PAYMENT, FrenchInvoices::EARLY_PAYMENT, 'B2B'],
            $this->values($x, $notes.'ram:Content'),
        );
        self::assertSame(['PMT', 'PMD', 'AAB', 'BAR'], $this->values($x, $notes.'ram:SubjectCode'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function factories(): iterable
    {
        foreach (array_keys(FrenchInvoices::all()) as $factory) {
            yield $factory => [$factory];
        }
    }

    #[DataProvider('factories')]
    public function test_ubl_round_trip_keeps_french_data_and_extension(string $factory): void
    {
        $invoice = $this->invoice($factory);
        $read = (new UblReader)->read((new UblWriter)->write($invoice));

        self::assertSame([], $read->ignored);
        self::assertEquals($invoice, $read->invoice);
        self::assertSame(FrenchProfile::En16931, $read->invoice->extensions()->get(FrenchInvoice::class)?->profile);
    }

    #[DataProvider('factories')]
    public function test_cii_round_trip_keeps_french_data_and_extension(string $factory): void
    {
        $invoice = $this->invoice($factory);
        $read = (new CiiReader)->read((new CiiWriter)->write($invoice));

        self::assertSame([], $read->ignored);
        self::assertEquals($invoice, $read->invoice);
        self::assertSame(FrenchProfile::En16931, $read->invoice->extensions()->get(FrenchInvoice::class)?->profile);
    }

    public function test_readers_take_the_profile_from_the_specification_identifier(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $ubl = str_replace(FrenchProfile::En16931->value.'<', FrenchProfile::ExtendedCtcFr->value.'<', (new UblWriter)->write($invoice));
        $cii = str_replace(FrenchProfile::En16931->value.'<', FrenchProfile::ExtendedCtcFr->value.'<', (new CiiWriter)->write($invoice));

        self::assertSame(FrenchProfile::ExtendedCtcFr, (new UblReader)->read($ubl)->invoice->extensions()->get(FrenchInvoice::class)?->profile);
        self::assertSame(FrenchProfile::ExtendedCtcFr, (new CiiReader)->read($cii)->invoice->extensions()->get(FrenchInvoice::class)?->profile);
    }

    public function test_readers_mark_an_extended_ctc_fr_invoice_without_a_billing_framework(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $xml = str_replace(
            [FrenchProfile::En16931->value.'<', '<cbc:ProfileID>B1</cbc:ProfileID>'],
            [FrenchProfile::ExtendedCtcFr->value.'<', ''],
            (new UblWriter)->write($invoice),
        );

        self::assertSame(FrenchProfile::ExtendedCtcFr, (new UblReader)->read($xml)->invoice->extensions()->get(FrenchInvoice::class)?->profile);
    }

    /**
     * The EN 16931 specification identifier alone is not French: every CEN invoice carries it.
     */
    public function test_readers_do_not_mark_a_plain_en16931_invoice(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $ubl = str_replace('<cbc:ProfileID>B1</cbc:ProfileID>', '', (new UblWriter)->write($invoice));

        self::assertFalse((new UblReader)->read($ubl)->invoice->extensions()->has(FrenchInvoice::class));
    }

    private function invoice(string $factory): Invoice
    {
        return FrenchInvoices::all()[$factory];
    }

    private function ubl(Invoice $invoice): DOMXPath
    {
        $x = $this->xpath((new UblWriter)->write($invoice));
        $x->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $x->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        return $x;
    }

    private function cii(Invoice $invoice): DOMXPath
    {
        $x = $this->xpath((new CiiWriter)->write($invoice));
        $x->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $x->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        return $x;
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));

        return new DOMXPath($document);
    }

    /**
     * @return list<string>
     */
    private function values(DOMXPath $x, string $expression): array
    {
        $nodes = $x->query($expression);
        self::assertNotFalse($nodes, $expression);
        $values = [];
        foreach ($nodes as $node) {
            $values[] = $node->nodeValue ?? '';
        }

        return $values;
    }
}
