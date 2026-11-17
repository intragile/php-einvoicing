<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests;

use DOMDocument;
use DOMElement;
use DOMXPath;
use FilesystemIterator;
use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles as FacturXRuleFiles;
use IntrAgile\EInvoicing\Tests\Support\UnitTestDocument;
use IntrAgile\EInvoicing\Validation\FacturX\FacturXSources;
use IntrAgile\EInvoicing\Validation\France\FranceSources;
use IntrAgile\EInvoicing\Validation\Peppol\PeppolSources;
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungSources;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Locates the official test fixtures: the CEN EN 16931 artefacts (validation-1.3.17, plus the
 * 1.3.16 rules the KoSIT configuration builds on), the XRechnung artefacts and corpus, the Peppol
 * BIS Billing rules and test corpus (downloaded only, never committed), the Factur-X 1.09.2
 * rule sources with the FeRD and Mustang example files (downloaded only, never committed) and the
 * ISO Schematron skeleton.
 */
final class Fixtures
{
    private const OFFICIAL_DIR = __DIR__.'/fixtures/official/cen-1.3.17';

    private const UBL_SCHEMA_DIR = __DIR__.'/fixtures/official/ubl-2.1/xsd/maindoc';

    /** Git-ignored folder filled by bin/fetch-official.php; OpenPeppol content is never committed. */
    private const PEPPOL_DIR = __DIR__.'/fixtures/peppol-'.PeppolSources::VERSION;

    /** Folders of the Peppol corpus that hold plain example invoices, in listing order. */
    private const PEPPOL_EXAMPLE_DIRS = ['examples', 'snippets'];

    private const CEN_1316_DIR = __DIR__.'/fixtures/official/cen-'.XRechnungSources::CEN_VERSION;

    private const XRECHNUNG_DIR = __DIR__.'/fixtures/official/xrechnung';

    private const XRECHNUNG_MANIFEST = __DIR__.'/fixtures/official/xrechnung.sha256';

    /**
     * Folders of the xrechnung tree whose XML files are instance documents, named as
     * bin/fetch-official.php names them after the pinned KoSIT releases.
     */
    private const XRECHNUNG_INSTANCE_DIRS = [
        'testsuite-'.XRechnungSources::CONFIGURATION_VERSION,
        'unit-'.XRechnungSources::SCHEMATRON_VERSION,
        'configuration-tests-'.XRechnungSources::CONFIGURATION_VERSION,
    ];

    /** Length of the "<SHA-256>  " prefix of a manifest line. */
    private const MANIFEST_PREFIX_LENGTH = 66;

    private const ISO_SKELETON = __DIR__.'/fixtures/iso-schematron/iso_svrl_for_xslt2.xsl';

    private const CII_SCHEMA = __DIR__.'/fixtures/official/cii-d16b/CrossIndustryInvoice_100pD16B.xsd';

    private const FACTURX_DIR = __DIR__.'/fixtures/official/facturx-'.FacturXSources::VERSION;

    /** Git-ignored folder filled by bin/fetch-official.php --facturx-examples, laid out by repository path. */
    private const FACTURX_EXAMPLES_DIR = __DIR__.'/fixtures/facturx-examples';

    /** Git-ignored folder filled by bin/fetch-official.php --france, laid out by repository path. */
    private const FRANCE_RFE_DIR = __DIR__.'/fixtures/france-rfe';

    private const FACTURX_EXAMPLES_MANIFEST = __DIR__.'/fixtures/facturx-examples.sha256';

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

    /** @var array<string, string>|null flag per file of the xrechnung tree, read once */
    private static ?array $xrechnungManifest = null;

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

    /**
     * The XRechnung instance corpus: name ("<folder>/<relative path>") => absolute path of every
     * XML instance of the KoSIT test suite, the XRechnung Schematron unit tests and the validator
     * configuration tests, sorted by name. Files flagged in the manifest as not well-formed or as
     * carrying a DOCTYPE are left out; see xrechnungRejectedCorpus().
     *
     * @return array<string, string>
     */
    public static function xrechnungCorpus(): array
    {
        $corpus = [];
        foreach (self::xrechnungManifest() as $name => $flag) {
            if ($flag === '' && self::isXrechnungInstance($name)) {
                $corpus[$name] = self::XRECHNUNG_DIR.'/'.$name;
            }
        }
        ksort($corpus, SORT_STRING);

        return $corpus;
    }

    /**
     * XRechnung instances that are not well-formed or carry a DOCTYPE: name => absolute path,
     * sorted by name. They are expected to be refused by the engine.
     *
     * @return array<string, string>
     */
    public static function xrechnungRejectedCorpus(): array
    {
        $rejected = [];
        foreach (self::xrechnungManifest() as $name => $flag) {
            if ($flag !== '' && self::isXrechnungInstance($name)) {
                $rejected[$name] = self::XRECHNUNG_DIR.'/'.$name;
            }
        }
        ksort($rejected, SORT_STRING);

        return $rejected;
    }

    /**
     * Absolute path of the KoSIT XRechnung Schematron for a syntax ("ubl" or "cii").
     */
    public static function xrechnungSchematron(string $syntax): string
    {
        return match ($syntax) {
            'ubl' => self::XRECHNUNG_DIR.'/schematron-'.XRechnungSources::SCHEMATRON_VERSION.'/ubl/XRechnung-UBL-validation.sch',
            'cii' => self::XRECHNUNG_DIR.'/schematron-'.XRechnungSources::SCHEMATRON_VERSION.'/cii/XRechnung-CII-validation.sch',
            default => throw new InvalidArgumentException("Unknown syntax \"{$syntax}\"; expected \"ubl\" or \"cii\"."),
        };
    }

    /**
     * Absolute path of the folder of the unpacked KoSIT validator configuration release.
     */
    public static function xrechnungConfiguration(): string
    {
        return self::XRECHNUNG_DIR.'/configuration-'.XRechnungSources::CONFIGURATION_VERSION;
    }

    /**
     * Absolute path of the preprocessed CEN 1.3.16 Schematron for a syntax ("ubl" or "cii").
     */
    public static function cen1316(string $syntax): string
    {
        if (! in_array($syntax, ['ubl', 'cii'], true)) {
            throw new InvalidArgumentException("Unknown syntax \"{$syntax}\"; expected \"ubl\" or \"cii\".");
        }

        return self::CEN_1316_DIR.'/'.$syntax.'/schematron/preprocessed/EN16931-'.strtoupper($syntax).'-validation-preprocessed.sch';
    }

    /**
     * The CEN 1.3.16 example invoices of both syntaxes: name ("<syntax>/<file>") => absolute path.
     *
     * @return array<string, string>
     */
    public static function cen1316Examples(): array
    {
        $examples = [];
        foreach (['cii', 'ubl'] as $syntax) {
            foreach (glob(self::CEN_1316_DIR.'/'.$syntax.'/examples/*.xml') ?: [] as $file) {
                $examples[$syntax.'/'.basename($file)] = $file;
            }
        }
        ksort($examples, SORT_STRING);

        return $examples;
    }

    /**
     * The Factur-X profiles that have a Schematron rule set, named like the ZF_250 files: the
     * profiles of the compiled rules package.
     *
     * @return list<string>
     */
    public static function facturxProfiles(): array
    {
        return FacturXRuleFiles::profiles();
    }

    /**
     * Absolute path of the Factur-X 1.09.2 Schematron of a profile ("MINIMUM", "BASIC-WL", "BASIC",
     * "EN16931" or "EXTENDED"); its code database FACTUR-X_<profile>_codedb.xml is in the same folder.
     */
    public static function facturxSchematron(string $profile): string
    {
        return self::FACTURX_DIR.'/schematron/FACTUR-X_'.self::facturxProfile($profile).'.sch';
    }

    /**
     * Absolute path of the official XSLT compiled from the Factur-X 1.09.2 Schematron of a profile;
     * its code database is in the same folder.
     */
    public static function facturxXslt(string $profile): string
    {
        return self::FACTURX_DIR.'/xslt/FACTUR-X_'.self::facturxProfile($profile).'.xslt';
    }

    /**
     * Absolute path of the Factur-X 1.09.2 schema folder, with one subfolder per profile (named
     * like the profile keys; the BASIC WL files themselves are named FACTUR-X_BASICWL*).
     */
    public static function facturxSchemaDir(): string
    {
        return self::FACTURX_DIR.'/schema';
    }

    /**
     * The FeRD example invoices (CII XML): name => absolute path, sorted by name. Skips the test
     * when the example files have not been downloaded.
     *
     * @return array<string, string>
     */
    public static function facturxExampleXml(): array
    {
        return self::facturxExamples(static fn (string $path): bool => self::isFerdExample($path) && str_ends_with($path, '.xml'));
    }

    /**
     * The FeRD example PDFs and the other Mustang test PDFs: name => absolute path, sorted by
     * name. Skips the test when the example files have not been downloaded.
     *
     * @return array<string, string>
     */
    public static function facturxExamplePdfs(): array
    {
        return self::facturxExamples(static fn (string $path): bool => str_ends_with(strtolower($path), '.pdf'));
    }

    /**
     * Whether the FeRD examples and Mustang test PDFs have been downloaded. Data providers check
     * it, because a skip raised inside a data provider is reported as an error.
     */
    public static function hasFacturxExamples(): bool
    {
        return is_dir(self::FACTURX_EXAMPLES_DIR);
    }

    /**
     * Absolute path of the downloaded France_RFE tree, in which every file sits at its repository
     * path. Skips the test when the folder is missing.
     */
    public static function franceRfeRoot(): string
    {
        if (! self::hasFranceRfe()) {
            Assert::markTestSkipped('Run composer fetch-official -- --france');
        }

        return self::FRANCE_RFE_DIR;
    }

    public static function hasFranceRfe(): bool
    {
        return is_dir(self::FRANCE_RFE_DIR);
    }

    /**
     * The France_RFE example XML files below FNFE_RFE_INVOICE/Z.example/TEST/<folder>, such as
     * "CII/EXTENDED-CTC-FR": file name => absolute path, sorted by name.
     *
     * @return array<string, string>
     */
    public static function franceExamples(string $folder): array
    {
        $root = self::franceRfeRoot();
        $prefix = 'FNFE_RFE_INVOICE/Z.example/TEST/'.$folder.'/';

        $examples = [];
        foreach (array_keys(FranceSources::files('example')) as $path) {
            if (str_starts_with($path, $prefix) && str_ends_with($path, '.xml') && ! str_contains(substr($path, strlen($prefix)), '/')) {
                $examples[basename($path)] = $root.'/'.$path;
            }
        }
        ksort($examples, SORT_STRING);

        return $examples;
    }

    /**
     * Absolute path of the downloaded example folder, in which every file sits at its repository
     * path. Skips the test when the folder is missing.
     */
    public static function facturxExamplesDir(): string
    {
        if (! self::hasFacturxExamples()) {
            Assert::markTestSkipped('Run composer fetch-official -- --facturx-examples');
        }

        return self::FACTURX_EXAMPLES_DIR;
    }

    /**
     * Manifest entries selected by $filter, keyed by a short name: the repository path without
     * its "src/test/resources/" part, such as "library/ZF252_Beispiele/…" or "validator/….pdf".
     *
     * @param  callable(string): bool  $filter
     * @return array<string, string>
     */
    private static function facturxExamples(callable $filter): array
    {
        $directory = self::facturxExamplesDir();

        $examples = [];
        foreach (array_keys(FacturXSources::examples(self::FACTURX_EXAMPLES_MANIFEST)) as $path) {
            if ($filter($path)) {
                $examples[str_replace('/src/test/resources/', '/', $path)] = $directory.'/'.$path;
            }
        }
        ksort($examples, SORT_STRING);

        return $examples;
    }

    private static function isFerdExample(string $path): bool
    {
        foreach (FacturXSources::FERD_EXAMPLES as $folder) {
            if (str_starts_with($path, $folder)) {
                return true;
            }
        }

        return false;
    }

    private static function facturxProfile(string $profile): string
    {
        $profiles = self::facturxProfiles();
        if (! in_array($profile, $profiles, true)) {
            throw new InvalidArgumentException("Unknown Factur-X profile \"{$profile}\"; expected one of ".implode(', ', $profiles).'.');
        }

        return $profile;
    }

    /**
     * Flag per file of the xrechnung tree: '' for a plain file, 'doctype' or 'unparseable'.
     *
     * @return array<string, string>
     */
    private static function xrechnungManifest(): array
    {
        if (self::$xrechnungManifest !== null) {
            return self::$xrechnungManifest;
        }
        $lines = file(self::XRECHNUNG_MANIFEST, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            ?: throw new RuntimeException('Cannot read '.self::XRECHNUNG_MANIFEST);

        $files = [];
        foreach ($lines as $line) {
            [$entry, $flag] = array_pad(explode("\t", $line, 2), 2, '');
            if (strlen($entry) <= self::MANIFEST_PREFIX_LENGTH || preg_match('/^[0-9a-f]{64}  $/', substr($entry, 0, self::MANIFEST_PREFIX_LENGTH)) !== 1) {
                throw new RuntimeException('Malformed line in '.self::XRECHNUNG_MANIFEST.": {$line}");
            }
            $files[substr($entry, self::MANIFEST_PREFIX_LENGTH)] = $flag;
        }

        return self::$xrechnungManifest = $files;
    }

    private static function isXrechnungInstance(string $name): bool
    {
        return str_ends_with($name, '.xml') && in_array(explode('/', $name, 2)[0], self::XRECHNUNG_INSTANCE_DIRS, true);
    }

    private static function peppolDir(): string
    {
        if (! is_dir(self::PEPPOL_DIR)) {
            Assert::markTestSkipped('Run composer fetch-official');
        }

        return self::PEPPOL_DIR;
    }

    /**
     * Paths, relative to $dir, of every file below it, sorted in byte order. Hidden files (names
     * starting with a dot, such as the .DS_Store files macOS Finder leaves behind) are skipped, so
     * a developer machine counts the same files as a fresh checkout.
     *
     * @return list<string>
     */
    public static function filesBelow(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && ! str_starts_with($file->getFilename(), '.')) {
                $files[] = substr($file->getPathname(), strlen($dir) + 1);
            }
        }
        sort($files, SORT_STRING);

        return $files;
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
