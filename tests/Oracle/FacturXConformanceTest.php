<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use DOMDocument;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Syntax\Cii\CiiDocument;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXPdfReader;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Tests\Support\Mutator;
use IntrAgile\EInvoicing\Validation\DynamicError;
use IntrAgile\EInvoicing\Validation\FacturX\FacturXValidator;
use IntrAgile\EInvoicing\Validation\Finding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Differential conformance of the five Factur-X 1.09.2 profiles: the PHP validator must give
 * exactly the verdict of the official Factur-X XSLT run by Saxon-HE, on the FeRD example XMLs,
 * the XML embedded in the FeRD example PDFs, the Factur-X writer's output and seeded mutations
 * of the example XMLs.
 *
 * The official stylesheets report some entries without a flag or an id; the oracle gives them
 * the flag "fatal" and the id the compiler synthesizes with the prefix "FX-REPORT", as the
 * rule sets are compiled. Verdicts and dynamic errors are compared as in ConformanceTest, and a
 * mismatch is written to build/conformance-facturx/<key>/ for inspection.
 *
 * PHPUnit builds every data provider even when the oracle group is excluded, so the providers
 * read and parse the corpus only where the oracle can run (SaxonC loaded) and the examples have
 * been downloaded; elsewhere each returns one placeholder case, whose test skips.
 */
#[Group('oracle')]
final class FacturXConformanceTest extends TestCase
{
    private const MUTATIONS_PER_EXAMPLE = 10;

    private const DEFAULT_FLAG = 'fatal';

    private const MISSING_ID_PREFIX = 'FX-REPORT';

    /** The one case of a data provider when the corpus is not built; its test skips. */
    private const NOT_BUILT = 'Factur-X examples not downloaded or SaxonC not loaded';

    private const DUMP_DIR = __DIR__.'/../../build/conformance-facturx';

    private static ?FacturXValidator $validator = null;

    /** @var array<string, RuleSet> */
    private static array $ruleSets = [];

    /** @var array<string, array{string, string}>|null */
    private static ?array $examples = null;

    /**
     * The FeRD example XMLs of the five Factur-X profiles, with the profile key their guideline
     * identifier declares.
     *
     * @return array<string, array{string, string}>
     */
    public static function examples(): array
    {
        if (! self::corpusAvailable()) {
            return [self::NOT_BUILT => ['MINIMUM', '']];
        }
        if (self::$examples === null) {
            self::$examples = [];
            foreach (Fixtures::facturxExampleXml() as $name => $file) {
                $key = self::ruleProfile(self::contents($file));
                if ($key !== null) {
                    self::$examples["example {$key} {$name}"] = [$key, $file];
                }
            }
        }

        return self::$examples;
    }

    /**
     * The FeRD example PDFs of the five Factur-X profiles; the test extracts the XML.
     *
     * @return array<string, array{string, string}>
     */
    public static function examplePdfs(): array
    {
        if (! self::corpusAvailable()) {
            return [self::NOT_BUILT => ['MINIMUM', '']];
        }
        $cases = [];
        foreach (Fixtures::facturxExamplePdfs() as $name => $file) {
            if (! str_contains($name, '/ZF252_Beispiele/') || str_contains($name, '/attachments/')) {
                continue;
            }
            $key = (new FacturXPdfReader)->read(self::contents($file))->profile?->ruleProfile();
            if ($key !== null) {
                $cases["pdf {$key} {$name}"] = [$key, $file];
            }
        }

        return $cases;
    }

    /**
     * The Factur-X writer's output for every model invoice in every profile.
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
        foreach (FacturXProfile::cases() as $profile) {
            $key = $profile->ruleProfile();
            if ($key === null) {
                continue;
            }
            foreach ($invoices as $name => $invoice) {
                $cases["model {$key} {$name}"] = [$key, (new FacturXXmlWriter)->write($invoice, $profile)->xml];
            }
        }

        return $cases;
    }

    /**
     * Mutations are identified by file and label and built in the test.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function mutations(): array
    {
        if (! self::corpusAvailable()) {
            return [self::NOT_BUILT => ['MINIMUM', '', '']];
        }
        $cases = [];
        foreach (self::examples() as [$key, $file]) {
            $name = basename($file);
            foreach (Mutator::labels(self::contents($file), crc32($name), self::MUTATIONS_PER_EXAMPLE) as $label) {
                $cases["mutation {$key} {$name} {$label}"] = [$key, $file, $label];
            }
        }

        return $cases;
    }

    public function test_corpus_sizes(): void
    {
        self::requireCorpus();
        $examples = ['MINIMUM' => 2, 'BASIC-WL' => 2, 'BASIC' => 3, 'EN16931' => 25, 'EXTENDED' => 25];

        self::assertSame($examples, self::perProfile(self::examples()));
        self::assertSame($examples, self::perProfile(self::examplePdfs()));
        self::assertSame(array_fill_keys(RuleFiles::profiles(), 3), self::perProfile(self::modelInvoices()));
        self::assertSame(array_map(static fn (int $count): int => $count * self::MUTATIONS_PER_EXAMPLE, $examples), self::perProfile(self::mutations()));
    }

    #[DataProvider('examples')]
    public function test_ferd_example_xml(string $key, string $file): void
    {
        self::requireCorpus();
        $this->assertParity($key, self::contents($file));
    }

    #[DataProvider('examplePdfs')]
    public function test_ferd_example_pdf_xml(string $key, string $file): void
    {
        self::requireCorpus();
        $this->assertParity($key, (new FacturXPdfReader)->read(self::contents($file))->xml);
    }

    #[DataProvider('modelInvoices')]
    public function test_writer_output(string $key, string $xml): void
    {
        $this->assertParity($key, $xml);
    }

    #[DataProvider('mutations')]
    public function test_mutated_examples(string $key, string $file, string $label): void
    {
        self::requireCorpus();
        $original = self::contents($file);
        $mutated = Mutator::mutation($original, crc32(basename($file)), self::MUTATIONS_PER_EXAMPLE, $label);

        self::assertNotSame(self::serialized($original), self::serialized($mutated), "{$label} leaves the document unchanged.");
        $this->assertParity($key, $mutated);
    }

    /**
     * Each profile has at least one mutation that changes the verdict of its example, so the
     * mutation cases cannot all pass by leaving the rules untouched. Parity with Saxon is checked
     * per case; this test needs only the PHP verdict.
     */
    public function test_mutations_change_a_verdict_in_every_profile(): void
    {
        self::requireCorpus();
        $changed = [];
        foreach (self::mutations() as [$key, $file, $label]) {
            if (isset($changed[$key])) {
                continue;
            }
            $original = self::contents($file);
            $mutated = Mutator::mutation($original, crc32(basename($file)), self::MUTATIONS_PER_EXAMPLE, $label);
            if ($this->ours($key, $mutated)[0] !== $this->ours($key, $original)[0]) {
                $changed[$key] = "{$label} in ".basename($file);
            }
        }

        self::assertSame([], array_values(array_diff(RuleFiles::profiles(), array_keys($changed))), 'Profiles whose mutations never change the verdict.');
    }

    private function assertParity(string $key, string $xml): void
    {
        [$ours, $dynamicErrors] = $this->ours($key, $xml);
        $errorCodes = array_values(array_unique(array_map(static fn (DynamicError $error): string => $error->errorCode, $dynamicErrors)));
        sort($errorCodes, SORT_STRING);

        try {
            $official = OfficialRules::verdict(OfficialRules::run(Fixtures::facturxXslt($key), $xml, true, self::DEFAULT_FLAG, self::MISSING_ID_PREFIX));
        } catch (OracleDynamicError $error) {
            if (! in_array($error->errorCode, $errorCodes, true)) {
                $this->dump($xml, 'error '.$error->getMessage(), $this->describe($ours, $dynamicErrors));
                self::fail(sprintf(
                    "Saxon stopped with %s; the validator reported dynamic errors [%s].\nSaxon: %s",
                    $error->errorCode,
                    implode(', ', $errorCodes),
                    $error->getMessage(),
                ));
            }
            $this->addToAssertionCount(1);

            return;
        }

        if ($official === $ours && $dynamicErrors === []) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->dump($xml, implode("\n", $official), $this->describe($ours, $dynamicErrors));
        self::fail(sprintf(
            "Verdicts differ.\nOnly Saxon: %s\nOnly ours: %s\nOur dynamic errors: %s",
            implode(', ', array_diff($official, $ours)) ?: '-',
            implode(', ', array_diff($ours, $official)) ?: '-',
            implode('; ', array_map(static fn (DynamicError $error): string => "{$error->errorCode} {$error->ruleId} at {$error->location}: {$error->message}", $dynamicErrors)) ?: '-',
        ));
    }

    /**
     * Our verdict and dynamic errors. A document that still declares the profile goes through the
     * validator; a mutation that broke or changed its guideline identifier runs the profile's rule
     * set directly, as the official stylesheet of the profile does.
     *
     * @return array{list<string>, list<DynamicError>}
     */
    private function ours(string $key, string $xml): array
    {
        if (self::ruleProfile($xml) === $key) {
            $report = (self::$validator ??= FacturXValidator::create())->validate($xml);

            return [$report->verdict(), $report->dynamicErrors];
        }

        $result = (new Runner)->run(self::$ruleSets[$key] ??= RuleSet::fromFile(RuleFiles::rules($key)), SafeXml::loadPreservingWhitespace($xml));
        $verdict = array_values(array_unique(array_map(static fn (Finding $finding): string => "{$finding->ruleId} {$finding->severity->value}", $result->findings)));
        sort($verdict, SORT_STRING);

        return [$verdict, $result->dynamicErrors];
    }

    /**
     * Profile key of the document's guideline identifier, or null for XRechnung, an unknown
     * identifier or a document that is not a CII invoice.
     */
    private static function ruleProfile(string $xml): ?string
    {
        try {
            $document = SafeXml::loadPreservingWhitespace($xml);
        } catch (UnsupportedDocument) {
            return null;
        }
        $root = $document->documentElement;
        if ($root === null || $root->namespaceURI !== CiiDocument::RSM || $root->localName !== 'CrossIndustryInvoice') {
            return null;
        }
        $id = (new CiiDocument($document))->text('rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');

        return $id === null ? null : FacturXProfile::fromGuidelineId($id)?->ruleProfile();
    }

    private static function corpusAvailable(): bool
    {
        return Fixtures::hasFacturxExamples() && OfficialRules::available();
    }

    /**
     * Skips the test when the providers returned their placeholder case.
     */
    private static function requireCorpus(): void
    {
        Fixtures::facturxExamplesDir();
        if (! OfficialRules::available()) {
            self::markTestSkipped('The SaxonC-HE PHP extension is not loaded; run docker/oracle/run.sh.');
        }
    }

    /**
     * The document as DOMDocument serialises it, the form Mutator returns its output in.
     */
    private static function serialized(string $xml): string
    {
        $document = new DOMDocument;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The document is not well-formed XML.');
        }

        return (string) $document->saveXML();
    }

    /**
     * Number of cases per profile key.
     *
     * @param  array<string, array{0: string}>  $cases
     * @return array<string, int>
     */
    private static function perProfile(array $cases): array
    {
        $counts = array_fill_keys(RuleFiles::profiles(), 0);
        foreach ($cases as $case) {
            $counts[$case[0]]++;
        }

        return $counts;
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
