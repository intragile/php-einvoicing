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

$root = dirname(__DIR__);
$cacheDir = $root.'/.cache/official';
$targetDir = $root.'/tests/fixtures/official/cen-1.3.17';

try {
    if (! class_exists(ZipArchive::class)) {
        throw new RuntimeException('The zip extension is required to unpack the official artefacts.');
    }

    ensureDirectory($cacheDir);
    ensureDirectory($targetDir);

    foreach (ARCHIVES as $syntax => [$file, $sha256]) {
        $archive = fetchVerified(RELEASE_URL.$file, $cacheDir.'/'.$file, $sha256);
        unpackArchive($archive, $targetDir.'/'.$syntax);
        fwrite(STDOUT, "{$syntax}: unpacked {$file}\n");
    }

    [$licenceFile, $licenceSha256] = LICENCE;
    $licence = fetchVerified(RAW_URL.$licenceFile, $cacheDir.'/'.$licenceFile, $licenceSha256);
    copyFile($licence, $targetDir.'/'.$licenceFile);
    fwrite(STDOUT, "licence: copied {$licenceFile}\n");
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
            'timeout' => 120,
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
 * Replaces $target with the selected directories of the archive.
 */
function unpackArchive(string $archive, string $target): void
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
            if ($name === false || str_ends_with($name, '/') || ! isSelected($name)) {
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

function isSelected(string $name): bool
{
    foreach (EXTRACT_PREFIXES as $prefix) {
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
