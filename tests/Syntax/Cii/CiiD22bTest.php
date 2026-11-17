<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Cii;

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\ChargeReasonCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\DocumentAllowance;
use IntrAgile\EInvoicing\Model\DocumentCharge;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\LineCharge;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiVersion;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Support\ReleaseInvoices;
use IntrAgile\EInvoicing\Tests\Support\Xsd;
use IntrAgile\EInvoicing\Tests\Validation\France\InstalledFranceRules;
use IntrAgile\EInvoicing\Validation\France\FranceRules;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class CiiD22bTest extends TestCase
{
    private const D22B_SCHEMA = 'CrossIndustryInvoice_100pD22B.xsd';

    public function test_two_preceding_invoices_are_written_in_d22b(): void
    {
        $xml = (new CiiWriter)->write(self::withTwoPrecedingInvoices());

        self::assertSame(2, substr_count($xml, '<ram:InvoiceReferencedDocument>'));
        self::assertEquals(
            [new PrecedingInvoiceReference(number: 'A-1'), new PrecedingInvoiceReference(number: 'A-2')],
            (new CiiReader)->read($xml)->invoice->precedingInvoices,
        );
    }

    public function test_d16b_still_refuses_two_preceding_invoices(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CII D16B carries one preceding invoice reference (BG-3)');

        (new CiiWriter(CiiVersion::D16B))->write(self::withTwoPrecedingInvoices());
    }

    public function test_xrechnung_cii_is_always_d16b(): void
    {
        $invoice = self::withTwoPrecedingInvoices()->withProcessControl(XRechnung::processControl());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CII D16B carries one preceding invoice reference (BG-3)');

        (new CiiWriter(CiiVersion::D22B))->write($invoice);
    }

    public function test_xrechnung_extension_is_also_d16b(): void
    {
        $invoice = self::withTwoPrecedingInvoices()->withProcessControl(new ProcessControl(XRechnung::EXTENSION));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('D16B');

        (new CiiWriter)->write($invoice);
    }

    #[DataProvider('singlePrecedingDocuments')]
    public function test_single_bg3_output_is_byte_identical_to_previous_writer(Invoice $invoice): void
    {
        // Without a second BG-3 the version only changes the guard, never an element, so the
        // D16B writer (the behaviour before D22B became the default) is the reference output.
        self::assertSame(
            (new CiiWriter(CiiVersion::D16B))->write($invoice),
            (new CiiWriter)->write($invoice),
        );
    }

    #[Group('france-rules')]
    #[DataProvider('matrix')]
    public function test_d22b_output_validates_against_fnfe_d22b_xsd(string $xml): void
    {
        $schema = FranceRules::schema(InstalledFranceRules::dir(), 'cii', self::D22B_SCHEMA);

        self::assertSame([], Xsd::errors($xml, $schema));
    }

    #[DataProvider('matrix')]
    public function test_d22b_output_validates_against_facturx_extended_xsd(string $xml): void
    {
        // The Factur-X EXTENDED schema is a superset of CII D22B for every EN 16931 element.
        self::assertSame([], Xsd::errors($xml, RuleFiles::schema('EXTENDED')));
    }

    /**
     * @return array<string, array{Invoice}>
     */
    public static function singlePrecedingDocuments(): array
    {
        $cases = [];
        foreach (self::invoices() as $name => $invoice) {
            if (count($invoice->precedingInvoices) <= 1) {
                $cases[$name] = [$invoice];
            }
        }

        return $cases;
    }

    /**
     * The five synthetic invoices, the CEN CII examples read and written again, and a credit note
     * with two preceding invoices, each written with the default writer.
     *
     * @return array<string, array{string}>
     */
    public static function matrix(): array
    {
        $writer = new CiiWriter;
        $cases = [];
        foreach (self::invoices() as $name => $invoice) {
            $cases[$name] = [$writer->write($invoice)];
        }

        return $cases;
    }

    /**
     * @return array<string, Invoice>
     */
    private static function invoices(): array
    {
        $base = ReleaseInvoices::germanDomestic('AC-2026-0001');
        $lines = $base->lines;
        $lines[0] = $lines[0]->withNetAmount(null)->withCharge(new LineCharge(amount: '10.00', reason: 'Express handling', reasonCode: ChargeReasonCode::AA));
        $allowanceCharge = TotalsCalculator::complete(
            $base->withLines($lines)
                ->withAllowance(new DocumentAllowance(amount: '50.00', vatCategory: VatCategory::StandardRate, vatRate: '19', baseAmount: '1000.00', percentage: '5', reason: 'Volume discount', reasonCode: AllowanceReasonCode::C_95))
                ->withCharge(new DocumentCharge(amount: '20.00', vatCategory: VatCategory::StandardRate, vatRate: '19', reason: 'Freight', reasonCode: ChargeReasonCode::AAC))
                ->withVatBreakdown([])
                ->withTotals(null),
        );

        $invoices = [
            'release-invoice' => ReleaseInvoices::germanDomestic(),
            'release-creditnote' => ReleaseInvoices::germanCreditNote(),
            'mixed-vat' => TotalsCalculator::complete(InvoiceFactory::mixedVat()),
            'not-subject-to-vat' => TotalsCalculator::complete(InvoiceFactory::notSubjectToVat()),
            'allowance-charge' => $allowanceCharge,
        ];
        $reader = new CiiReader;
        foreach (Fixtures::official('cii') as $file) {
            $invoices['cen-'.basename($file, '.xml')] = $reader->read((string) file_get_contents($file))->invoice;
        }
        $invoices['two-preceding-invoices'] = self::withTwoPrecedingInvoices();

        return $invoices;
    }

    private static function withTwoPrecedingInvoices(): Invoice
    {
        return TotalsCalculator::complete(InvoiceFactory::mixedVat())
            ->withType(InvoiceTypeCode::C_381)
            ->withPrecedingInvoices([
                new PrecedingInvoiceReference(number: 'A-1'),
                new PrecedingInvoiceReference(number: 'A-2'),
            ]);
    }
}
