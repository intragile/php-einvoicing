<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France;

use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use IntrAgile\EInvoicing\Tests\Validation\France\InstalledFranceRules;
use IntrAgile\EInvoicing\Validation\France\FranceValidator;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('france-rules')]
final class FrenchRulesTest extends TestCase
{
    private static ?FranceValidator $validator = null;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function documents(): iterable
    {
        foreach (FrenchWritersTest::factories() as $name => [$factory]) {
            yield "{$name} UBL" => [$factory, 'ubl'];
            yield "{$name} CII" => [$factory, 'cii'];
        }
    }

    #[DataProvider('documents')]
    public function test_french_invoice_is_valid_under_the_french_rules(string $factory, string $syntax): void
    {
        $invoice = FrenchInvoices::all()[$factory];
        $xml = $syntax === 'ubl' ? (new UblWriter)->write($invoice) : (new CiiWriter)->write($invoice);

        $report = self::validator()->validate($xml);

        self::assertTrue($report->isValid(), self::describe($report));
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
}
