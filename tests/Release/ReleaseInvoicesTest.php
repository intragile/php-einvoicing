<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Release;

use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Tests\Support\ReleaseInvoices;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Validation\FacturX\FacturXValidator;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReleaseInvoicesTest extends TestCase
{
    private static ?XRechnungValidator $validator = null;

    /**
     * Case keys only: the documents are written inside the test method.
     *
     * @return iterable<string, array{string}>
     */
    public static function documentKeys(): iterable
    {
        foreach (['xrechnung-ubl-invoice', 'xrechnung-ubl-creditnote', 'xrechnung-cii', 'xrechnung-extension-ubl', 'xrechnung-extension-cii'] as $key) {
            yield $key => [$key];
        }
    }

    #[DataProvider('documentKeys')]
    public function test_release_document_is_valid_xrechnung(string $key): void
    {
        $documents = ReleaseInvoices::documents();
        self::assertArrayHasKey($key, $documents);

        $report = self::validator()->validate($documents[$key]);

        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertSame([], $report->fatal(), self::describe($report));
        self::assertTrue($report->isValid(), self::describe($report));
    }

    /**
     * Case keys only: the Factur-X profiles with Factur-X rules of their own.
     *
     * @return iterable<string, array{string}>
     */
    public static function facturxProfiles(): iterable
    {
        foreach (FacturXProfile::cases() as $profile) {
            if ($profile->ruleProfile() !== null) {
                yield $profile->name => [$profile->name];
            }
        }
    }

    /**
     * The release gate requires the official Factur-X rules to report nothing at all for the
     * release invoice, warnings included. The release invoice states its date of supply (BT-72),
     * which a German invoice must show, so the delivery element the CII schema requires is not
     * empty (PEPPOL-EN16931-R008 in BASIC, EN 16931 and EXTENDED).
     */
    #[DataProvider('facturxProfiles')]
    public function test_release_invoice_gives_no_facturx_finding(string $case): void
    {
        $profile = constant(FacturXProfile::class.'::'.$case);
        self::assertInstanceOf(FacturXProfile::class, $profile);
        $xml = (new FacturXXmlWriter)->write(ReleaseInvoices::germanDomestic(), $profile)->xml;

        $report = FacturXValidator::create()->validate($xml, $profile);

        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertSame([], $report->verdict(), self::describe($report));
    }

    public function test_documents_has_exactly_the_release_keys(): void
    {
        self::assertSame(
            ['xrechnung-ubl-invoice', 'xrechnung-ubl-creditnote', 'xrechnung-cii', 'xrechnung-extension-ubl', 'xrechnung-extension-cii'],
            array_keys(ReleaseInvoices::documents()),
        );
    }

    public function test_invoices_carry_the_release_identifiers(): void
    {
        $invoice = ReleaseInvoices::germanDomestic();
        self::assertSame('RE-2026-0815', $invoice->number);
        self::assertSame(ReleaseInvoices::DELIVERY_DATE, $invoice->delivery?->actualDeliveryDate?->format('Y-m-d'));
        self::assertSame(XRechnung::CIUS, $invoice->processControl?->specificationIdentifier);
        self::assertNotNull($invoice->totals);
        self::assertSame('1130.50', (string) $invoice->totals->amountDue);
        self::assertSame('RE-2026-0816', ReleaseInvoices::germanDomestic('RE-2026-0816')->number);

        $creditNote = ReleaseInvoices::germanCreditNote();
        self::assertSame('GS-2026-0042', $creditNote->number);
        self::assertSame(InvoiceTypeCode::C_381, $creditNote->type);
        self::assertSame('RE-2026-0815', $creditNote->precedingInvoices[0]->number ?? null);

        self::assertSame(XRechnung::EXTENSION, ReleaseInvoices::germanExtension()->processControl?->specificationIdentifier);

        $bis3 = ReleaseInvoices::bis3('BIS-2026-0001');
        self::assertSame('BIS-2026-0001', $bis3->number);
        self::assertSame(PeppolBis3::CUSTOMIZATION, $bis3->processControl?->specificationIdentifier);
        self::assertSame(PeppolBis3::PROCESS, $bis3->processControl->businessProcessType);
        self::assertNotNull($bis3->totals);
        self::assertSame(ElectronicAddressScheme::C_9930, $bis3->seller->electronicAddress?->scheme);
        self::assertSame(ElectronicAddressScheme::C_0204, $bis3->buyer->electronicAddress?->scheme);
        self::assertSame(ElectronicAddressScheme::EM, ReleaseInvoices::germanDomestic()->seller->electronicAddress?->scheme);
    }

    private static function validator(): XRechnungValidator
    {
        return self::$validator ??= XRechnungValidator::create();
    }

    private static function describe(ValidationReport $report): string
    {
        $lines = array_map(static fn (Finding $finding): string => "{$finding->ruleId} {$finding->severity->value} at {$finding->location}: {$finding->message}", $report->findings);
        foreach ($report->schemaErrors as $error) {
            $lines[] = 'XSD: '.trim($error->message);
        }

        return implode("\n", $lines);
    }
}
