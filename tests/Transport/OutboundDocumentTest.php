<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport;

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\XRechnungPeppol;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutboundDocumentTest extends TestCase
{
    private const BIS = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    public function test_reads_the_peppol_base_example(): void
    {
        $xml = Fixtures::peppolExamples()['examples/base-example.xml'];

        $document = OutboundDocument::fromXml($xml);

        self::assertSame($xml, $document->xml);
        self::assertSame('0088:9482348239847239874', $document->sender->toString());
        self::assertSame('0002:FR23342', $document->receiver->toString());
        self::assertSame(PeppolBis3::INVOICE, $document->documentType->value);
        self::assertSame(PeppolBis3::PROCESS, $document->process->value);
        self::assertSame('GB', $document->countryC1);
    }

    public function test_reads_the_peppol_credit_note_example(): void
    {
        $document = OutboundDocument::fromXml(Fixtures::peppolExamples()['examples/base-creditnote-correction.xml']);

        self::assertSame(PeppolBis3::CREDIT_NOTE, $document->documentType->value);
    }

    public function test_from_invoice_reads_the_written_ubl(): void
    {
        $document = OutboundDocument::fromInvoice(self::invoice());

        self::assertTrue($document->sender->equals(new ParticipantId('0208', '0123456789')));
        self::assertSame('9930:DE123456789', $document->receiver->toString());
        self::assertSame(PeppolBis3::INVOICE, $document->documentType->value);
        self::assertSame(PeppolBis3::PROCESS, $document->process->value);
        self::assertSame('HU', $document->countryC1);
        self::assertSame((new UblWriter)->write(self::invoice()), $document->xml);
    }

    public function test_profile_id_of_the_document_is_the_process(): void
    {
        $invoice = self::invoice()->withProcessControl(new ProcessControl(self::BIS, 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0'));

        self::assertSame('urn:fdc:peppol.eu:2017:poacc:billing:01:1.0', OutboundDocument::fromInvoice($invoice)->process->value);

        $other = self::invoice()->withProcessControl(new ProcessControl(self::BIS, 'urn:example:process'));

        self::assertSame('urn:example:process', OutboundDocument::fromInvoice($other)->process->value);
    }

    public function test_credit_note_has_the_credit_note_document_type(): void
    {
        $creditNote = self::invoice()
            ->withType(InvoiceTypeCode::C_381)
            ->withPrecedingInvoice(new PrecedingInvoiceReference('MIX-2026-0000'));

        self::assertSame(PeppolBis3::CREDIT_NOTE, OutboundDocument::fromInvoice($creditNote)->documentType->value);
    }

    public function test_explicit_country_c1_overrides_the_seller_country(): void
    {
        self::assertSame('BE', OutboundDocument::fromInvoice(self::invoice(), 'BE')->countryC1);
    }

    public function test_country_c1_must_be_two_upper_case_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OutboundDocument::fromInvoice(self::invoice(), 'hu');
    }

    public function test_country_c1_with_a_trailing_newline_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OutboundDocument::fromInvoice(self::invoice(), "HU\n");
    }

    public function test_self_billing_is_not_sent(): void
    {
        $invoice = self::invoice()->withProcessControl(new ProcessControl(
            'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:selfbilling:3.0',
            'urn:fdc:peppol.eu:2017:poacc:selfbilling:01:1.0',
        ));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('self-billing is not sent over Peppol by this version');

        OutboundDocument::fromInvoice($invoice);
    }

    public function test_padded_buyer_scheme_id_names_bt_49_1(): void
    {
        $xml = str_replace('schemeID="9930"', 'schemeID=" 9930"', (new UblWriter)->write(self::invoice()));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('BT-49-1');

        OutboundDocument::fromXml($xml);
    }

    public function test_padded_seller_scheme_id_names_bt_34_1(): void
    {
        $xml = str_replace('schemeID="0208"', 'schemeID="0208 "', (new UblWriter)->write(self::invoice()));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('BT-34-1');

        OutboundDocument::fromXml($xml);
    }

    public function test_padded_endpoint_value_is_trimmed(): void
    {
        $xml = str_replace('>DE123456789</cbc:EndpointID>', '> DE123456789 </cbc:EndpointID>', (new UblWriter)->write(self::invoice()));

        self::assertSame('9930:DE123456789', OutboundDocument::fromXml($xml)->receiver->toString());
    }

    public function test_cii_without_an_xrechnung_id_is_refused(): void
    {
        self::assertRefusedWithSendableIds((string) file_get_contents(__DIR__.'/../fixtures/cii/all-terms-invoice.xml'));
    }

    public function test_bis_invoice_unchanged(): void
    {
        $xml = (new UblWriter)->write(self::invoice());

        $document = OutboundDocument::fromXml($xml);

        self::assertSame($xml, $document->xml);
        self::assertSame(PeppolBis3::INVOICE, $document->documentType->value);
        self::assertSame(PeppolBis3::PROCESS, $document->process->value);
        self::assertSame('0208:0123456789', $document->sender->toString());
        self::assertSame('9930:DE123456789', $document->receiver->toString());
        self::assertSame('HU', $document->countryC1);
    }

    public function test_invoice_without_process_control_is_refused(): void
    {
        $invoice = self::invoice()->withProcessControl(null);

        $message = self::assertRefusedWithSendableIds((new UblWriter)->write($invoice));

        self::assertStringContainsString('process control', $message);
    }

    public function test_xrechnung_ubl_invoice_gets_its_doctype(): void
    {
        self::assertXRechnung(
            OutboundDocument::fromInvoice(self::xrechnung(XRechnung::CIUS)),
            'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0::2.1',
        );
    }

    public function test_xrechnung_ubl_credit_note_gets_its_doctype(): void
    {
        self::assertXRechnung(
            OutboundDocument::fromInvoice(self::creditNote(self::xrechnung(XRechnung::CIUS))),
            'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2::CreditNote##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0::2.1',
        );
    }

    public function test_xrechnung_cii_invoice_gets_its_doctype(): void
    {
        $invoice = self::xrechnung(XRechnung::CIUS);
        $document = OutboundDocument::fromInvoice($invoice, null, Syntax::Cii);

        self::assertSame((new CiiWriter)->write($invoice), $document->xml);
        self::assertXRechnung(
            $document,
            'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100::CrossIndustryInvoice##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0::D16B',
        );
    }

    public function test_xrechnung_cii_credit_note_gets_the_cii_invoice_doctype(): void
    {
        $document = OutboundDocument::fromXml((new CiiWriter)->write(self::creditNote(self::xrechnung(XRechnung::CIUS))));

        self::assertXRechnung($document, XRechnungPeppol::CII_INVOICE);
    }

    public function test_xrechnung_ubl_extension_invoice_gets_its_doctype(): void
    {
        self::assertXRechnung(
            OutboundDocument::fromXml((new UblWriter)->write(self::xrechnung(XRechnung::EXTENSION))),
            'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_3.0::2.1',
        );
    }

    public function test_xrechnung_cii_extension_invoice_gets_its_doctype(): void
    {
        self::assertXRechnung(
            OutboundDocument::fromXml((new CiiWriter)->write(self::xrechnung(XRechnung::EXTENSION))),
            'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100::CrossIndustryInvoice##urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_3.0::D16B',
        );
    }

    public function test_xrechnung_cii_explicit_country_c1_overrides_the_seller_country(): void
    {
        self::assertSame('BE', OutboundDocument::fromInvoice(self::xrechnung(XRechnung::CIUS), 'BE', Syntax::Cii)->countryC1);
    }

    public function test_xrechnung_without_business_process_defaults_to_peppol_billing(): void
    {
        $invoice = self::invoice()->withProcessControl(new ProcessControl(XRechnung::CIUS));

        self::assertSame(PeppolBis3::PROCESS, OutboundDocument::fromInvoice($invoice, null, Syntax::Cii)->process->value);
        self::assertSame(PeppolBis3::PROCESS, OutboundDocument::fromInvoice($invoice)->process->value);
    }

    /**
     * @return iterable<string, array{Syntax}>
     */
    public static function syntaxes(): iterable
    {
        yield 'ubl' => [Syntax::Ubl];
        yield 'cii' => [Syntax::Cii];
    }

    #[DataProvider('syntaxes')]
    public function test_xrechnung_with_another_business_process_is_refused(Syntax $syntax): void
    {
        $invoice = self::invoice()->withProcessControl(new ProcessControl(XRechnung::CIUS, 'urn:example:process'));

        try {
            OutboundDocument::fromInvoice($invoice, null, $syntax);
            self::fail('UnsupportedDocument was not thrown.');
        } catch (UnsupportedDocument $exception) {
            self::assertStringContainsString('urn:example:process', $exception->getMessage());
            self::assertStringContainsString(PeppolBis3::PROCESS, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function nearMisses(): iterable
    {
        $ubl = static fn (Invoice $invoice): string => (new UblWriter)->write($invoice);
        $bis = $ubl(self::invoice());

        yield 'trailing space' => [str_replace(self::BIS.'</cbc:CustomizationID>', self::BIS.' </cbc:CustomizationID>', $bis), null];
        yield 'leading space' => [str_replace('>'.self::BIS.'</cbc:CustomizationID>', '> '.self::BIS.'</cbc:CustomizationID>', $bis), null];
        yield 'xrechnung 2.3' => [$ubl(self::xrechnung('urn:cen.eu:en16931:2017#compliant#urn:xoev-de:kosit:standard:xrechnung_2.3')), null];
        yield 'xrechnung cius with a suffix' => [$ubl(self::xrechnung(XRechnung::CIUS.'#conformant#urn:example:extension')), null];
        yield 'cvd' => [$ubl(self::xrechnung(XRechnung::CVD)), 'has no Peppol document type'];
        yield 'cvd in cii' => [(new CiiWriter)->write(self::xrechnung(XRechnung::CVD)), 'has no Peppol document type'];
        yield 'credit note extension' => [$ubl(self::creditNote(self::xrechnung(XRechnung::EXTENSION))), 'deprecated in Peppol Code Lists v9.7'];
        yield 'cii with the bis id' => [(new CiiWriter)->write(self::invoice()), null];
    }

    #[DataProvider('nearMisses')]
    public function test_near_miss_customization_ids_are_refused(string $xml, ?string $reason): void
    {
        $message = self::assertRefusedWithSendableIds($xml);

        if ($reason !== null) {
            self::assertStringContainsString($reason, $message);
        }
    }

    public function test_from_invoice_cii_requires_xrechnung(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('XRechnung');

        OutboundDocument::fromInvoice(self::invoice(), null, Syntax::Cii);
    }

    public function test_cii_missing_buyer_endpoint_names_bt_49(): void
    {
        $invoice = self::xrechnung(XRechnung::CIUS);
        $invoice = $invoice->withBuyer(self::withAddress($invoice->buyer, null));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('BT-49');

        OutboundDocument::fromInvoice($invoice, null, Syntax::Cii);
    }

    public function test_cii_padded_seller_scheme_id_names_bt_34_1(): void
    {
        $xml = str_replace('schemeID="0208"', 'schemeID="0208 "', (new CiiWriter)->write(self::xrechnung(XRechnung::CIUS)));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('BT-34-1');

        OutboundDocument::fromXml($xml);
    }

    public function test_other_ubl_documents_are_refused(): void
    {
        $this->expectException(UnsupportedDocument::class);

        OutboundDocument::fromXml('<Order xmlns="urn:oasis:names:specification:ubl:schema:xsd:Order-2"/>');
    }

    public function test_missing_buyer_endpoint_names_bt_49(): void
    {
        $invoice = self::invoice();
        $invoice = $invoice->withBuyer(self::withAddress($invoice->buyer, null));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('BT-49');

        OutboundDocument::fromInvoice($invoice);
    }

    public function test_missing_seller_endpoint_names_bt_34(): void
    {
        $invoice = self::invoice();
        $invoice = $invoice->withSeller(self::withAddress($invoice->seller, null));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('BT-34');

        OutboundDocument::fromInvoice($invoice);
    }

    private static function assertXRechnung(OutboundDocument $document, string $documentType): void
    {
        self::assertSame($documentType, $document->documentType->value);
        self::assertSame(PeppolBis3::PROCESS, $document->process->value);
        self::assertSame('0208:0123456789', $document->sender->toString());
        self::assertSame('9930:DE123456789', $document->receiver->toString());
        self::assertSame('HU', $document->countryC1);
    }

    /**
     * Asserts that the document is refused with a message that lists every sendable
     * specification identifier, and returns the message.
     */
    private static function assertRefusedWithSendableIds(string $xml): string
    {
        try {
            OutboundDocument::fromXml($xml);
        } catch (UnsupportedDocument $exception) {
            $message = $exception->getMessage();
            self::assertStringContainsString(self::BIS, $message);
            self::assertStringContainsString(XRechnung::CIUS.' ', $message);
            self::assertStringContainsString(XRechnung::EXTENSION, $message);

            return $message;
        }
        self::fail('UnsupportedDocument was not thrown.');
    }

    private static function invoice(): Invoice
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat());

        return $invoice
            ->withProcessControl(new ProcessControl(self::BIS, PeppolBis3::PROCESS))
            ->withSeller(self::withAddress($invoice->seller, new ElectronicAddress('0123456789', ElectronicAddressScheme::C_0208)))
            ->withBuyer(self::withAddress($invoice->buyer, new ElectronicAddress('DE123456789', ElectronicAddressScheme::C_9930)));
    }

    private static function xrechnung(string $specification): Invoice
    {
        return self::invoice()->withProcessControl(XRechnung::processControl($specification));
    }

    private static function creditNote(Invoice $invoice): Invoice
    {
        return $invoice
            ->withType(InvoiceTypeCode::C_381)
            ->withPrecedingInvoice(new PrecedingInvoiceReference('MIX-2026-0000'));
    }

    private static function withAddress(Party $party, ?ElectronicAddress $address): Party
    {
        return new Party(
            name: $party->name,
            tradingName: $party->tradingName,
            identifiers: $party->identifiers,
            legalRegistration: $party->legalRegistration,
            vatIdentifier: $party->vatIdentifier,
            taxRegistration: $party->taxRegistration,
            additionalLegalInformation: $party->additionalLegalInformation,
            electronicAddress: $address,
            address: $party->address,
            contact: $party->contact,
        );
    }
}
