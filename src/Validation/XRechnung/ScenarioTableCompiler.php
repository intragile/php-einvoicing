<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\XRechnung;

use DOMDocument;
use DOMElement;
use UnexpectedValueException;

/**
 * Compiles the scenarios.xml of the KoSIT validator configuration into the scenario table the
 * XRechnung validator runs: per scenario its name, namespace bindings, match expression, XML
 * Schema, Schematron rule sets in step order and custom levels.
 *
 * The table models exactly what decides a verdict. Documentation elements and the report
 * stylesheets are skipped; anything else the table cannot represent (another rule set, a second
 * schema, a PSVI step, an unknown element, another acceptance expression) is refused with the
 * offending element named, so that a configuration update can never drop a validation step
 * silently.
 *
 * @internal
 *
 * @phpstan-type ScenarioEntry array{name: string, namespaces: array<string, string>, match: string, schema: string, ruleSets: list<array{id: string, version: string}>, customLevels: array<string, 'error'|'warning'|'information'>}
 * @phpstan-type ScenarioTable array{format: int, configuration: string, scenarios: list<ScenarioEntry>}
 */
final class ScenarioTableCompiler
{
    /**
     * Version of the table layout, stored in the table.
     */
    public const FORMAT = 1;

    public const NAMESPACE = 'http://www.xoev.de/de/validator/framework/1/scenarios';

    /**
     * The only acceptance expression the table supports: the document is accepted when the
     * report's assessment says so, which KoSIT decides from the highest level after custom levels.
     */
    public const ACCEPT_MATCH = '/rep:report/rep:assessment[1]/rep:accept[1]';

    /**
     * Schema files are taken from below this configuration folder and stored relative to it.
     */
    private const SCHEMA_PREFIX = 'resources/';

    private const LEVELS = ['error', 'warning', 'information'];

    /**
     * Top-level elements that carry no validation step.
     */
    private const IGNORED_TOP_LEVEL = ['name', 'author', 'date', 'description', 'noScenarioReport'];

    /**
     * Compiles the contents of a scenarios.xml file.
     *
     * @return ScenarioTable
     *
     * @throws UnexpectedValueException when the configuration holds anything the table cannot model
     */
    public static function compile(string $scenariosXml): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $scenariosXml !== '' && $document->loadXML($scenariosXml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $document->documentElement;
        if (! $loaded || $root === null || $document->doctype !== null || ! self::is($root, 'scenarios')) {
            throw new UnexpectedValueException('Not a KoSIT scenarios document: expected a well-formed {'.self::NAMESPACE.'}scenarios root without a DOCTYPE.');
        }

        $scenarios = [];
        foreach (self::children($root) as $element) {
            if (self::is($element, 'scenario')) {
                $scenario = self::scenario($element);
                if (isset($scenarios[$scenario['name']])) {
                    throw new UnexpectedValueException("Duplicate scenario name \"{$scenario['name']}\".");
                }
                $scenarios[$scenario['name']] = $scenario;

                continue;
            }
            if (! self::isOneOf($element, self::IGNORED_TOP_LEVEL)) {
                throw self::unmodelled($element, 'scenarios');
            }
        }
        if ($scenarios === []) {
            throw new UnexpectedValueException('The scenarios document defines no scenario.');
        }

        return [
            'format' => self::FORMAT,
            'configuration' => XRechnungSources::CONFIGURATION_VERSION,
            'scenarios' => array_values($scenarios),
        ];
    }

    /**
     * The rule set a Schematron location of the configuration stands for. The configuration ships
     * the rules as stylesheets; the table names the compiled rule sets built from the same releases.
     *
     * @return array<string, array{id: string, version: string}>
     */
    public static function ruleSetLocations(): array
    {
        return [
            'resources/ubl/2.1/xsl/EN16931-UBL-validation.xsl' => ['id' => 'en16931-ubl', 'version' => XRechnungSources::CEN_VERSION],
            'resources/cii/16b/xsl/EN16931-CII-validation.xsl' => ['id' => 'en16931-cii', 'version' => XRechnungSources::CEN_VERSION],
            'resources/xrechnung/'.XRechnungSources::XRECHNUNG_VERSION.'/xsl/XRechnung-UBL-validation.xsl' => ['id' => 'xrechnung-ubl', 'version' => XRechnungSources::SCHEMATRON_VERSION],
            'resources/xrechnung/'.XRechnungSources::XRECHNUNG_VERSION.'/xsl/XRechnung-CII-validation.xsl' => ['id' => 'xrechnung-cii', 'version' => XRechnungSources::SCHEMATRON_VERSION],
        ];
    }

    /**
     * @return ScenarioEntry
     */
    private static function scenario(DOMElement $scenario): array
    {
        $name = null;
        $namespaces = [];
        $match = null;
        $schema = null;
        $ruleSets = [];
        $customLevels = [];
        $acceptMatch = null;

        foreach (self::children($scenario) as $element) {
            switch (self::is($element) ? $element->localName : null) {
                case 'name':
                    $name = self::collapse($element->textContent);
                    break;
                case 'description':
                    break;
                case 'namespace':
                    $prefix = $element->getAttribute('prefix');
                    if ($prefix === '' || isset($namespaces[$prefix])) {
                        throw new UnexpectedValueException("Scenario \"{$name}\": missing or duplicate namespace prefix \"{$prefix}\".");
                    }
                    $namespaces[$prefix] = trim($element->textContent);
                    break;
                case 'match':
                    $match = $element->textContent;
                    break;
                case 'validateWithXmlSchema':
                    if ($schema !== null) {
                        throw new UnexpectedValueException("Scenario \"{$name}\": more than one validateWithXmlSchema step.");
                    }
                    $schema = self::schema($element, (string) $name);
                    break;
                case 'validateWithSchematron':
                    $ruleSets[] = self::ruleSet($element, (string) $name);
                    break;
                case 'createReport':
                    $customLevels = self::customLevels($element, (string) $name);
                    break;
                case 'acceptMatch':
                    $acceptMatch = trim($element->textContent);
                    break;
                default:
                    throw self::unmodelled($element, "scenario \"{$name}\"");
            }
        }

        if ($name === null || $name === '') {
            throw new UnexpectedValueException('A scenario has no name.');
        }
        if ($match === null || trim($match) === '') {
            throw new UnexpectedValueException("Scenario \"{$name}\" has no match expression.");
        }
        if ($schema === null) {
            throw new UnexpectedValueException("Scenario \"{$name}\" has no validateWithXmlSchema step.");
        }
        if ($acceptMatch !== self::ACCEPT_MATCH) {
            throw new UnexpectedValueException(sprintf(
                'Scenario "%s": unsupported acceptMatch "%s"; only "%s" is supported.',
                $name,
                $acceptMatch ?? '(none)',
                self::ACCEPT_MATCH,
            ));
        }

        return [
            'name' => $name,
            'namespaces' => $namespaces,
            'match' => $match,
            'schema' => $schema,
            'ruleSets' => $ruleSets,
            'customLevels' => $customLevels,
        ];
    }

    /**
     * The schema of a validateWithXmlSchema step, relative to the configuration's resources/
     * folder (the layout of the shipped schemas/ folder).
     */
    private static function schema(DOMElement $step, string $scenario): string
    {
        $locations = self::resourceLocations($step, $scenario);
        if (count($locations) !== 1) {
            throw new UnexpectedValueException("Scenario \"{$scenario}\": validateWithXmlSchema with ".count($locations).' resources; the table models exactly one schema.');
        }
        $location = $locations[0];
        $relative = substr($location, strlen(self::SCHEMA_PREFIX));
        if (! str_starts_with($location, self::SCHEMA_PREFIX)
            || ! str_ends_with($location, '.xsd')
            || preg_match('~(^|/)\.{1,2}(/|$)|\\\\|^/|//~', $relative) === 1
        ) {
            throw new UnexpectedValueException("Scenario \"{$scenario}\": schema location \"{$location}\" is not an .xsd file below ".self::SCHEMA_PREFIX.'.');
        }

        return $relative;
    }

    /**
     * @return array{id: string, version: string}
     */
    private static function ruleSet(DOMElement $step, string $scenario): array
    {
        if ($step->hasAttribute('psvi') && in_array(trim($step->getAttribute('psvi')), ['true', '1'], true)) {
            throw new UnexpectedValueException("Scenario \"{$scenario}\": validateWithSchematron with psvi=\"true\" is not supported.");
        }
        $locations = self::resourceLocations($step, $scenario);
        if (count($locations) !== 1) {
            throw new UnexpectedValueException("Scenario \"{$scenario}\": validateWithSchematron needs exactly one resource.");
        }
        $known = self::ruleSetLocations();
        if (! isset($known[$locations[0]])) {
            throw new UnexpectedValueException(sprintf(
                'Scenario "%s": unknown Schematron location "%s"; known locations: %s.',
                $scenario,
                $locations[0],
                implode(', ', array_keys($known)),
            ));
        }

        return $known[$locations[0]];
    }

    /**
     * @return array<string, 'error'|'warning'|'information'> rule id => level
     */
    private static function customLevels(DOMElement $createReport, string $scenario): array
    {
        $levels = [];
        foreach (self::children($createReport) as $element) {
            if (self::is($element, 'resource')) {
                continue;
            }
            if (! self::is($element, 'customLevel')) {
                throw self::unmodelled($element, "createReport of scenario \"{$scenario}\"");
            }
            $level = trim($element->getAttribute('level'));
            if (! in_array($level, self::LEVELS, true)) {
                throw new UnexpectedValueException("Scenario \"{$scenario}\": unknown custom level \"{$level}\"; expected one of ".implode(', ', self::LEVELS).'.');
            }
            $ids = preg_split('/\s+/', trim($element->textContent), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($ids === []) {
                throw new UnexpectedValueException("Scenario \"{$scenario}\": customLevel \"{$level}\" names no rule.");
            }
            foreach ($ids as $id) {
                if (isset($levels[$id])) {
                    throw new UnexpectedValueException("Scenario \"{$scenario}\": rule \"{$id}\" has more than one custom level.");
                }
                $levels[$id] = $level;
            }
        }

        return $levels;
    }

    /**
     * @return list<string>
     */
    private static function resourceLocations(DOMElement $step, string $scenario): array
    {
        $locations = [];
        foreach (self::children($step) as $resource) {
            if (! self::is($resource, 'resource')) {
                throw self::unmodelled($resource, "{$step->localName} of scenario \"{$scenario}\"");
            }
            $location = null;
            foreach (self::children($resource) as $element) {
                if (self::is($element, 'location')) {
                    $location = trim($element->textContent);
                } elseif (! self::is($element, 'name')) {
                    throw self::unmodelled($element, "resource of scenario \"{$scenario}\"");
                }
            }
            if ($location === null || $location === '') {
                throw new UnexpectedValueException("Scenario \"{$scenario}\": a {$step->localName} resource has no location.");
            }
            $locations[] = $location;
        }

        return $locations;
    }

    /**
     * @return list<DOMElement>
     */
    private static function children(DOMElement $parent): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private static function is(DOMElement $element, ?string $localName = null): bool
    {
        return $element->namespaceURI === self::NAMESPACE && ($localName === null || $element->localName === $localName);
    }

    /**
     * @param  list<string>  $localNames
     */
    private static function isOneOf(DOMElement $element, array $localNames): bool
    {
        return self::is($element) && in_array($element->localName, $localNames, true);
    }

    private static function unmodelled(DOMElement $element, string $where): UnexpectedValueException
    {
        $name = $element->namespaceURI === self::NAMESPACE ? $element->localName : '{'.$element->namespaceURI.'}'.$element->localName;

        return new UnexpectedValueException("Unsupported element \"{$name}\" in {$where}: the scenario table does not model it.");
    }

    private static function collapse(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
