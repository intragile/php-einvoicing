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
     * Failed assertions of the stylesheet at $xsltPath on the document $xml, in report order.
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

        return self::failedAssertions($report);
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

    private static function rootRuleFired(DOMXPath $xpath): bool
    {
        foreach ($xpath->query('//svrl:fired-rule/@context') ?: [] as $context) {
            foreach (explode('|', $context->nodeValue ?? '') as $alternative) {
                if (preg_match('~^/[A-Za-z][\w.-]*:[\w.-]+$~', trim($alternative)) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<FiredRule>
     */
    private static function failedAssertions(string $report): array
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
        if (! self::rootRuleFired($xpath)) {
            throw new RuntimeException('No rule context of the stylesheet matched the document element; is it the right syntax?');
        }

        $rules = [];
        foreach ($xpath->query('//svrl:failed-assert') ?: [] as $assert) {
            if (! $assert instanceof DOMElement) {
                continue;
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
