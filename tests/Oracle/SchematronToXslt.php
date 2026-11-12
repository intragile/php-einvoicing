<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\Tests\Fixtures;
use PHPUnit\Framework\Assert;
use RuntimeException;
use Saxon\SaxonProcessor;
use Saxon\XsltExecutable;

/**
 * Compiles a Schematron file into a validation stylesheet with the ISO Schematron XSLT 2
 * skeleton (iso_svrl_for_xslt2.xsl), run by SaxonC-HE: the pipeline OpenPeppol's own validator
 * uses, and the stand-in for official compiled stylesheets where none are published.
 *
 * Only self-contained files are accepted: no sch:include, no abstract or instantiated patterns,
 * so the skeleton's include and abstract-expansion steps are not needed. The skeleton parameter
 * allow-foreign is set to true so that xsl:function elements in the schema are copied into the
 * generated stylesheet.
 *
 * The result is cached under build/oracle-xslt/, keyed by the SHA-256 of the schema and the
 * skeleton files.
 */
final class SchematronToXslt
{
    private const CACHE_DIR = __DIR__.'/../../build/oracle-xslt';

    private const ISO = 'http://purl.oclc.org/dsdl/schematron';

    private static ?SaxonProcessor $processor = null;

    private static ?XsltExecutable $skeleton = null;

    /** @var array<string, string> compiled stylesheet by Schematron path, for this process */
    private static array $compiled = [];

    /**
     * Absolute path of the stylesheet compiled from the Schematron file at $schPath.
     */
    public static function compile(string $schPath): string
    {
        return self::$compiled[$schPath] ??= self::compileUncached($schPath);
    }

    private static function compileUncached(string $schPath): string
    {
        $schema = file_get_contents($schPath);
        if ($schema === false) {
            throw new RuntimeException("Cannot read {$schPath}.");
        }
        $skeletonDir = dirname(Fixtures::isoSkeleton());
        $hash = hash_init('sha256');
        hash_update($hash, $schema);
        foreach (['iso_svrl_for_xslt2.xsl', 'iso_schematron_skeleton_for_saxon.xsl'] as $file) {
            hash_update($hash, (string) file_get_contents($skeletonDir.'/'.$file));
        }
        $target = self::CACHE_DIR.'/'.hash_final($hash).'.xslt';
        if (is_file($target)) {
            return (string) realpath($target);
        }

        self::assertSelfContained($schema, $schPath);
        if (! extension_loaded(OfficialRules::EXTENSION)) {
            Assert::markTestSkipped('The SaxonC-HE PHP extension ("'.OfficialRules::EXTENSION.'") is not loaded; run docker/oracle/run.sh.');
        }
        $processor = self::$processor ??= new SaxonProcessor;
        $skeleton = self::$skeleton ??= $processor->newXslt30Processor()->compileFromString(self::skeletonWrapper());
        $xslt = $skeleton->transformToString($processor->parseXmlFromString($schema));
        if (! is_string($xslt) || ! str_contains($xslt, 'http://www.w3.org/1999/XSL/Transform')) {
            throw new RuntimeException("The ISO skeleton produced no stylesheet for {$schPath}.");
        }

        if (! is_dir(self::CACHE_DIR) && ! mkdir(self::CACHE_DIR, 0777, true) && ! is_dir(self::CACHE_DIR)) {
            throw new RuntimeException('Cannot create '.self::CACHE_DIR.'.');
        }
        // Write then rename, so an interrupted run never leaves a truncated stylesheet in the cache.
        $temporary = $target.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($temporary, $xslt);
        rename($temporary, $target);

        return (string) realpath($target);
    }

    /**
     * Rejects schemas that need the skeleton's include or abstract-pattern preprocessing steps.
     */
    private static function assertSelfContained(string $schema, string $schPath): void
    {
        $document = new DOMDocument;
        if (! $document->loadXML($schema, LIBXML_NONET)) {
            throw new RuntimeException("{$schPath} is not well-formed XML.");
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('sch', self::ISO);
        $needsPreprocessing = $xpath->evaluate('count(//sch:include | //sch:extends | //sch:pattern[@abstract = "true" or @is-a])');
        if ($needsPreprocessing !== 0.0) {
            throw new RuntimeException("{$schPath} uses include, extends or abstract patterns; preprocess it first.");
        }
    }

    /**
     * A stylesheet that imports the ISO skeleton and overrides its allow-foreign parameter.
     */
    private static function skeletonWrapper(): string
    {
        $href = htmlspecialchars('file://'.str_replace('%2F', '/', rawurlencode((string) realpath(Fixtures::isoSkeleton()))), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XSL
            <xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
              <xsl:import href="{$href}"/>
              <xsl:param name="allow-foreign">true</xsl:param>
            </xsl:stylesheet>
            XSL;
    }
}
