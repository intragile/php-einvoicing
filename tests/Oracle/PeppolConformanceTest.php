<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use DOMDocument;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\ProcessControl;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Support\Mutator;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\InstalledRules;
use IntrAgile\EInvoicing\Validation\DynamicError;
use IntrAgile\EInvoicing\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Differential conformance for Peppol BIS Billing 3.0: Validator::peppol() must give exactly the
 * verdict of OpenPeppol's Schematron files compiled by the ISO skeleton and run by Saxon-HE (see
 * SkeletonGuardTest for why that pipeline stands in for official stylesheets).
 *
 * Saxon's verdict for a document is the union of its two layers, OpenPeppol's copy of the CEN
 * rules and the Peppol rules; like the validator, it requires a rule of the CEN layer to handle
 * the document element. When either layer stops with a dynamic error, the validator must report a
 * dynamic error with each such code and findings are not compared; otherwise the sorted sets of
 * "<rule id> <flag>" strings must be identical and the validator must report no dynamic error.
 * A mismatch is written to the git-ignored build/conformance/peppol/<key>/ for inspection.
 *
 * Documents whose element is not an invoice or credit note (the UBL Orders the Peppol corpus uses
 * for its syntax-independent rules) are outside the validator's scope: it must refuse them.
 */
#[Group('oracle')]
final class PeppolConformanceTest extends TestCase
{
    private const MUTATIONS_PER_EXAMPLE = 20;

    private const DUMP_DIR = __DIR__.'/../../build/conformance/peppol';

    private const CUSTOMIZATION_ID = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    private const PROFILE_ID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /** Document elements the validator accepts, as "{namespace}local-name". */
    private const INVOICE_ROOTS = [
        '{urn:oasis:names:specification:ubl:schema:xsd:Invoice-2}Invoice',
        '{urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2}CreditNote',
        '{urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100}CrossIndustryInvoice',
    ];

    private static ?Validator $validator = null;

    /**
     * @return array<string, array{string, string}>
     */
    public static function cenExamples(): array
    {
        $cases = [];
        foreach (['ubl', 'cii'] as $syntax) {
            foreach (Fixtures::official($syntax) as $file) {
                $cases["cen {$syntax} ".basename($file)] = [$syntax, $file];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function peppolExamples(): array
    {
        $cases = [];
        foreach (Fixtures::peppolExamples() as $name => $xml) {
            if (self::isInvoice($xml)) {
                $cases["example {$name}"] = [self::syntax($xml), $xml];
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
        foreach (Fixtures::peppolUnitTests() as $document) {
            if (self::isInvoice($document->xml)) {
                $cases['unit '.$document->name()] = [$document->syntax, $document->xml];
            }
        }

        return $cases;
    }

    /**
     * Corpus documents of another kind than an invoice or credit note.
     *
     * @return array<string, array{string}>
     */
    public static function otherDocuments(): array
    {
        $cases = [];
        foreach (Fixtures::peppolExamples() as $name => $xml) {
            if (! self::isInvoice($xml)) {
                $cases["example {$name}"] = [$xml];
            }
        }
        foreach (Fixtures::peppolUnitTests() as $document) {
            if (! self::isInvoice($document->xml)) {
                $cases['unit '.$document->name()] = [$document->xml];
            }
        }

        return $cases;
    }

    /**
     * Seeded mutations of every Peppol example (seed = crc32 of the example name), including the
     * Peppol kinds: a wrong check digit and another business process. Documents are built in the
     * test.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function mutations(): array
    {
        $cases = [];
        foreach (Fixtures::peppolExamples() as $name => $xml) {
            if (! self::isInvoice($xml)) {
                continue;
            }
            foreach (Mutator::labels($xml, crc32($name), self::MUTATIONS_PER_EXAMPLE, Mutator::PEPPOL_KINDS) as $label) {
                $cases["mutation {$name} {$label}"] = [self::syntax($xml), $name, $label];
            }
        }

        return $cases;
    }

    /**
     * Model-built invoices with the Peppol customization and business process identifiers.
     *
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
            $invoice = self::peppol($invoice);
            $cases["model ubl {$name}"] = ['ubl', (new UblWriter)->write($invoice)];
            $cases["model cii {$name}"] = ['cii', (new CiiWriter)->write($invoice)];
        }

        return $cases;
    }

    public function test_corpus_sizes(): void
    {
        self::assertCount(34, self::cenExamples());
        self::assertCount(26, self::peppolExamples());
        self::assertCount(1025, self::unitTests());
        self::assertCount(4, self::otherDocuments());
        self::assertCount(26 * self::MUTATIONS_PER_EXAMPLE, self::mutations());
        self::assertCount(6, self::modelInvoices());
    }

    public function test_mutations_cover_the_peppol_kinds(): void
    {
        $kinds = array_map(static fn (array $case): string => explode(' ', $case[2])[0], self::mutations());

        self::assertContains('check-digit', $kinds);
        self::assertContains('profile-id', $kinds);
    }

    #[DataProvider('cenExamples')]
    public function test_cen_examples(string $syntax, string $file): void
    {
        $this->assertParity($syntax, self::contents($file));
    }

    #[DataProvider('peppolExamples')]
    public function test_peppol_examples(string $syntax, string $xml): void
    {
        $this->assertParity($syntax, $xml);
    }

    #[DataProvider('unitTests')]
    public function test_peppol_unit_test_corpus(string $syntax, string $xml): void
    {
        $this->assertParity($syntax, $xml);
    }

    #[DataProvider('mutations')]
    public function test_mutated_peppol_examples(string $syntax, string $name, string $label): void
    {
        $xml = Fixtures::peppolExamples()[$name] ?? throw new RuntimeException("{$name} is not in the Peppol corpus.");

        $this->assertParity($syntax, Mutator::mutation($xml, crc32($name), self::MUTATIONS_PER_EXAMPLE, $label, Mutator::PEPPOL_KINDS));
    }

    #[DataProvider('modelInvoices')]
    public function test_model_invoices(string $syntax, string $xml): void
    {
        self::assertStringContainsString(self::PROFILE_ID, $xml);
        $this->assertParity($syntax, $xml);
    }

    #[DataProvider('otherDocuments')]
    public function test_other_documents_are_refused(string $xml): void
    {
        $this->expectException(UnsupportedDocument::class);

        self::validator()->validate($xml);
    }

    private function assertParity(string $syntax, string $xml): void
    {
        $report = self::validator()->validate($xml);
        $ours = $report->verdict();
        $errorCodes = array_values(array_unique(array_map(static fn (DynamicError $error): string => $error->errorCode, $report->dynamicErrors)));
        sort($errorCodes, SORT_STRING);

        $rules = [];
        $saxonErrors = [];
        foreach ([["cen-{$syntax}", true], ["peppol-{$syntax}", false]] as [$layer, $requireRootRule]) {
            try {
                array_push($rules, ...OfficialRules::run(SchematronToXslt::compile(Fixtures::peppolSchematron($layer)), $xml, $requireRootRule));
            } catch (OracleDynamicError $error) {
                $saxonErrors[$error->errorCode] = "{$layer}: {$error->getMessage()}";
            }
        }

        if ($saxonErrors !== []) {
            $missing = array_diff(array_keys($saxonErrors), $errorCodes);
            if ($missing !== []) {
                $this->dump($xml, 'error '.implode("\nerror ", $saxonErrors), $this->describe($ours, $report->dynamicErrors));
                self::fail(sprintf(
                    "Saxon stopped with %s; the validator reported dynamic errors [%s].\nSaxon: %s",
                    implode(', ', array_keys($saxonErrors)),
                    implode(', ', $errorCodes),
                    implode("\n", $saxonErrors),
                ));
            }
            $this->addToAssertionCount(1);

            return;
        }

        $official = OfficialRules::verdict($rules);
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

    private static function validator(): Validator
    {
        return self::$validator ??= Validator::peppol(InstalledRules::dir());
    }

    private static function peppol(Invoice $invoice): Invoice
    {
        return $invoice->withProcessControl(new ProcessControl(self::CUSTOMIZATION_ID, self::PROFILE_ID));
    }

    private static function isInvoice(string $xml): bool
    {
        $root = self::load($xml)->documentElement;

        return $root !== null && in_array('{'.$root->namespaceURI.'}'.$root->localName, self::INVOICE_ROOTS, true);
    }

    /**
     * @return 'ubl'|'cii'
     */
    private static function syntax(string $xml): string
    {
        return str_contains((string) self::load($xml)->documentElement?->namespaceURI, 'CrossIndustryInvoice') ? 'cii' : 'ubl';
    }

    private static function load(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The document is not well-formed XML.');
        }

        return $document;
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

    private static function contents(string $file): string
    {
        return file_get_contents($file) ?: throw new RuntimeException("Cannot read {$file}.");
    }
}
