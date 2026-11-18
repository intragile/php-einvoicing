<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\France;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Syntax\Cii\CiiVersion;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use IntrAgile\EInvoicing\Tests\Support\PdfBuilder;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\FrenchDocument;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\FrenchDocumentType;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrenchDocumentTest extends TestCase
{
    public function test_ubl_cius_invoice_is_detected(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $xml = (new UblWriter)->write($invoice);

        $document = FrenchDocument::fromXml($xml);

        self::assertSame(FrenchDocumentType::UblInvoice, $document->type);
        self::assertSame('xml.ubl.invoice.frcius.v1', $document->type->value);
        self::assertSame($invoice->number, $document->number);
        self::assertSame($xml, $document->bytes);
        self::assertEquals($document, FrenchDocument::fromInvoice($invoice));
    }

    public function test_ubl_credit_note_is_detected(): void
    {
        $document = FrenchDocument::fromInvoice(FrenchInvoices::creditNoteWithTwoPrecedingInvoices());

        self::assertSame(FrenchDocumentType::UblCreditNote, $document->type);
        self::assertSame('xml.ubl.credit_note.frcius.v1', $document->type->value);
        self::assertSame('AV202600001', $document->number);
    }

    public function test_extended_ctc_fr_ubl_is_extension_type(): void
    {
        $invoice = FrenchInvoices::extendedCtcFrUbl();
        $document = FrenchDocument::fromInvoice($invoice);

        self::assertSame(FrenchDocumentType::UblInvoiceExtended, $document->type);
        self::assertSame('xml.ubl.invoice.fr.extension.v1', $document->type->value);
        self::assertSame($invoice->number, $document->number);

        $creditNote = FrenchDocument::fromXml(str_replace(
            FrenchProfile::En16931->value.'<',
            FrenchProfile::ExtendedCtcFr->value.'<',
            (new UblWriter)->write(FrenchInvoices::creditNoteWithTwoPrecedingInvoices()),
        ));
        self::assertSame(FrenchDocumentType::UblCreditNoteExtended, $creditNote->type);
        self::assertSame('xml.ubl.credit_note.fr.extension.v1', $creditNote->type->value);
    }

    public function test_cii_d22b_invoice_is_detected(): void
    {
        $invoice = FrenchInvoices::creditNoteWithTwoPrecedingInvoices();
        $xml = (new CiiWriter(CiiVersion::D22B))->write($invoice);

        $document = FrenchDocument::fromXml($xml);

        self::assertSame(FrenchDocumentType::CiiInvoice, $document->type);
        self::assertSame('xml.cii.cross_industry_invoice.frcius.v1', $document->type->value);
        self::assertSame('AV202600001', $document->number);
        self::assertSame($xml, $document->bytes);
        self::assertEquals($document, FrenchDocument::fromInvoice($invoice, Syntax::Cii));
    }

    public function test_factur_x_pdf_is_detected_with_number(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $xml = (new FacturXXmlWriter)->write($invoice, FacturXProfile::En16931)->xml;
        $pdf = (new FacturXPdfWriter)->embed(self::basePdf(), $xml, FacturXProfile::En16931, modified: new DateTimeImmutable('2026-11-18T00:00:00Z'))->pdf;

        $document = FrenchDocument::fromFacturX($pdf);

        self::assertSame(FrenchDocumentType::FacturX, $document->type);
        self::assertSame('xml.cii.cross_industry_invoice.facturx.fr.all_profiles.v1', $document->type->value);
        self::assertSame($invoice->number, $document->number);
        self::assertSame($pdf, $document->bytes);
    }

    /**
     * @return iterable<string, array{FacturXProfile}>
     */
    public static function profilesWithoutLines(): iterable
    {
        yield 'MINIMUM' => [FacturXProfile::Minimum];
        yield 'BASIC WL' => [FacturXProfile::BasicWl];
    }

    #[DataProvider('profilesWithoutLines')]
    public function test_factur_x_profiles_without_lines_are_refused(FacturXProfile $profile): void
    {
        $xml = str_replace(
            '>'.FacturXProfile::En16931->guidelineId().'<',
            '>'.$profile->guidelineId().'<',
            (new FacturXXmlWriter)->write(FrenchInvoices::domesticB2b(), FacturXProfile::En16931)->xml,
        );
        self::assertStringContainsString($profile->guidelineId(), $xml);
        $pdf = (new FacturXPdfWriter)->embed(self::basePdf(), $xml, $profile, modified: new DateTimeImmutable('2026-11-18T00:00:00Z'))->pdf;

        try {
            FrenchDocument::fromFacturX($pdf);
            self::fail(sprintf('A Factur-X %s document was accepted.', $profile->value));
        } catch (UnsupportedDocument $e) {
            self::assertStringContainsString($profile->value, $e->getMessage());
            self::assertStringContainsString('not an invoice under the French reform', $e->getMessage());
        }
    }

    public function test_peppol_bis_document_is_refused_with_hint(): void
    {
        $invoice = FrenchInvoices::domesticB2b()->withProcessControl(new ProcessControl(PeppolBis3::CUSTOMIZATION, PeppolBis3::PROCESS));

        try {
            FrenchDocument::fromInvoice($invoice);
            self::fail('A Peppol BIS document was accepted.');
        } catch (UnsupportedDocument $e) {
            self::assertStringContainsString('BT-24', $e->getMessage());
            self::assertStringContainsString(PeppolBis3::CUSTOMIZATION, $e->getMessage());
            self::assertStringContainsString('B2BrouterTransport', $e->getMessage());
        }
    }

    public function test_cii_extended_ctc_fr_is_refused(): void
    {
        $xml = str_replace(
            FrenchProfile::En16931->value.'<',
            FrenchProfile::ExtendedCtcFr->value.'<',
            (new CiiWriter(CiiVersion::D22B))->write(FrenchInvoices::domesticB2b()),
        );

        try {
            FrenchDocument::fromXml($xml);
            self::fail('A CII EXTENDED-CTC-FR document was accepted.');
        } catch (UnsupportedDocument $e) {
            self::assertStringContainsString('EXTENDED-CTC-FR', $e->getMessage());
            self::assertStringContainsString('UBL', $e->getMessage());
        }
    }

    public function test_other_documents_are_refused(): void
    {
        $this->expectException(UnsupportedDocument::class);

        FrenchDocument::fromXml('<?xml version="1.0"?><Order xmlns="urn:oasis:names:specification:ubl:schema:xsd:Order-2"/>');
    }

    /**
     * A one-page PDF without PDF/A metadata; embedding into it only warns.
     */
    private static function basePdf(): string
    {
        return (new PdfBuilder('1.7'))
            ->object(1, '<< /Type /Catalog /Pages 2 0 R >>')
            ->object(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>')
            ->object(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>')
            ->trailer('/Root 1 0 R /ID [<4652454E4348> <00>]')
            ->classic();
    }
}
