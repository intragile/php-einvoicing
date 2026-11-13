<?php

declare(strict_types=1);

/*
 * Downloads the CEN EN 16931 validation artefacts (release validation-1.3.17) and unpacks the
 * examples, the compiled XSLT, the preprocessed Schematron and the code-list Schematron into
 * tests/fixtures/official/cen-1.3.17/{ubl,cii}/.
 *
 * Every download is verified against a pinned SHA-256 sum. Verified archives are cached in
 * .cache/official/, so a second run needs no network and produces the same tree.
 *
 * The examples inside the release zips are byte-identical to ubl/examples and cii/examples
 * in the repository at the same tag.
 *
 * It also downloads the repository archive at the same tag and unpacks its test/ tree (the CEN
 * unit tests) into tests/fixtures/official/cen-1.3.17/unit-tests/. GitHub does not guarantee
 * byte-stable source archives, so every unpacked file is verified against the per-file SHA-256
 * sums in unit-tests/MANIFEST.sha256 instead of pinning the archive. Pass --write-manifest to
 * (re)create that manifest from the archive instead of verifying against it.
 *
 * It also downloads the OASIS UBL 2.1 OS package and unpacks the Invoice and CreditNote schemas
 * with their common modules into tests/fixtures/official/ubl-2.1/xsd/, for schema validation of
 * written documents.
 *
 * It also downloads the UN/CEFACT Cross Industry Invoice D16B schemas (SCRDM subset, uncoupled
 * code lists) that the CEN repository ships at the same tag, into
 * tests/fixtures/official/cii-d16b/, for schema validation of written CII documents.
 *
 * It also downloads the Peppol BIS Billing 3.0 rule files pinned in PeppolSources, and the unit
 * tests, examples and snippets of the peppol-bis-invoice-3 repository archive at the same tag,
 * into tests/fixtures/peppol-<version>/. OpenPeppol publishes no licence for this material, so
 * that folder is git-ignored and never committed; the per-file SHA-256 sums of the unpacked
 * archive entries are kept in tests/fixtures/peppol-<version>.sha256 (relative paths and sums
 * only), which --write-manifest also recreates.
 *
 * It also downloads the XRechnung artefacts pinned in XRechnungSources (KoSIT Schematron, validator
 * configuration, test suite and the two KoSIT source archives, all Apache-2.0) and unpacks them
 * unmodified into tests/fixtures/official/xrechnung/, with an upstream LICENSE beside each part.
 * Every file of that tree is listed with its SHA-256 sum in tests/fixtures/official/xrechnung.sha256
 * (--write-manifest recreates it); XML instances that carry a DOCTYPE or are not well-formed are
 * flagged there. It also unpacks the examples and preprocessed Schematron of the CEN
 * validation-1.3.16 release, the rule version the KoSIT configuration is built on, into
 * tests/fixtures/official/cen-1.3.16/{ubl,cii}/. The KoSIT validator jar is never downloaded.
 *
 * Factur-X is fetched only on request, and then nothing else is:
 *  --facturx           rebuilds tests/fixtures/official/facturx-<version>/ from the Mustang commit
 *                      pinned in FacturXSources: the ZF_250 schema, Schematron and XSLT folders
 *                      (Apache-2.0) and the repository's LICENSE and NOTICE, each file verified
 *                      against tests/fixtures/official/facturx.sha256.
 *  --facturx-examples  downloads the FeRD ZF252_Beispiele files and the other Mustang test PDFs
 *                      into the git-ignored tests/fixtures/facturx-examples/ (by repository path),
 *                      each verified against tests/fixtures/facturx-examples.sha256. These files
 *                      carry no licence statement and are never committed.
 * With --write-manifest, the file list is taken from the GitHub tree of the pinned commit and the
 * manifest is rewritten; for --facturx the folders must still match the aggregate SHA-256 values
 * and the licence sums pinned in FacturXSources.
 */

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Validation\FacturX\FacturXSources;
use IntrAgile\EInvoicing\Validation\Peppol\PeppolSources;
use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungSources;

require_once dirname(__DIR__).'/src/Validation/FacturX/FacturXSources.php';
require_once dirname(__DIR__).'/src/Validation/Peppol/PeppolSources.php';
require_once dirname(__DIR__).'/src/Validation/XRechnung/XRechnungSources.php';
require_once dirname(__DIR__).'/src/Exception/UnsafeXml.php';
require_once dirname(__DIR__).'/src/Exception/UnsupportedDocument.php';
require_once dirname(__DIR__).'/src/Syntax/SafeXml.php';

const TAG = 'validation-1.3.17';
const RELEASE_URL = 'https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/download/'.TAG.'/';
const RAW_URL = 'https://raw.githubusercontent.com/ConnectingEurope/eInvoicing-EN16931/'.TAG.'/';
const USER_AGENT = 'IntrAgile Kft. info@intragile.eu';

/** The repository source archive at TAG, its cached file name and the top-level folder inside. */
const REPOSITORY_ARCHIVE = [
    'https://github.com/ConnectingEurope/eInvoicing-EN16931/archive/refs/tags/'.TAG.'.zip',
    'eInvoicing-EN16931-'.TAG.'.zip',
    'eInvoicing-EN16931-'.TAG.'/',
];

/** Per-file SHA-256 sums of the unpacked unit tests, relative to the unit-tests directory. */
const UNIT_TEST_MANIFEST = 'MANIFEST.sha256';

/** Release assets per syntax: file name and its SHA-256 sum. */
const ARCHIVES = [
    'ubl' => ['en16931-ubl-1.3.17.zip', 'bf02a0e3f6cbe692a4a28e70be7bdcaded5612b4169b543e13d6cb4f33c29e92'],
    'cii' => ['en16931-cii-1.3.17.zip', 'e5f3235824b6b38454b1d54cf3f14e01efb5b33971fbefc4e64997d0ef619e89'],
];

/** The EUPL 1.2 text that must travel with every redistributed copy. */
const LICENCE = ['LICENSE.txt', 'fc22ec1dcd8bee4636a395fb332e2308cde870fb3fdc71a2e260b919877cdef5'];

/**
 * Archive directories that are unpacked. The code-list Schematron feeds bin/generate-codelists.php;
 * the preprocessed Schematron (all includes and abstract patterns resolved) is the input of the
 * Schematron compiler. The other Schematron sources are already contained in the preprocessed file.
 */
const EXTRACT_PREFIXES = ['examples/', 'xslt/', 'schematron/preprocessed/', 'schematron/codelist/'];

/** The OASIS UBL 2.1 OS package: URL, file name and SHA-256 sum. */
const UBL_SCHEMA = [
    'https://docs.oasis-open.org/ubl/os-UBL-2.1/UBL-2.1.zip',
    'UBL-2.1.zip',
    '60b80d76394a8a2add90723ecb8e0e2e9d826775de9749df37a72d60703f86ed',
];

/** The specification page whose "Notices" section must accompany every copy of the schemas. */
const UBL_SPECIFICATION_PAGE = 'UBL-2.1.html';

/** Schema files unpacked from the UBL package: the two main documents and what they import. */
const UBL_SCHEMA_PREFIXES = ['xsd/common/', 'xsd/maindoc/UBL-Invoice-2.1.xsd', 'xsd/maindoc/UBL-CreditNote-2.1.xsd'];

/** The UN/CEFACT CII D16B schemas in the CEN repository at TAG. */
const CII_SCHEMA_URL = RAW_URL.'cii/schema/D16B%20SCRDM%20(Subset)/uncoupled%20clm/CII/uncefact/data/standard/';

/** CII schema files with their SHA-256 sums; the first is the main document schema. */
const CII_SCHEMAS = [
    'CrossIndustryInvoice_100pD16B.xsd' => 'b9798aafcba039d0630f0015ffae5092a1758bf924d19d97b6bf0bf9d95f22a0',
    'CrossIndustryInvoice_QualifiedDataType_100pD16B.xsd' => 'a39e0662c31fbdca22237118d6b6bbfa16f69efbd1ee5b23787366349c5b924d',
    'CrossIndustryInvoice_ReusableAggregateBusinessInformationEntity_100pD16B.xsd' => 'cc682d67791ffe16c45320619ff089a1c8f3ad22691fc426e4056f2e67909fe0',
    'CrossIndustryInvoice_UnqualifiedDataType_100pD16B.xsd' => '5d4ce8a6445caa4b7691f6fba6ad08e312e6a853cd3a422666d121ee2d13ef8c',
];

/** The peppol-bis-invoice-3 source archive at PeppolSources::VERSION, its cache name and top-level folder. */
const PEPPOL_ARCHIVE = [
    'https://github.com/OpenPEPPOL/peppol-bis-invoice-3/archive/refs/tags/'.PeppolSources::VERSION.'.zip',
    'peppol-bis-invoice-3-'.PeppolSources::VERSION.'.zip',
    'peppol-bis-invoice-3-'.PeppolSources::VERSION.'/rules/',
];

/**
 * Archive folders below rules/ that are unpacked: the invoice unit tests, the envelope unit tests
 * (unit-SBDH) and the examples. The national examples are not needed.
 */
const PEPPOL_PREFIXES = ['unit-UBL-', 'unit-CII-', 'unit-SBDH/', 'examples/', 'snippets/'];

/** Raw URL of the CEN 1.3.16 licence text; the file is the same as for 1.3.17. */
const CEN_1316_LICENCE_URL = 'https://raw.githubusercontent.com/ConnectingEurope/eInvoicing-EN16931/validation-'.XRechnungSources::CEN_VERSION.'/LICENSE.txt';

/** The Apache-2.0 text of the KoSIT test suite repository, which its release zip does not contain. */
const TESTSUITE_LICENCE = [
    'https://raw.githubusercontent.com/itplr-kosit/xrechnung-testsuite/v'.XRechnungSources::CONFIGURATION_VERSION.'/LICENSE',
    'xrechnung-testsuite-LICENSE',
    'c71d239df91726fc519c6eb72d318ec65820627232b2f796219e87dcf35d0ab4',
];

/** Cache file names of the KoSIT source archives, whose URLs end in a bare tag name. */
const XRECHNUNG_SOURCE_CACHE = [
    'schematron-source' => 'xrechnung-schematron-v'.XRechnungSources::SCHEMATRON_VERSION.'.tar.gz',
    'configuration-source' => 'validator-configuration-xrechnung-v'.XRechnungSources::CONFIGURATION_VERSION.'.tar.gz',
];

/** Name of the manifest of the xrechnung/ tree, next to that folder. */
const XRECHNUNG_MANIFEST = 'xrechnung.sha256';

/** Seconds a single Factur-X file download may stall before it fails. */
const FACTURX_FILE_TIMEOUT = 60;

/** Repository folders whose PDF files belong to the example corpus as well. */
const MUSTANG_TEST_RESOURCES = ['library/src/test/resources/', 'validator/src/test/resources/'];

/** GitHub API listing of every file at the pinned Mustang commit, used to (re)write the manifests. */
const MUSTANG_TREE_URL = 'https://api.github.com/repos/ZUGFeRD/mustangproject/git/trees/'.FacturXSources::MUSTANG_COMMIT.'?recursive=1';

/** Folders of the xrechnung/ tree whose XML files form the instance corpus, checked for well-formedness. */
const XRECHNUNG_INSTANCE_DIRS = ['testsuite-'.XRechnungSources::CONFIGURATION_VERSION, 'unit-'.XRechnungSources::SCHEMATRON_VERSION, 'configuration-tests-'.XRechnungSources::CONFIGURATION_VERSION];

$root = dirname(__DIR__);
$cacheDir = $root.'/.cache/official';
$targetDir = $root.'/tests/fixtures/official/cen-1.3.17';
$schemaDir = $root.'/tests/fixtures/official/ubl-2.1';
$ciiSchemaDir = $root.'/tests/fixtures/official/cii-d16b';
$cenDir = $root.'/tests/fixtures/official/cen-'.XRechnungSources::CEN_VERSION;
$xrechnungDir = $root.'/tests/fixtures/official/xrechnung';
$peppolDir = $root.'/tests/fixtures/peppol-'.PeppolSources::VERSION;
$facturxDir = $root.'/tests/fixtures/official/facturx-'.FacturXSources::VERSION;
$facturxExamplesDir = $root.'/tests/fixtures/facturx-examples';
$options = getopt('', ['write-manifest', 'facturx', 'facturx-examples']) ?: [];
$writeManifest = array_key_exists('write-manifest', $options);

try {
    if (array_key_exists('facturx', $options) || array_key_exists('facturx-examples', $options)) {
        if (array_key_exists('facturx', $options)) {
            $count = fetchFacturx($facturxDir, dirname($facturxDir).'/facturx.sha256', $writeManifest);
            fwrite(STDOUT, 'facturx-'.FacturXSources::VERSION.": {$count} files".($writeManifest ? ', manifest written' : ', manifest verified')."\n");
        }
        if (array_key_exists('facturx-examples', $options)) {
            $count = fetchFacturxExamples($facturxExamplesDir, $facturxExamplesDir.'.sha256', $writeManifest);
            fwrite(STDOUT, "facturx-examples: {$count} files".($writeManifest ? ', manifest written' : ', manifest verified')."\n");
        }
        exit(0);
    }

    if (! class_exists(ZipArchive::class)) {
        throw new RuntimeException('The zip extension is required to unpack the official artefacts.');
    }

    // Staging folders and partial downloads left behind by an interrupted run.
    foreach ([$cacheDir, dirname($targetDir), $targetDir, dirname($peppolDir)] as $directory) {
        foreach (glob($directory.'/*.tmp') ?: [] as $debris) {
            removeTree($debris);
        }
    }

    ensureDirectory($cacheDir);
    ensureDirectory($targetDir);

    foreach (ARCHIVES as $syntax => [$file, $sha256]) {
        $archive = fetchVerified(RELEASE_URL.$file, $cacheDir.'/'.$file, $sha256);
        unpackArchive($archive, $targetDir.'/'.$syntax, EXTRACT_PREFIXES);
        fwrite(STDOUT, "{$syntax}: unpacked {$file}\n");
    }

    [$repositoryUrl, $repositoryFile, $repositoryRoot] = REPOSITORY_ARCHIVE;
    $repositoryArchive = $cacheDir.'/'.$repositoryFile;
    if (! is_file($repositoryArchive)) {
        download($repositoryUrl, $repositoryArchive);
    }
    $unitTestCount = unpackUnitTests($repositoryArchive, $repositoryRoot.'test/', $targetDir.'/unit-tests', $writeManifest);
    fwrite(STDOUT, "unit-tests: unpacked {$unitTestCount} files from {$repositoryFile}".($writeManifest ? ', manifest written' : ', manifest verified')."\n");

    [$licenceFile, $licenceSha256] = LICENCE;
    $licence = fetchVerified(RAW_URL.$licenceFile, $cacheDir.'/'.$licenceFile, $licenceSha256);
    copyFile($licence, $targetDir.'/'.$licenceFile);
    fwrite(STDOUT, "licence: copied {$licenceFile}\n");

    [$schemaUrl, $schemaFile, $schemaSha256] = UBL_SCHEMA;
    $schemaArchive = fetchVerified($schemaUrl, $cacheDir.'/'.$schemaFile, $schemaSha256);
    unpackArchive($schemaArchive, $schemaDir, UBL_SCHEMA_PREFIXES);
    writeOasisNotice($schemaArchive, $schemaDir.'/NOTICE.txt');
    fwrite(STDOUT, "ubl-2.1: unpacked {$schemaFile} with NOTICE.txt\n");

    ensureDirectory($ciiSchemaDir);
    foreach (CII_SCHEMAS as $ciiSchemaFile => $ciiSchemaSha256) {
        copyFile(fetchVerified(CII_SCHEMA_URL.$ciiSchemaFile, $cacheDir.'/'.$ciiSchemaFile, $ciiSchemaSha256), $ciiSchemaDir.'/'.$ciiSchemaFile);
    }
    fwrite(STDOUT, 'cii-d16b: copied '.count(CII_SCHEMAS)." schema files\n");

    unpackCen1316($cacheDir, $cenDir);
    fwrite(STDOUT, 'cen-'.XRechnungSources::CEN_VERSION.": unpacked both release archives with LICENSE.txt\n");

    $rejected = unpackXrechnung($cacheDir, $xrechnungDir, dirname($xrechnungDir).'/'.XRECHNUNG_MANIFEST, $writeManifest);
    fwrite(STDOUT, 'xrechnung: unpacked the KoSIT artefacts'.($writeManifest ? ', manifest written' : ', manifest verified').'; '.count($rejected)." instance(s) flagged as unparseable or carrying a DOCTYPE\n");
    foreach ($rejected as $name => $flag) {
        fwrite(STDOUT, "  {$flag}: {$name}\n");
    }

    $peppolCount = unpackPeppol($cacheDir, $peppolDir, $peppolDir.'.sha256', $writeManifest);
    fwrite(STDOUT, 'peppol-'.PeppolSources::VERSION.': copied '.count(PeppolSources::all())." rule files, unpacked {$peppolCount} files"
        .($writeManifest ? ', manifest written' : ', manifest verified')."\n");
} catch (RuntimeException $e) {
    fwrite(STDERR, 'fetch-official: '.$e->getMessage()."\n");
    exit(1);
}

/**
 * Replaces $target with the examples and the preprocessed Schematron of both CEN 1.3.16 release
 * archives (per syntax) and the licence text.
 */
function unpackCen1316(string $cacheDir, string $target): void
{
    $staging = $target.'.tmp';
    removeTree($staging);
    ensureDirectory($staging);

    $archives = XRechnungSources::archives();
    foreach (['ubl' => 'cen-ubl', 'cii' => 'cen-cii'] as $syntax => $key) {
        $archive = fetchVerified($archives[$key]['url'], $cacheDir.'/'.basename($archives[$key]['url']), $archives[$key]['sha256']);
        extractEntries($archive, $staging.'/'.$syntax, ['examples/', 'schematron/preprocessed/']);
    }

    copyFile(fetchVerified(CEN_1316_LICENCE_URL, $cacheDir.'/LICENSE-cen-'.XRechnungSources::CEN_VERSION.'.txt', LICENCE[1]), $staging.'/LICENSE.txt');
    replaceTree($staging, $target);
}

/**
 * Replaces $target with the KoSIT artefacts, unmodified, and checks the tree against the manifest
 * at $manifestPath or writes that manifest. Returns the flagged instances (name => flag).
 *
 * @return array<string, string>
 */
function unpackXrechnung(string $cacheDir, string $target, string $manifestPath, bool $writeManifest): array
{
    $expected = $writeManifest ? null : readXrechnungManifest($manifestPath);

    $archives = XRechnungSources::archives();
    $path = static function (string $key) use ($archives, $cacheDir): string {
        $cacheName = XRECHNUNG_SOURCE_CACHE[$key] ?? basename($archives[$key]['url']);

        return fetchVerified($archives[$key]['url'], $cacheDir.'/'.$cacheName, $archives[$key]['sha256']);
    };

    $schematronVersion = XRechnungSources::SCHEMATRON_VERSION;
    $configurationVersion = XRechnungSources::CONFIGURATION_VERSION;

    $staging = $target.'.tmp';
    removeTree($staging);
    ensureDirectory($staging);

    // The Schematron release: schematron/ is the folder, the licence sits beside it.
    extractEntries($path('schematron'), $staging.'/schematron-'.$schematronVersion, ['schematron/', 'LICENSE'], 'schematron/');

    // The validator configuration release; the zip has no licence, the source archive does.
    $configurationSource = $path('configuration-source');
    $configurationRoot = 'validator-configuration-xrechnung-'.$configurationVersion.'/';
    extractEntries($path('configuration'), $staging.'/configuration-'.$configurationVersion, ['']);
    extractTarEntries($configurationSource, $staging.'/configuration-'.$configurationVersion, [$configurationRoot.'LICENSE', $configurationRoot.'NOTICE'], [$configurationRoot]);

    // The KoSIT test suite; its release zip has no licence either.
    extractEntries($path('testsuite'), $staging.'/testsuite-'.$configurationVersion, ['instances/']);
    copyFile(fetchVerified(TESTSUITE_LICENCE[0], $cacheDir.'/'.TESTSUITE_LICENCE[1], TESTSUITE_LICENCE[2]), $staging.'/testsuite-'.$configurationVersion.'/LICENSE');

    // The Schematron unit-test instances.
    $schematronRoot = 'xrechnung-schematron-'.$schematronVersion.'/';
    extractTarEntries($path('schematron-source'), $staging.'/unit-'.$schematronVersion, [$schematronRoot.'test/instances/', $schematronRoot.'LICENSE'], [$schematronRoot.'test/instances/', $schematronRoot]);

    // The configuration's own test files: every XML file below src/test/.
    extractTarEntries($configurationSource, $staging.'/configuration-tests-'.$configurationVersion, [$configurationRoot.'src/test/', $configurationRoot.'LICENSE'], [$configurationRoot.'src/test/', $configurationRoot], '.xml');

    $actual = hashTree($staging);
    $flags = [];
    foreach ($actual as $name => $sum) {
        $folder = explode('/', $name, 2)[0];
        if (in_array($folder, XRECHNUNG_INSTANCE_DIRS, true) && str_ends_with($name, '.xml')) {
            $flag = instanceFlag($staging.'/'.$name);
            if ($flag !== '') {
                $flags[$name] = $flag;
            }
        }
    }

    if ($expected === null) {
        writeXrechnungManifest($actual, $flags, $manifestPath);
    } else {
        checkManifest($expected['sums'], $actual, $staging, "XRechnung artefacts do not match {$manifestPath}");
        if ($expected['flags'] !== $flags) {
            removeTree($staging);
            throw new RuntimeException("Instance flags differ from {$manifestPath}; rerun with --write-manifest if the sources were intentionally changed.");
        }
    }

    replaceTree($staging, $target);

    return $flags;
}

/**
 * 'doctype' for an XML file with a DOCTYPE declaration, 'unparseable' for one that is not
 * well-formed (or empty), otherwise an empty string.
 */
function instanceFlag(string $path): string
{
    try {
        SafeXml::loadPreservingWhitespace((string) file_get_contents($path));
    } catch (UnsafeXml) {
        return 'doctype';
    } catch (UnsupportedDocument) {
        return 'unparseable';
    }

    return '';
}

/**
 * Writes "<sum>  <relative path>" per file, with a tab and the flag after the path for flagged files.
 *
 * @param  array<string, string>  $sums
 * @param  array<string, string>  $flags
 */
function writeXrechnungManifest(array $sums, array $flags, string $path): void
{
    $lines = [];
    foreach ($sums as $name => $sum) {
        $lines[] = "{$sum}  {$name}".(isset($flags[$name]) ? "\t{$flags[$name]}" : '')."\n";
    }
    if (file_put_contents($path, implode('', $lines)) === false) {
        throw new RuntimeException("Cannot write {$path}");
    }
}

/**
 * @return array{sums: array<string, string>, flags: array<string, string>}
 */
function readXrechnungManifest(string $path): array
{
    $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
    if ($lines === false || $lines === []) {
        throw new RuntimeException("Cannot read {$path}; run with --write-manifest to create it.");
    }

    $sums = [];
    $flags = [];
    foreach ($lines as $line) {
        if (preg_match('/^([0-9a-f]{64})  ([^\t]+)(?:\t(doctype|unparseable))?$/', $line, $match) !== 1) {
            throw new RuntimeException("Malformed line in {$path}: {$line}");
        }
        $sums[$match[2]] = $match[1];
        if (isset($match[3])) {
            $flags[$match[2]] = $match[3];
        }
    }

    return ['sums' => $sums, 'flags' => $flags];
}

/**
 * Writes the files of a tar archive (plain or gzip-compressed) whose names start with one of the
 * prefixes below $destinationDir, each named as withoutPrefix() returns it for $strip. With
 * $suffix, only files with that ending are written, and LICENSE files.
 *
 * @param  list<string>  $prefixes
 * @param  list<string>  $strip  prefixes to remove from entry names, most specific first
 */
function extractTarEntries(string $archive, string $destinationDir, array $prefixes, array $strip, string $suffix = ''): void
{
    try {
        $tar = new PharData($archive);
    } catch (Exception $e) {
        throw new RuntimeException("Cannot open {$archive}: ".$e->getMessage());
    }

    $base = 'phar://'.$archive.'/';
    /** @var SplFileInfo $entry */
    foreach (new RecursiveIteratorIterator($tar) as $entry) {
        $name = substr($entry->getPathname(), strlen($base));
        if (! isSelected($name, $prefixes)) {
            continue;
        }
        // A suffix filter keeps LICENSE files too, so that a folder of filtered files still
        // carries the licence of the archive it was taken from.
        if ($suffix !== '' && ! str_ends_with($name, $suffix) && ! str_ends_with($name, '/LICENSE')) {
            continue;
        }
        if (str_starts_with($name, '/') || str_contains($name, '\\') || in_array('..', explode('/', $name), true)) {
            throw new RuntimeException("Unsafe entry name in {$archive}: {$name}");
        }

        $contents = file_get_contents($entry->getPathname());
        if ($contents === false) {
            throw new RuntimeException("Cannot read {$name} from {$archive}");
        }

        $destination = $destinationDir.'/'.withoutPrefix($name, $strip);
        ensureDirectory(dirname($destination));
        if (file_put_contents($destination, $contents) === false) {
            throw new RuntimeException("Cannot write {$destination}");
        }
    }
}

/**
 * $name without the first of $prefixes it starts with, or unchanged when it starts with none.
 * Listing a folder before its parent ("root/test/instances/", "root/") flattens the files of the
 * folder while files next to it, such as root/LICENSE, keep only their name below the root.
 *
 * @param  list<string>  $prefixes
 */
function withoutPrefix(string $name, array $prefixes): string
{
    foreach ($prefixes as $prefix) {
        if ($prefix !== '' && str_starts_with($name, $prefix)) {
            return substr($name, strlen($prefix));
        }
    }

    return $name;
}

/**
 * Returns the cached file when its checksum matches, otherwise downloads it and verifies it.
 */
function fetchVerified(string $url, string $path, string $sha256): string
{
    if (is_file($path) && hash_file('sha256', $path) === $sha256) {
        return $path;
    }

    $body = httpGet($url);

    $actual = hash('sha256', $body);
    if ($actual !== $sha256) {
        throw new RuntimeException("Checksum mismatch for {$url}: expected {$sha256}, got {$actual}");
    }

    if (file_put_contents($path, $body) === false) {
        throw new RuntimeException("Cannot write {$path}");
    }

    return $path;
}

/**
 * Downloads a file whose contents are verified after unpacking rather than by an archive checksum.
 */
function download(string $url, string $path): void
{
    $body = httpGet($url);

    // Written under a temporary name first, so an interrupted write never leaves a truncated cache.
    $partial = $path.'.tmp';
    if (file_put_contents($partial, $body) === false || ! rename($partial, $path)) {
        throw new RuntimeException("Cannot write {$path}");
    }
}

function httpGet(string $url, int $timeout = 300): string
{
    $context = stream_context_create([
        'http' => [
            'user_agent' => USER_AGENT,
            'timeout' => $timeout,
            'follow_location' => 1,
            'max_redirects' => 5,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        throw new RuntimeException("Download failed: {$url}");
    }

    return $body;
}

/**
 * Replaces $target with the archive entries below $prefix (with $prefix removed) and checks them
 * against the manifest of the current tree, or writes a new manifest. Returns the file count.
 */
function unpackUnitTests(string $archive, string $prefix, string $target, bool $writeManifest): int
{
    $manifestPath = $target.'/'.UNIT_TEST_MANIFEST;
    $expected = $writeManifest ? null : readManifest($manifestPath);

    $staging = $target.'.tmp';
    removeTree($staging);
    ensureDirectory($staging);
    extractEntries($archive, $staging, [$prefix], $prefix);
    $actual = hashTree($staging);

    if ($expected === null) {
        writeManifest($actual, $staging.'/'.UNIT_TEST_MANIFEST);
    } else {
        checkManifest($expected, $actual, $staging, "Unit tests in {$archive} do not match ".UNIT_TEST_MANIFEST);
        copyFile($manifestPath, $staging.'/'.UNIT_TEST_MANIFEST);
    }

    replaceTree($staging, $target);

    return count($actual);
}

/**
 * Replaces $target with the Peppol rule files (in sch/, each verified against its pinned sum) and
 * the selected entries of the repository archive (verified against the manifest at
 * $manifestPath, or recorded there with --write-manifest). Returns the archive file count.
 */
function unpackPeppol(string $cacheDir, string $target, string $manifestPath, bool $writeManifest): int
{
    $expected = $writeManifest ? null : readManifest($manifestPath);

    [$archiveUrl, $archiveFile, $archiveRoot] = PEPPOL_ARCHIVE;
    $archive = $cacheDir.'/'.$archiveFile;
    if (! is_file($archive)) {
        download($archiveUrl, $archive);
    }

    $staging = $target.'.tmp';
    removeTree($staging);
    ensureDirectory($staging);
    extractEntries($archive, $staging, array_map(static fn (string $prefix): string => $archiveRoot.$prefix, PEPPOL_PREFIXES), $archiveRoot);
    $actual = hashTree($staging);

    if ($expected === null) {
        writeManifest($actual, $manifestPath);
    } else {
        checkManifest($expected, $actual, $staging, "Peppol files in {$archive} do not match ".basename($manifestPath));
    }

    $sourceCache = $cacheDir.'/peppol-'.PeppolSources::VERSION;
    ensureDirectory($sourceCache);
    ensureDirectory($staging.'/sch');
    foreach (PeppolSources::all() as $source) {
        copyFile(fetchVerified(PeppolSources::BASE_URL.$source['file'], $sourceCache.'/'.$source['file'], $source['sha256']), $staging.'/sch/'.$source['file']);
    }

    replaceTree($staging, $target);

    return count($actual);
}

/**
 * Rebuilds $target from the pinned Mustang commit: every file of the manifest at $manifestPath is
 * taken from the current tree when it still matches, otherwise downloaded and verified. With
 * $writeManifest the file list comes from the commit's tree instead, the folders are checked
 * against the aggregates pinned in FacturXSources, and the manifest is rewritten. Returns the
 * file count.
 */
function fetchFacturx(string $target, string $manifestPath, bool $writeManifest): int
{
    if ($writeManifest) {
        $sources = [];
        foreach (mustangTree() as $path) {
            if (isset(FacturXSources::LICENCE_FILES[$path])) {
                $sources[$path] = $path;
            }
            foreach (FacturXSources::FOLDERS as $folder => $repositoryFolder) {
                if (str_starts_with($path, $repositoryFolder.'/')) {
                    $sources[$folder.substr($path, strlen($repositoryFolder))] = $path;
                }
            }
        }
        $expected = null;
    } else {
        $expected = readManifest($manifestPath);
        $sources = [];
        foreach (array_keys($expected) as $name) {
            $sources[$name] = facturxRepositoryPath($name);
        }
    }

    $staging = $target.'.tmp';
    $actual = downloadMustangFiles($sources, $expected, $target, $staging);

    if ($expected === null) {
        $problems = [];
        foreach (FacturXSources::AGGREGATES as $folder => $aggregate) {
            if (folderAggregate($actual, $folder) !== $aggregate) {
                $problems[] = "{$folder}/ does not match its pinned aggregate";
            }
        }
        foreach (FacturXSources::LICENCE_FILES as $file => $sha256) {
            if (($actual[$file] ?? null) !== $sha256) {
                $problems[] = "{$file} does not match its pinned checksum";
            }
        }
        if ($problems !== []) {
            removeTree($staging);
            throw new RuntimeException('Mustang '.FacturXSources::MUSTANG_COMMIT.":\n  ".implode("\n  ", $problems));
        }
        writeManifest($actual, $manifestPath);
    }

    replaceTree($staging, $target);

    return count($actual);
}

/**
 * Fills $target with the example files of the manifest at $manifestPath, each at its repository
 * path, reusing files that still match and downloading and verifying the others. With
 * $writeManifest the file list comes from the commit's tree (FeRD ZF252_Beispiele and every PDF of
 * the Mustang test resources) and the manifest is rewritten. Returns the file count.
 */
function fetchFacturxExamples(string $target, string $manifestPath, bool $writeManifest): int
{
    if ($writeManifest) {
        $paths = array_values(array_filter(mustangTree(), static fn (string $path): bool => isSelected($path, FacturXSources::FERD_EXAMPLES)
            || (isSelected($path, MUSTANG_TEST_RESOURCES) && str_ends_with(strtolower($path), '.pdf'))));
        $sources = array_combine($paths, $paths);
        $expected = null;
    } else {
        $expected = FacturXSources::examples($manifestPath);
        $sources = array_combine(array_keys($expected), array_keys($expected));
    }

    $staging = $target.'.tmp';
    $actual = downloadMustangFiles($sources, $expected, $target, $staging);

    if ($expected === null) {
        writeManifest($actual, $manifestPath);
    }

    replaceTree($staging, $target);

    return count($actual);
}

/**
 * Writes each source (local name => repository path) below $staging. A file is copied from
 * $current when it already matches its expected sum, otherwise it is downloaded from the pinned
 * commit; a download that does not match its expected sum aborts the run before it is written.
 * Returns the SHA-256 sum of every file, keyed by local name and sorted.
 *
 * @param  array<string, string>  $sources
 * @param  array<string, string>|null  $expected  null to accept and record any content
 * @return array<string, string>
 */
function downloadMustangFiles(array $sources, ?array $expected, string $current, string $staging): array
{
    removeTree($staging);
    ensureDirectory($staging);

    $sums = [];
    $complete = false;
    try {
        foreach ($sources as $name => $path) {
            if (str_starts_with($name, '/') || str_contains($name, '\\') || in_array('..', explode('/', $name), true)) {
                throw new RuntimeException("Unsafe file name: {$name}");
            }

            $destination = $staging.'/'.$name;
            ensureDirectory(dirname($destination));

            if ($expected !== null && is_file($current.'/'.$name) && hash_file('sha256', $current.'/'.$name) === $expected[$name]) {
                copyFile($current.'/'.$name, $destination);
                $sums[$name] = $expected[$name];

                continue;
            }

            $body = httpGet(FacturXSources::url($path), FACTURX_FILE_TIMEOUT);
            $sum = hash('sha256', $body);
            if ($expected !== null && $sum !== $expected[$name]) {
                throw new RuntimeException("Checksum mismatch for {$name} ({$path}): expected {$expected[$name]}, got {$sum}");
            }
            if (file_put_contents($destination, $body) === false) {
                throw new RuntimeException("Cannot write {$destination}");
            }
            $sums[$name] = $sum;
        }
        $complete = true;
    } finally {
        // A failed download, check or write leaves no half-filled staging folder behind.
        if (! $complete) {
            removeTree($staging);
        }
    }
    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * Repository path of a file of the local ZF_250 copy: the licence files sit at the repository
 * root, everything else in the folder that FacturXSources::FOLDERS maps its first segment to.
 */
function facturxRepositoryPath(string $name): string
{
    if (isset(FacturXSources::LICENCE_FILES[$name])) {
        return $name;
    }

    [$folder, $rest] = array_pad(explode('/', $name, 2), 2, '');
    if (! isset(FacturXSources::FOLDERS[$folder]) || $rest === '') {
        throw new RuntimeException("Unexpected entry in the Factur-X manifest: {$name}");
    }

    return FacturXSources::FOLDERS[$folder].'/'.$rest;
}

/**
 * The aggregate SHA-256 of the files below $folder/ (see FacturXSources::AGGREGATES).
 *
 * @param  array<string, string>  $sums  local name => SHA-256 sum
 */
function folderAggregate(array $sums, string $folder): string
{
    $lines = [];
    foreach ($sums as $name => $sum) {
        if (str_starts_with($name, $folder.'/')) {
            $lines[substr($name, strlen($folder) + 1)] = "{$sum}  ./".substr($name, strlen($folder) + 1)."\n";
        }
    }
    ksort($lines, SORT_STRING);

    return hash('sha256', implode('', $lines));
}

/**
 * Paths of every file at the pinned Mustang commit.
 *
 * @return list<string>
 */
function mustangTree(): array
{
    $tree = json_decode(httpGet(MUSTANG_TREE_URL, FACTURX_FILE_TIMEOUT), true);
    if (! is_array($tree) || ! is_array($tree['tree'] ?? null) || ($tree['truncated'] ?? true) !== false) {
        throw new RuntimeException('Cannot list the Mustang tree (missing or truncated): '.MUSTANG_TREE_URL);
    }

    $paths = [];
    foreach ($tree['tree'] as $entry) {
        if (is_array($entry) && ($entry['type'] ?? null) === 'blob' && is_string($entry['path'] ?? null)) {
            $paths[] = $entry['path'];
        }
    }

    return $paths;
}

/**
 * SHA-256 sums of every file below $dir, keyed by path relative to $dir, sorted by path.
 *
 * @return array<string, string>
 */
function hashTree(string $dir): array
{
    $sums = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        $relative = substr($file->getPathname(), strlen($dir) + 1);
        $sums[$relative] = (string) hash_file('sha256', $file->getPathname());
    }
    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * Writes sums in sha256sum format: one "<sum>  <relative path>" line per file.
 *
 * @param  array<string, string>  $sums
 */
function writeManifest(array $sums, string $path): void
{
    $lines = array_map(static fn (string $name, string $sum): string => "{$sum}  {$name}\n", array_keys($sums), $sums);
    if (file_put_contents($path, implode('', $lines)) === false) {
        throw new RuntimeException("Cannot write {$path}");
    }
}

/**
 * Throws, after removing $staging, unless the unpacked files match the manifest exactly.
 *
 * @param  array<string, string>  $expected
 * @param  array<string, string>  $actual
 */
function checkManifest(array $expected, array $actual, string $staging, string $message): void
{
    $problems = [];
    foreach (array_diff_key($expected, $actual) as $name => $sum) {
        $problems[] = "missing {$name}";
    }
    foreach ($actual as $name => $sum) {
        if (! isset($expected[$name])) {
            $problems[] = "unexpected {$name}";
        } elseif ($expected[$name] !== $sum) {
            $problems[] = "checksum mismatch {$name}";
        }
    }

    if ($problems !== []) {
        removeTree($staging);
        throw new RuntimeException($message.' (delete the cached archive to download it again):'."\n  ".implode("\n  ", array_slice($problems, 0, 20)));
    }
}

/**
 * Reads a manifest in sha256sum format: one "<sum>  <relative path>" line per file.
 *
 * @return array<string, string> relative path => SHA-256 sum
 */
function readManifest(string $path): array
{
    $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
    if ($lines === false || $lines === []) {
        throw new RuntimeException("Cannot read {$path}; run with --write-manifest to create it.");
    }

    $sums = [];
    foreach ($lines as $line) {
        if (preg_match('/^([0-9a-f]{64})  (.+)$/', $line, $match) !== 1) {
            throw new RuntimeException("Malformed line in {$path}: {$line}");
        }
        $sums[$match[2]] = $match[1];
    }

    return $sums;
}

/**
 * Replaces $target with the archive entries that start with one of the prefixes.
 *
 * @param  list<string>  $prefixes
 */
function unpackArchive(string $archive, string $target, array $prefixes): void
{
    $staging = $target.'.tmp';
    removeTree($staging);
    ensureDirectory($staging);
    extractEntries($archive, $staging, $prefixes);
    replaceTree($staging, $target);
}

/**
 * Writes the archive entries that start with one of the prefixes below $destinationDir, with
 * $strip removed from the start of each entry name.
 *
 * @param  list<string>  $prefixes
 */
function extractEntries(string $archive, string $destinationDir, array $prefixes, string $strip = ''): void
{
    $zip = new ZipArchive;
    if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException("Cannot open {$archive}");
    }

    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/') || ! isSelected($name, $prefixes)) {
                continue;
            }

            if (str_starts_with($name, '/') || str_contains($name, '\\') || in_array('..', explode('/', $name), true)) {
                throw new RuntimeException("Unsafe entry name in {$archive}: {$name}");
            }

            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                throw new RuntimeException("Cannot read {$name} from {$archive}");
            }

            $destination = $destinationDir.'/'.substr($name, str_starts_with($name, $strip) ? strlen($strip) : 0);
            ensureDirectory(dirname($destination));
            if (file_put_contents($destination, $contents) === false) {
                throw new RuntimeException("Cannot write {$destination}");
            }
        }
    } finally {
        $zip->close();
    }
}

function replaceTree(string $staging, string $target): void
{
    removeTree($target);
    if (! rename($staging, $target)) {
        throw new RuntimeException("Cannot move {$staging} to {$target}");
    }
}

/**
 * Copies the complete "Notices" section of the UBL 2.1 specification next to the schemas,
 * converted from HTML to plain text: tags (including link targets) are removed, entities decoded
 * and paragraphs reflowed; the wording is unchanged. The OASIS notice permits copies only when
 * the copyright notice and that section are included; the schema files themselves carry the
 * copyright line only.
 */
function writeOasisNotice(string $archive, string $target): void
{
    $zip = new ZipArchive;
    if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException("Cannot open {$archive}");
    }
    $page = $zip->getFromName(UBL_SPECIFICATION_PAGE);
    $zip->close();

    if ($page === false || preg_match('#<h2 class="notices-heading">Notices</h2>(.*?)</div>#s', $page, $match) !== 1) {
        throw new RuntimeException('Cannot find the Notices section in '.UBL_SPECIFICATION_PAGE);
    }

    $paragraphs = [];
    foreach (preg_split('#</p>#', $match[1]) ?: [] as $paragraph) {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($paragraph), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($text !== '') {
            $paragraphs[] = wordwrap($text, 100);
        }
    }

    $notice = 'OASIS Universal Business Language (UBL) 2.1 OS: Notices'."\n"
        .'Complete text from '.UBL_SCHEMA[0].' ('.UBL_SPECIFICATION_PAGE.'),'."\n"
        .'converted to plain text and reflowed; wording unchanged, link targets omitted.'."\n"
        .'Also published at https://docs.oasis-open.org/ubl/os-UBL-2.1/UBL-2.1.html'."\n\n"
        .implode("\n\n", $paragraphs)."\n";

    if (file_put_contents($target, $notice) === false) {
        throw new RuntimeException("Cannot write {$target}");
    }
}

/**
 * @param  list<string>  $prefixes
 */
function isSelected(string $name, array $prefixes): bool
{
    foreach ($prefixes as $prefix) {
        if (str_starts_with($name, $prefix)) {
            return true;
        }
    }

    return false;
}

function ensureDirectory(string $path): void
{
    if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
        throw new RuntimeException("Cannot create directory {$path}");
    }
}

function copyFile(string $from, string $to): void
{
    if (! copy($from, $to)) {
        throw new RuntimeException("Cannot copy {$from} to {$to}");
    }
}

function removeTree(string $path): void
{
    if (! file_exists($path)) {
        return;
    }
    if (! is_dir($path)) {
        if (! unlink($path)) {
            throw new RuntimeException("Cannot remove {$path}");
        }

        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    /** @var SplFileInfo $item */
    foreach ($items as $item) {
        $removed = $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        if (! $removed) {
            throw new RuntimeException("Cannot remove {$item->getPathname()}");
        }
    }

    if (! rmdir($path)) {
        throw new RuntimeException("Cannot remove {$path}");
    }
}
