<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle\Kosit;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Rules\En16931\RuleFiles as En16931RuleFiles;
use IntrAgile\EInvoicing\Rules\XRechnung\RuleFiles as XRechnungRuleFiles;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Validation\ValidationReport;
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Verdict equality with the KoSIT validator 1.6.3 (XRechnung configuration 2026-08-31) on every
 * document of the XRechnung corpus, the CEN 1.3.16 examples and deterministic mutations of five
 * test suite instances. Run it with docker/kosit/run.sh; without KOSIT_ORACLE_URL the tests are
 * skipped.
 *
 * Per document, these must equal KoSIT's: the scenario name, the XML Schema validity, the sorted
 * set of distinct "<rule id> <level>" strings after the scenario's custom levels (KoSIT "error"
 * is our "fatal"), and the acceptance (rep:accept, HTTP 200) against isValid().
 *
 * When KoSIT matches no scenario, XRechnungValidator must throw UnsupportedDocument. Files that
 * are not well-formed or carry a DOCTYPE must not be accepted by KoSIT and make us throw
 * UnsupportedDocument or UnsafeXml; the corpus has no DOCTYPE file, so one is built from a test
 * suite instance (Mutations::withDoctype()), on which we must throw UnsafeXml.
 *
 * Dynamic errors. When an assert of a Schematron step cannot be evaluated (for example a cast of
 * a repeated element, XPTY0004), KoSIT's XSLT transformation of that step aborts: the step reports
 * no message at all, the later steps still run, and the daemon answers HTTP 422 with rep:reject
 * (KositProbeTest fact e). We report every assert that could be evaluated plus the dynamic
 * errors, and isValid() is false. The comparison therefore also requires that KoSIT reports a
 * processing error exactly when we report dynamic errors, and on such a document compares
 * KoSIT's messages with our verdict minus the entries of every rule set that raised a dynamic
 * error. The rule sets of a scenario must not share a rule id, so that each entry and each
 * dynamic error belongs to exactly one rule set; the test fails otherwise, and also on a dynamic
 * error in a rule context, which carries no rule id. No corpus document raises a dynamic error;
 * Mutations::dynamicErrors() adds one document per Schematron step that does.
 */
#[Group('kosit-oracle')]
final class KositConformanceTest extends TestCase
{
    private static ?XRechnungValidator $validator = null;

    /** @var array<string, list<string>> rule ids per rule set, keyed by "<id> <version>" */
    private static array $ruleIds = [];

    private KositClient $client;

    protected function setUp(): void
    {
        $client = KositClient::fromEnvironment();
        if ($client === null) {
            self::markTestSkipped('KOSIT_ORACLE_URL is not set; run docker/kosit/run.sh.');
        }
        $this->client = $client;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function documents(): iterable
    {
        foreach (Fixtures::xrechnungCorpus() as $name => $path) {
            yield $name => [self::read($path)];
        }
        foreach (Fixtures::cen1316Examples() as $name => $path) {
            yield 'cen-1.3.16/'.$name => [self::read($path)];
        }
        foreach (Mutations::all() as $name => $xml) {
            yield 'mutation/'.$name => [$xml];
        }
        foreach (Mutations::dynamicErrors() as $name => $xml) {
            yield 'dynamic-error/'.$name => [$xml];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedDocuments(): iterable
    {
        foreach (Fixtures::xrechnungRejectedCorpus() as $name => $path) {
            yield $name => [self::read($path)];
        }
    }

    #[DataProvider('documents')]
    public function test_verdict_equals_kosit(string $xml): void
    {
        $kosit = $this->client->check($xml);

        if ($kosit->scenario === null) {
            self::assertFalse($kosit->accepted);
            $this->expectException(UnsupportedDocument::class);
            self::validator()->validate($xml);

            return;
        }

        $report = self::validator()->validate($xml);
        $processingError = $report->dynamicErrors !== [];
        $verdict = $processingError ? self::verdictWithoutAbortedRuleSets($report) : $report->verdict();

        self::assertSame(
            [
                'scenario' => $kosit->scenario,
                'schemaValid' => $kosit->schemaValid,
                'processingError' => $kosit->processingError,
                'verdict' => $kosit->verdict,
                'accepted' => $kosit->accepted,
            ],
            [
                'scenario' => $report->scenario,
                'schemaValid' => $report->schemaErrors === [],
                'processingError' => $processingError,
                'verdict' => $verdict,
                'accepted' => $report->isValid(),
            ],
        );
    }

    public function test_doctype_document_is_refused_like_kosit(): void
    {
        $xml = Mutations::withDoctype();

        self::assertFalse($this->client->check($xml)->accepted);
        $this->expectException(UnsafeXml::class);
        self::validator()->validate($xml);
    }

    #[DataProvider('rejectedDocuments')]
    public function test_unparseable_document_is_refused_like_kosit(string $xml): void
    {
        self::assertFalse($this->client->check($xml)->accepted);

        try {
            self::validator()->validate($xml);
            self::fail('The validator accepted a document that is not well-formed or carries a DOCTYPE.');
        } catch (UnsupportedDocument|UnsafeXml $refused) {
            self::assertNotSame('', $refused->getMessage());
        }
    }

    /**
     * Our verdict without the entries of the rule sets in which an assert raised a dynamic error,
     * which is what KoSIT reports when such a step aborts.
     *
     * @return list<string>
     */
    private static function verdictWithoutAbortedRuleSets(ValidationReport $report): array
    {
        $owner = [];
        foreach ($report->ruleSets as $ruleSet) {
            foreach (self::ruleIds($ruleSet) as $id) {
                if (isset($owner[$id]) && $owner[$id] !== $ruleSet) {
                    self::fail("The rule id {$id} occurs in both {$owner[$id]} and {$ruleSet}.");
                }
                $owner[$id] = $ruleSet;
            }
        }

        $aborted = [];
        foreach ($report->dynamicErrors as $error) {
            $ruleSet = $error->ruleSet ?? self::fail("The dynamic error at {$error->location} names no rule set.");
            if (! in_array($ruleSet, $report->ruleSets, true)) {
                self::fail("The dynamic error at {$error->location} belongs to {$ruleSet}, which did not run.");
            }
            $aborted[$ruleSet] = true;
        }

        return array_values(array_filter(
            $report->verdict(),
            static fn (string $entry): bool => ! isset($aborted[$owner[explode(' ', $entry, 2)[0]] ?? ''])
        ));
    }

    /**
     * @return list<string>
     */
    private static function ruleIds(string $ruleSet): array
    {
        if (! isset(self::$ruleIds[$ruleSet])) {
            [$id, $version] = explode(' ', $ruleSet, 2);
            $path = match ($id) {
                'en16931-ubl' => En16931RuleFiles::ubl($version),
                'en16931-cii' => En16931RuleFiles::cii($version),
                'xrechnung-ubl' => XRechnungRuleFiles::ubl(),
                'xrechnung-cii' => XRechnungRuleFiles::cii(),
                default => throw new RuntimeException("Unknown rule set {$ruleSet}."),
            };
            $rules = RuleSet::fromFile($path);
            if ("{$rules->id()} {$rules->version()}" !== $ruleSet) {
                throw new RuntimeException("The rule file {$path} holds {$rules->id()} {$rules->version()}, not {$ruleSet}.");
            }
            $ids = [];
            foreach ($rules->patterns() as $pattern) {
                foreach ($pattern['rules'] as $rule) {
                    foreach ($rule['asserts'] as $assert) {
                        $ids[$assert['id']] = true;
                    }
                }
            }
            self::$ruleIds[$ruleSet] = array_map(strval(...), array_keys($ids));
        }

        return self::$ruleIds[$ruleSet];
    }

    private static function validator(): XRechnungValidator
    {
        return self::$validator ??= XRechnungValidator::create();
    }

    private static function read(string $path): string
    {
        $xml = file_get_contents($path);
        if ($xml === false) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        return $xml;
    }
}
