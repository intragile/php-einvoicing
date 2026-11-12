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
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OutboundDocumentTest extends TestCase
{
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
        $invoice = self::invoice()->withProcessControl(new ProcessControl('urn:cen.eu:en16931:2017', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0'));

        self::assertSame('urn:fdc:peppol.eu:2017:poacc:billing:01:1.0', OutboundDocument::fromInvoice($invoice)->process->value);

        $other = self::invoice()->withProcessControl(new ProcessControl('urn:cen.eu:en16931:2017', 'urn:example:process'));

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

    public function test_cii_is_not_sent(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('CII is not sent over Peppol by this version');

        OutboundDocument::fromXml((string) file_get_contents(__DIR__.'/../fixtures/cii/all-terms-invoice.xml'));
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

    private static function invoice(): Invoice
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat());

        return $invoice
            ->withSeller(self::withAddress($invoice->seller, new ElectronicAddress('0123456789', ElectronicAddressScheme::C_0208)))
            ->withBuyer(self::withAddress($invoice->buyer, new ElectronicAddress('DE123456789', ElectronicAddressScheme::C_9930)));
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
