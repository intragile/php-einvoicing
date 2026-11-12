<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation;

use Brick\Math\BigDecimal;
use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Rules\En16931\RuleFiles;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\HostileXml;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Validation\DynamicError;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\Severity;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use IntrAgile\EInvoicing\Validation\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ValidatorTest extends TestCase
{
    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    /**
     * Fatal rule ids that the official rules report on the original CEN examples, keyed by
     * "<syntax> <file name>". Taken from the SaxonC oracle on CEN 1.3.17 (OfficialRules::fatal() on
     * every file of Fixtures::official()): it rejects none of them.
     *
     * @var array<string, list<string>>
     */
    private static array $oracleFatal = [];

    /**
     * @return array<string, array{string, string}>
     */
    public static function officialExamples(): array
    {
        $cases = [];
        foreach (['ubl', 'cii'] as $syntax) {
            foreach (Fixtures::official($syntax) as $file) {
                $cases["{$syntax} ".basename($file)] = [$syntax, $file];
            }
        }

        return $cases;
    }

    #[DataProvider('officialExamples')]
    public function test_official_examples_get_the_oracle_verdict(string $syntax, string $file): void
    {
        $report = Validator::en16931()->validate(self::contents($file));

        self::assertSame(Syntax::from($syntax), $report->syntax);
        self::assertSame([], $report->dynamicErrors);
        $expected = self::$oracleFatal["{$syntax} ".basename($file)] ?? [];
        self::assertSame($expected, self::fatalIds($report), implode("\n", $report->verdict()));
        self::assertSame($expected === [], $report->isValid());
    }

    public function test_report_names_the_rule_set(): void
    {
        $report = Validator::en16931()->validate(self::contents(self::example('ubl', 'ubl-tc434-example1.xml')));

        self::assertSame(['en16931-ubl 1.3.17'], $report->ruleSets);
    }

    public function test_report_filters_findings_by_severity(): void
    {
        $finding = static fn (string $id, Severity $severity): Finding => new Finding($id, $severity, '/', "[{$id}]", 'false()');
        $report = new ValidationReport(['x 1'], Syntax::Ubl, [
            $finding('F-1', Severity::Fatal),
            $finding('I-1', Severity::Information),
            $finding('W-1', Severity::Warning),
            $finding('I-2', Severity::Information),
        ], []);

        self::assertSame(['I-1', 'I-2'], array_map(static fn (Finding $f): string => $f->ruleId, $report->information()));
        self::assertSame(['W-1'], array_map(static fn (Finding $f): string => $f->ruleId, $report->warnings()));
        self::assertSame(['F-1'], array_map(static fn (Finding $f): string => $f->ruleId, $report->fatal()));
        self::assertFalse($report->isValid());
    }

    public function test_model_invoice_written_as_cii_is_valid(): void
    {
        $report = Validator::en16931()->validateInvoice(TotalsCalculator::complete(InvoiceFactory::mixedVat()), Syntax::Cii);

        self::assertSame(Syntax::Cii, $report->syntax);
        self::assertTrue($report->isValid(), implode("\n", $report->verdict()));
    }

    public function test_model_invoice_is_written_as_ubl_by_default(): void
    {
        $report = Validator::en16931()->validateInvoice(TotalsCalculator::complete(InvoiceFactory::notSubjectToVat()));

        self::assertSame(Syntax::Ubl, $report->syntax);
        self::assertTrue($report->isValid(), implode("\n", $report->verdict()));
    }

    public function test_detects_br_co_16(): void
    {
        $xml = self::mutate(self::example('ubl', 'ubl-tc434-example1.xml'), static function (DOMXPath $xpath): void {
            $amount = self::element($xpath, '//cbc:PayableAmount');
            $amount->textContent = (string) BigDecimal::of(trim($amount->textContent))->plus(1);
        });

        $report = Validator::en16931()->validate($xml);

        self::assertContains('BR-CO-16 fatal', $report->verdict());
        self::assertFalse($report->isValid());
        self::assertContains('BR-CO-16', array_map(static fn ($finding): string => $finding->ruleId, $report->fatal()));
    }

    public function test_whitespace_next_to_a_comment_is_kept_in_string_values(): void
    {
        $xml = self::contents(self::example('cii', 'CII_example7.xml'));
        $code = '<ram:CategoryCode>O</ram:CategoryCode>';
        $header = strrpos($xml, $code);
        self::assertNotFalse($header);
        $xml = substr_replace($xml, '<ram:CategoryCode> <!--x-->O</ram:CategoryCode>', $header, strlen($code));

        $report = Validator::en16931()->validate($xml);

        self::assertContains('BR-48 fatal', $report->verdict());
        self::assertFalse($report->isValid());
    }

    public function test_non_numeric_amount_reports_dynamic_error(): void
    {
        $xml = self::mutate(self::example('ubl', 'ubl-tc434-example1.xml'), static function (DOMXPath $xpath): void {
            self::element($xpath, '//cbc:PayableAmount')->textContent = '12,50';
            self::element($xpath, '/*/cbc:ID')->textContent = '';
        });

        $report = Validator::en16931()->validate($xml);

        self::assertNotSame([], $report->dynamicErrors);
        self::assertContains('FORG0001', self::errorCodes($report));
        self::assertSame('BR-CO-16', $report->dynamicErrors[0]->ruleId);
        self::assertSame('/ubl:Invoice[1]/cac:LegalMonetaryTotal[1]', $report->dynamicErrors[0]->location);
        self::assertFalse($report->isValid());
        self::assertContains('BR-02 fatal', $report->verdict(), 'Rules unrelated to the amount must still be evaluated.');
    }

    public function test_duplicate_singleton_reports_xpty0004(): void
    {
        $xml = self::mutate(self::example('ubl', 'ubl-tc434-example1.xml'), static function (DOMXPath $xpath): void {
            $percent = self::element($xpath, '//cac:InvoiceLine[1]/cac:Item/cac:ClassifiedTaxCategory/cbc:Percent');
            $percent->parentNode?->insertBefore($percent->cloneNode(true), $percent);
        });

        $report = Validator::en16931()->validate($xml);

        self::assertContains('XPTY0004', self::errorCodes($report));
        self::assertFalse($report->isValid());
    }

    public function test_rejects_unknown_root(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('{urn:x}Order');

        Validator::en16931()->validate('<Order xmlns="urn:x"/>');
    }

    public function test_refuses_when_the_rule_set_does_not_handle_the_root(): void
    {
        $validator = new Validator(['ubl' => RuleSet::fromFile(RuleFiles::cii()), 'cii' => RuleSet::fromFile(RuleFiles::cii())]);

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('en16931-cii');

        $validator->validate(self::contents(self::example('ubl', 'ubl-tc434-example1.xml')));
    }

    public function test_rule_sets_of_a_syntax_run_in_order(): void
    {
        $ubl = RuleSet::fromFile(RuleFiles::ubl());
        $validator = new Validator(['ubl' => [$ubl, $ubl], 'cii' => RuleSet::fromFile(RuleFiles::cii())]);
        $xml = self::mutate(self::example('ubl', 'ubl-tc434-example1.xml'), static function (DOMXPath $xpath): void {
            self::element($xpath, '//cbc:PayableAmount')->textContent = '12,50';
            self::element($xpath, '/*/cbc:ID')->textContent = '';
        });

        $single = Validator::en16931()->validate($xml);
        $double = $validator->validate($xml);

        self::assertSame(['en16931-ubl 1.3.17', 'en16931-ubl 1.3.17'], $double->ruleSets);
        self::assertNotSame([], $single->findings);
        self::assertNotSame([], $single->dynamicErrors);
        self::assertEquals([...$single->findings, ...$single->findings], $double->findings);
        self::assertEquals([...$single->dynamicErrors, ...$single->dynamicErrors], $double->dynamicErrors);
    }

    public function test_requires_at_least_one_rule_set_in_a_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ubl');

        new Validator(['ubl' => [], 'cii' => RuleSet::fromFile(RuleFiles::cii())]);
    }

    public function test_requires_a_rule_set_per_syntax(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cii');

        new Validator(['ubl' => RuleSet::fromFile(RuleFiles::ubl())]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hostileDocuments(): array
    {
        return HostileXml::cases();
    }

    #[DataProvider('hostileDocuments')]
    public function test_rejects_doctype(string $xml): void
    {
        $this->expectException(UnsafeXml::class);

        Validator::en16931()->validate($xml);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonXml(): array
    {
        return [
            'empty' => [''],
            'text' => ['not xml at all'],
            'JSON' => ['{"invoice": 1}'],
            'truncated' => ['<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">'],
        ];
    }

    #[DataProvider('nonXml')]
    public function test_rejects_non_xml(string $xml): void
    {
        $this->expectException(UnsupportedDocument::class);

        Validator::en16931()->validate($xml);
    }

    public function test_validation_scales_linearly(): void
    {
        $validator = Validator::en16931();
        $small = (new UblWriter)->write(self::invoiceWithLines(100));
        $large = (new UblWriter)->write(self::invoiceWithLines(1000));
        self::assertTrue($validator->validate($small)->isValid());

        $time = static function (string $xml) use ($validator): float {
            $best = INF;
            for ($run = 0; $run < 3; $run++) {
                $started = hrtime(true);
                $validator->validate($xml);
                $best = min($best, (hrtime(true) - $started) / 1e9);
            }

            return $best;
        };
        $t100 = $time($small);
        $t1000 = $time($large);

        self::assertLessThan(15 * $t100, $t1000, sprintf('100 lines: %.3f s, 1,000 lines: %.3f s.', $t100, $t1000));
        self::assertLessThan(5.0, $t1000, sprintf('1,000 lines took %.3f s.', $t1000));
    }

    private static function invoiceWithLines(int $count): Invoice
    {
        $lines = [];
        for ($i = 1; $i <= $count; $i++) {
            $lines[] = new InvoiceLine(
                id: (string) $i,
                quantity: (string) ($i % 7 + 1),
                unit: UnitCode::H87,
                price: new Price(netPrice: BigDecimal::ofUnscaledValue($i % 5000 + 100, 2)),
                item: new Item(name: "Item {$i}"),
                vat: new LineVat(category: VatCategory::StandardRate, rate: $i % 2 === 0 ? '27' : '5'),
            );
        }

        return TotalsCalculator::complete(InvoiceFactory::mixedVat()->withLines($lines)->withVatBreakdown([]));
    }

    /**
     * @return list<string>
     */
    private static function fatalIds(ValidationReport $report): array
    {
        $ids = array_values(array_unique(array_map(static fn ($finding): string => $finding->ruleId, $report->fatal())));
        sort($ids, SORT_STRING);

        return $ids;
    }

    /**
     * @return list<string>
     */
    private static function errorCodes(ValidationReport $report): array
    {
        return array_map(static fn (DynamicError $error): string => $error->errorCode, $report->dynamicErrors);
    }

    /**
     * @param  callable(DOMXPath): void  $change
     */
    private static function mutate(string $file, callable $change): string
    {
        $document = new DOMDocument;
        $document->loadXML(self::contents($file), LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('cbc', self::CBC);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $change($xpath);

        return (string) $document->saveXML();
    }

    private static function element(DOMXPath $xpath, string $path): DOMElement
    {
        $element = ($xpath->query($path) ?: null)?->item(0);
        if (! $element instanceof DOMElement) {
            throw new RuntimeException("{$path} not found.");
        }

        return $element;
    }

    private static function example(string $syntax, string $name): string
    {
        foreach (Fixtures::official($syntax) as $file) {
            if (basename($file) === $name) {
                return $file;
            }
        }

        throw new RuntimeException("Official example {$name} not found.");
    }

    private static function contents(string $file): string
    {
        return file_get_contents($file) ?: throw new RuntimeException("Cannot read {$file}.");
    }
}
