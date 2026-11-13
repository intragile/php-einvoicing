<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle\Kosit;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * The verdict of the KoSIT validator on one document, read from its VARL report (namespace
 * http://www.xoev.de/de/validator/varl/1, schema resources/xsd/report.xsd of the configuration).
 *
 * The report names the matched scenario in rep:scenarioMatched/s:scenario/s:name, or carries
 * rep:noScenarioMatched instead. Each validation step is a rep:validationStepResult whose id
 * KoSIT assigns: "val-xml" (well-formedness), "val-xsd" (XML Schema) and "val-sch.<n>" (the n-th
 * Schematron of the scenario). Messages keep the flag of the Schematron assert in their level
 * attribute; the custom levels of the scenario (s:customLevel, embedded in the report's copy of
 * the scenario) are applied here, as KoSIT applies them to the acceptance.
 *
 * A processing error (HTTP 422) is either a body that is not well-formed (no scenario) or, for a
 * matched scenario, a Schematron step that hit an XPath dynamic error: that step's transformation
 * aborts, so it reports no message at all (and still says valid="true"), the later steps run, and
 * the document is rejected.
 */
final readonly class KositReport
{
    private const NS_REPORT = 'http://www.xoev.de/de/validator/varl/1';

    private const NS_SCENARIOS = 'http://www.xoev.de/de/validator/framework/1/scenarios';

    /**
     * @param  list<string>  $verdict
     * @param  bool  $processingError  whether the daemon answered HTTP 422
     */
    private function __construct(
        public ?string $scenario,
        public bool $schemaValid,
        public bool $schematronRan,
        public array $verdict,
        public bool $accepted,
        public bool $processingError,
    ) {}

    /**
     * @param  int  $httpStatus  the status the daemon answered with: 200 when accepted, 406 when
     *                           rejected, 422 when the input is not well-formed
     *
     * @throws RuntimeException when the report is not a VARL report, gives one code two custom
     *                          levels, or its acceptance contradicts the status or the verdict
     */
    public static function fromXml(string $reportXml, int $httpStatus): self
    {
        $document = new DOMDocument;
        if ($reportXml === '' || ! $document->loadXML($reportXml, LIBXML_NONET)) {
            throw new RuntimeException('The KoSIT answer is not well-formed XML.');
        }
        $root = $document->documentElement;
        if (! $root instanceof DOMElement || $root->namespaceURI !== self::NS_REPORT || $root->localName !== 'report') {
            throw new RuntimeException('The KoSIT answer is not a rep:report.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('rep', self::NS_REPORT);
        $xpath->registerNamespace('s', self::NS_SCENARIOS);

        $scenario = self::firstText($xpath, '/rep:report/rep:scenarioMatched/s:scenario/s:name');

        // A customLevel names a whitespace-separated list of codes (s:Tokens in scenarios.xsd).
        $customLevels = [];
        foreach (self::elements($xpath, '/rep:report/rep:scenarioMatched/s:scenario//s:customLevel') as $level) {
            foreach (preg_split('/\s+/', trim($level->textContent), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $code) {
                if (isset($customLevels[$code])) {
                    throw new RuntimeException(sprintf('The KoSIT scenario "%s" gives code "%s" more than one custom level.', $scenario ?? '', $code));
                }
                $customLevels[$code] = $level->getAttribute('level');
            }
        }

        $steps = '/rep:report/rep:scenarioMatched/rep:validationStepResult';
        $schemaValid = self::elements($xpath, $steps.'[@id = "val-xsd" and @valid = "true"]') !== [];
        $schematronSteps = self::elements($xpath, $steps.'[starts-with(@id, "val-sch.")]');

        $verdict = [];
        foreach ($schematronSteps as $step) {
            foreach (self::elements($xpath, 'rep:message', $step) as $message) {
                $code = $message->getAttribute('code');
                $level = $customLevels[$code] ?? $message->getAttribute('level');
                $verdict[$code.' '.($level === 'error' ? 'fatal' : $level)] = true;
            }
        }
        $verdict = array_keys($verdict);
        sort($verdict, SORT_STRING);

        $accepted = self::elements($xpath, '/rep:report/rep:assessment/rep:accept') !== [];
        if ($accepted !== ($httpStatus === 200)) {
            throw new RuntimeException(sprintf(
                'The KoSIT report says %s but the daemon answered HTTP %d.',
                $accepted ? 'accept' : 'reject',
                $httpStatus,
            ));
        }

        // With a matched scenario, a valid schema and no processing error, the scenarios of this
        // configuration accept exactly the documents without an error-level Schematron message
        // after custom levels.
        $processingError = $httpStatus === 422;
        $fatal = array_values(array_filter($verdict, static fn (string $entry): bool => str_ends_with($entry, ' fatal')));
        if ($scenario !== null && $schemaValid && ! $processingError && $accepted !== ($fatal === [])) {
            throw new RuntimeException(sprintf(
                'The KoSIT report says %s but the derived verdict has %s.',
                $accepted ? 'accept' : 'reject',
                $fatal === [] ? 'no fatal entry' : 'the fatal entries '.implode(', ', $fatal),
            ));
        }

        return new self($scenario, $schemaValid, $schematronSteps !== [], $verdict, $accepted, $processingError);
    }

    private static function firstText(DOMXPath $xpath, string $expression): ?string
    {
        $elements = self::elements($xpath, $expression);

        return $elements === [] ? null : trim($elements[0]->textContent);
    }

    /**
     * @return list<DOMElement>
     */
    private static function elements(DOMXPath $xpath, string $expression, ?DOMElement $context = null): array
    {
        $nodes = $xpath->query($expression, $context);
        $elements = [];
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $elements[] = $node;
                }
            }
        }

        return $elements;
    }
}
