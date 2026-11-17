<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Flux10;

use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\France\BillingFramework;
use IntrAgile\EInvoicing\France\EReporting\B2cAggregator;
use IntrAgile\EInvoicing\France\EReporting\EReport;
use IntrAgile\EInvoicing\France\EReporting\TransactionCategory;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Syntax\Flux10\Flux10Writer;
use IntrAgile\EInvoicing\Tests\Support\EReports;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use IntrAgile\EInvoicing\Tests\Validation\France\InstalledFranceRules;
use IntrAgile\EInvoicing\Validation\France\Flux10Validator;
use IntrAgile\EInvoicing\Validation\MessageValidationReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('france-rules')]
final class Flux10RulesTest extends TestCase
{
    /**
     * Billing frameworks (BT-23) a Flux 10 invoice may carry (F10-FACTURE-G1.02). A FrenchInvoices
     * factory with another framework cannot be reported as a 10.1 invoice and is left out.
     */
    private const REPORTABLE_FRAMEWORKS = ['B1', 'S1', 'M1', 'B2', 'S2', 'M2', 'B4', 'S4', 'M4', 'S5', 'S6', 'B7', 'S7'];

    /**
     * FrenchInvoices factories left out because their billing framework is outside the G1.02
     * list. Every current factory uses B1 or S1, so none is excluded.
     *
     * @var list<string>
     */
    private const EXCLUDED_FACTORIES = [];

    private static ?Flux10Validator $validator = null;

    private static function validator(): Flux10Validator
    {
        return self::$validator ??= Flux10Validator::create(InstalledFranceRules::dir());
    }

    /**
     * @return iterable<string, array{EReport}>
     */
    public static function reports(): iterable
    {
        foreach (EReports::all() as $name => $report) {
            yield $name => [$report];
        }
    }

    /**
     * Consumer sales aggregated by B2cAggregator: a day of sales at two rates, a day with only a
     * credit note and a day of a mixed invoice split by a classifier.
     *
     * @return iterable<string, array{EReport}>
     */
    public static function aggregatedDays(): iterable
    {
        $sales = (new B2cAggregator)->aggregate([
            EReports::consumerSale(BillingFramework::B1, 'T-1', '2026-11-10', [
                EReports::consumerLine('1', 'GOODS', '50.00', '5.5'),
                EReports::consumerLine('2', 'GOODS', '100.00', '20'),
            ])->withTaxPointDateCode('3'),
            EReports::consumerSale(BillingFramework::B1, 'T-2', '2026-11-10', [EReports::consumerLine('1', 'GOODS', '200.00', '20')])->withTaxPointDateCode('3'),
        ]);
        yield 'sales' => [EReports::days('TR-2026-0201', $sales)];

        $creditNote = (new B2cAggregator)->aggregate([
            EReports::consumerSale(BillingFramework::S1, 'A-1', '2026-11-12', [EReports::consumerLine('1', 'SERVICE', '100.00', '20')], InvoiceTypeCode::C_381),
        ]);
        yield 'credit note' => [EReports::days('TR-2026-0202', $creditNote)];

        $classifier = static fn (InvoiceLine $line, Invoice $invoice): TransactionCategory => match ($line->item->name) {
            'SERVICE' => TransactionCategory::Services,
            'EXPORT' => TransactionCategory::NotTaxedInFrance,
            default => TransactionCategory::Goods,
        };
        $mixed = (new B2cAggregator($classifier))->aggregate([
            EReports::consumerSale(BillingFramework::M1, 'T-3', '2026-11-13', [
                EReports::consumerLine('1', 'GOODS', '10.05', '5.5'),
                EReports::consumerLine('2', 'SERVICE', '100.00', '20'),
                EReports::consumerLine('3', 'GOODS', '10.05', '5.5'),
                EReports::consumerLine('4', 'EXPORT', '40.00', null),
            ]),
        ]);
        yield 'mixed with classifier' => [EReports::days('TR-2026-0203', $mixed)];
    }

    /**
     * @return iterable<string, array{Invoice}>
     */
    public static function frenchInvoices(): iterable
    {
        foreach (self::frenchFactories() as $name => $invoice) {
            if (self::reportable($invoice)) {
                yield $name => [$invoice->withBuyer(EReports::germanBuyer())];
            }
        }
    }

    public function test_excluded_factories_are_the_unreportable_frameworks(): void
    {
        $excluded = array_keys(array_filter(self::frenchFactories(), static fn (Invoice $invoice): bool => ! self::reportable($invoice)));

        self::assertSame(self::EXCLUDED_FACTORIES, $excluded);
    }

    #[DataProvider('reports')]
    public function test_every_ereports_factory_is_valid(EReport $report): void
    {
        self::assertValid((new Flux10Writer)->write($report));
    }

    #[DataProvider('aggregatedDays')]
    public function test_aggregated_consumer_sales_are_valid(EReport $report): void
    {
        self::assertValid((new Flux10Writer)->write($report));
    }

    #[DataProvider('frenchInvoices')]
    public function test_every_french_invoice_reported_as_international_sale_is_valid(Invoice $invoice): void
    {
        self::assertNotNull($invoice->extensions()->get(FrenchInvoice::class));

        self::assertValid((new Flux10Writer)->write(EReports::invoices('TR-2026-0100', [$invoice])));
    }

    /**
     * Every FrenchInvoices factory, by name.
     *
     * @return array<string, Invoice>
     */
    private static function frenchFactories(): array
    {
        return [
            ...FrenchInvoices::all(),
            'extendedCtcFr' => FrenchInvoices::extendedCtcFr(),
            'extendedCtcFrUbl' => FrenchInvoices::extendedCtcFrUbl(),
            'facturXExtended' => FrenchInvoices::facturXExtended(),
        ];
    }

    private static function reportable(Invoice $invoice): bool
    {
        return in_array($invoice->processControl?->businessProcessType, self::REPORTABLE_FRAMEWORKS, true);
    }

    private static function assertValid(string $xml): void
    {
        $report = self::validator()->validate($xml);

        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertSame([], $report->dynamicErrors, self::describe($report));
        self::assertSame([], $report->findings, self::describe($report));
        self::assertTrue($report->isValid());
    }

    private static function describe(MessageValidationReport $report): string
    {
        $lines = [];
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
}
