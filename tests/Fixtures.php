<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests;

use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Tests\Support\UnitTestDocument;
use InvalidArgumentException;
use RuntimeException;

/**
 * Locates the official CEN EN 16931 artefacts (validation-1.3.17) used as test fixtures.
 */
final class Fixtures
{
    private const OFFICIAL_DIR = __DIR__.'/fixtures/official/cen-1.3.17';

    private const UBL_SCHEMA_DIR = __DIR__.'/fixtures/official/ubl-2.1/xsd/maindoc';

    private const CII_SCHEMA = __DIR__.'/fixtures/official/cii-d16b/CrossIndustryInvoice_100pD16B.xsd';

    private const STYLESHEETS = [
        'ubl' => 'EN16931-UBL-validation.xslt',
        'cii' => 'EN16931-CII-validation.xslt',
    ];

    private const SCHEMATRON = [
        'ubl' => 'EN16931-UBL-validation-preprocessed.sch',
        'cii' => 'EN16931-CII-validation-preprocessed.sch',
    ];

    /** Unit-test directories, in the order their documents are listed. */
    private const UNIT_TEST_DIRS = ['Invoice-unit-UBL', 'CreditNote-unit-UBL', 'cii', 'testfiles'];

    /** Container format of the CEN unit tests: a testSet with one invoice in each test child. */
    private const VEFA_NAMESPACE = 'http://difi.no/xsd/vefa/validator/1.0';

    /** Syntax of an invoice by the namespace of its document element. */
    private const SYNTAX_BY_NAMESPACE = [
        'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2' => 'ubl',
        'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2' => 'ubl',
        'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100' => 'cii',
    ];

    /**
     * Absolute paths of the official example invoices for a syntax, sorted by file name.
     *
     * @return list<string>
     */
    public static function official(string $syntax): array
    {
        $files = array_filter(
            glob(self::syntaxDir($syntax).'/examples/*') ?: [],
            static fn (string $file): bool => strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'xml',
        );
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Absolute path of the official compiled Schematron (XSLT) for a syntax.
     */
    public static function officialXslt(string $syntax): string
    {
        return self::syntaxDir($syntax).'/xslt/'.self::STYLESHEETS[$syntax];
    }

    /**
     * Absolute path of the official preprocessed Schematron (includes and abstract patterns
     * resolved) for a syntax.
     */
    public static function schematron(string $syntax): string
    {
        return self::syntaxDir($syntax).'/schematron/preprocessed/'.self::SCHEMATRON[$syntax];
    }

    /**
     * Every invoice in the CEN unit-test corpus: one entry per test of a testSet file, one entry
     * with index 0 per plain invoice file. Ordered by directory, file name and position.
     *
     * @return list<UnitTestDocument>
     */
    public static function unitTests(): array
    {
        $documents = [];
        foreach (self::UNIT_TEST_DIRS as $dir) {
            $path = self::OFFICIAL_DIR.'/unit-tests/'.$dir;
            $files = array_values(array_filter(
                scandir($path) ?: throw new RuntimeException("Cannot list {$path}"),
                static fn (string $file): bool => strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'xml',
            ));
            sort($files, SORT_STRING);

            foreach ($files as $file) {
                array_push($documents, ...self::unitTestDocuments($path.'/'.$file));
            }
        }

        return $documents;
    }

    /**
     * @return list<UnitTestDocument>
     */
    private static function unitTestDocuments(string $file): array
    {
        $document = new DOMDocument;
        if (! $document->load($file, LIBXML_NONET) || $document->documentElement === null) {
            throw new RuntimeException("{$file} is not well-formed XML.");
        }
        $root = $document->documentElement;

        if ($root->namespaceURI !== self::VEFA_NAMESPACE) {
            return [new UnitTestDocument($file, 0, self::invoiceSyntax($root, $file), (string) $document->saveXML())];
        }

        $documents = [];
        $index = 0;
        foreach ($root->childNodes as $test) {
            if (! $test instanceof DOMElement || $test->namespaceURI !== self::VEFA_NAMESPACE || $test->localName !== 'test') {
                continue;
            }

            $invoices = [];
            foreach ($test->childNodes as $child) {
                if ($child instanceof DOMElement && $child->namespaceURI !== self::VEFA_NAMESPACE) {
                    $invoices[] = $child;
                }
            }
            if (count($invoices) !== 1) {
                throw new RuntimeException("Test {$index} in {$file} holds ".count($invoices).' documents; expected 1.');
            }

            $documents[] = new UnitTestDocument($file, $index, self::invoiceSyntax($invoices[0], $file), self::standalone($invoices[0]));
            $index++;
        }

        return $documents;
    }

    /**
     * @return 'ubl'|'cii'
     */
    private static function invoiceSyntax(DOMElement $element, string $file): string
    {
        return self::SYNTAX_BY_NAMESPACE[(string) $element->namespaceURI]
            ?? throw new RuntimeException("Unexpected document element {{$element->namespaceURI}}{$element->localName} in {$file}.");
    }

    /**
     * Serialises an embedded element as its own document, declaring on its root every namespace
     * in scope at that point, including those declared only on ancestors.
     */
    private static function standalone(DOMElement $element): string
    {
        $standalone = new DOMDocument('1.0', 'UTF-8');
        $root = $standalone->importNode($element, true);
        if (! $root instanceof DOMElement) {
            throw new RuntimeException('Cannot import the embedded document.');
        }
        $standalone->appendChild($root);

        $xpath = new DOMXPath($element->ownerDocument ?? throw new RuntimeException('Detached element.'));
        foreach ($xpath->query('namespace::*', $element) ?: [] as $namespace) {
            $prefix = (string) $namespace->localName;
            if ($prefix === 'xml') {
                continue;
            }
            $attribute = $namespace->nodeName === 'xmlns' ? 'xmlns' : 'xmlns:'.$prefix;
            if (! $root->hasAttribute($attribute)) {
                $root->setAttributeNS('http://www.w3.org/2000/xmlns/', $attribute, (string) $namespace->nodeValue);
            }
        }

        return (string) $standalone->saveXML();
    }

    /**
     * Absolute path of the OASIS UBL 2.1 main document schema for an Invoice or a CreditNote.
     */
    public static function ublSchema(bool $creditNote): string
    {
        return self::UBL_SCHEMA_DIR.($creditNote ? '/UBL-CreditNote-2.1.xsd' : '/UBL-Invoice-2.1.xsd');
    }

    /**
     * Absolute path of the UN/CEFACT Cross Industry Invoice D16B main schema.
     */
    public static function ciiSchema(): string
    {
        return self::CII_SCHEMA;
    }

    private static function syntaxDir(string $syntax): string
    {
        if (! isset(self::STYLESHEETS[$syntax])) {
            throw new InvalidArgumentException("Unknown syntax \"{$syntax}\"; expected \"ubl\" or \"cii\".");
        }

        return self::OFFICIAL_DIR.'/'.$syntax;
    }
}
