<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Flux10;

use IntrAgile\EInvoicing\France\EReporting\EReport;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\Model\Invoice;
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
