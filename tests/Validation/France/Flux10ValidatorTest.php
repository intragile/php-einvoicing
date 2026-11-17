<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\France;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\France\Flux10Validator;
use IntrAgile\EInvoicing\Validation\France\FranceSources;
use IntrAgile\EInvoicing\Validation\MessageType;
use IntrAgile\EInvoicing\Validation\MessageValidationReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('france-rules')]
final class Flux10ValidatorTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../fixtures/france/';

    private static ?Flux10Validator $validator = null;

    private static function validator(): Flux10Validator
    {
        return self::$validator ??= Flux10Validator::create(InstalledFranceRules::dir());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fixtures(): iterable
    {
        yield 'transactions' => ['flux10-base.xml'];
        yield 'payments' => ['flux10-payments-base.xml'];
    }

    #[DataProvider('fixtures')]
    public function test_flux10_fixtures_are_valid(string $name): void
    {
        $report = self::validator()->validate(self::fixture($name));

        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertSame([], $report->dynamicErrors, self::describe($report));
        self::assertSame([], $report->findings, self::describe($report));
        self::assertTrue($report->isValid());
        self::assertSame(MessageType::Flux10, $report->type);
        self::assertSame(['fr-flux10 '.FranceSources::VERSION], $report->ruleSets);
    }

    public function test_flux10_with_both_reports_fires_g6_29(): void
    {
        $payments = self::fixture('flux10-payments-base.xml');
        $start = strpos($payments, '<PaymentsReport>');
        $end = strpos($payments, '</PaymentsReport>');
        self::assertIsInt($start);
        self::assertIsInt($end);
        $paymentsReport = substr($payments, $start, $end + strlen('</PaymentsReport>') - $start);
        $xml = str_replace('</TransactionsReport>', '</TransactionsReport>'.$paymentsReport, self::fixture('flux10-base.xml'), $count);
        self::assertSame(1, $count);

        $report = self::validator()->validate($xml);

        self::assertContains('G6.29 fatal', $report->verdict(), self::describe($report));
        self::assertFalse($report->isValid());
    }

    public function test_rule_set_runs_when_the_schema_check_fails(): void
    {
        // An element the schema does not allow, next to a value a rule refuses.
        $xml = str_replace(
            ['<TypeCode>IN</TypeCode>', '</ReportDocument>'],
            ['<TypeCode>XX</TypeCode>', '<Unexpected/></ReportDocument>'],
            self::fixture('flux10-base.xml'),
            $count,
        );
        self::assertSame(2, $count);

        $report = self::validator()->validate($xml);

        self::assertNotSame([], $report->schemaErrors, self::describe($report));
        self::assertContains('G8.01 fatal', $report->verdict(), self::describe($report));
    }

    public function test_namespaced_report_is_refused(): void
    {
        $xml = str_replace('<Report>', '<Report xmlns="urn:example:flux10">', self::fixture('flux10-base.xml'), $count);
        self::assertSame(1, $count);

        try {
            self::validator()->validate($xml);
            self::fail('A namespaced Report was accepted as a Flux 10 transmission.');
        } catch (UnsupportedDocument $exception) {
            self::assertStringContainsString('{urn:example:flux10}Report', $exception->getMessage());
            self::assertStringContainsString('{}Report', $exception->getMessage());
        }
    }

    public function test_missing_rules_name_the_install_command(): void
    {
        $dir = BuildDir::create('flux10-validator-empty');
        try {
            $this->expectException(RulesNotInstalled::class);
            $this->expectExceptionMessage('vendor/bin/einvoicing-rules install france');

            Flux10Validator::create($dir)->validate(self::fixture('flux10-base.xml'));
        } finally {
            BuildDir::remove($dir);
        }
    }

    private static function fixture(string $name): string
    {
        $xml = file_get_contents(self::FIXTURES.$name);
        self::assertIsString($xml);

        return $xml;
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
