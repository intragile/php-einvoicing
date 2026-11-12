<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use PHPUnit\Framework\Assert;
use RuntimeException;
use Saxon\SaxonProcessor;
use Throwable;

/**
 * Evaluates a single XPath expression with Saxon-HE (SaxonC PHP extension) and prints the result
 * in the canonical form of IntrAgile\EInvoicing\XPath\Canonical, so both engines can be compared
 * on the same expected string.
 */
final class OfficialXPath
{
    private static ?SaxonProcessor $processor = null;

    /**
     * @param  array<string, string>  $namespaces  prefix => URI bound in the expression
     * @param  string  $prelude  top-level XSLT declarations (such as xsl:function) the expression may use
     */
    public static function probe(string $expression, string $xml, array $namespaces, string $prelude = ''): string
    {
        if (! OfficialRules::available()) {
            Assert::markTestSkipped('The SaxonC-HE PHP extension ("'.OfficialRules::EXTENSION.'") is not loaded; run docker/oracle/run.sh.');
        }

        $processor = self::$processor ??= new SaxonProcessor;

        try {
            $executable = $processor->newXslt30Processor()->compileFromString(self::stylesheet($expression, $namespaces, $prelude));
        } catch (Throwable $exception) {
            return 'error:'.self::errorCode($exception->getMessage());
        }

        $result = $executable->transformToString($processor->parseXmlFromString($xml));
        if (! is_string($result)) {
            throw new RuntimeException("Saxon produced no result for {$expression}.");
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $namespaces
     */
    private static function stylesheet(string $expression, array $namespaces, string $prelude): string
    {
        $declarations = '';
        foreach ($namespaces as $prefix => $uri) {
            $declarations .= sprintf(' xmlns:%s="%s"', $prefix, self::escape($uri));
        }
        $select = self::escape("concat('(', string-join(for \$i in ({$expression}) return p:item(\$i), ', '), ')')");

        return <<<XSL
            <xsl:stylesheet version="3.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:err="http://www.w3.org/2005/xqt-errors"
                xmlns:p="urn:intragile:probe"{$declarations} exclude-result-prefixes="#all">
              <xsl:output method="text"/>
              <xsl:function name="p:step" as="xs:string">
                <xsl:param name="n" as="node()"/>
                <xsl:sequence select="if (\$n instance of attribute()) then concat('/@', name(\$n))
                    else if (\$n instance of element()) then concat('/', name(\$n), '[', count(\$n/preceding-sibling::*[name() eq name(\$n)]) + 1, ']')
                    else concat('/text()[', count(\$n/preceding-sibling::text()) + 1, ']')"/>
              </xsl:function>
              <xsl:function name="p:item" as="xs:string">
                <xsl:param name="i" as="item()"/>
                <xsl:sequence select="if (\$i instance of document-node()) then 'node:/'
                    else if (\$i instance of node()) then concat('node:', string-join(for \$a in \$i/ancestor-or-self::node()[not(self::document-node())] return p:step(\$a), ''))
                    else concat(if (\$i instance of xs:integer) then 'xs:integer'
                        else if (\$i instance of xs:decimal) then 'xs:decimal'
                        else if (\$i instance of xs:double) then 'xs:double'
                        else if (\$i instance of xs:string) then 'xs:string'
                        else if (\$i instance of xs:untypedAtomic) then 'xs:untypedAtomic'
                        else if (\$i instance of xs:boolean) then 'xs:boolean'
                        else if (\$i instance of xs:date) then 'xs:date'
                        else 'unknown', ':', string(\$i))"/>
              </xsl:function>
              {$prelude}
              <xsl:template match="/">
                <xsl:try>
                  <xsl:value-of select="{$select}"/>
                  <xsl:catch><xsl:value-of select="concat('error:', local-name-from-QName(\$err:code))"/></xsl:catch>
                </xsl:try>
              </xsl:template>
            </xsl:stylesheet>
            XSL;
    }

    private static function errorCode(string $message): string
    {
        if (preg_match('/\b([A-Z]{4}[0-9]{4})\b/', $message, $match) !== 1) {
            throw new RuntimeException("Saxon reported an error without an error code: {$message}");
        }

        return $match[1];
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
