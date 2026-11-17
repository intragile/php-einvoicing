<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France\Negative;

use DOMDocument;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Rules\En16931\RuleFiles as En16931RuleFiles;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles as FacturXRuleFiles;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Syntax\Cii\CiiDocument;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use IntrAgile\EInvoicing\Tests\Validation\France\InstalledFranceRules;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\France\FranceRules;
use IntrAgile\EInvoicing\Validation\France\FranceSources;
use IntrAgile\EInvoicing\Validation\France\FranceValidator;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use IntrAgile\EInvoicing\Validation\Severity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The negative corpus: for every French rule in scope, a document that breaks it.
 *
 * Layout: one file per rule set key (see KEYS) in each of two folders.
 * - cases/<key>.php returns a list of recipes.
 * - unreachable/<key>.php returns rule id => reason, for rules no document can make fire after an
 *   honest attempt (for example: only an XSD-invalid document could). A reason is never empty.
 *
 * A recipe is an array with these keys:
 * - 'set': the rule set key; must equal the file's key.
 * - 'rule': the compiled rule id, exactly as the rule set reports it. Ids the source repeats carry
 *   the compiler's ordinal suffix, such as "G1.104-2" in Flux 10 or "BR-FR-01_BT-1-1" in Flux 2
 *   UBL; the coverage test lists the missing ids by their compiled names.
 * - 'base': the document to start from, one of
 *   - 'writer:<FrenchInvoices method>:ubl' or ':cii': the factory invoice written by UblWriter or
 *     CiiWriter (for example 'writer:domesticB2b:cii', 'writer:extendedCtcFr:cii',
 *     'writer:extendedCtcFrUbl:ubl');
 *   - 'writer:<FrenchInvoices method>:facturx-extended': the factory invoice reduced to Factur-X
 *     EXTENDED by FacturXXmlWriter (use 'writer:facturXExtended:facturx-extended');
 *   - 'fixture:<name>': tests/fixtures/france/<name>.xml, our own documents: 'fixture:cdar-base',
 *     'fixture:flux10-base' (transactions report) and 'fixture:flux10-payments-base';
 *   - 'fnfe:<manifest path>': a France_RFE example, only for sub-line and bi-directional cases the
 *     factory cannot build.
 *   Prefer writer bases. Every base a recipe names must have no finding, schema error or dynamic
 *   error at all under its chain (test_every_base_is_valid).
 * - 'edits': a list of [XPath 1.0, operation, argument] DOM edits applied in order; see Mutator for
 *   the operations set, remove, append, before and rename. Prefixes are those the target rule set
 *   declares, plus rsm/ram/udt/qdt (CII) and ubl/cn/cac/cbc (UBL) where the rule set does not
 *   declare them. An XPath that selects nothing fails the test. The edited document must still
 *   conform to the XML Schema of its chain (use before to keep the element order): a rule that
 *   only an XSD-invalid document reaches is unreachable.
 * - 'fork' (facturx-extended only, optional): 'fires' (default) when the FNFE Factur-X EXTENDED
 *   fork reports the rule and the rules-facturx EXTENDED rule set does not; 'silent' when only
 *   rules-facturx reports it. Either way the two rule sets must disagree on the document.
 *
 * test_recipe_fires_its_rule runs the target rule set on the edited document and passes when the
 * document still conforms to its schema and the rule id is reported at a severity the rule set
 * compiles it with. Other findings are allowed; set
 * NEGATIVE_CORPUS_VERBOSE=1 to print them. Its data sets are named "<key>:<rule id>", with ":2",
 * ":3" for further recipes of one rule, so --filter 'test_recipe_fires_its_rule.*flux2-ubl:' runs
 * one rule set.
 *
 * test_coverage_is_complete has one data set per key (--filter 'test_coverage_is_complete.*flux2-ubl'):
 * every id in scope has a recipe or an unreachable entry, and no recipe or entry names an id out of
 * scope. Scope: every id of flux2-cii, flux2-ubl, cdar and flux10; the ids of extended-ctc-fr-cii
 * and -ubl that EN 16931 1.3.16 (CII or UBL) does not have; the FORK_CHANGED ids of
 * facturx-extended.
 *
 * To add a recipe: pick a base, write the smallest edits that break only the rule, add the recipe
 * to cases/<key>.php, and run the rule set's filters above.
 */
#[Group('france-rules')]
final class NegativeCorpusTest extends TestCase
{
    public const KEYS = ['flux2-cii', 'flux2-ubl', 'extended-ctc-fr-cii', 'extended-ctc-fr-ubl', 'facturx-extended', 'cdar', 'flux10'];

    /** The asserts the FNFE Factur-X EXTENDED fork changes against rules-facturx EXTENDED. */
    public const FORK_CHANGED = [
        'BR-FXEXT-CO-10', 'BR-FXEXT-CO-12', 'BR-FXEXT-CO-13',
        'BR-FXEXT-S-01', 'BR-FXEXT-Z-01', 'BR-FXEXT-E-01', 'BR-FXEXT-AE-01', 'BR-FXEXT-IC-01',
        'BR-FXEXT-G-01', 'BR-FXEXT-O-01', 'BR-FXEXT-AF-01', 'BR-FXEXT-AG-01',
    ];

    private const RECIPE_KEYS = ['set', 'rule', 'base', 'edits', 'fork'];

    private const FIXTURE_DIR = __DIR__.'/../../fixtures/france';

    private const CEN_VERSION = '1.3.16';

    /** Prefixes recipes may use even when the target rule set does not declare them. */
    private const DEFAULT_NAMESPACES = [
        'rsm' => CiiDocument::RSM,
        'ram' => CiiDocument::RAM,
        'udt' => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
        'qdt' => 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100',
        'ubl' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'cn' => 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2',
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
    ];

    private const CDAR_ROOT = 'urn:un:unece:uncefact:data:standard:CrossDomainAcknowledgementAndResponse:100';

    private static ?FranceValidator $validator = null;

    /**
     * Every base a recipe names, plus our own fixtures.
     *
     * @return iterable<string, array{string}>
     */
    public static function bases(): iterable
    {
        $bases = [];
        foreach (glob(self::FIXTURE_DIR.'/*.xml') ?: [] as $file) {
            $bases['fixture:'.basename($file, '.xml')] = true;
        }
        foreach (self::allRecipes() as $recipe) {
            $bases[$recipe['base']] = true;
        }
        ksort($bases, SORT_STRING);
        foreach (array_keys($bases) as $base) {
            yield $base => [$base];
        }
    }

    /**
     * @return iterable<string, array{array{set: string, rule: string, base: string, edits: list<array<int, string>>, fork?: string}}>
     */
    public static function recipes(): iterable
    {
        $seen = [];
        foreach (self::allRecipes() as $recipe) {
            $name = $recipe['set'].':'.$recipe['rule'];
            $seen[$name] = ($seen[$name] ?? 0) + 1;
            yield $seen[$name] === 1 ? $name : $name.':'.$seen[$name] => [$recipe];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function keys(): iterable
    {
        foreach (self::KEYS as $key) {
            yield $key => [$key];
        }
    }

    #[DataProvider('bases')]
    public function test_every_base_is_valid(string $base): void
    {
        $problems = self::chain(self::load($base));

        self::assertSame([], $problems, "The base {$base} is not clean under its chain.");
    }

    /**
     * @param  array{set: string, rule: string, base: string, edits: list<array<int, string>>, fork?: string}  $recipe
     */
    #[DataProvider('recipes')]
    public function test_recipe_fires_its_rule(array $recipe): void
    {
        $key = $recipe['set'];
        $ruleSet = self::ruleSet($key);
        $severities = self::severities($ruleSet)[$recipe['rule']] ?? [];
        self::assertNotSame([], $severities, "The rule set fr-{$key} has no rule {$recipe['rule']}.");

        $xml = self::edited($recipe);
        $document = SafeXml::loadPreservingWhitespace($xml);
        $findings = (new Runner)->run($ruleSet, $document)->findings;
        $fires = self::fires($findings, $recipe['rule'], $severities);

        $chain = self::chain($xml);
        if (getenv('NEGATIVE_CORPUS_VERBOSE') === '1') {
            fwrite(STDOUT, "\n{$key}:{$recipe['rule']}\n".implode("\n", $chain)."\n");
        }
        $schemaErrors = array_values(array_filter($chain, static fn (string $line): bool => str_starts_with($line, 'schema ')));
        self::assertSame([], $schemaErrors, "The edited document breaks its XML Schema; a rule only an XSD-invalid document reaches belongs in unreachable/{$key}.php.");

        if ($key !== 'facturx-extended') {
            self::assertTrue($fires, "{$recipe['rule']} does not fire. Findings of fr-{$key}:\n".self::describe($findings));

            return;
        }

        $upstream = RuleSet::fromFile(FacturXRuleFiles::rules((string) FacturXProfile::Extended->ruleProfile()));
        $upstreamFindings = (new Runner)->run($upstream, $document)->findings;
        $upstreamFires = self::fires($upstreamFindings, $recipe['rule'], self::severities($upstream)[$recipe['rule']] ?? []);
        $message = "Fork:\n".self::describe($findings)."\nrules-facturx EXTENDED:\n".self::describe($upstreamFindings);
        if (($recipe['fork'] ?? 'fires') === 'fires') {
            self::assertTrue($fires && ! $upstreamFires, "{$recipe['rule']} must fire in the fork only.\n{$message}");
        } else {
            self::assertTrue(! $fires && $upstreamFires, "{$recipe['rule']} must fire in rules-facturx only.\n{$message}");
        }
    }

    #[DataProvider('keys')]
    public function test_coverage_is_complete(string $key): void
    {
        $scope = self::scope($key);
        $covered = array_values(array_unique(array_column(self::recipesOf($key), 'rule')));
        $unreachable = self::unreachableOf($key);

        $problems = [];
        foreach (array_diff($scope, $covered, array_keys($unreachable)) as $id) {
            $problems[] = "{$id}: no recipe and no unreachable entry";
        }
        foreach (array_diff($covered, $scope) as $id) {
            $problems[] = "{$id}: a recipe names an id outside the scope";
        }
        foreach ($unreachable as $id => $reason) {
            if (! in_array($id, $scope, true)) {
                $problems[] = "{$id}: an unreachable entry names an id outside the scope";
            }
            if (in_array($id, $covered, true)) {
                $problems[] = "{$id}: both a recipe and an unreachable entry";
            }
            if (trim($reason) === '') {
                $problems[] = "{$id}: the unreachable reason is empty";
            }
        }

        self::assertSame([], $problems, sprintf(
            'fr-%s: %d ids in scope, %d with recipes, %d unreachable.',
            $key,
            count($scope),
            count($covered),
            count($unreachable),
        ));
    }

    /**
     * The ids the corpus must cover for one rule set.
     *
     * @return list<string>
     */
    private static function scope(string $key): array
    {
        $ids = array_keys(self::severities(self::ruleSet($key)));

        if ($key === 'facturx-extended') {
            $missing = array_diff(self::FORK_CHANGED, $ids);
            self::assertSame([], array_values($missing), 'The fork has no such rule.');

            return self::FORK_CHANGED;
        }
        if ($key === 'extended-ctc-fr-cii' || $key === 'extended-ctc-fr-ubl') {
            $cen = RuleSet::fromFile($key === 'extended-ctc-fr-cii' ? En16931RuleFiles::cii(self::CEN_VERSION) : En16931RuleFiles::ubl(self::CEN_VERSION));

            return array_values(array_diff($ids, array_keys(self::severities($cen))));
        }

        return $ids;
    }

    /**
     * The severities each rule id is compiled with, in rule set order.
     *
     * @return array<string, list<Severity>>
     */
    private static function severities(RuleSet $ruleSet): array
    {
        $severities = [];
        foreach ($ruleSet->patterns() as $pattern) {
            foreach ($pattern['rules'] as $rule) {
                foreach ($rule['asserts'] as $assert) {
                    if (! in_array($assert['severity'], $severities[$assert['id']] ?? [], true)) {
                        $severities[$assert['id']][] = $assert['severity'];
                    }
                }
            }
        }

        return $severities;
    }

    /**
     * @param  list<Finding>  $findings
     * @param  list<Severity>  $severities
     */
    private static function fires(array $findings, string $id, array $severities): bool
    {
        foreach ($findings as $finding) {
            if ($finding->ruleId === $id && in_array($finding->severity, $severities, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every problem of a document under its chain: schema errors, dynamic errors and findings of
     * any severity. An invoice runs through FranceValidator (validateFacturX() for a Factur-X
     * guideline identifier); a CDAR or Flux 10 document through its FNFE schema and rule set.
     *
     * @return list<string>
     */
    private static function chain(string $xml): array
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $root = $document->documentElement;

        if ($root?->namespaceURI === self::CDAR_ROOT) {
            return self::message($document, 'cdar', 'CrossDomainAcknowledgementAndResponse_100pD22B.xsd');
        }
        if ($root?->namespaceURI === null && $root?->localName === 'Report') {
            return self::message($document, 'flux10', 'ereporting.xsd');
        }

        // The EN 16931 identifier is both a French specification identifier and the Factur-X EN 16931
        // guideline identifier; such a document is validated as a standalone CII invoice.
        $guideline = $root?->namespaceURI === CiiDocument::RSM
            ? (string) (new CiiDocument($document))->token('rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID')
            : '';
        $facturX = FacturXProfile::fromGuidelineId($guideline) !== null && FrenchProfile::fromSpecificationIdentifier($guideline) === null;
        $report = $facturX ? self::validator()->validateFacturX($xml) : self::validator()->validate($xml);

        $problems = [];
        foreach ($report->schemaErrors as $error) {
            $problems[] = "schema {$error->line}: {$error->message}";
        }
        foreach ($report->dynamicErrors as $error) {
            $problems[] = "dynamic {$error->ruleId} {$error->errorCode}: {$error->message}";
        }

        return [...$problems, ...self::lines($report->findings)];
    }

    /**
     * @return list<string>
     */
    private static function message(DOMDocument $document, string $key, string $schema): array
    {
        $dir = InstalledFranceRules::dir();
        $problems = [];
        foreach ((new SchemaValidator)->validate($document, FranceRules::schema($dir, $key, $schema)) as $error) {
            $problems[] = "schema {$error->line}: {$error->message}";
        }
        $result = (new Runner)->run(self::ruleSet($key), $document);
        foreach ($result->dynamicErrors as $error) {
            $problems[] = "dynamic {$error->ruleId} {$error->errorCode}: {$error->message}";
        }

        return [...$problems, ...self::lines($result->findings)];
    }

    /**
     * @param  list<Finding>  $findings
     * @return list<string>
     */
    private static function lines(array $findings): array
    {
        return array_map(
            static fn (Finding $finding): string => "{$finding->ruleId} {$finding->severity->value} at {$finding->location}",
            $findings,
        );
    }

    /**
     * @param  list<Finding>  $findings
     */
    private static function describe(array $findings): string
    {
        return $findings === [] ? '(none)' : implode("\n", self::lines($findings));
    }

    /**
     * The document a recipe produces: its base with its edits applied.
     *
     * @param  array{set: string, rule: string, base: string, edits: list<array<int, string>>, fork?: string}  $recipe
     */
    public static function edited(array $recipe): string
    {
        return Mutator::apply(self::load($recipe['base']), $recipe['edits'], self::ruleSet($recipe['set'])->namespaces() + self::DEFAULT_NAMESPACES);
    }

    /**
     * The XML of a base.
     */
    public static function load(string $base): string
    {
        $parts = explode(':', $base, 3);

        switch ($parts[0]) {
            case 'writer':
                [, $method, $syntax] = $parts + [1 => '', 2 => ''];
                if (! method_exists(FrenchInvoices::class, $method)) {
                    throw new RuntimeException("FrenchInvoices has no factory {$method}.");
                }
                $invoice = FrenchInvoices::{$method}();
                if (! $invoice instanceof Invoice) {
                    throw new RuntimeException("FrenchInvoices::{$method}() is not an invoice factory.");
                }

                return match ($syntax) {
                    'ubl' => (new UblWriter)->write($invoice),
                    'cii' => (new CiiWriter)->write($invoice),
                    'facturx-extended' => (new FacturXXmlWriter)->write($invoice, FacturXProfile::Extended)->xml,
                    default => throw new RuntimeException("Unknown writer syntax in the base {$base}; expected ubl, cii or facturx-extended."),
                };
            case 'fixture':
                $file = self::FIXTURE_DIR.'/'.($parts[1] ?? '').'.xml';
                if (! is_file($file)) {
                    throw new RuntimeException("The base {$base} names no file in tests/fixtures/france.");
                }

                return (string) file_get_contents($file);
            case 'fnfe':
                $path = substr($base, strlen('fnfe:'));
                if (! isset(FranceSources::all()[$path])) {
                    throw new RuntimeException("The base {$base} is not a path of the France_RFE manifest.");
                }

                return (string) file_get_contents(Fixtures::franceRfeRoot().'/'.$path);
            default:
                throw new RuntimeException("Unknown base {$base}; expected writer:, fixture: or fnfe:.");
        }
    }

    private static function ruleSet(string $key): RuleSet
    {
        return FranceRules::load(InstalledFranceRules::dir(), $key);
    }

    private static function validator(): FranceValidator
    {
        return self::$validator ??= FranceValidator::create(InstalledFranceRules::dir());
    }

    /**
     * @return list<array{set: string, rule: string, base: string, edits: list<array<int, string>>, fork?: string}>
     */
    public static function allRecipes(): array
    {
        $all = [];
        foreach (self::KEYS as $key) {
            array_push($all, ...self::recipesOf($key));
        }

        return $all;
    }

    /**
     * The recipes of cases/<key>.php, with their shape checked.
     *
     * @return list<array{set: string, rule: string, base: string, edits: list<array<int, string>>, fork?: string}>
     */
    private static function recipesOf(string $key): array
    {
        $loaded = require __DIR__."/cases/{$key}.php";
        if (! is_array($loaded) || ! array_is_list($loaded)) {
            throw new RuntimeException("cases/{$key}.php must return a list of recipes.");
        }

        $recipes = [];
        foreach ($loaded as $index => $recipe) {
            $where = "cases/{$key}.php recipe {$index}";
            if (! is_array($recipe) || array_diff(array_keys($recipe), self::RECIPE_KEYS) !== []) {
                throw new RuntimeException("{$where} must be an array with only the keys ".implode(', ', self::RECIPE_KEYS).'.');
            }
            $set = $recipe['set'] ?? null;
            $rule = $recipe['rule'] ?? null;
            $base = $recipe['base'] ?? null;
            $edits = $recipe['edits'] ?? null;
            if ($set !== $key || ! is_string($rule) || $rule === '' || ! is_string($base) || ! is_array($edits) || $edits === [] || ! array_is_list($edits)) {
                throw new RuntimeException("{$where} needs 'set' => '{$key}', a 'rule', a 'base' and a non-empty list of 'edits'.");
            }
            $checked = [];
            foreach ($edits as $edit) {
                if (! is_array($edit) || ! array_is_list($edit) || count($edit) < 2 || count($edit) > 3 || array_filter($edit, is_string(...)) !== $edit) {
                    throw new RuntimeException("{$where} ({$rule}) has an edit that is not [XPath, operation, argument?] of strings.");
                }
                $checked[] = $edit;
            }
            $entry = ['set' => $key, 'rule' => $rule, 'base' => $base, 'edits' => $checked];
            if (array_key_exists('fork', $recipe)) {
                if ($key !== 'facturx-extended' || ! in_array($recipe['fork'], ['fires', 'silent'], true)) {
                    throw new RuntimeException("{$where} ({$rule}): 'fork' is 'fires' or 'silent', and only for facturx-extended.");
                }
                $entry['fork'] = $recipe['fork'];
            }
            $recipes[] = $entry;
        }

        return $recipes;
    }

    /**
     * @return array<string, string>
     */
    private static function unreachableOf(string $key): array
    {
        $loaded = require __DIR__."/unreachable/{$key}.php";
        if (! is_array($loaded)) {
            throw new RuntimeException("unreachable/{$key}.php must return rule id => reason.");
        }
        $entries = [];
        foreach ($loaded as $id => $reason) {
            if (! is_string($id) || ! is_string($reason)) {
                throw new RuntimeException("unreachable/{$key}.php must map rule ids to reasons.");
            }
            $entries[$id] = $reason;
        }

        return $entries;
    }
}
