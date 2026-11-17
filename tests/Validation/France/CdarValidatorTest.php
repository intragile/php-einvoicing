<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\France;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\BuildDir;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\France\CdarValidator;
use IntrAgile\EInvoicing\Validation\France\FranceSources;
use IntrAgile\EInvoicing\Validation\France\MessageType;
use IntrAgile\EInvoicing\Validation\MessageValidationReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('france-rules')]
final class CdarValidatorTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../../fixtures/france/cdar-base.xml';

    private static ?CdarValidator $validator = null;

    private static function validator(): CdarValidator
    {
        return self::$validator ??= CdarValidator::create(InstalledFranceRules::dir());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function examples(): iterable
    {
        if (! Fixtures::hasFranceRfe()) {
            yield 'France_RFE not downloaded' => [''];

            return;
        }
        foreach (Fixtures::franceExamples('CDAR') as $name => $path) {
            yield $name => [$path];
        }
    }

    public function test_example_list_has_every_fnfe_cdar_example(): void
    {
        self::assertCount(59, Fixtures::franceExamples('CDAR'));
    }

    #[DataProvider('examples')]
    public function test_every_fnfe_cdar_example_is_valid(string $path): void
    {
        if ($path === '') {
            // Skips the test: the examples have not been downloaded.
            Fixtures::franceRfeRoot();
        }
        $xml = file_get_contents($path);
        self::assertIsString($xml);

        $report = self::validator()->validate($xml);

        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertSame([], $report->dynamicErrors, self::describe($report));
        self::assertSame([], $report->findings, self::describe($report));
        self::assertTrue($report->isValid());
    }

    public function test_cdar_base_fixture_is_valid(): void
    {
        $report = self::validator()->validate((string) file_get_contents(self::FIXTURE));

        self::assertSame([], $report->schemaErrors, self::describe($report));
        self::assertSame([], $report->dynamicErrors, self::describe($report));
        self::assertSame([], $report->findings, self::describe($report));
        self::assertTrue($report->isValid());
    }

    public function test_report_lists_the_rule_set(): void
    {
        $report = self::validator()->validate((string) file_get_contents(self::FIXTURE));

        self::assertSame(MessageType::Cdar, $report->type);
        self::assertSame(['fr-cdar '.FranceSources::VERSION], $report->ruleSets);
    }

    public function test_finding_makes_the_message_invalid(): void
    {
        $xml = str_replace('<ram:StatusCode>', '<ram:StatusCode>9', (string) file_get_contents(self::FIXTURE), $count);
        self::assertGreaterThan(0, $count);

        $report = self::validator()->validate($xml);

        self::assertFalse($report->isValid());
        self::assertNotSame([], $report->verdict());
    }

    public function test_invoice_given_to_cdar_validator_is_refused(): void
    {
        $xml = file_get_contents(Fixtures::franceRfeRoot().'/FNFE_RFE_INVOICE/Z.example/TEST/CII/EN16931/UC1_F202500003_00-INV_20250701_EN16931_FX_CII_Commentee.xml');
        self::assertIsString($xml);

        try {
            self::validator()->validate($xml);
            self::fail('A CII invoice was accepted as a CDAR message.');
        } catch (UnsupportedDocument $exception) {
            self::assertStringContainsString('{urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100}CrossIndustryInvoice', $exception->getMessage());
            self::assertStringContainsString('{urn:un:unece:uncefact:data:standard:CrossDomainAcknowledgementAndResponse:100}CrossDomainAcknowledgementAndResponse', $exception->getMessage());
        }
    }

    public function test_missing_rules_name_the_install_command(): void
    {
        $dir = BuildDir::create('cdar-validator-empty');
        try {
            $this->expectException(RulesNotInstalled::class);
            $this->expectExceptionMessage('vendor/bin/einvoicing-rules install france');

            CdarValidator::create($dir)->validate((string) file_get_contents(self::FIXTURE));
        } finally {
            BuildDir::remove($dir);
        }
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
