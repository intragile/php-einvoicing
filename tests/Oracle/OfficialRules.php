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
 * The official stylesheet is not run as the entry point but imported, unchanged, by a small XSLT
 * 3.0 wrapper whose only template catches dynamic errors around xsl:apply-imports. The SaxonC 13
 * PHP API exposes no error code for a failed transformation (the exception carries only the
 * description) and crashes the process on the next transformation after such an exception, so
 * the error code is taken from the XSLT catch instead.
 *
 * Calling it without the extension marks the running test as skipped; the image built from
 * docker/oracle/Dockerfile provides the extension.
 */
final class OfficialRules
{
    public const EXTENSION = 'saxonc';

    private const SVRL = 'http://purl.oclc.org/dsdl/svrl';

    private const ERROR_NAMESPACE = 'urn:intragile:oracle';

    private static ?SaxonProcessor $processor = null;

    /** @var array<string, XsltExecutable> */
    private static array $executables = [];

    /** @var array<string, XsltExecutable> */
    private static array $directExecutables = [];

    public static function available(): bool
    {
        return extension_loaded(self::EXTENSION);
    }

    /**
     * Failed assertions and successful reports of the stylesheet at $xsltPath on the document $xml,
     * in report order.
     *
     * @param  bool  $requireRootRule  whether a rule on the document element must have fired; false
     *                                 for a second rule layer, such as the Peppol rules, whose
     *                                 relative contexts never match the document element itself
     * @param  string|null  $defaultFlag  the flag of an entry the stylesheet reports without one;
     *                                    null keeps the empty flag
     * @param  string|null  $missingIdPrefix  when given, an entry without an id gets the id the
     *                                        Schematron compiler synthesizes from the context of
     *                                        the rule that fired it and its test; when null, such
     *                                        an entry is an error
     * @return list<FiredRule>
     *
     * @throws OracleDynamicError when Saxon stops the stylesheet with a dynamic error
     */
    public static function run(string $xsltPath, string $xml, bool $requireRootRule = true, ?string $defaultFlag = null, ?string $missingIdPrefix = null): array
    {
        $processor = self::processor();
        $executable = self::$executables[$xsltPath] ??= $processor->newXslt30Processor()->compileFromString(self::wrapper($xsltPath));

        $report = self::transform($processor, $executable, $xsltPath, $xml);
        self::throwDynamicError($report);

        return self::firedRules($report, $requireRootRule ? self::rootElement($xml) : null, $defaultFlag, $missingIdPrefix);
    }

    /**
     * Like run(), but with the official stylesheet itself as the entry point. Only for documents
     * that raise no dynamic error: SaxonC then throws without an error code and the next
     * transformation in the process crashes.
     *
     * @return list<FiredRule>
     */
    public static function runDirect(string $xsltPath, string $xml): array
    {
        $processor = self::processor();
        $executable = self::$directExecutables[$xsltPath] ??= $processor->newXslt30Processor()->compileFromFile(self::existing($xsltPath));

        return self::firedRules(self::transform($processor, $executable, $xsltPath, $xml), self::rootElement($xml));
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

    private static function processor(): SaxonProcessor
    {
        if (! self::available()) {
            Assert::markTestSkipped('The SaxonC-HE PHP extension ("'.self::EXTENSION.'") is not loaded; run docker/oracle/run.sh.');
        }

        return self::$processor ??= new SaxonProcessor;
    }

    private static function transform(SaxonProcessor $processor, XsltExecutable $executable, string $xsltPath, string $xml): string
    {
        $report = $executable->transformToString($processor->parseXmlFromString($xml));
        if (! is_string($report) || $report === '') {
            throw new RuntimeException("The stylesheet {$xsltPath} produced no SVRL report.");
        }

        return $report;
    }

    private static function existing(string $xsltPath): string
    {
        $path = realpath($xsltPath);
        if ($path === false || ! is_file($path)) {
            throw new RuntimeException("Stylesheet not found: {$xsltPath}");
        }

        return $path;
    }

    /**
     * An XSLT 3.0 stylesheet that imports the official one and runs it from a document-node
     * template of higher import precedence, catching any dynamic error into an element in the
     * ERROR_NAMESPACE that carries the local name of the error code.
     */
    private static function wrapper(string $xsltPath): string
    {
        $href = htmlspecialchars('file://'.str_replace('%2F', '/', rawurlencode(self::existing($xsltPath))), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $namespace = self::ERROR_NAMESPACE;

        return <<<XSL
            <xsl:stylesheet version="3.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:err="http://www.w3.org/2005/xqt-errors" xmlns:oracle="{$namespace}" exclude-result-prefixes="err">
              <xsl:import href="{$href}"/>
              <xsl:template match="/">
                <xsl:try>
                  <xsl:apply-imports/>
                  <xsl:catch>
                    <oracle:dynamic-error code="{local-name-from-QName(\$err:code)}"><xsl:value-of select="\$err:description"/></oracle:dynamic-error>
                  </xsl:catch>
                </xsl:try>
              </xsl:template>
            </xsl:stylesheet>
            XSL;
    }

    /**
     * @throws OracleDynamicError when the wrapper caught a dynamic error
     */
    private static function throwDynamicError(string $report): void
    {
        if (! str_contains($report, self::ERROR_NAMESPACE)) {
            return;
        }
        $document = new DOMDocument;
        if (! $document->loadXML($report, LIBXML_NONET) || ! $document->documentElement instanceof DOMElement) {
            throw new RuntimeException('The oracle report is not well-formed XML.');
        }
        $root = $document->documentElement;
        if ($root->namespaceURI === self::ERROR_NAMESPACE && $root->localName === 'dynamic-error') {
            throw new OracleDynamicError($root->getAttribute('code'), trim($root->textContent));
        }
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
     * @param  array{string, string}|null  $root  Namespace URI and local name of the validated document
     *                                            element, or null when no rule on it needs to fire.
     * @param  string|null  $defaultFlag  flag of an entry without one
     * @param  string|null  $missingIdPrefix  prefix of the synthesized id of an entry without one
     * @return list<FiredRule>
     */
    private static function firedRules(string $report, ?array $root, ?string $defaultFlag = null, ?string $missingIdPrefix = null): array
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
        if ($root !== null && ! self::rootRuleFired($xpath, $root)) {
            throw new RuntimeException('No rule context of the stylesheet matched the document element; is it the right syntax?');
        }

        $rules = [];
        // The SVRL writes each fired rule before the entries it reports, so the nearest preceding
        // fired-rule carries the context of an entry.
        $context = null;
        foreach ($xpath->query('//svrl:fired-rule | //svrl:failed-assert | //svrl:successful-report') ?: [] as $entry) {
            if (! $entry instanceof DOMElement) {
                continue;
            }
            if ($entry->localName === 'fired-rule') {
                $context = $entry->getAttribute('context');

                continue;
            }
            $id = $entry->getAttribute('id');
            if (! $entry->hasAttribute('id') && $missingIdPrefix !== null) {
                if ($context === null) {
                    throw new RuntimeException('The SVRL report contains a '.$entry->localName.' without a rule id before any fired rule.');
                }
                // The formula of the Schematron compiler, restated so the oracle does not share its code.
                $id = "{$missingIdPrefix}-".substr(hash('sha256', $context."\n".$entry->getAttribute('test')), 0, 10);
            }
            if ($id === '') {
                throw new RuntimeException('The SVRL report contains a '.$entry->localName.' without a rule id.');
            }
            $flag = $entry->getAttribute('flag');
            if (! $entry->hasAttribute('flag') && $defaultFlag !== null) {
                $flag = $defaultFlag;
            }
            $text = $xpath->evaluate('normalize-space(svrl:text)', $entry);
            $rules[] = new FiredRule($id, $flag, $entry->getAttribute('location'), is_string($text) ? $text : '');
        }

        return $rules;
    }
}
