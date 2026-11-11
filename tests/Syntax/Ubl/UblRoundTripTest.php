<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Ubl;

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\PaymentCard;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Support\Xsd;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UblRoundTripTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../fixtures/ubl';

    private const HOSTILE_TEXT = InvoiceFactory::HOSTILE_TEXT;

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

    /**
     * Invoices built through the model, completed by the calculator.
     *
     * @return array<string, array{Invoice}>
     */
    public static function modelInvoices(): array
    {
        $mixed = TotalsCalculator::complete(InvoiceFactory::mixedVat());

        return [
            'mixed VAT' => [$mixed],
            'not subject to VAT' => [TotalsCalculator::complete(InvoiceFactory::notSubjectToVat())],
            'mixed VAT as credit note' => [$mixed->withType(InvoiceTypeCode::C_381)->withDueDate(null)],
            'credit note with due date only' => [$mixed->withType(InvoiceTypeCode::C_381)],
            'credit note with payment and due date' => [$mixed->withType(InvoiceTypeCode::C_381)->withPayment(
                new PaymentInstructions(meansCode: PaymentMeansCode::C_58, creditTransfers: [new CreditTransfer(accountIdentifier: 'HU42117730161111101800000000')]),
            )],
            'hostile text' => [InvoiceFactory::hostileText()],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function writtenDocuments(): array
    {
        $cases = [];
        foreach (self::officialExamples() as $name => [$file]) {
            $cases[$name] = [(new UblWriter)->write((new UblReader)->read(self::contents($file))->invoice)];
        }
        foreach (self::modelInvoices() as $name => [$invoice]) {
            $cases[$name] = [(new UblWriter)->write($invoice)];
        }
        foreach (['all-terms-invoice.xml', 'creditnote-specifics.xml'] as $file) {
            $cases[$file] = [(new UblWriter)->write((new UblReader)->read(self::contents(self::FIXTURES.'/'.$file))->invoice)];
        }

        return $cases;
    }

    #[DataProvider('officialExamples')]
    public function test_round_trip_official_examples(string $file): void
    {
        $first = (new UblReader)->read(self::contents($file));

        $second = (new UblReader)->read((new UblWriter)->write($first->invoice));

        self::assertEquals($first->invoice, $second->invoice);
        self::assertTrue($first->invoice == $second->invoice);
        self::assertSame([], $second->ignored);
    }

    #[DataProvider('modelInvoices')]
    public function test_round_trip_model_invoices(Invoice $invoice): void
    {
        $read = (new UblReader)->read((new UblWriter)->write($invoice));

        self::assertEquals($invoice, $read->invoice);
        self::assertTrue($invoice == $read->invoice);
        self::assertSame([], $read->ignored);
    }

    public function test_round_trip_all_terms_fixtures(): void
    {
        foreach (['all-terms-invoice.xml', 'creditnote-specifics.xml'] as $file) {
            $first = (new UblReader)->read(self::contents(self::FIXTURES.'/'.$file));
            $second = (new UblReader)->read((new UblWriter)->write($first->invoice));

            self::assertEquals($first->invoice, $second->invoice, $file);
            self::assertTrue($first->invoice == $second->invoice, $file);
            self::assertSame([], $second->ignored, $file);
        }
    }

    public function test_a_real_card_network_is_reported_and_the_written_placeholder_is_not(): void
    {
        $first = (new UblReader)->read(self::contents(self::FIXTURES.'/all-terms-invoice.xml'));
        self::assertSame(['/Invoice/cac:PaymentMeans/cac:CardAccount/cbc:NetworkID'], $first->ignored);

        $xml = (new UblWriter)->write($first->invoice);

        self::assertStringContainsString('<cbc:NetworkID>NA</cbc:NetworkID>', $xml);
        self::assertSame([], (new UblReader)->read($xml)->ignored);
    }

    public function test_a_card_network_placeholder_with_surrounding_whitespace_is_recognised(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withPayment(new PaymentInstructions(
            meansCode: PaymentMeansCode::C_54,
            card: new PaymentCard(primaryAccountNumber: '1234', holderName: 'Card Holder'),
        ));
        $xml = str_replace('<cbc:NetworkID>NA</cbc:NetworkID>', "<cbc:NetworkID> NA\n</cbc:NetworkID>", (new UblWriter)->write($invoice), $count);
        self::assertSame(1, $count);

        $read = (new UblReader)->read($xml);

        self::assertEquals($invoice, $read->invoice);
        self::assertSame([], $read->ignored);
    }

    public function test_a_purchase_order_equal_to_the_order_placeholder_is_refused_beside_a_sales_order(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())
            ->withPurchaseOrderReference('NA')
            ->withSalesOrderReference('SO-77');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/BT-13.*BT-14/');

        (new UblWriter)->write($invoice);
    }

    #[DataProvider('writtenDocuments')]
    public function test_output_is_xsd_valid(string $xml): void
    {
        $creditNote = str_contains($xml, '<CreditNote xmlns=');

        self::assertSame([], Xsd::errors($xml, Fixtures::ublSchema($creditNote)));
    }

    public function test_text_content_survives_byte_exact(): void
    {
        $invoice = InvoiceFactory::hostileText();

        $xml = (new UblWriter)->write($invoice);
        $read = (new UblReader)->read($xml)->invoice;

        self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        self::assertStringContainsString('&amp; Co. &lt;b&gt;', $xml);
        self::assertStringContainsString('őŐűŰ 😀', $xml, 'UTF-8 is written as is, not as character references.');

        self::assertSame(self::HOSTILE_TEXT, $read->seller->name);
        self::assertSame(self::HOSTILE_TEXT, $read->seller->tradingName);
        self::assertSame(self::HOSTILE_TEXT, $read->seller->address?->line1);
        self::assertSame(self::HOSTILE_TEXT, $read->seller->contact?->name);
        self::assertSame(self::HOSTILE_TEXT, $read->buyer->name);
        self::assertSame(self::HOSTILE_TEXT, $read->notes[0]->text);
        self::assertSame(TextSubjectCode::AAI, $read->notes[0]->subjectCode);
        self::assertSame(self::HOSTILE_TEXT, $read->notes[1]->text);
        self::assertSame(self::HOSTILE_TEXT, $read->paymentTerms);
        self::assertSame(self::HOSTILE_TEXT, $read->lines[0]->note);
        self::assertSame(self::HOSTILE_TEXT, $read->lines[0]->item->name);
        self::assertSame(self::HOSTILE_TEXT, $read->lines[0]->item->description);
        $exempt = array_values(array_filter($read->vatBreakdown, static fn (VatBreakdown $entry): bool => $entry->category === VatCategory::Exempt));
        self::assertSame(self::HOSTILE_TEXT, $exempt[0]->exemptionReason ?? null);
        self::assertEquals($invoice, $read);
    }

    public function test_root_element_and_specification_identifier(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withProcessControl(null);

        $invoiceXml = (new UblWriter)->write($invoice);
        $creditNoteXml = (new UblWriter)->write($invoice->withType(InvoiceTypeCode::C_381));

        self::assertStringContainsString('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"', $invoiceXml);
        self::assertStringContainsString('<cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>', $invoiceXml);
        self::assertStringContainsString('<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"', $creditNoteXml);
        self::assertStringContainsString('<cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>', $creditNoteXml);
        self::assertStringContainsString('<cac:CreditNoteLine>', $creditNoteXml);
        self::assertStringContainsString('<cbc:CreditedQuantity unitCode="HUR">10</cbc:CreditedQuantity>', $creditNoteXml);
        self::assertStringContainsString('<cbc:CustomizationID>urn:cen.eu:en16931:2017</cbc:CustomizationID>', $invoiceXml);
    }

    public function test_credit_note_due_date_without_payment_instructions_round_trips(): void
    {
        $creditNote = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withType(InvoiceTypeCode::C_381);
        self::assertNull($creditNote->payment);

        $xml = (new UblWriter)->write($creditNote);
        $read = (new UblReader)->read($xml);

        self::assertStringContainsString("<cac:PaymentMeans>\n    <cbc:PaymentMeansCode>1</cbc:PaymentMeansCode>\n    <cbc:PaymentDueDate>2026-12-11</cbc:PaymentDueDate>\n  </cac:PaymentMeans>", $xml);
        self::assertSame('2026-12-11', $read->invoice->dueDate?->format('Y-m-d'));
        self::assertNull($read->invoice->payment, 'The placeholder payment means is not BG-16.');
        self::assertSame([], $read->ignored);
        self::assertEquals($creditNote, $read->invoice);
        self::assertTrue($creditNote == $read->invoice);
    }

    public function test_real_payment_means_code_1_on_a_credit_note_stays_bg16(): void
    {
        $creditNote = TotalsCalculator::complete(InvoiceFactory::mixedVat())
            ->withType(InvoiceTypeCode::C_381)
            ->withPayment(new PaymentInstructions(meansCode: PaymentMeansCode::C_1, remittanceInformation: 'REF-1'));

        $read = (new UblReader)->read((new UblWriter)->write($creditNote))->invoice;

        self::assertEquals($creditNote, $read);
    }

    public function test_amounts_keep_their_scale_and_currency(): void
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat());
        $invoice = $invoice->withLines([
            $invoice->lines[0]->withQuantity('10.0000')->withNetAmount('1100.00'),
            ...array_slice($invoice->lines, 1),
        ]);

        $xml = (new UblWriter)->write($invoice);

        self::assertStringContainsString('<cbc:InvoicedQuantity unitCode="HUR">10.0000</cbc:InvoicedQuantity>', $xml);
        self::assertStringContainsString('<cbc:LineExtensionAmount currencyID="EUR">1100.00</cbc:LineExtensionAmount>', $xml);
        self::assertStringContainsString('<cbc:PayableAmount currencyID="EUR">2599.50</cbc:PayableAmount>', $xml);
        self::assertStringNotContainsString('E+', $xml);
    }

    public function test_missing_totals_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BG-22');

        (new UblWriter)->write(InvoiceFactory::mixedVat());
    }

    private static function contents(string $file): string
    {
        $contents = file_get_contents($file);
        self::assertIsString($contents);

        return $contents;
    }
}
