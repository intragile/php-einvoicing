<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Poland;

use DateTimeImmutable;
use DOMDocument;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\France\Negative\Mutator;
use IntrAgile\EInvoicing\Tests\Support\PolishInvoices;
use IntrAgile\EInvoicing\Tests\Support\PolishSamples;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\MessageValidationReport;
use IntrAgile\EInvoicing\Validation\Poland\Fa3Rules;
use IntrAgile\EInvoicing\Validation\Poland\Fa3Validator;
use IntrAgile\EInvoicing\Validation\SchemaError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The FA(3) brochure rules: silent on every valid document we hold, and each rule reported on a
 * schema-valid document edited to break it (fa3-rule-cases.php).
 *
 * A case is ['rule' => id, 'base' => name, 'edits' => Mutator edits with the prefix "fa"]. The base
 * is an official template or brochure fixture file name (ending in ".xml") or the name of a
 * PolishInvoices factory, written with Fa3Writer at a fixed clock.
 */
final class Fa3RulesTest extends TestCase
{
    private const CLOCK = '2026-11-18T09:30:47+01:00';

    private const NAMESPACES = ['fa' => Fa3Format::NS];

    /**
     * @return array<string, array{string}>
     */
    public static function validCorpus(): array
    {
        $documents = [];
        foreach (PolishSamples::officialNames() as $name) {
            $documents['official/'.$name] = [PolishSamples::official($name)];
        }
        foreach (Fixtures::polandBrochure() as $name => $xml) {
            $documents['brochure/'.$name] = [$xml];
        }
        foreach (array_keys(PolishInvoices::all() + PolishInvoices::kinds()) as $name) {
            $documents['factory/'.$name] = [self::base($name)];
        }

        return $documents;
    }

    #[DataProvider('validCorpus')]
    public function test_valid_corpus_has_no_findings(string $xml): void
    {
        $report = Fa3Validator::create()->validate($xml);

        self::assertSame([], self::schemaMessages($report));
        self::assertSame([], self::describe($report->findings));
        self::assertTrue($report->isValid());
    }

    public function test_valid_corpus_holds_every_document(): void
    {
        self::assertCount(3 + 5 + 14, self::validCorpus());
    }

    /**
     * @return array<string, array{string, string, list<array<int, string>>}>
     */
    public static function cases(): array
    {
        $cases = [];
        foreach (self::caseList() as $case) {
            $name = $case['rule'];
            for ($n = 2; isset($cases[$name]); $n++) {
                $name = $case['rule'].':'.$n;
            }
            $cases[$name] = [$case['rule'], $case['base'], $case['edits']];
        }

        return $cases;
    }

    /**
     * @param  list<array<int, string>>  $edits
     */
    #[DataProvider('cases')]
    public function test_case_fires_its_rule(string $rule, string $base, array $edits): void
    {
        $xml = Mutator::apply(self::base($base), $edits, self::NAMESPACES);
        $report = Fa3Validator::create()->validate($xml);

        self::assertSame([], self::schemaMessages($report), 'A case must stay schema-valid.');
        $severity = Fa3Rules::rules()[$rule]['severity'];
        self::assertSame(["{$rule} {$severity->value}"], $report->verdict(), implode("\n", self::describe($report->findings)));
    }

    public function test_every_case_base_is_clean(): void
    {
        foreach (array_unique(array_column(self::caseList(), 'base')) as $base) {
            self::assertSame([], self::describe(Fa3Validator::create()->validate(self::base($base))->findings), $base);
        }
    }

    public function test_every_rule_has_a_case(): void
    {
        $covered = array_unique(array_column(self::caseList(), 'rule'));

        self::assertSame([], array_values(array_diff(array_keys(Fa3Rules::rules()), $covered)));
        self::assertSame([], array_values(array_diff($covered, array_keys(Fa3Rules::rules()))));
    }

    public function test_every_rule_cites_a_source(): void
    {
        $ids = array_map(static fn (int $n): string => sprintf('PL-FA3-%02d', $n), range(1, 21));
        self::assertSame($ids, array_keys(Fa3Rules::rules()));

        foreach (Fa3Rules::rules() as $id => $rule) {
            self::assertMatchesRegularExpression('/\ABroszura FA\(3\) 2026-08, pp?\. \d+|\Aksef-api faktury\/numer-ksef\.md\z/', $rule['source'], $id);
            self::assertNotSame('', $rule['message'], $id);
        }
    }

    public function test_findings_carry_the_rule_source_and_a_node_path(): void
    {
        $xml = Mutator::apply(self::base('domesticReverseCharge'), [['/fa:Faktura/fa:Fa/fa:Adnotacje/fa:P_18', 'set', '2']], self::NAMESPACES);

        $findings = Fa3Rules::check(self::load($xml));

        self::assertCount(1, $findings);
        self::assertSame('PL-FA3-19', $findings[0]->ruleId);
        self::assertSame(Fa3Rules::rules()['PL-FA3-19']['source'], $findings[0]->test);
        self::assertSame('/fa:Faktura[1]/fa:Fa[1]/fa:FaWiersz[1]/fa:P_12[1]', $findings[0]->location);
    }

    public function test_rules_run_when_the_schema_fails(): void
    {
        $xml = Mutator::apply(Fixtures::polandBrochure()['rozliczenie.xml'], [
            ['/fa:Faktura/fa:Fa/fa:P_1', 'set', 'not-a-date'],
            ['/fa:Faktura/fa:Fa/fa:Rozliczenie/fa:DoRozliczenia', 'set', '36.00'],
        ], self::NAMESPACES);

        $report = Fa3Validator::create()->validate($xml);

        self::assertCount(1, $report->schemaErrors);
        self::assertStringContainsString('P_1', $report->schemaErrors[0]->message);
        self::assertSame(['PL-FA3-12 fatal'], $report->verdict());
        self::assertSame(['fa3-xsd 1-0E', 'pl-fa3 2026-08'], $report->ruleSets);
    }

    public function test_example_24_balance(): void
    {
        $rozliczenie = Fixtures::polandBrochure()['rozliczenie.xml'];
        $balance = static fn (string $value): MessageValidationReport => Fa3Validator::create()->validate(
            Mutator::apply($rozliczenie, [['/fa:Faktura/fa:Fa/fa:Rozliczenie/fa:DoRozliczenia', 'set', $value]], self::NAMESPACES),
        );

        self::assertSame([], $balance('37')->verdict());
        self::assertSame([], $balance('37.00')->verdict());
        self::assertSame(['PL-FA3-12 fatal'], $balance('36')->verdict());
    }

    public function test_printed_brochure_number_fires_pl_fa3_15(): void
    {
        $xml = Mutator::apply(Fixtures::polandBrochure()['correction-before-after.xml'], [
            ['/fa:Faktura/fa:Fa/fa:DaneFaKorygowanej/fa:NrKSeFFaKorygowanej', 'set', '9999999999-20260714-D5FB0C9ED490-9A'],
        ], self::NAMESPACES);

        $report = Fa3Validator::create()->validate($xml);

        self::assertSame([], self::schemaMessages($report));
        self::assertSame(['PL-FA3-15 fatal'], $report->verdict());
        self::assertSame('/fa:Faktura[1]/fa:Fa[1]/fa:DaneFaKorygowanej[1]/fa:NrKSeFFaKorygowanej[1]', $report->findings[0]->location);
    }

    /**
     * P_15 of a correction, an advance or a settlement is not the invoice total (p. 52), so a small
     * P_15 there says nothing about the split payment threshold.
     */
    public function test_split_payment_threshold_ignores_kinds_without_an_invoice_total(): void
    {
        $splitPayment = ["{$this->fa()}/fa:Adnotacje/fa:P_18A", 'set', '1'];
        $correction = Mutator::apply(Fixtures::polandBrochure()['correction-before-after.xml'], [
            $splitPayment,
            ["{$this->fa()}/fa:P_15", 'set', '-500.00'],
        ], self::NAMESPACES);
        $advance = Mutator::apply(Fixtures::polandBrochure()['advance-eur.xml'], [
            $splitPayment,
            ["{$this->fa()}/fa:KodWaluty", 'set', 'PLN'],
            ["{$this->fa()}/fa:P_14_1W", 'remove'],
            ["{$this->fa()}/fa:P_15", 'set', '100.00'],
        ], self::NAMESPACES);

        foreach (['KOR' => $correction, 'ZAL' => $advance] as $kind => $xml) {
            $report = Fa3Validator::create()->validate($xml);
            self::assertSame([], self::schemaMessages($report), $kind);
            self::assertSame([], $report->verdict(), $kind);
        }
    }

    public function test_simplified_invoice_may_omit_rate_totals(): void
    {
        $xml = Mutator::apply(self::base('domestic'), [
            ["{$this->fa()}/fa:RodzajFaktury", 'set', 'UPR'],
            ["{$this->fa()}/fa:P_13_2", 'remove'],
            ["{$this->fa()}/fa:P_14_2", 'remove'],
        ], self::NAMESPACES);

        $report = Fa3Validator::create()->validate($xml);

        self::assertSame([], self::schemaMessages($report));
        self::assertSame([], $report->verdict());
    }

    public function test_amounts_compare_as_decimals(): void
    {
        $xml = Mutator::apply(Fixtures::polandBrochure()['rozliczenie.xml'], [
            ['/fa:Faktura/fa:Fa/fa:Rozliczenie/fa:SumaObciazen', 'set', '17'],
            ['/fa:Faktura/fa:Fa/fa:Rozliczenie/fa:SumaOdliczen', 'set', '300.0'],
        ], self::NAMESPACES);

        self::assertSame([], Fa3Validator::create()->validate($xml)->verdict());
    }

    private function fa(): string
    {
        return '/fa:Faktura/fa:Fa';
    }

    /**
     * @return list<array{rule: string, base: string, edits: list<array<int, string>>}>
     */
    private static function caseList(): array
    {
        /** @var list<array{rule: string, base: string, edits: list<array<int, string>>}> */
        return require __DIR__.'/fa3-rule-cases.php';
    }

    private static function base(string $name): string
    {
        if (str_ends_with($name, '.xml')) {
            if (in_array($name, PolishSamples::officialNames(), true)) {
                return PolishSamples::official($name);
            }
            $brochure = Fixtures::polandBrochure();
            self::assertArrayHasKey($name, $brochure);

            return $brochure[$name];
        }
        $factories = PolishInvoices::all() + PolishInvoices::kinds();
        self::assertArrayHasKey($name, $factories);

        return (new Fa3Writer(static fn (): DateTimeImmutable => new DateTimeImmutable(self::CLOCK)))->write($factories[$name]);
    }

    private static function load(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        self::assertTrue($document->loadXML($xml));

        return $document;
    }

    /**
     * @return list<string>
     */
    private static function schemaMessages(MessageValidationReport $report): array
    {
        return array_map(static fn (SchemaError $error): string => $error->message, $report->schemaErrors);
    }

    /**
     * @param  list<Finding>  $findings
     * @return list<string>
     */
    private static function describe(array $findings): array
    {
        return array_map(
            static fn (Finding $finding): string => "{$finding->ruleId} {$finding->severity->value} {$finding->location}: {$finding->message}",
            $findings,
        );
    }
}
