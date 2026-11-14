<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Peppol;

use IntrAgile\EInvoicing\Peppol\PeppolDocumentTypes;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\XRechnungPeppol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PeppolDocumentTypesTest extends TestCase
{
    public function test_identifiers_are_the_published_values(): void
    {
        self::assertSame('urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0', PeppolDocumentTypes::BIS_CUSTOMIZATION);
        self::assertSame('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1', PeppolBis3::INVOICE);
        self::assertSame('urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2::CreditNote##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1', PeppolBis3::CREDIT_NOTE);
        self::assertSame('urn:fdc:peppol.eu:2017:poacc:billing:01:1.0', PeppolBis3::PROCESS);
        self::assertSame(PeppolDocumentTypes::xrechnung(), XRechnungPeppol::documentTypes());
    }

    /**
     * @return iterable<string, array{string, string|null, string|null}>
     */
    public static function pairs(): iterable
    {
        yield 'bis invoice' => [PeppolDocumentTypes::UBL_INVOICE_ROOT, PeppolBis3::CUSTOMIZATION, PeppolBis3::INVOICE];
        yield 'bis credit note' => [PeppolDocumentTypes::UBL_CREDIT_NOTE_ROOT, PeppolBis3::CUSTOMIZATION, PeppolBis3::CREDIT_NOTE];
        yield 'bis in cii' => [PeppolDocumentTypes::CII_ROOT, PeppolBis3::CUSTOMIZATION, null];
        yield 'bis padded' => [PeppolDocumentTypes::UBL_INVOICE_ROOT, PeppolBis3::CUSTOMIZATION.' ', null];
        yield 'xrechnung cii' => [PeppolDocumentTypes::CII_ROOT, XRechnung::CIUS, XRechnungPeppol::CII_INVOICE];
        yield 'xrechnung ubl credit note' => [PeppolDocumentTypes::UBL_CREDIT_NOTE_ROOT, XRechnung::CIUS, XRechnungPeppol::UBL_CREDIT_NOTE];
        yield 'extension ubl invoice' => [PeppolDocumentTypes::UBL_INVOICE_ROOT, XRechnung::EXTENSION, XRechnungPeppol::UBL_INVOICE_EXTENSION];
        yield 'extension ubl credit note' => [PeppolDocumentTypes::UBL_CREDIT_NOTE_ROOT, XRechnung::EXTENSION, null];
        yield 'cvd' => [PeppolDocumentTypes::UBL_INVOICE_ROOT, XRechnung::CVD, null];
        yield 'no identifier' => [PeppolDocumentTypes::UBL_INVOICE_ROOT, null, null];
    }

    #[DataProvider('pairs')]
    public function test_resolve_matches_exact_pairs(string $rootKey, ?string $specification, ?string $expected): void
    {
        self::assertSame($expected, PeppolDocumentTypes::resolve($rootKey, $specification));
    }

    public function test_specification_is_read_untrimmed_from_ubl_and_cii(): void
    {
        $ubl = SafeXml::load('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><cbc:CustomizationID> a </cbc:CustomizationID></Invoice>')->documentElement;
        $cii = SafeXml::load('<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"><rsm:ExchangedDocumentContext><ram:GuidelineSpecifiedDocumentContextParameter><ram:ID>b </ram:ID></ram:GuidelineSpecifiedDocumentContextParameter></rsm:ExchangedDocumentContext></rsm:CrossIndustryInvoice>')->documentElement;
        $order = SafeXml::load('<Order xmlns="urn:oasis:names:specification:ubl:schema:xsd:Order-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><cbc:CustomizationID>c</cbc:CustomizationID></Order>')->documentElement;
        self::assertNotNull($ubl);
        self::assertNotNull($cii);
        self::assertNotNull($order);

        self::assertSame(' a ', PeppolDocumentTypes::specification($ubl));
        self::assertSame('b ', PeppolDocumentTypes::specification($cii));
        self::assertNull(PeppolDocumentTypes::specification($order));
    }

    public function test_sendable_lists_every_accepted_identifier(): void
    {
        $sendable = PeppolDocumentTypes::sendable();

        self::assertStringContainsString(PeppolBis3::CUSTOMIZATION, $sendable);
        self::assertStringContainsString(XRechnung::CIUS.' ', $sendable);
        self::assertStringContainsString(XRechnung::EXTENSION, $sendable);
    }
}
