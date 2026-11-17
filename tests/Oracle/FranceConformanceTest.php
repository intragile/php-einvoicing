<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use IntrAgile\EInvoicing\Schematron\Runner;
use IntrAgile\EInvoicing\Syntax\Cii\CiiDocument;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXXmlWriter;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\France\Negative\NegativeCorpusTest;
use IntrAgile\EInvoicing\Tests\Oracle\Kosit\Mutations;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use IntrAgile\EInvoicing\Tests\Validation\France\InstalledFranceRules;
use IntrAgile\EInvoicing\Validation\DynamicError;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\France\FranceRules;
use IntrAgile\EInvoicing\Validation\France\FranceSources;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Differential conformance of the seven French rule sets: each compiled rule set must give exactly
 * the verdict of FNFE's own XSLT for it, run unchanged by Saxon-HE through the xsl:try wrapper of
 * OfficialRules.
 *
 * The corpus of a rule set: every FNFE example the rule set applies to, the FrenchInvoices
 * factory invoices as our writers produce them, our CDAR and Flux 10 fixtures, the round trips of
 * the FNFE EXTENDED-CTC-FR and Factur-X examples through our readers and writers, the documents
 * of the negative corpus recipes that target the rule set, and leaf mutations of one or two FNFE
 * examples (or fixtures) per document family: for each element with text, the text replaced by
 * "X Y", the element removed and, for a number, extra decimals appended. A document goes through
 * the French rule sets its validation chain runs (FranceValidator): Flux 2 of its syntax, plus
 * EXTENDED-CTC-FR for that profile or the Factur-X EXTENDED fork for that guideline identifier;
 * a recipe document goes through the rule set it targets; a mutation through the sets of its base.
 *
 * Ids: an SVRL entry without an id gets the id the compiled rule set gives the assert with the
 * same context and normalised test (FranceIds). OfficialRules marks such an entry with a hash of
 * its context and its raw SVRL @test, exactly as the report carries it; FranceIds keys are built
 * from the compiled test with whitespace collapsed (and quantifier braces rendered as the
 * stylesheet's attribute value template renders them), and each key is hashed the same way to map
 * the marker back. Where the report's @test and the collapsed compiled test differ, the marker
 * matches no key and the case fails with an exception naming the entry, never with a wrong id.
 * An entry without a flag takes the compile default of its rule set: "warning" for
 * EXTENDED-CTC-FR CII, "fatal" otherwise.
 *
 * When Saxon returns a report, the sorted sets of "<rule id> <flag>" strings must be identical
 * and our run must report no dynamic error. When Saxon stops with a dynamic error, our run must
 * report one with the same code. Two differences are accepted, both Saxon evaluating eagerly what
 * our lazy evaluation never needs (XPath 2.0 section 2.3.4 permits both; see the UserFunction
 * docblock). They are named by their mechanism here; the engine notes call the first one "G6",
 * which is unrelated to the G6.xx rules of Flux 10.
 * - Eager function locals (eagerLocalAllowed()): after many calls Saxon evaluates the unread
 *   locals of custom:is-valid-date-format eagerly, so a CII or UBL date set to "X Y" stops it with
 *   FORG0001 in a cast; we report the BR-FR-03 rule of that date.
 * - Eager rule let (eagerLetAllowed()): Saxon evaluates a single-valued rule-level let eagerly, so
 *   a Flux 10 amount set to "X Y" stops it with FORG0001 in a sum() that no evaluated assert path
 *   reads; we report the G1.14 format rule of that amount.
 * Their uses are pinned (test_allow_lists_are_used_as_pinned). The pinned counts assume the
 * corpus runs in its declaration order, which decides when Saxon's function calls warm up. A
 * mismatch is written to build/conformance-france/<case>/.
 *
 * PHPUnit builds every data provider even when the oracle group is excluded, so the provider reads
 * the corpus only where the oracle can run (SaxonC loaded) and the France_RFE files have been
 * downloaded; elsewhere it returns one placeholder case, whose test skips.
 *
 * @phpstan-type Target array{kind: string, namespace: string, local: string, index: int}
 */
#[Group('oracle')]
final class FranceConformanceTest extends TestCase
{
    /** FNFE stylesheet of each rule set, by FranceSources::ruleSources() key. */
    private const XSLT = [
        'flux2-cii' => 'FNFE_RFE_INVOICE/CII/EN16931/2xslt/BR-FR-Flux2-Schematron-CII.xslt',
        'flux2-ubl' => 'FNFE_RFE_INVOICE/UBL/EN16931/2xslt/BR-FR-Flux2-Schematron-UBL.xslt',
        'extended-ctc-fr-cii' => 'FNFE_RFE_INVOICE/CII/EXTENDED-CTC-FR/2xslt/EXTENDED-CTC-FR-CII.xslt',
        'extended-ctc-fr-ubl' => 'FNFE_RFE_INVOICE/UBL/EXTENDED-CTC-FR/2xslt/EXTENDED-CTC-FR-UBL.xslt',
        'cdar' => 'FNFE_RFE_INVOICE/CDAR/2xslt/BR-FR-CDV-Schematron-CDAR.xslt',
        'flux10' => 'FNFE_RFE_INVOICE/Flux10/Flux10.xslt',
        'facturx-extended' => 'FNFE_RFE_INVOICE/Factur-X/EXTENDED/2xslt/FACTUR-X_EXTENDED.xslt',
    ];

    /** Rule sets with a rule on the document element; Flux 2, CDAR and Flux 10 have none. */
    private const ROOT_RULE = ['extended-ctc-fr-cii', 'extended-ctc-fr-ubl', 'facturx-extended'];

    /** Rule sets compiled with defaultFlag "warning". */
    private const WARNING_DEFAULT = ['extended-ctc-fr-cii'];

    /** The bases whose leaves are mutated. */
    private const MUTATION_BASES = [
        'fnfe:FNFE_RFE_INVOICE/Z.example/TEST/CII/EN16931/UC1_F202500003_00-INV_20250701_EN16931_FX_CII_Commentee.xml',
        'fnfe:FNFE_RFE_INVOICE/Z.example/TEST/CII/EXTENDED-CTC-FR/UC10_F202600004_MULTI-VENDEUR_EXTENDED-CTC-FR_CII_Commentee.xml',
        'fnfe:FNFE_RFE_INVOICE/Z.example/TEST/FX/BASICWL/Facture_F20260023-LE_FOURNISSEUR-POUR-LE_CLIENT_BASICWL_FX_CII_Commentee.xml',
        'fnfe:FNFE_RFE_INVOICE/Z.example/TEST/FX/EXTENDED/UC12_F202600025_SOUS-LIGNE_EXTENDED_FX_CII_Commentee.xml',
        'fnfe:FNFE_RFE_INVOICE/Z.example/TEST/UBL/EN16931/UC1_F202500003_00-INV_20250701_EN16931_UBL_Commentee.xml',
        'fnfe:FNFE_RFE_INVOICE/Z.example/TEST/UBL/EXTENDED-CTC-FR/UC10_F202600004_MULTI-VENDEUR_EXTENDED-CTC-FR_UBL_Commentee.xml',
        'fnfe:FNFE_RFE_INVOICE/Z.example/TEST/CDAR/UC1_F202500003_01-CDV-200_Deposee.xml',
        'fnfe:FNFE_RFE_INVOICE/Z.example/TEST/CDAR/UC2_F202500004_02-CDV-213_Rejetee.xml',
        'fixture:flux10-base',
        'fixture:flux10-payments-base',
    ];

    /** The writer outputs, as NegativeCorpusTest bases, besides the FrenchInvoices::all() factories. */
    private const EXTENDED_WRITER_BASES = [
        'writer:extendedCtcFr:cii',
        'writer:extendedCtcFrUbl:ubl',
        'writer:facturXExtended:facturx-extended',
    ];

    private const FIXTURE_BASES = ['fixture:cdar-base', 'fixture:flux10-base', 'fixture:flux10-payments-base'];

    /** Example folders whose documents are read and written back. */
    private const ROUND_TRIP_FOLDERS = ['CII/EXTENDED-CTC-FR', 'UBL/EXTENDED-CTC-FR', 'FX/EN16931', 'FX/EXTENDED'];

    /** Elements with text: the leaves a mutation changes. */
    private const LEAVES = '//*[not(*)][normalize-space(text()) != ""]';

    private const MUTATED_TEXT = 'X Y';

    /** Marks an SVRL entry without an id; OfficialRules appends a hash of its context and test. */
    private const SVRL_PREFIX = 'SVRL-NO-ID';

    private const CDAR_ROOT = 'urn:un:unece:uncefact:data:standard:CrossDomainAcknowledgementAndResponse:100';

    /** The one case of the data provider when the corpus is not built; its test skips. */
    private const NOT_BUILT = 'France_RFE files not downloaded or SaxonC not loaded';

    private const DUMP_DIR = __DIR__.'/../../build/conformance-france';

    private const UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    private const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const XSL = 'http://www.w3.org/1999/XSL/Transform';

    private const QDT = 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100';

    /**
     * The date elements whose BR-FR-03 check calls custom:is-valid-date-format, by namespace. CII
     * writes most dates as udt:DateTimeString and the preceding invoice date (BT-26) as
     * qdt:DateTimeString.
     */
    private const CHECKED_DATES = [
        self::UDT => ['DateTimeString'],
        self::QDT => ['DateTimeString'],
        self::CBC => ['IssueDate', 'TaxPointDate', 'DueDate', 'ActualDeliveryDate', 'StartDate', 'EndDate'],
    ];

    /** The Flux 10 amount elements, the contexts of the amount format rule G1.14. */
    private const FLUX10_AMOUNTS = [
        'Amount', 'TaxExclusiveAmount', 'TaxAmount', 'TaxableAmount', 'TaxTotal',
        'PriceAmount', 'AllowanceChargeAmount', 'AllowanceChargeBaseAmount',
    ];

    /** The date format rule of Flux 2, with its BT suffix. */
    private const DATE_RULE = '/^BR-FR-03(_\S+)?$/';

    /** The amount format rule of Flux 10, with its ordinal. */
    private const AMOUNT_RULE = '/^G1\.14(-\d+)?$/';

    /** How often the whole corpus meets eager function locals on a CII date. */
    private const EAGER_LOCAL_CII_USES = 26;

    /** @var array{eager-local-cii: int, eager-local-ubl: int, eager-let: int} uses per entry */
    private static array $allowed = ['eager-local-cii' => 0, 'eager-local-ubl' => 0, 'eager-let' => 0];

    private static int $parityRuns = 0;

    /** @var array<string, DOMDocument> */
    private static array $stylesheets = [];

    /** @var array<string, RuleSet> */
    private static array $ruleSets = [];

    /** @var array<string, array<string, string>> rule set key => SVRL marker id => FranceIds key */
    private static array $markers = [];

    /** @var array<string, array<string, string>> rule set key => FranceIds::map() */
    private static array $ids = [];

    /** @var array<string, string> */
    private static array $bases = [];

    /** @var list<array{set: string, rule: string, base: string, edits: list<array<int, string>>, fork?: string}>|null */
    private static ?array $recipes = null;

    /** @var array<string, array{string, string, string}>|null */
    private static ?array $cases = null;

    /**
     * Every case: rule set key, base and variant ("" for the base itself, "recipe:<index>" or
     * "mutation:<label>"). Documents are built in the test.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function corpus(): array
    {
        if (! self::corpusAvailable()) {
            return [self::NOT_BUILT => ['', '', '']];
        }
        if (self::$cases !== null) {
            return self::$cases;
        }

        $cases = [];
        $add = static function (string $set, string $base, string $variant) use (&$cases): void {
            $cases[trim("{$set} {$base} {$variant}")] = [$set, $base, $variant];
        };

        foreach (self::documentBases() as $base) {
            foreach (self::setsOf(self::base($base)) as $set) {
                $add($set, $base, '');
            }
        }
        foreach (self::recipes() as $index => $recipe) {
            $add($recipe['set'], $recipe['base'], "recipe:{$index}");
        }
        foreach (self::MUTATION_BASES as $base) {
            $xml = self::base($base);
            $sets = self::setsOf($xml);
            foreach (self::mutationLabels($xml) as $label) {
                foreach ($sets as $set) {
                    $add($set, $base, "mutation:{$label}");
                }
            }
        }

        return self::$cases = $cases;
    }

    public function test_corpus_sizes(): void
    {
        self::requireCorpus();
        $sizes = [];
        foreach (self::corpus() as [$set, $base, $variant]) {
            $kind = $variant === '' ? strstr($base, ':', true) : strstr($variant, ':', true);
            $sizes[$set][(string) $kind] = ($sizes[$set][(string) $kind] ?? 0) + 1;
        }
        ksort($sizes);
        foreach ($sizes as &$kinds) {
            ksort($kinds);
        }
        unset($kinds);

        self::assertSame([
            'cdar' => ['fixture' => 1, 'fnfe' => 59, 'mutation' => 143, 'recipe' => 37],
            'extended-ctc-fr-cii' => ['fnfe' => 5, 'mutation' => 555, 'recipe' => 74, 'roundtrip' => 5, 'writer' => 1],
            'extended-ctc-fr-ubl' => ['fnfe' => 5, 'mutation' => 513, 'recipe' => 63, 'roundtrip' => 5, 'writer' => 1],
            'facturx-extended' => ['fnfe' => 5, 'mutation' => 305, 'recipe' => 12, 'roundtrip' => 5, 'writer' => 1],
            'flux10' => ['fixture' => 2, 'mutation' => 214, 'recipe' => 150],
            'flux2-cii' => ['fnfe' => 28, 'mutation' => 1563, 'recipe' => 167, 'roundtrip' => 18, 'writer' => 7],
            'flux2-ubl' => ['fnfe' => 13, 'mutation' => 757, 'recipe' => 164, 'roundtrip' => 5, 'writer' => 6],
        ], $sizes);
    }

    #[DataProvider('corpus')]
    public function test_parity(string $set, string $base, string $variant): void
    {
        self::requireCorpus();
        self::$parityRuns++;
        $xml = self::document($base, $variant);

        [$ours, $dynamicErrors] = self::ours($set, $xml);
        $errorCodes = array_values(array_unique(array_map(static fn (DynamicError $error): string => $error->errorCode, $dynamicErrors)));
        sort($errorCodes, SORT_STRING);

        try {
            $official = self::official($set, $xml);
        } catch (OracleDynamicError $error) {
            if (in_array($error->errorCode, $errorCodes, true)) {
                $this->addToAssertionCount(1);

                return;
            }
            $target = self::target($base, $variant);
            $entry = self::eagerLocalAllowed($set, $error->errorCode, $ours, $target, $xml) ?? (self::eagerLetAllowed($set, $error, $ours, $target, $xml) ? 'eager-let' : null);
            if ($entry !== null) {
                self::$allowed[$entry]++;
                $this->addToAssertionCount(1);

                return;
            }
            $this->dump($xml, 'error '.$error->errorCode.' '.$error->getMessage(), self::describe($ours, $dynamicErrors));
            self::fail(sprintf(
                "Saxon stopped with %s; we reported dynamic errors [%s] and the verdict [%s].\nSaxon: %s",
                $error->errorCode,
                implode(', ', $errorCodes),
                implode(', ', $ours),
                $error->getMessage(),
            ));
        }

        if ($official === $ours && $dynamicErrors === []) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->dump($xml, implode("\n", $official), self::describe($ours, $dynamicErrors));
        self::fail(sprintf(
            "Verdicts differ.\nOnly Saxon: %s\nOnly ours: %s\nOur dynamic errors: %s",
            implode(', ', array_diff($official, $ours)) ?: '-',
            implode(', ', array_diff($ours, $official)) ?: '-',
            implode('; ', array_map(static fn (DynamicError $error): string => "{$error->errorCode} {$error->ruleId} at {$error->location}: {$error->message}", $dynamicErrors)) ?: '-',
        ));
    }

    public function test_allow_lists_are_used_only_for_their_patterns(): void
    {
        self::requireCorpus();

        // Eager function locals: the date format rule BR-FR-03 of the mutated CII or UBL date.
        $ciiBase = 'fnfe:FNFE_RFE_INVOICE/Z.example/TEST/CII/EN16931/UC1_F202500003_00-INV_20250701_EN16931_FX_CII_Commentee.xml';
        $ublBase = 'fnfe:FNFE_RFE_INVOICE/Z.example/TEST/UBL/EN16931/UC1_F202500003_00-INV_20250701_EN16931_UBL_Commentee.xml';
        $wlBase = 'fnfe:FNFE_RFE_INVOICE/Z.example/TEST/FX/BASICWL/Facture_F20260023-LE_FOURNISSEUR-POUR-LE_CLIENT_BASICWL_FX_CII_Commentee.xml';
        $case = static fn (string $base, string $variant): array => [
            self::target($base, $variant) ?? throw new RuntimeException("{$variant} is not a mutation."),
            self::document($base, $variant),
        ];
        [$cii, $ciiXml] = $case($ciiBase, 'mutation:004-text-DateTimeString');
        [$ubl, $ublXml] = $case($ublBase, 'mutation:004-text-IssueDate');
        [$due, $dueXml] = $case($ublBase, 'mutation:005-text-DueDate');
        [$preceding, $precedingXml] = $case($wlBase, 'mutation:183-text-DateTimeString');
        $ours = ['BR-FR-03_BT-2 fatal', 'BR-FR-CO-07_BT-9 fatal'];

        self::assertSame(self::UDT, $cii['namespace']);
        self::assertSame(self::QDT, $preceding['namespace']);
        self::assertSame('eager-local-cii', self::eagerLocalAllowed('flux2-cii', 'FORG0001', $ours, $cii, $ciiXml));
        self::assertSame('eager-local-cii', self::eagerLocalAllowed('flux2-cii', 'FORG0001', ['BR-FR-03_BT-26 fatal'], $preceding, $precedingXml), 'BT-26');
        self::assertSame('eager-local-ubl', self::eagerLocalAllowed('flux2-ubl', 'FORG0001', $ours, $ubl, $ublXml));
        self::assertSame('eager-local-ubl', self::eagerLocalAllowed('flux2-ubl', 'FORG0001', ['BR-FR-03_BT-9 fatal'], $due, $dueXml), 'BT-9');

        self::assertNull(self::eagerLocalAllowed('flux2-ubl', 'FORG0001', ['BR-FR-03_BT-9 fatal'], $ubl, $ublXml), 'BR-FR-03 of another date');
        self::assertNull(self::eagerLocalAllowed('flux2-cii', 'FORG0001', ['BR-FR-03_BT-26 fatal'], $cii, $ciiXml), 'BR-FR-03 of another date');
        self::assertNull(self::eagerLocalAllowed('flux2-ubl', 'FORG0001', ['BR-FR-03_BT-2 warning'], $ubl, $ublXml), 'another severity');
        self::assertNull(self::eagerLocalAllowed('flux2-cii', 'FOAR0001', $ours, $cii, $ciiXml), 'another error code');
        self::assertNull(self::eagerLocalAllowed('flux2-ubl', 'XPTY0004', $ours, $ubl, $ublXml), 'another error code');
        self::assertNull(self::eagerLocalAllowed('flux2-cii', 'FORG0001', ['BR-FR-CO-07_BT-9 fatal'], $cii, $ciiXml), 'no BR-FR-03 finding');
        self::assertNull(self::eagerLocalAllowed('flux2-ubl', 'FORG0001', [], $ubl, $ublXml), 'no finding');
        self::assertNull(self::eagerLocalAllowed('flux2-cii', 'FORG0001', ['BR-FR-030 fatal'], $cii, $ciiXml), 'another rule');
        self::assertNull(self::eagerLocalAllowed('flux2-cii', 'FORG0001', $ours, ['kind' => 'remove'] + $cii, $ciiXml), 'the date removed, not changed');
        self::assertNull(self::eagerLocalAllowed('flux2-ubl', 'FORG0001', $ours, ['kind' => 'decimals'] + $ubl, $ublXml), 'not the "X Y" text');
        foreach ([[self::UDT, 'Indicator'], [self::CBC, 'PayableAmount'], [self::CBC, 'LatestDeliveryDate'], ['', 'IssueDate'], [self::UDT, 'IssueDate'], [self::CBC, 'DateTimeString']] as [$namespace, $local]) {
            self::assertNull(self::eagerLocalAllowed('flux2-ubl', 'FORG0001', $ours, ['namespace' => $namespace, 'local' => $local] + $ubl, $ublXml), "{{$namespace}}{$local} is not a checked date");
        }
        self::assertNull(self::eagerLocalAllowed('flux2-cii', 'FORG0001', $ours, null, $ciiXml), 'not a mutation');

        // Eager rule let: the total checks of Flux 10 sum the amounts of their tax breakdown.
        [$amount, $xml] = $case('fixture:flux10-base', 'mutation:037-text-TaxableAmount');
        $module = 'file://'.realpath(Fixtures::franceRfeRoot().'/'.self::XSLT['flux10']);
        $error = static fn (string $code = 'FORG0001', ?int $line = 2861, ?string $where = null): OracleDynamicError => new OracleDynamicError($code, 'Cannot convert string "X Y" to double', $line, $where ?? $module);
        $format = ['G1.14-7 fatal'];

        self::assertTrue(self::eagerLetAllowed('flux10', $error(), $format, $amount, $xml));
        self::assertTrue(self::eagerLetAllowed('flux10', $error(), ['G1.14-7 fatal', 'G1.53 fatal'], $amount, $xml));
        self::assertFalse(self::eagerLetAllowed('flux10', $error('XPTY0004'), $format, $amount, $xml), 'another error code');
        self::assertFalse(self::eagerLetAllowed('flux10', $error(line: 2876), $format, $amount, $xml), 'located on an assert of the rule');
        self::assertFalse(self::eagerLetAllowed('flux10', $error(line: 3914), $format, $amount, $xml), 'a rule whose lets do not read the amount');
        self::assertFalse(self::eagerLetAllowed('flux10', $error(line: 1), $format, $amount, $xml), 'not a rule template');
        self::assertFalse(self::eagerLetAllowed('flux10', $error(line: null), $format, $amount, $xml), 'no location');
        self::assertFalse(self::eagerLetAllowed('flux10', $error(where: 'file:///elsewhere/Flux10.xslt'), $format, $amount, $xml), 'another stylesheet');
        self::assertFalse(self::eagerLetAllowed('flux10', $error(), ['G1.14-9 fatal'], $amount, $xml), 'the format rule of another amount');
        self::assertFalse(self::eagerLetAllowed('flux10', $error(), ['G1.14-7 warning'], $amount, $xml), 'another severity');
        self::assertFalse(self::eagerLetAllowed('flux10', $error(), [], $amount, $xml), 'no finding');
        self::assertFalse(self::eagerLetAllowed('flux10', $error(), $format, ['kind' => 'remove'] + $amount, $xml), 'the amount removed, not changed');
        self::assertFalse(self::eagerLetAllowed('flux10', $error(), $format, ['local' => 'CurrencyCode'] + $amount, $xml), 'not an amount');
        self::assertFalse(self::eagerLetAllowed('flux10', $error(), $format, null, $xml), 'not a mutation');

        // Neither entry covers the other's pattern.
        self::assertNull(self::eagerLocalAllowed('flux10', 'FORG0001', $format, $amount, $xml), 'eager function locals do not cover an amount');
        self::assertFalse(self::eagerLetAllowed('flux2-cii', $error(), $ours, $cii, $ciiXml), 'the eager rule let does not cover a date');
    }

    /**
     * Each allow-list entry is used exactly as often as pinned when the whole corpus runs in its
     * declaration order; a partial run (a filter) may use each at most that often.
     */
    public function test_allow_lists_are_used_as_pinned(): void
    {
        self::requireCorpus();
        $pinned = ['eager-local-cii' => self::EAGER_LOCAL_CII_USES, 'eager-local-ubl' => 16, 'eager-let' => 4];

        if (self::$parityRuns === count(self::corpus())) {
            self::assertSame($pinned, self::$allowed);

            return;
        }
        foreach ($pinned as $entry => $uses) {
            self::assertLessThanOrEqual($uses, self::$allowed[$entry], $entry);
        }
    }

    /**
     * The first accepted difference, eager function locals: Saxon stops with FORG0001, the
     * document is a mutation that set the text of a checked date element (CHECKED_DATES) to
     * "X Y", and our verdict contains, as fatal, the date format rule BR-FR-03 of an assert whose
     * rule context selects that element. Returns the entry ("eager-local-cii" or
     * "eager-local-ubl"), or null when the case is not one.
     *
     * @param  list<string>  $ours
     * @param  Target|null  $target
     * @return 'eager-local-cii'|'eager-local-ubl'|null
     */
    private static function eagerLocalAllowed(string $set, string $saxonErrorCode, array $ours, ?array $target, string $xml): ?string
    {
        if ($saxonErrorCode !== 'FORG0001' || $target === null || $target['kind'] !== 'text'
            || ! in_array($target['local'], self::CHECKED_DATES[$target['namespace']] ?? [], true)) {
            return null;
        }
        $document = self::parse($xml);
        $leaf = self::leaves($document)[$target['index']] ?? null;
        if ($leaf === null) {
            return null;
        }
        foreach (self::ruleIdsSelecting($set, $document, $leaf, self::DATE_RULE) as $id) {
            if (in_array("{$id} fatal", $ours, true)) {
                return $target['namespace'] === self::CBC ? 'eager-local-ubl' : 'eager-local-cii';
            }
        }

        return null;
    }

    /**
     * The second accepted difference, eager rule let: Saxon evaluates a single-valued rule-level let eagerly,
     * even on the first call, although no evaluated assert path reads it. All of these hold:
     * - Saxon stops with FORG0001, located on the start tag of a rule template of the rule set's
     *   own stylesheet (not on an assert instruction inside it);
     * - a let of that template (an xsl:variable child, the compiled rule-level let) converts the
     *   mutated element to a number with sum() or number();
     * - the document is a mutation that set the text of a Flux 10 amount (FLUX10_AMOUNTS) to "X Y";
     * - our verdict contains the amount format rule (G1.14) of an assert whose rule context
     *   selects the mutated element.
     *
     * @param  list<string>  $ours
     * @param  Target|null  $target
     */
    private static function eagerLetAllowed(string $set, OracleDynamicError $error, array $ours, ?array $target, string $xml): bool
    {
        if ($error->errorCode !== 'FORG0001' || $target === null || $target['kind'] !== 'text'
            || $target['namespace'] !== '' || ! in_array($target['local'], self::FLUX10_AMOUNTS, true)
            || $error->lineNumber === null || $error->module === null) {
            return false;
        }

        $stylesheet = realpath(Fixtures::franceRfeRoot().'/'.self::XSLT[$set]);
        if ($stylesheet === false || ! str_starts_with($error->module, 'file://') || rawurldecode(substr($error->module, strlen('file://'))) !== $stylesheet) {
            return false;
        }
        $template = self::ruleTemplateAt($stylesheet, $error->lineNumber);
        if ($template === null) {
            return false;
        }
        $document = self::parse($xml);
        $leaf = self::leaves($document)[$target['index']] ?? null;
        if ($leaf === null || ! self::letConverts($template, $document, $leaf)) {
            return false;
        }

        foreach (self::ruleIdsSelecting($set, $document, $leaf, self::AMOUNT_RULE) as $id) {
            if (in_array("{$id} fatal", $ours, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The xsl:template of a Schematron rule whose start tag ends on $line (where Saxon locates an
     * error it raises while evaluating the template's variables), or null.
     */
    private static function ruleTemplateAt(string $stylesheet, int $line): ?DOMElement
    {
        if (! isset(self::$stylesheets[$stylesheet])) {
            $document = new DOMDocument;
            if (! $document->load($stylesheet, LIBXML_NONET)) {
                throw new RuntimeException("Cannot parse {$stylesheet}.");
            }
            self::$stylesheets[$stylesheet] = $document;
        }
        $xpath = new DOMXPath(self::$stylesheets[$stylesheet]);
        $xpath->registerNamespace('xsl', self::XSL);
        $xpath->registerNamespace('svrl', 'http://purl.oclc.org/dsdl/svrl');
        foreach ($xpath->query('//xsl:template[svrl:fired-rule]') ?: [] as $template) {
            if ($template instanceof DOMElement && $template->getLineNo() === $line) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Whether a let of the rule template (an xsl:variable child) converts $leaf to a number: its
     * select applies sum() or number() to a relative path that, from a node the template matches,
     * selects $leaf.
     */
    private static function letConverts(DOMElement $template, DOMDocument $document, DOMElement $leaf): bool
    {
        $xpath = new DOMXPath($document);
        // The FNFE rule contexts of Flux 10 are absolute paths, which XPath 1.0 evaluates as is;
        // a match pattern it cannot evaluate selects nothing here.
        $contexts = @$xpath->query($template->getAttribute('match'));
        foreach ($template->childNodes as $let) {
            if (! $let instanceof DOMElement || $let->namespaceURI !== self::XSL || $let->localName !== 'variable') {
                continue;
            }
            preg_match_all('/\b(?:sum|number)\(\s*([A-Za-z_][\w.-]*(?:\/[A-Za-z_][\w.-]*)*)\s*\)/', $let->getAttribute('select'), $paths);
            foreach ($paths[1] as $path) {
                foreach ($contexts ?: [] as $context) {
                    if (! $context instanceof DOMElement) {
                        continue;
                    }
                    foreach (@$xpath->query($path, $context) ?: [] as $node) {
                        if ($node instanceof DOMElement && $node->isSameNode($leaf)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Our ids matching $idPattern on the asserts whose rule context selects $leaf.
     *
     * A rule context is a match pattern: each relative alternative of it matches at any depth, so
     * it is evaluated with "//" in front, with the prefixes of the rule set. A context XPath 1.0
     * cannot evaluate selects nothing here, which can only withhold an allowance.
     *
     * @return list<string>
     */
    private static function ruleIdsSelecting(string $set, DOMDocument $document, DOMElement $leaf, string $idPattern): array
    {
        $ruleSet = self::ruleSet($set);
        $xpath = new DOMXPath($document);
        foreach ($ruleSet->namespaces() as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }
        $ids = [];
        foreach ($ruleSet->patterns() as $pattern) {
            foreach ($pattern['rules'] as $rule) {
                $alternatives = array_map(
                    static fn (string $alternative): string => str_starts_with($alternative, '/') ? $alternative : '//'.$alternative,
                    self::alternatives($rule['context']),
                );
                $hit = false;
                foreach (@$xpath->query(implode(' | ', $alternatives)) ?: [] as $node) {
                    $hit = $hit || ($node instanceof DOMElement && $node->isSameNode($leaf));
                }
                foreach ($hit ? $rule['asserts'] : [] as $assert) {
                    if (preg_match($idPattern, $assert['id']) === 1) {
                        $ids[] = $assert['id'];
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * The top-level alternatives of a union pattern, trimmed; a "|" inside brackets, parentheses
     * or a string literal does not split.
     *
     * @return list<string>
     */
    private static function alternatives(string $pattern): array
    {
        $alternatives = [];
        $depth = 0;
        $quote = null;
        $current = '';
        foreach (str_split($pattern) as $char) {
            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '[' || $char === '(') {
                $depth++;
            } elseif ($char === ']' || $char === ')') {
                $depth--;
            } elseif ($char === '|' && $depth === 0) {
                $alternatives[] = trim($current);
                $current = '';

                continue;
            }
            $current .= $char;
        }
        $alternatives[] = trim($current);

        return $alternatives;
    }

    /**
     * The element a "text" or other leaf mutation changes, as found in its base.
     *
     * @return Target|null null for a document that is not a mutation
     */
    private static function target(string $base, string $variant): ?array
    {
        if (preg_match('/^mutation:(\d+)-(text|remove|decimals)-/', $variant, $match) !== 1) {
            return null;
        }
        $leaf = self::leaves(self::parse(self::base($base)))[(int) $match[1]] ?? throw new RuntimeException("The mutation {$variant} names no leaf.");

        return ['kind' => $match[2], 'namespace' => $leaf->namespaceURI ?? '', 'local' => $leaf->localName ?? '', 'index' => (int) $match[1]];
    }

    /**
     * Our verdict and dynamic errors.
     *
     * @return array{list<string>, list<DynamicError>}
     */
    private static function ours(string $set, string $xml): array
    {
        $result = (new Runner)->run(self::ruleSet($set), SafeXml::loadPreservingWhitespace($xml));
        $verdict = array_values(array_unique(array_map(static fn (Finding $finding): string => "{$finding->ruleId} {$finding->severity->value}", $result->findings)));
        sort($verdict, SORT_STRING);

        return [$verdict, $result->dynamicErrors];
    }

    /**
     * Saxon's verdict with FNFE's stylesheet, with our ids for entries without one.
     *
     * @return list<string>
     *
     * @throws OracleDynamicError when Saxon stops with a dynamic error
     */
    private static function official(string $set, string $xml): array
    {
        $path = self::XSLT[$set];
        if (! isset(FranceSources::files('oracle')[$path])) {
            throw new RuntimeException("{$path} is not an oracle file of the France_RFE manifest.");
        }
        // SaxonC parses a string, not a byte stream, and refuses the UTF-8 byte order mark the FNFE
        // examples start with; the mark is not part of the document's content.
        $fired = OfficialRules::run(
            Fixtures::franceRfeRoot().'/'.$path,
            str_starts_with($xml, "\u{FEFF}") ? substr($xml, 3) : $xml,
            in_array($set, self::ROOT_RULE, true),
            in_array($set, self::WARNING_DEFAULT, true) ? 'warning' : 'fatal',
            self::SVRL_PREFIX,
        );

        return OfficialRules::verdict(array_map(
            static fn (FiredRule $rule): FiredRule => new FiredRule(self::ourId($set, $rule->id), $rule->flag, $rule->location, $rule->text),
            $fired,
        ));
    }

    /**
     * The id of an SVRL entry: its own, or for an entry OfficialRules marked as having none, the
     * id of the assert with its context and test.
     */
    private static function ourId(string $set, string $id): string
    {
        if (! str_starts_with($id, self::SVRL_PREFIX.'-')) {
            return $id;
        }
        if (! isset(self::$markers[$set])) {
            self::$ids[$set] = FranceIds::map(self::ruleSet($set));
            self::$markers[$set] = [];
            foreach (array_keys(self::$ids[$set]) as $key) {
                self::$markers[$set][self::SVRL_PREFIX.'-'.substr(hash('sha256', $key), 0, 10)] = $key;
            }
        }
        $key = self::$markers[$set][$id] ?? throw new RuntimeException("The SVRL entry {$id} has no id and matches no assert of fr-{$set} by context and normalised test.");

        return FranceIds::lookup(self::$ids[$set], $key);
    }

    /**
     * The French rule sets the validation chain of a document runs.
     *
     * @return list<string>
     */
    private static function setsOf(string $xml): array
    {
        $document = SafeXml::loadPreservingWhitespace($xml);
        $root = $document->documentElement;
        if ($root === null) {
            throw new RuntimeException('The document has no document element.');
        }
        if ($root->namespaceURI === self::CDAR_ROOT) {
            return ['cdar'];
        }
        if ($root->namespaceURI === null && $root->localName === 'Report') {
            return ['flux10'];
        }
        if ($root->namespaceURI === CiiDocument::RSM) {
            $id = (string) (new CiiDocument($document))->token('rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID');

            return match (true) {
                FrenchProfile::fromSpecificationIdentifier($id) === FrenchProfile::ExtendedCtcFr => ['flux2-cii', 'extended-ctc-fr-cii'],
                FacturXProfile::fromGuidelineId($id) === FacturXProfile::Extended => ['flux2-cii', 'facturx-extended'],
                default => ['flux2-cii'],
            };
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $customization = $xpath->evaluate('string(/*/cbc:CustomizationID)');
        $id = is_string($customization) ? trim($customization) : '';

        return FrenchProfile::fromSpecificationIdentifier($id) === FrenchProfile::ExtendedCtcFr ? ['flux2-ubl', 'extended-ctc-fr-ubl'] : ['flux2-ubl'];
    }

    /**
     * Every base checked unchanged: the FNFE examples, the writer outputs, our fixtures and the
     * round trips.
     *
     * @return list<string>
     */
    private static function documentBases(): array
    {
        $bases = [];
        foreach (array_keys(FranceSources::files('example')) as $path) {
            if (str_ends_with($path, '.xml')) {
                $bases[] = "fnfe:{$path}";
            }
        }
        foreach (array_keys(FrenchInvoices::all()) as $factory) {
            $bases[] = "writer:{$factory}:ubl";
            $bases[] = "writer:{$factory}:cii";
        }
        array_push($bases, ...self::EXTENDED_WRITER_BASES, ...self::FIXTURE_BASES);
        foreach (self::ROUND_TRIP_FOLDERS as $folder) {
            foreach (Fixtures::franceExamples($folder) as $file) {
                $bases[] = 'roundtrip:'.substr($file, strlen(Fixtures::franceRfeRoot()) + 1);
            }
        }

        return $bases;
    }

    /**
     * The XML of a base: a NegativeCorpusTest base, or "roundtrip:<manifest path>" for an FNFE
     * example read into the model and written back in its syntax (Factur-X in its profile).
     */
    private static function base(string $base): string
    {
        if (isset(self::$bases[$base])) {
            return self::$bases[$base];
        }
        if (! str_starts_with($base, 'roundtrip:')) {
            return self::$bases[$base] = NegativeCorpusTest::load($base);
        }

        $path = substr($base, strlen('roundtrip:'));
        $source = NegativeCorpusTest::load("fnfe:{$path}");
        if (str_contains($path, '/UBL/')) {
            $xml = (new UblWriter)->write((new UblReader)->read($source)->invoice);
        } elseif (str_contains($path, '/FX/')) {
            $document = SafeXml::loadPreservingWhitespace($source);
            $profile = FacturXProfile::fromGuidelineId((string) (new CiiDocument($document))->token('rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'))
                ?? throw new RuntimeException("{$path} declares no Factur-X profile.");
            $xml = (new FacturXXmlWriter)->write((new CiiReader)->read($source)->invoice, $profile)->xml;
        } else {
            $xml = (new CiiWriter)->write((new CiiReader)->read($source)->invoice);
        }

        return self::$bases[$base] = $xml;
    }

    private static function document(string $base, string $variant): string
    {
        if ($variant === '') {
            return self::base($base);
        }
        if (str_starts_with($variant, 'recipe:')) {
            $recipe = self::recipes()[(int) substr($variant, strlen('recipe:'))] ?? throw new RuntimeException("No recipe {$variant}.");

            return NegativeCorpusTest::edited($recipe);
        }
        if (str_starts_with($variant, 'mutation:')) {
            return self::mutation(self::base($base), substr($variant, strlen('mutation:')));
        }

        throw new RuntimeException("Unknown variant {$variant}.");
    }

    /**
     * @return list<array{set: string, rule: string, base: string, edits: list<array<int, string>>, fork?: string}>
     */
    private static function recipes(): array
    {
        return self::$recipes ??= NegativeCorpusTest::allRecipes();
    }

    /**
     * Labels "<leaf index>-<kind>-<local name>" of the leaf mutations of a document.
     *
     * @return list<string>
     */
    private static function mutationLabels(string $xml): array
    {
        $labels = [];
        foreach (self::leaves(self::parse($xml)) as $index => $leaf) {
            $kinds = ['text', 'remove'];
            if (preg_match('/^-?\d+(\.\d+)?$/', trim($leaf->textContent)) === 1) {
                $kinds[] = 'decimals';
            }
            foreach ($kinds as $kind) {
                $labels[] = sprintf('%03d-%s-%s', $index, $kind, $leaf->localName);
            }
        }

        return $labels;
    }

    private static function mutation(string $xml, string $label): string
    {
        if (preg_match('/^(\d+)-(text|remove|decimals)-/', $label, $match) !== 1) {
            throw new RuntimeException("Malformed mutation label {$label}.");
        }
        [, $index, $kind] = $match;

        return Mutations::apply($xml, static function (DOMXPath $xpath) use ($index, $kind, $label): void {
            $leaf = self::leaves($xpath->document)[(int) $index] ?? throw new RuntimeException("The mutation {$label} names no leaf.");
            $text = trim($leaf->textContent);
            match ($kind) {
                'text' => $leaf->textContent = self::MUTATED_TEXT,
                'remove' => $leaf->parentNode?->removeChild($leaf),
                default => $leaf->textContent = (str_contains($text, '.') ? $text : $text.'.').'001',
            };
        }, $label);
    }

    /**
     * @return list<DOMElement>
     */
    private static function leaves(DOMDocument $document): array
    {
        $leaves = [];
        foreach ((new DOMXPath($document))->query(self::LEAVES) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $leaves[] = $node;
            }
        }

        return $leaves;
    }

    private static function parse(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = true;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The document is not well-formed XML.');
        }

        return $document;
    }

    private static function ruleSet(string $set): RuleSet
    {
        return self::$ruleSets[$set] ??= FranceRules::load(InstalledFranceRules::dir(), $set);
    }

    private static function corpusAvailable(): bool
    {
        return Fixtures::hasFranceRfe() && OfficialRules::available();
    }

    /**
     * Skips the test when the provider returned its placeholder case.
     */
    private static function requireCorpus(): void
    {
        Fixtures::franceRfeRoot();
        if (! OfficialRules::available()) {
            self::markTestSkipped('The SaxonC-HE PHP extension is not loaded; run docker/oracle/run.sh.');
        }
    }

    /**
     * @param  list<string>  $verdict
     * @param  list<DynamicError>  $errors
     */
    private static function describe(array $verdict, array $errors): string
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
}
