<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Support\Mutator;
use IntrAgile\EInvoicing\Validation\DynamicError;
use IntrAgile\EInvoicing\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Differential conformance: the PHP validator must give exactly the verdict of the official CEN
 * EN 16931 1.3.17 stylesheets run by Saxon-HE, on the official examples, the CEN unit-test
 * corpus, seeded mutations of the examples and model-built invoices.
 *
 * When Saxon returns a report, the sorted set of "<rule id> <flag>" strings must be identical and
 * the validator must report no dynamic error. When Saxon stops with a dynamic error, the validator
 * must report a dynamic error with the same code; its findings are not compared, because Saxon
 * produced none. A mismatch is written to build/conformance/<key>/ for inspection.
 */
#[Group('oracle')]
final class ConformanceTest extends TestCase
{
    private const MUTATIONS_PER_EXAMPLE = 20;

    private const DUMP_DIR = __DIR__.'/../../build/conformance';

    private static ?Validator $validator = null;

    /**
     * @return array<string, array{string, string}>
     */
    public static function examples(): array
    {
        $cases = [];
        foreach (['ubl', 'cii'] as $syntax) {
            foreach (Fixtures::official($syntax) as $file) {
                $cases["example {$syntax} ".basename($file)] = [$syntax, $file];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unitTests(): array
    {
        $cases = [];
        foreach (Fixtures::unitTests() as $document) {
            $cases['unit '.$document->name()] = [$document->syntax, $document->xml];
        }

        return $cases;
    }

    /**
     * Mutations are identified by file and label and built in the test: one official example is
     * 3 MB, and holding all its variants in the data set would exhaust the memory limit.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function mutations(): array
    {
        $cases = [];
        foreach (['ubl', 'cii'] as $syntax) {
            foreach (Fixtures::official($syntax) as $file) {
                $name = basename($file);
                foreach (Mutator::labels(self::contents($file), crc32($name), self::MUTATIONS_PER_EXAMPLE) as $label) {
                    $cases["mutation {$syntax} {$name} {$label}"] = [$syntax, $file, $label];
                }
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function modelInvoices(): array
    {
        $invoices = [
            'mixed VAT' => TotalsCalculator::complete(InvoiceFactory::mixedVat()),
            'not subject to VAT' => TotalsCalculator::complete(InvoiceFactory::notSubjectToVat()),
            'hostile text' => InvoiceFactory::hostileText(),
        ];
        $cases = [];
        foreach ($invoices as $name => $invoice) {
            $cases["model ubl {$name}"] = ['ubl', (new UblWriter)->write($invoice)];
            $cases["model cii {$name}"] = ['cii', (new CiiWriter)->write($invoice)];
        }

        return $cases;
    }

    /**
     * Documents in which only a rule-context predicate raises a dynamic error: a charge indicator
     * that is not a boolean fails "udt:Indicator = false()" in the contexts of the allowance and
     * charge rules. XSLT treats the error as "no match" and Saxon still produces a report.
     *
     * @return array<string, array{string, string}>
     */
    public static function contextErrorDocuments(): array
    {
        $cases = [];
        foreach (['CII_example2.xml', 'CII_example5.xml', 'huf_example_cii.xml'] as $name) {
            $document = new DOMDocument;
            $document->loadXML(self::contents(self::example('cii', $name)), LIBXML_NONET);
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
            $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
            $indicator = ($xpath->query('(//ram:SpecifiedTradeAllowanceCharge/ram:ChargeIndicator/udt:Indicator)[1]') ?: null)?->item(0);
            if (! $indicator instanceof DOMElement) {
                throw new RuntimeException("{$name} has no charge indicator.");
            }
            $indicator->textContent = 'yes';
            $cases["context error cii {$name}"] = ['cii', (string) $document->saveXML()];
        }

        return $cases;
    }

    /**
     * Every document of the corpus except the mutations, which test_wrapper_keeps_the_official_verdict_on_mutations
     * builds one at a time.
     *
     * @return array<string, array{string, string}>
     */
    public static function guardDocuments(): array
    {
        $cases = array_map(static fn (array $case): array => [$case[0], self::contents($case[1])], self::examples());

        return [...$cases, ...self::unitTests(), ...self::contextErrorDocuments(), ...self::modelInvoices()];
    }

    public function test_corpus_sizes(): void
    {
        self::assertCount(34, self::examples());
        self::assertCount(1169, self::unitTests());
        self::assertCount(34 * self::MUTATIONS_PER_EXAMPLE, self::mutations());
        self::assertCount(6, self::modelInvoices());
        self::assertCount(3, self::contextErrorDocuments());
    }

    /**
     * The oracle runs the official stylesheet through a wrapper that only catches dynamic errors;
     * on documents without such an error it must report exactly what the stylesheet reports when
     * it is the entry point itself.
     */
    #[DataProvider('guardDocuments')]
    public function test_wrapper_keeps_the_official_verdict(string $syntax, string $xml): void
    {
        $this->assertWrapperKeepsVerdict($syntax, $xml);
    }

    #[DataProvider('mutations')]
    public function test_wrapper_keeps_the_official_verdict_on_mutations(string $syntax, string $file, string $label): void
    {
        $this->assertWrapperKeepsVerdict($syntax, self::mutated($file, $label));
    }

    #[DataProvider('examples')]
    public function test_official_examples(string $syntax, string $file): void
    {
        $this->assertParity($syntax, self::contents($file));
    }

    #[DataProvider('unitTests')]
    public function test_unit_test_corpus(string $syntax, string $xml): void
    {
        $this->assertParity($syntax, $xml);
    }

    #[DataProvider('mutations')]
    public function test_mutated_examples(string $syntax, string $file, string $label): void
    {
        $this->assertParity($syntax, self::mutated($file, $label));
    }

    #[DataProvider('modelInvoices')]
    public function test_model_invoices(string $syntax, string $xml): void
    {
        $this->assertParity($syntax, $xml);
    }

    #[DataProvider('contextErrorDocuments')]
    public function test_rule_context_errors_drop_only_the_node(string $syntax, string $xml): void
    {
        try {
            OfficialRules::run(Fixtures::officialXslt($syntax), $xml);
        } catch (OracleDynamicError $error) {
            self::fail("The crafted document must not stop Saxon, but it raised {$error->getMessage()}.");
        }

        $this->assertParity($syntax, $xml);
    }

    private function assertWrapperKeepsVerdict(string $syntax, string $xml): void
    {
        $xslt = Fixtures::officialXslt($syntax);
        try {
            $wrapped = OfficialRules::run($xslt, $xml);
        } catch (OracleDynamicError $error) {
            // The direct run would throw without a code and crash the next transformation.
            self::assertNotSame('', $error->errorCode);

            return;
        }

        self::assertSame(OfficialRules::verdict(OfficialRules::runDirect($xslt, $xml)), OfficialRules::verdict($wrapped));
    }

    private function assertParity(string $syntax, string $xml): void
    {
        $report = (self::$validator ??= Validator::en16931())->validate($xml);
        $ours = $report->verdict();
        $errorCodes = array_values(array_unique(array_map(static fn (DynamicError $error): string => $error->errorCode, $report->dynamicErrors)));
        sort($errorCodes, SORT_STRING);

        try {
            $official = OfficialRules::verdict(OfficialRules::run(Fixtures::officialXslt($syntax), $xml));
        } catch (OracleDynamicError $error) {
            if (! in_array($error->errorCode, $errorCodes, true)) {
                $message = sprintf(
                    "Saxon stopped with %s; the validator reported dynamic errors [%s].\nSaxon: %s",
                    $error->errorCode,
                    implode(', ', $errorCodes),
                    $error->getMessage(),
                );
                $this->dump($xml, 'error '.$error->getMessage(), $this->describe($ours, $report->dynamicErrors));
                self::fail($message);
            }
            $this->addToAssertionCount(1);

            return;
        }

        if ($official === $ours && $report->dynamicErrors === []) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->dump($xml, implode("\n", $official), $this->describe($ours, $report->dynamicErrors));
        self::fail(sprintf(
            "Verdicts differ.\nOnly Saxon: %s\nOnly ours: %s\nOur dynamic errors: %s",
            implode(', ', array_diff($official, $ours)) ?: '-',
            implode(', ', array_diff($ours, $official)) ?: '-',
            implode('; ', array_map(static fn (DynamicError $error): string => "{$error->errorCode} {$error->ruleId} at {$error->location}: {$error->message}", $report->dynamicErrors)) ?: '-',
        ));
    }

    /**
     * @param  list<string>  $verdict
     * @param  list<DynamicError>  $errors
     */
    private function describe(array $verdict, array $errors): string
    {
        $lines = $verdict;
        foreach ($errors as $error) {
            $lines[] = "error {$error->errorCode} {$error->ruleId} at {$error->location}: {$error->message}";
        }

        return implode("\n", $lines);
    }

    private function dump(string $xml, string $official, string $ours): void
    {
        $dir = self::DUMP_DIR.'/'.preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $this->dataName());
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create {$dir}.");
        }
        file_put_contents($dir.'/document.xml', $xml);
        file_put_contents($dir.'/saxon.txt', $official."\n");
        file_put_contents($dir.'/ours.txt', $ours."\n");
    }

    private static function mutated(string $file, string $label): string
    {
        return Mutator::mutation(self::contents($file), crc32(basename($file)), self::MUTATIONS_PER_EXAMPLE, $label);
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
