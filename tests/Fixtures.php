<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests;

use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Tests\Support\UnitTestDocument;
use IntrAgile\EInvoicing\Validation\Peppol\PeppolSources;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Locates the official test fixtures: the CEN EN 16931 artefacts (validation-1.3.17), the Peppol
 * BIS Billing rules and test corpus (downloaded only, never committed) and the ISO Schematron
 * skeleton.
 */
final class Fixtures
{
    private const OFFICIAL_DIR = __DIR__.'/fixtures/official/cen-1.3.17';

    private const UBL_SCHEMA_DIR = __DIR__.'/fixtures/official/ubl-2.1/xsd/maindoc';

    /** Git-ignored folder filled by bin/fetch-official.php; OpenPeppol content is never committed. */
    private const PEPPOL_DIR = __DIR__.'/fixtures/peppol-'.PeppolSources::VERSION;

    /** Folders of the Peppol corpus that hold plain example invoices, in listing order. */
    private const PEPPOL_EXAMPLE_DIRS = ['examples', 'snippets'];

    private const ISO_SKELETON = __DIR__.'/fixtures/iso-schematron/iso_svrl_for_xslt2.xsl';

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

    /** Namespace of the Peppol Business Message Envelope (Standard Business Document Header). */
    private const SBDH_NAMESPACE = 'http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader';

    /** Namespace of any UBL 2 main document, such as Invoice-2 or Order-2. */
    private const UBL_DOCUMENT_NAMESPACE = '/^urn:oasis:names:specification:ubl:schema:xsd:[A-Za-z]+-2$/';

    /** Syntax of a non-UBL invoice by the namespace of its document element. */
    private const SYNTAX_BY_NAMESPACE = [
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
     * Absolute path of a downloaded Peppol Schematron file, keyed as in PeppolSources::all().
     */
    public static function peppolSchematron(string $key): string
    {
        $source = PeppolSources::all()[$key] ?? throw new InvalidArgumentException("Unknown Peppol rule file \"{$key}\".");

        return self::peppolDir().'/sch/'.$source['file'];
    }

    /**
     * Every invoice in the Peppol unit-test corpus (rules/unit-UBL-* and rules/unit-CII-*): one
     * entry per test of a testSet file, ordered by directory, file name and position. Every
     * regular file is read, whatever its extension, because some upstream files are misnamed.
     *
     * @return list<UnitTestDocument>
     */
    public static function peppolUnitTests(): array
    {
        $peppolDir = self::peppolDir();
        $dirs = array_merge(glob($peppolDir.'/unit-UBL-*', GLOB_ONLYDIR) ?: [], glob($peppolDir.'/unit-CII-*', GLOB_ONLYDIR) ?: []);
        sort($dirs, SORT_STRING);

        $documents = [];
        foreach ($dirs as $dir) {
            foreach (self::regularFiles($dir) as $file) {
                array_push($documents, ...self::unitTestDocuments($dir.'/'.$file, 'peppol/'));
            }
        }

        return $documents;
    }

    /**
     * Every Standard Business Document in the Peppol envelope unit tests (rules/unit-SBDH): one
     * entry per test of a testSet file, ordered by file name and position. The syntax of an entry
     * is that of the enveloped invoice.
     *
     * @return list<UnitTestDocument>
     */
    public static function peppolEnvelopeTests(): array
    {
        $dir = self::peppolDir().'/unit-SBDH';
        $documents = [];
        foreach (self::regularFiles($dir) as $file) {
            array_push($documents, ...self::unitTestDocuments($dir.'/'.$file, 'peppol/'));
        }

        return $documents;
    }

    /**
     * The Peppol example invoices (rules/examples and rules/snippets), keyed by a name such as
     * "examples/base-example.xml", in directory and file-name order.
     *
     * @return array<string, string> name => XML
     */
    public static function peppolExamples(): array
    {
        $examples = [];
        foreach (self::PEPPOL_EXAMPLE_DIRS as $dir) {
            $path = self::peppolDir().'/'.$dir;
            foreach (self::regularFiles($path) as $file) {
                $examples[$dir.'/'.$file] = (string) file_get_contents($path.'/'.$file);
            }
        }

        return $examples;
    }

    /**
     * Absolute path of the ISO Schematron SVRL stylesheet for XSLT 2 (it imports the Saxon
     * skeleton from the same folder).
     */
    public static function isoSkeleton(): string
    {
        return self::ISO_SKELETON;
    }

    private static function peppolDir(): string
    {
        if (! is_dir(self::PEPPOL_DIR)) {
            Assert::markTestSkipped('Run composer fetch-official');
        }

        return self::PEPPOL_DIR;
    }

    /**
     * Names of the regular files in a directory, sorted.
     *
     * @return list<string>
     */
    private static function regularFiles(string $dir): array
    {
        $files = array_values(array_filter(
            scandir($dir) ?: throw new RuntimeException("Cannot list {$dir}"),
            static fn (string $file): bool => is_file($dir.'/'.$file),
        ));
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return list<UnitTestDocument>
     */
    private static function unitTestDocuments(string $file, string $corpus = ''): array
    {
        $document = new DOMDocument;
        if (! $document->load($file, LIBXML_NONET) || $document->documentElement === null) {
            throw new RuntimeException("{$file} is not well-formed XML.");
        }
        $root = $document->documentElement;

        if ($root->namespaceURI !== self::VEFA_NAMESPACE) {
            return [new UnitTestDocument($file, 0, self::invoiceSyntax($root, $file), (string) $document->saveXML(), $corpus)];
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

            $documents[] = new UnitTestDocument($file, $index, self::invoiceSyntax($invoices[0], $file), self::standalone($invoices[0]), $corpus);
            $index++;
        }

        return $documents;
    }

    /**
     * Syntax of a corpus document. Besides invoices and credit notes, the Peppol corpus tests its
     * syntax-independent rules on other UBL documents (an Order in PEPPOL-COMMON-R041.xml), and
     * its envelope rules on Standard Business Documents, whose syntax is that of the enveloped
     * document (the last child element).
     *
     * @return 'ubl'|'cii'
     */
    private static function invoiceSyntax(DOMElement $element, string $file): string
    {
        $namespace = (string) $element->namespaceURI;
        if ($namespace === self::SBDH_NAMESPACE && $element->localName === 'StandardBusinessDocument') {
            $payload = $element->lastElementChild
                ?? throw new RuntimeException("A StandardBusinessDocument in {$file} envelopes no document.");

            return self::invoiceSyntax($payload, $file);
        }
        if (preg_match(self::UBL_DOCUMENT_NAMESPACE, $namespace) === 1) {
            return 'ubl';
        }

        return self::SYNTAX_BY_NAMESPACE[$namespace]
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
