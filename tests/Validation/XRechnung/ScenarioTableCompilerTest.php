<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\XRechnung;

use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Validation\XRechnung\ScenarioTableCompiler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ScenarioTableCompilerTest extends TestCase
{
    /**
     * The scenario names of the KoSIT configuration 2026-08-31, in configuration order (the order
     * in which KoSIT tries the match expressions).
     */
    private const SCENARIOS = [
        'EN16931 XRechnung (UBL Invoice)',
        'EN16931 XRechnung Extension (UBL Invoice)',
        'EN16931 XRechnung CVD (UBL Invoice)',
        'EN16931 XRechnung (UBL CreditNote)',
        'EN16931 XRechnung CVD (UBL CreditNote)',
        'EN16931 XRechnung (CII)',
        'EN16931 XRechnung Extension (CII)',
        'EN16931 XRechnung CVD (CII)',
        'EN16931 (UBL Invoice)',
        'EN16931 (UBL CreditNote)',
        'EN16931 (CII)',
    ];

    public function test_compiles_all_eleven_scenarios(): void
    {
        $table = self::compileFixture();

        self::assertSame(1, $table['format']);
        self::assertSame('2026-08-31', $table['configuration']);
        self::assertSame(self::SCENARIOS, array_column($table['scenarios'], 'name'));
    }

    public function test_first_scenario_is_compiled_verbatim(): void
    {
        $scenario = self::compileFixture()['scenarios'][0];

        self::assertSame([
            'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            'invoice' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            'rep' => 'http://www.xoev.de/de/validator/varl/1',
        ], $scenario['namespaces']);
        self::assertSame(
            "exists(/invoice:Invoice/cbc:CustomizationID[ . = 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0']) ",
            $scenario['match'],
        );
        self::assertSame('ubl/2.1/xsd/maindoc/UBL-Invoice-2.1.xsd', $scenario['schema']);
        self::assertSame([
            ['id' => 'en16931-ubl', 'version' => '1.3.16'],
            ['id' => 'xrechnung-ubl', 'version' => '2.6.0'],
        ], $scenario['ruleSets']);
    }

    public function test_rule_sets_and_schemas_per_scenario(): void
    {
        $scenarios = self::byName(self::compileFixture());

        self::assertSame('ubl/2.1/xsd/maindoc/UBL-CreditNote-2.1.xsd', $scenarios['EN16931 XRechnung CVD (UBL CreditNote)']['schema']);
        self::assertSame('cii/16b/xsd/CrossIndustryInvoice_100pD16B.xsd', $scenarios['EN16931 XRechnung (CII)']['schema']);
        self::assertSame([
            ['id' => 'en16931-cii', 'version' => '1.3.16'],
            ['id' => 'xrechnung-cii', 'version' => '2.6.0'],
        ], $scenarios['EN16931 XRechnung Extension (CII)']['ruleSets']);
        self::assertSame([['id' => 'en16931-cii', 'version' => '1.3.16']], $scenarios['EN16931 (CII)']['ruleSets']);
        self::assertSame([['id' => 'en16931-ubl', 'version' => '1.3.16']], $scenarios['EN16931 (UBL CreditNote)']['ruleSets']);
    }

    /**
     * BR-CL-21 is lowered to "warning" by the XRechnung scenarios and to "information" by the two
     * Extension scenarios. UBL-CR-646 is raised to "error" by the XRechnung UBL Invoice scenario
     * and the two UBL CVD scenarios, lowered to "information" by the UBL Extension scenario, and
     * left alone (the CEN "warning") in the UBL CreditNote scenario.
     */
    public function test_custom_levels_per_scenario(): void
    {
        $scenarios = self::byName(self::compileFixture());
        $level = static fn (string $scenario, string $rule): ?string => $scenarios[$scenario]['customLevels'][$rule] ?? null;

        self::assertSame('warning', $level('EN16931 XRechnung (UBL Invoice)', 'BR-CL-21'));
        self::assertSame('information', $level('EN16931 XRechnung Extension (UBL Invoice)', 'BR-CL-21'));
        self::assertSame('information', $level('EN16931 XRechnung Extension (CII)', 'BR-CL-21'));
        self::assertSame('warning', $level('EN16931 XRechnung CVD (CII)', 'BR-CL-21'));

        self::assertSame('error', $level('EN16931 XRechnung (UBL Invoice)', 'UBL-CR-646'));
        self::assertSame('error', $level('EN16931 XRechnung CVD (UBL Invoice)', 'UBL-CR-646'));
        self::assertSame('error', $level('EN16931 XRechnung CVD (UBL CreditNote)', 'UBL-CR-646'));
        self::assertSame('information', $level('EN16931 XRechnung Extension (UBL Invoice)', 'UBL-CR-646'));
        self::assertNull($level('EN16931 XRechnung (UBL CreditNote)', 'UBL-CR-646'));

        self::assertSame([], $scenarios['EN16931 (UBL Invoice)']['customLevels']);
        self::assertSame(56, array_sum(array_map(static fn (array $scenario): int => count($scenario['customLevels']), $scenarios)));
    }

    public function test_custom_level_lists_every_rule_id(): void
    {
        $table = ScenarioTableCompiler::compile(self::scenarios(customLevels: '<customLevel level="information">BR-01  BR-02</customLevel>'));

        self::assertSame(['BR-01' => 'information', 'BR-02' => 'information'], $table['scenarios'][0]['customLevels']);
    }

    public function test_unknown_schematron_location_throws(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('resources/peppol/PEPPOL-EN16931-UBL.xsl');

        ScenarioTableCompiler::compile(self::scenarios(schematron: 'resources/peppol/PEPPOL-EN16931-UBL.xsl'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'schema outside resources/' => [self::scenarios(schema: '../ubl/maindoc/UBL-Invoice-2.1.xsd'), '../ubl/maindoc/UBL-Invoice-2.1.xsd'];
        yield 'schema with a parent segment' => [self::scenarios(schema: 'resources/ubl/../../x.xsd'), 'resources/ubl/../../x.xsd'];
        yield 'missing match' => [self::scenarios(match: null), 'match'];
        yield 'other acceptMatch' => [self::scenarios(acceptMatch: '<acceptMatch>count(//rep:message) = 0</acceptMatch>'), 'count(//rep:message) = 0'];
        yield 'missing acceptMatch' => [self::scenarios(acceptMatch: ''), 'acceptMatch'];
        yield 'two schemas' => [self::scenarios(schemaResources: 2), 'validateWithXmlSchema'];
        yield 'psvi schematron step' => [self::scenarios(psvi: true), 'psvi'];
        yield 'unmodelled scenario element' => [self::scenarios(extra: '<validateWithXQuery/>'), 'validateWithXQuery'];
        yield 'unknown custom level' => [self::scenarios(customLevels: '<customLevel level="fatal">BR-01</customLevel>'), 'fatal'];
        yield 'duplicate custom level' => [self::scenarios(customLevels: '<customLevel level="warning">BR-01</customLevel><customLevel level="information">BR-01</customLevel>'), 'BR-01'];
        yield 'not a scenarios document' => ['<scenarios xmlns="urn:other"/>', 'scenarios'];
    }

    #[DataProvider('refusals')]
    public function test_refuses_what_the_table_cannot_model(string $xml, string $named): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($named);

        ScenarioTableCompiler::compile($xml);
    }

    public function test_documentation_elements_are_ignored(): void
    {
        $table = ScenarioTableCompiler::compile(self::scenarios());

        self::assertSame(['Test scenario'], array_column($table['scenarios'], 'name'));
        self::assertSame('ubl/2.1/xsd/maindoc/UBL-Invoice-2.1.xsd', $table['scenarios'][0]['schema']);
    }

    /**
     * @return array{format: int, configuration: string, scenarios: list<array{name: string, namespaces: array<string, string>, match: string, schema: string, ruleSets: list<array{id: string, version: string}>, customLevels: array<string, string>}>}
     */
    private static function compileFixture(): array
    {
        $xml = file_get_contents(Fixtures::xrechnungConfiguration().'/scenarios.xml');
        self::assertIsString($xml);

        return ScenarioTableCompiler::compile($xml);
    }

    /**
     * @param  array{scenarios: list<array{name: string, namespaces: array<string, string>, match: string, schema: string, ruleSets: list<array{id: string, version: string}>, customLevels: array<string, string>}>}  $table
     * @return array<string, array{name: string, namespaces: array<string, string>, match: string, schema: string, ruleSets: list<array{id: string, version: string}>, customLevels: array<string, string>}>
     */
    private static function byName(array $table): array
    {
        return array_column($table['scenarios'], null, 'name');
    }

    /**
     * A one-scenario configuration in the KoSIT format, with one part replaced.
     */
    private static function scenarios(
        string $schematron = 'resources/ubl/2.1/xsl/EN16931-UBL-validation.xsl',
        string $schema = 'resources/ubl/2.1/xsd/maindoc/UBL-Invoice-2.1.xsd',
        ?string $match = 'exists(/invoice:Invoice)',
        string $acceptMatch = '<acceptMatch>/rep:report/rep:assessment[1]/rep:accept[1]</acceptMatch>',
        string $customLevels = '',
        int $schemaResources = 1,
        bool $psvi = false,
        string $extra = '',
    ): string {
        $matchElement = $match === null ? '' : "<match>{$match}</match>";
        $schemaElements = str_repeat("<resource><name>Schema</name><location>{$schema}</location></resource>", $schemaResources);
        $psviAttribute = $psvi ? ' psvi="true"' : '';

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <scenarios xmlns="http://www.xoev.de/de/validator/framework/1/scenarios" frameworkVersion="1.0.0">
              <name>Test</name>
              <author>Test</author>
              <date>2026-09-02</date>
              <description><p>Test configuration</p></description>
              <scenario>
                <name>Test scenario</name>
                <description><p>One scenario</p></description>
                <namespace prefix="invoice">urn:oasis:names:specification:ubl:schema:xsd:Invoice-2</namespace>
                <namespace prefix="rep">http://www.xoev.de/de/validator/varl/1</namespace>
                {$matchElement}
                <validateWithXmlSchema>{$schemaElements}</validateWithXmlSchema>
                <validateWithSchematron{$psviAttribute}>
                  <resource><name>Rules</name><location>{$schematron}</location></resource>
                </validateWithSchematron>
                {$extra}
                <createReport>
                  <resource><name>Report</name><location>resources/xrechnung-report.xsl</location></resource>
                  {$customLevels}
                </createReport>
                {$acceptMatch}
              </scenario>
              <noScenarioReport>
                <resource><name>Default Report</name><location>resources/default-report.xsl</location></resource>
              </noScenarioReport>
            </scenarios>
            XML;
    }
}
