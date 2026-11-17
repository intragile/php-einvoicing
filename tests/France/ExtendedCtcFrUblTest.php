<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\France\FrenchLine;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\France\InformationalLine;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use IntrAgile\EInvoicing\Tests\Validation\France\InstalledFranceRules;
use IntrAgile\EInvoicing\Validation\France\FranceValidator;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class ExtendedCtcFrUblTest extends TestCase
{
    private const LINE = '/inv:Invoice/cac:InvoiceLine';

    private const TOTAL = '/inv:Invoice/cac:LegalMonetaryTotal/';

    private const UNMAPPED_HEADER_ELEMENTS = ['/Invoice/cbc:UBLVersionID', '/Invoice/cac:PaymentMeans/cac:CardAccount/cbc:NetworkID'];

    private static ?FranceValidator $validator = null;

    public function test_group_detail_information_lines_written_in_line_order(): void
    {
        $x = $this->ubl(FrenchInvoices::extendedCtcFr(false));
        // The line structure is the billing reference to the invoice itself (BT-1), as in the FNFE examples.
        $reference = self::LINE.'/cac:BillingReference[cac:InvoiceDocumentReference/cbc:ID="F202600003"]/';

        self::assertSame(['1', '2', '3', '4'], $this->values($x, self::LINE.'/cbc:ID'));
        self::assertSame(['GROUP', 'DETAIL', 'DETAIL', 'INFORMATION'], $this->values($x, $reference.'cac:InvoiceDocumentReference/cbc:DocumentStatusCode'));
        self::assertSame(['1', '1'], $this->values($x, $reference.'cac:BillingReferenceLine/cbc:ID'));
        self::assertSame(['500.00', '200.00', '300.00', '0.00'], $this->values($x, self::LINE.'/cbc:LineExtensionAmount'));
        self::assertSame(['500.00'], $this->values($x, self::TOTAL.'cbc:LineExtensionAmount'));
    }

    public function test_line_seller_written_in_manufacturer_party(): void
    {
        $x = $this->ubl(FrenchInvoices::extendedCtcFr(false));
        $seller = self::LINE.'[2]/cac:Item/cac:ManufacturerParty/';

        self::assertSame(['587451236587'], $this->values($x, $seller.'cac:PartyIdentification/cbc:ID[@schemeID="0088"]'));
        self::assertSame(['LE VENDEUR'], $this->values($x, $seller.'cac:PartyLegalEntity/cbc:RegistrationName'));
        self::assertSame([FrenchInvoices::SELLER_SIREN], $this->values($x, $seller.'cac:PartyLegalEntity/cbc:CompanyID[@schemeID="0002"]'));
        self::assertSame(['FR'], $this->values($x, $seller.'cac:PostalAddress/cac:Country/cbc:IdentificationCode'));
        self::assertSame(['FR88100000009'], $this->values($x, $seller.'cac:PartyTaxScheme/cbc:CompanyID'));
        self::assertSame(['VAT'], $this->values($x, $seller.'cac:PartyTaxScheme/cac:TaxScheme/cbc:ID'));
    }

    public function test_line_preceding_invoice_and_delivery_written(): void
    {
        $x = $this->ubl(FrenchInvoices::extendedCtcFr(false));
        $line = self::LINE.'[3]/';
        $reference = $line.'cac:BillingReference/cac:InvoiceDocumentReference/';
        $location = $line.'cac:Delivery/cac:DeliveryLocation/';

        // The preceding invoice is a billing reference of its own, after the line structure.
        self::assertSame(['F202600003', 'F202500099'], $this->values($x, $reference.'cbc:ID'));
        self::assertSame(['2025-12-15'], $this->values($x, $reference.'cbc:IssueDate'));
        self::assertSame(['ENTREPOT NORD'], $this->values($x, $location.'cbc:Name'));
        self::assertSame(['5 rue du quai'], $this->values($x, $location.'cac:Address/cbc:StreetName'));
        self::assertSame(['LILLE'], $this->values($x, $location.'cac:Address/cbc:CityName'));
        self::assertSame(['FR'], $this->values($x, $location.'cac:Address/cac:Country/cbc:IdentificationCode'));
        self::assertSame(['2025-12-20'], $this->values($x, $line.'cac:Delivery/cbc:ActualDeliveryDate'));
        self::assertSame(['#AAI#Livraison partielle'], $this->values($x, $line.'cbc:Note'));
    }

    public function test_line_vat_extras_written(): void
    {
        $x = $this->ubl(FrenchInvoices::extendedCtcFr(false, 'TVA SUR LES DEBITS'));
        $line = self::LINE.'[2]/';

        self::assertSame(['TVA SUR LES DEBITS'], $this->values($x, $line.'cac:Item/cac:ClassifiedTaxCategory/cbc:TaxExemptionReason'));
        self::assertSame(['3'], $this->values($x, $line.'cac:InvoicePeriod/cbc:DescriptionCode'));
        self::assertSame(['20.00'], $this->values($x, $line.'cac:TaxTotal/cbc:TaxAmount[@currencyID="EUR"]'));
        self::assertSame(['S1', 'F202600040'], $this->values($x, $line.'cac:DocumentReference/cbc:ID'));
        self::assertSame(['AVV', 'AFL'], $this->values($x, $line.'cac:DocumentReference/cbc:ID/@schemeID'));
    }

    public function test_extended_data_round_trips(): void
    {
        $invoice = FrenchInvoices::extendedCtcFr(false, 'TVA SUR LES DEBITS');
        $read = (new UblReader)->read((new UblWriter)->write($invoice));

        self::assertSame([], $read->ignored);
        self::assertEquals($invoice, $read->invoice);
    }

    public function test_extended_credit_note_round_trips(): void
    {
        $invoice = self::creditNote();
        $xml = (new UblWriter)->write($invoice);
        $read = (new UblReader)->read($xml);

        self::assertStringContainsString('<cac:CreditNoteLine>', $xml);
        self::assertSame([], $read->ignored);
        self::assertEquals($invoice, $read->invoice);
    }

    /**
     * UBL has no element for the line amount with VAT (EXT-FR-FE-184), so the writer refuses it
     * instead of dropping it.
     */
    public function test_line_amount_with_vat_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EXT-FR-FE-184');

        (new UblWriter)->write(FrenchInvoices::extendedCtcFr());
    }

    /**
     * UBL carries the line VAT point date code (EXT-FR-FE-180) in the line period, which EN 16931
     * (BR-CO-20) then requires to have a start or end date.
     */
    public function test_line_vat_date_code_without_line_period_is_refused(): void
    {
        $invoice = FrenchInvoices::extendedCtcFr(false);
        [$first, $second] = $invoice->lines;
        $french = $invoice->extensions()->get(FrenchInvoice::class);
        self::assertNotNull($french);
        [$group, $information] = $french->informationalLines;
        $informationWithCode = new InformationalLine(
            id: $information->id,
            subType: $information->subType,
            item: $information->item,
            quantity: $information->quantity,
            unit: $information->unit,
            netAmount: $information->netAmount,
            french: new FrenchLine(vatDueDateCode: '3'),
        );

        foreach ([
            'regular line' => $invoice->withLines([$first->withPeriod(null), $second]),
            'informational line' => $invoice->withExtension(new FrenchInvoice($french->profile, [$group, $informationWithCode], $french->lineOrder)),
        ] as $case => $broken) {
            try {
                (new UblWriter)->write($broken);
                self::fail("Line VAT date code without a line period written: {$case}");
            } catch (InvalidArgumentException $e) {
                foreach (['EXT-FR-FE-180', 'BR-CO-20', 'BT-134', 'BT-135'] as $term) {
                    self::assertStringContainsString($term, $e->getMessage(), $case);
                }
            }
        }
    }

    /**
     * A line billing reference carrying the invoice number (BT-1) holds the line structure, so a
     * preceding invoice with that number would be read back as no preceding invoice.
     */
    public function test_line_preceding_invoice_with_the_invoice_number_is_refused(): void
    {
        $invoice = FrenchInvoices::extendedCtcFr(false);
        [$first, $second] = $invoice->lines;
        $french = $second->extensions()->get(FrenchLine::class);
        self::assertNotNull($french);
        $second = $second->withExtension(new FrenchLine(
            parentLineId: $french->parentLineId,
            subType: $french->subType,
            precedingInvoice: new PrecedingInvoiceReference($invoice->number, new DateTimeImmutable('2026-10-01')),
        ));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EXT-FR-FE-136');

        (new UblWriter)->write($invoice->withLines([$first, $second]));
    }

    /**
     * Without the extended profile no EXT-FR-FE element is read, so the GROUP line, which has no
     * VAT category, makes the document unsupported.
     */
    public function test_extended_elements_are_not_read_outside_the_extended_profile(): void
    {
        $xml = (new UblWriter)->write(FrenchInvoices::extendedCtcFr(false));
        $xml = str_replace(FrenchProfile::ExtendedCtcFr->value.'<', FrenchProfile::En16931->value.'<', $xml);

        $this->expectExceptionMessage('BT-151');

        (new UblReader)->read($xml);
    }

    public function test_non_extended_output_is_unchanged_by_french_line_support(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $xml = (new UblWriter)->write($invoice);

        self::assertStringNotContainsString('BillingReferenceLine', $xml);
        self::assertStringNotContainsString('ManufacturerParty', $xml);
        self::assertEquals($invoice, (new UblReader)->read($xml)->invoice);
    }

    #[Group('france-rules')]
    public function test_written_extended_invoice_is_valid_under_the_french_rules(): void
    {
        $report = self::validator()->validate((new UblWriter)->write(FrenchInvoices::extendedCtcFr(false)));

        self::assertTrue($report->isValid(), self::describe($report));
    }

    #[Group('france-rules')]
    public function test_written_extended_credit_note_is_schema_valid(): void
    {
        $report = self::validator()->validate((new UblWriter)->write(self::creditNote()));

        self::assertSame([], $report->schemaErrors, self::describe($report));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function examples(): iterable
    {
        foreach (['UC10_', 'UC11_', 'UC12_', 'UC13_AUTOF', 'UC13_F'] as $prefix) {
            yield $prefix => [$prefix];
        }
    }

    #[Group('france-rules')]
    #[DataProvider('examples')]
    public function test_extended_ctc_fr_ubl_examples_round_trip(string $prefix): void
    {
        $source = (string) file_get_contents(self::example($prefix));

        $read = (new UblReader)->read($source);

        // Only header elements outside EN 16931 and EXTENDED-CTC-FR stay unmapped: the UBL
        // version and, in UC10, a card network identifier that the writer replaces with its
        // placeholder. Every line element is read.
        self::assertSame([], array_diff($read->ignored, self::UNMAPPED_HEADER_ELEMENTS), implode(', ', $read->ignored));
        $invoice = $read->invoice;
        self::assertSame(FrenchProfile::ExtendedCtcFr, $invoice->extensions()->get(FrenchInvoice::class)?->profile);

        $written = (new UblWriter)->write($invoice);
        $report = self::validator()->validate($written);
        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertTrue($report->isValid(), self::describe($report));

        $original = $this->xpath($source);
        $copy = $this->xpath($written);
        foreach (['cbc:LineExtensionAmount' => 'BT-106', 'cbc:TaxExclusiveAmount' => 'BT-109'] as $element => $term) {
            $expected = $this->values($original, self::TOTAL.$element);
            self::assertCount(1, $expected, $term);
            self::assertSame($expected, $this->values($copy, self::TOTAL.$element), $term);
        }

        // BT-106 is the sum of the regular lines; GROUP and INFORMATION lines stay outside it.
        $sum = BigDecimal::zero();
        foreach ($invoice->lines as $line) {
            $sum = $sum->plus($line->netAmount ?? BigDecimal::zero());
        }
        self::assertTrue($sum->isEqualTo($this->values($original, self::TOTAL.'cbc:LineExtensionAmount')[0]), 'BT-106 is the sum of Invoice::$lines');

        // Every line, informational or not, is written back in document order.
        self::assertSame($this->values($original, self::LINE.'/cbc:ID'), $this->values($copy, self::LINE.'/cbc:ID'));

        $again = (new UblReader)->read($written);
        self::assertSame([], $again->ignored);
        self::assertEquals($invoice, $again->invoice);
        self::assertSame($written, (new UblWriter)->write($again->invoice));
    }

    private static function creditNote(): Invoice
    {
        return FrenchInvoices::extendedCtcFr(false)
            ->withNumber('AV202600003')
            ->withType(InvoiceTypeCode::C_381)
            ->withPrecedingInvoices([new PrecedingInvoiceReference('F202600001', new DateTimeImmutable('2026-10-01'))]);
    }

    private static function example(string $prefix): string
    {
        foreach (Fixtures::franceExamples('UBL/EXTENDED-CTC-FR') as $name => $file) {
            if (str_starts_with($name, $prefix)) {
                return $file;
            }
        }

        self::fail("No UBL EXTENDED-CTC-FR example {$prefix}");
    }

    private static function validator(): FranceValidator
    {
        return self::$validator ??= FranceValidator::create(InstalledFranceRules::dir());
    }

    private static function describe(ValidationReport $report): string
    {
        $lines = [implode(' -> ', $report->ruleSets)];
        foreach ($report->schemaErrors as $error) {
            $lines[] = "schema {$error->line}: {$error->message}";
        }
        foreach ($report->dynamicErrors as $error) {
            $lines[] = "dynamic: {$error->ruleId} {$error->errorCode}: {$error->message}";
        }
        foreach ($report->findings as $finding) {
            $lines[] = "{$finding->ruleId} {$finding->severity->value} at {$finding->location}: {$finding->message}";
        }

        return implode("\n", $lines);
    }

    private function ubl(Invoice $invoice): DOMXPath
    {
        return $this->xpath((new UblWriter)->write($invoice));
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));
        $x = new DOMXPath($document);
        $x->registerNamespace('inv', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $x->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $x->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        return $x;
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
