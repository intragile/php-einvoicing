<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\InstalledRules;
use IntrAgile\EInvoicing\Validation\Exception\RulesNotInstalled;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class PeppolValidatorTest extends TestCase
{
    /**
     * Every invoice and credit note among the Peppol examples (rules/examples and rules/snippets)
     * for which Saxon, running OpenPeppol's Schematron compiled by the ISO skeleton, reports no
     * fatal finding (PeppolConformanceTest, oracle group). Hard-coded so that this test runs
     * without the oracle; the conformance run proves the list.
     */
    private const VALID_EXAMPLES = [
        'examples/Allowance-example.xml',
        'examples/Vat-category-S.xml',
        'examples/base-creditnote-correction.xml',
        'examples/base-example.xml',
        'examples/base-example_profile02.xml',
        'examples/base-negative-inv-correction.xml',
        'examples/sales-order-example.xml',
        'examples/vat-category-E.xml',
        'examples/vat-category-O.xml',
        'examples/vat-category-Z.xml',
        'snippets/Calc.xml',
        'snippets/CreditNote-snippets.xml',
        'snippets/Snippet-cn.xml',
        'snippets/Snippet-full-profile-02.xml',
        'snippets/Snippet-full.xml',
        'snippets/Snippet-refs.xml',
        'snippets/base-creditnote-correction.xml',
        'snippets/base-example.xml',
        'snippets/base-negative-inv-correction.xml',
        'snippets/direct-debit.xml',
        'snippets/giro.xml',
        'snippets/item.xml',
        'snippets/payment-credit.xml',
        'snippets/payment1.xml',
        'snippets/price.xml',
        'snippets/vat-calc.xml',
    ];

    public function test_examples_without_a_fatal_finding_are_valid(): void
    {
        $validator = self::validator();
        $valid = [];
        foreach (Fixtures::peppolExamples() as $name => $xml) {
            $report = $validator->validate($xml);
            self::assertSame([], $report->dynamicErrors, $name);
            if ($report->isValid()) {
                $valid[] = $name;
            }
        }

        self::assertSame(self::VALID_EXAMPLES, $valid);
    }

    public function test_official_base_example_is_valid(): void
    {
        $report = self::validator()->validate(self::example('examples/base-example.xml'));

        self::assertSame([], $report->dynamicErrors);
        self::assertTrue($report->isValid(), implode("\n", $report->verdict()));
    }

    public function test_report_lists_the_cen_layer_before_the_peppol_layer(): void
    {
        $report = self::validator()->validate(self::example('examples/base-example.xml'));

        self::assertSame(Syntax::Ubl, $report->syntax);
        self::assertSame(['peppol-cen-ubl 3.0.22', 'peppol-ubl 3.0.22'], $report->ruleSets);
    }

    public function test_cen_example_without_peppol_profile_is_invalid(): void
    {
        $report = self::validator()->validate(self::contents(self::cenExample('ubl', 'guide-example1.xml')));

        self::assertFalse($report->isValid());
        self::assertNotSame([], self::peppolIds($report->fatal()), implode("\n", $report->verdict()));
    }

    public function test_cii_runs_both_layers(): void
    {
        $report = self::validator()->validate(self::contents(self::cenExample('cii', 'CII_example7.xml')));

        self::assertSame(Syntax::Cii, $report->syntax);
        self::assertSame(['peppol-cen-cii 3.0.22', 'peppol-cii 3.0.22'], $report->ruleSets);
        self::assertNotSame([], self::peppolIds($report->fatal()), implode("\n", $report->verdict()));
    }

    public function test_huge_identifier_validates_quickly(): void
    {
        $validator = self::validator();
        $xml = self::example('examples/base-example.xml');
        $validator->validate($xml);
        $endpoint = '<cbc:EndpointID schemeID="0088">9482348239847239874</cbc:EndpointID>';
        self::assertStringContainsString($endpoint, $xml);
        // A 50,000-digit GLN whose check digit is wrong: the GLN rule walks every digit.
        $huge = str_replace($endpoint, '<cbc:EndpointID schemeID="0088">'.str_repeat('1', 49_999).'7</cbc:EndpointID>', $xml);

        $start = hrtime(true);
        $report = $validator->validate($huge);
        $seconds = (hrtime(true) - $start) / 1e9;

        self::assertLessThan(2.0, $seconds);
        self::assertSame(['peppol-cen-ubl 3.0.22', 'peppol-ubl 3.0.22'], $report->ruleSets);
        self::assertSame([], $report->dynamicErrors);
        self::assertContains('PEPPOL-COMMON-R040 fatal', $report->verdict());
    }

    public function test_order_documents_are_not_validated(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('Order');

        self::validator()->validate('<Order xmlns="urn:oasis:names:specification:ubl:schema:xsd:Order-2"><ID xmlns="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">1</ID></Order>');
    }

    public function test_missing_rules_name_the_install_command(): void
    {
        $dir = dirname(__DIR__, 2).'/build/no-peppol-rules-here';

        $this->expectException(RulesNotInstalled::class);
        $this->expectExceptionMessage('vendor/bin/einvoicing-rules install peppol');

        Validator::peppol($dir);
    }

    private static function validator(): Validator
    {
        return Validator::peppol(InstalledRules::dir());
    }

    /**
     * @param  list<Finding>  $findings
     * @return list<string>
     */
    private static function peppolIds(array $findings): array
    {
        return array_values(array_filter(
            array_map(static fn (Finding $finding): string => $finding->ruleId, $findings),
            static fn (string $id): bool => str_starts_with($id, 'PEPPOL-'),
        ));
    }

    private static function example(string $name): string
    {
        return Fixtures::peppolExamples()[$name] ?? self::fail("{$name} is not in the Peppol corpus.");
    }

    private static function cenExample(string $syntax, string $file): string
    {
        foreach (Fixtures::official($syntax) as $path) {
            if (basename($path) === $file) {
                return $path;
            }
        }
        self::fail("{$file} is not among the CEN {$syntax} examples.");
    }

    private static function contents(string $path): string
    {
        return (string) file_get_contents($path);
    }
}
