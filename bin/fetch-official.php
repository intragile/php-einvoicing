<?php

declare(strict_types=1);

/*
 * Downloads the CEN EN 16931 validation artefacts (release validation-1.3.17) and unpacks the
 * examples, the compiled XSLT and the code-list Schematron into
 * tests/fixtures/official/cen-1.3.17/{ubl,cii}/.
 *
 * Every download is verified against a pinned SHA-256 sum. Verified archives are cached in
 * .cache/official/, so a second run needs no network and produces the same tree.
 *
 * The examples inside the release zips are byte-identical to ubl/examples and cii/examples
 * in the repository at the same tag.
 *
 * It also downloads the OASIS UBL 2.1 OS package and unpacks the Invoice and CreditNote schemas
 * with their common modules into tests/fixtures/official/ubl-2.1/xsd/, for schema validation of
 * written documents.
 *
 * It also downloads the UN/CEFACT Cross Industry Invoice D16B schemas (SCRDM subset, uncoupled
 * code lists) that the CEN repository ships at the same tag, into
 * tests/fixtures/official/cii-d16b/, for schema validation of written CII documents.
 */

const TAG = 'validation-1.3.17';
const RELEASE_URL = 'https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/download/'.TAG.'/';
const RAW_URL = 'https://raw.githubusercontent.com/ConnectingEurope/eInvoicing-EN16931/'.TAG.'/';
const USER_AGENT = 'IntrAgile Kft. info@intragile.eu';

/** Release assets per syntax: file name and its SHA-256 sum. */
const ARCHIVES = [
    'ubl' => ['en16931-ubl-1.3.17.zip', 'bf02a0e3f6cbe692a4a28e70be7bdcaded5612b4169b543e13d6cb4f33c29e92'],
    'cii' => ['en16931-cii-1.3.17.zip', 'e5f3235824b6b38454b1d54cf3f14e01efb5b33971fbefc4e64997d0ef619e89'],
];

/** The EUPL 1.2 text that must travel with every redistributed copy. */
const LICENCE = ['LICENSE.txt', 'fc22ec1dcd8bee4636a395fb332e2308cde870fb3fdc71a2e260b919877cdef5'];

/**
 * Archive directories that are unpacked. The code-list Schematron feeds bin/generate-codelists.php;
 * the other Schematron sources are compiled into the XSLT and not needed.
 */
const EXTRACT_PREFIXES = ['examples/', 'xslt/', 'schematron/codelist/'];

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

$root = dirname(__DIR__);
$cacheDir = $root.'/.cache/official';
$targetDir = $root.'/tests/fixtures/official/cen-1.3.17';
$schemaDir = $root.'/tests/fixtures/official/ubl-2.1';
$ciiSchemaDir = $root.'/tests/fixtures/official/cii-d16b';

try {
    if (! class_exists(ZipArchive::class)) {
        throw new RuntimeException('The zip extension is required to unpack the official artefacts.');
    }

    ensureDirectory($cacheDir);
    ensureDirectory($targetDir);

    foreach (ARCHIVES as $syntax => [$file, $sha256]) {
        $archive = fetchVerified(RELEASE_URL.$file, $cacheDir.'/'.$file, $sha256);
        unpackArchive($archive, $targetDir.'/'.$syntax, EXTRACT_PREFIXES);
        fwrite(STDOUT, "{$syntax}: unpacked {$file}\n");
    }

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
} catch (RuntimeException $e) {
    fwrite(STDERR, 'fetch-official: '.$e->getMessage()."\n");
    exit(1);
}

/**
 * Returns the cached file when its checksum matches, otherwise downloads it and verifies it.
 */
function fetchVerified(string $url, string $path, string $sha256): string
{
    if (is_file($path) && hash_file('sha256', $path) === $sha256) {
        return $path;
    }

    $context = stream_context_create([
        'http' => [
            'user_agent' => USER_AGENT,
            'timeout' => 300,
            'follow_location' => 1,
            'max_redirects' => 5,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        throw new RuntimeException("Download failed: {$url}");
    }

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
 * Replaces $target with the archive entries that start with one of the prefixes.
 *
 * @param  list<string>  $prefixes
 */
function unpackArchive(string $archive, string $target, array $prefixes): void
{
    $zip = new ZipArchive;
    if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException("Cannot open {$archive}");
    }

    $staging = $target.'.tmp';
    removeTree($staging);
    ensureDirectory($staging);

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

            $destination = $staging.'/'.$name;
            ensureDirectory(dirname($destination));
            if (file_put_contents($destination, $contents) === false) {
                throw new RuntimeException("Cannot write {$destination}");
            }
        }
    } finally {
        $zip->close();
    }

    removeTree($target);
    if (! rename($staging, $target)) {
        throw new RuntimeException("Cannot move {$staging} to {$target}");
    }
}

/**
 * Copies the "Notices" section of the UBL 2.1 specification, verbatim as plain text, next to the
 * schemas. The OASIS notice permits copies only when the copyright notice and that section are
 * included; the schema files themselves carry the copyright line only.
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
        .'Copied verbatim from '.UBL_SCHEMA[0].' ('.UBL_SPECIFICATION_PAGE.'),'."\n"
        .'also published at https://docs.oasis-open.org/ubl/os-UBL-2.1/UBL-2.1.html'."\n\n"
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

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    /** @var SplFileInfo $item */
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($path);
}
