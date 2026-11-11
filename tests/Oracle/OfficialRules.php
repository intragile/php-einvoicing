<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Assert;
use RuntimeException;
use Saxon\SaxonProcessor;
use Saxon\XsltExecutable;

/**
 * Runs the official CEN EN 16931 validation stylesheets (compiled Schematron, XSLT 2.0)
 * through the SaxonC-HE PHP extension and returns the failed assertions from the SVRL report.
 *
 * Calling it without the extension marks the running test as skipped; the image built from
 * docker/oracle/Dockerfile provides the extension.
 */
final class OfficialRules
{
    public const EXTENSION = 'saxonc';

    private const SVRL = 'http://purl.oclc.org/dsdl/svrl';

    private static ?SaxonProcessor $processor = null;

    /** @var array<string, XsltExecutable> */
    private static array $executables = [];

    public static function available(): bool
    {
        return extension_loaded(self::EXTENSION);
    }

    /**
     * Failed assertions and successful reports of the stylesheet at $xsltPath on the document $xml,
     * in report order.
     *
     * @return list<FiredRule>
     */
    public static function run(string $xsltPath, string $xml): array
    {
        if (! self::available()) {
            Assert::markTestSkipped('The SaxonC-HE PHP extension ("'.self::EXTENSION.'") is not loaded; run docker/oracle/run.sh.');
        }

        $processor = self::$processor ??= new SaxonProcessor;
        $executable = self::$executables[$xsltPath] ??= self::compile($processor, $xsltPath);

        $report = $executable->transformToString($processor->parseXmlFromString($xml));
        if (! is_string($report) || $report === '') {
            throw new RuntimeException("The stylesheet {$xsltPath} produced no SVRL report.");
        }

        return self::firedRules($report, self::rootElement($xml));
    }

    /**
     * @param  list<FiredRule>  $rules
     * @return list<FiredRule>
     */
    public static function fatal(array $rules): array
    {
        return array_values(array_filter($rules, static fn (FiredRule $rule): bool => $rule->isFatal()));
    }

    /**
     * Verdict of a run as comparable data: sorted, de-duplicated "id flag" pairs.
     *
     * @param  list<FiredRule>  $rules
     * @return list<string>
     */
    public static function verdict(array $rules): array
    {
        $verdict = array_values(array_unique(array_map(static fn (FiredRule $rule): string => "{$rule->id} {$rule->flag}", $rules)));
        sort($verdict, SORT_STRING);

        return $verdict;
    }

    private static function compile(SaxonProcessor $processor, string $xsltPath): XsltExecutable
    {
        if (! is_file($xsltPath)) {
            throw new RuntimeException("Stylesheet not found: {$xsltPath}");
        }

        return $processor->newXslt30Processor()->compileFromFile($xsltPath);
    }

    /**
     * Namespace URI and local name of the document element.
     *
     * @return array{string, string}
     */
    private static function rootElement(string $xml): array
    {
        $document = new DOMDocument;
        if (! $document->loadXML($xml, LIBXML_NONET) || ! $document->documentElement instanceof DOMElement) {
            throw new RuntimeException('The document is not well-formed XML.');
        }

        return [$document->documentElement->namespaceURI ?? '', $document->documentElement->localName ?? $document->documentElement->nodeName];
    }

    /**
     * Whether a fired rule has a context that selects exactly the document element, such as
     * "/ubl:Invoice" in "/ubl:Invoice | /cn:CreditNote", with the prefix resolved through the
     * namespace declarations the stylesheet writes into the report.
     *
     * @param  array{string, string}  $root
     */
    private static function rootRuleFired(DOMXPath $xpath, array $root): bool
    {
        $namespaces = [];
        foreach ($xpath->query('//svrl:ns-prefix-in-attribute-values') ?: [] as $declaration) {
            if ($declaration instanceof DOMElement) {
                $namespaces[$declaration->getAttribute('prefix')] = $declaration->getAttribute('uri');
            }
        }

        foreach ($xpath->query('//svrl:fired-rule/@context') ?: [] as $context) {
            foreach (explode('|', $context->nodeValue ?? '') as $alternative) {
                if (preg_match('~^/([A-Za-z][\w.-]*):([\w.-]+)$~', trim($alternative), $match) === 1
                    && ($namespaces[$match[1]] ?? null) === $root[0]
                    && $match[2] === $root[1]) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Failed assertions and successful reports of an SVRL report.
     *
     * @param  array{string, string}  $root  Namespace URI and local name of the validated document element.
     * @return list<FiredRule>
     */
    private static function firedRules(string $report, array $root): array
    {
        $document = new DOMDocument;
        if (! $document->loadXML($report, LIBXML_NONET)) {
            throw new RuntimeException('The SVRL report is not well-formed XML.');
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('svrl', self::SVRL);

        // A document of another syntax or namespace still matches generic contexts (such as every
        // element named *Amount) and would pass with few or no failed assertions, so the rule on the
        // document element itself (for example "/ubl:Invoice | /cn:CreditNote") must have fired.
        if (! self::rootRuleFired($xpath, $root)) {
            throw new RuntimeException('No rule context of the stylesheet matched the document element; is it the right syntax?');
        }

        $rules = [];
        foreach ($xpath->query('//svrl:failed-assert | //svrl:successful-report') ?: [] as $assert) {
            if (! $assert instanceof DOMElement) {
                continue;
            }
            if ($assert->getAttribute('id') === '') {
                throw new RuntimeException('The SVRL report contains a '.$assert->localName.' without a rule id.');
            }
            $text = $xpath->evaluate('normalize-space(svrl:text)', $assert);
            $rules[] = new FiredRule(
                $assert->getAttribute('id'),
                $assert->getAttribute('flag'),
                $assert->getAttribute('location'),
                is_string($text) ? $text : '',
            );
        }

        return $rules;
    }
}
