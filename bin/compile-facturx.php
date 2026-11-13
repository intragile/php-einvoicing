<?php

declare(strict_types=1);

/*
 * Builds the Apache-2.0 package in packages/rules-facturx/ from the Factur-X artefacts:
 *
 *   - rules/facturx-<profile>-<version>.php: the Schematron of each profile, compiled with its
 *     code database embedded;
 *   - schemas/<profile>/: the profile's XML Schemas, copied byte for byte.
 *
 * Usage:
 *
 *   php bin/compile-facturx.php           writes the files above
 *   php bin/compile-facturx.php --check   exits 1 when a generated or copied file is missing,
 *                                         stale or no longer part of the set
 *
 * The sources are the committed fixtures in tests/fixtures/official/facturx-<version>/.
 */

use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Schematron\CompiledRuleSetWriter;
use IntrAgile\EInvoicing\Schematron\CompileOptions;
use IntrAgile\EInvoicing\Schematron\Compiler;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$check = in_array('--check', array_slice($argv ?? [], 1), true);
$sourceDir = "{$root}/tests/fixtures/official/facturx-".RuleFiles::VERSION;
$packageDir = "{$root}/packages/rules-facturx";
$origin = 'Factur-X '.RuleFiles::VERSION.' technical artefacts (Apache-2.0)';

/** @var array<string, string> $expected absolute target path => contents */
$expected = [];

foreach (RuleFiles::profiles() as $profile) {
    $id = 'facturx-'.strtolower(str_replace('-', '', $profile));
    $compiled = Compiler::compile(
        "{$sourceDir}/schematron/FACTUR-X_{$profile}.sch",
        $id,
        RuleFiles::VERSION,
        null,
        new CompileOptions(defaultFlag: 'fatal', missingIdPrefix: 'FX-REPORT'),
    );
    $expected[RuleFiles::rules($profile)] = CompiledRuleSetWriter::render($compiled, $origin);

    $schemas = glob("{$sourceDir}/schema/{$profile}/*.xsd");
    if ($schemas === false || $schemas === []) {
        fwrite(STDERR, "No schemas found in {$sourceDir}/schema/{$profile}.\n");
        exit(1);
    }
    foreach ($schemas as $schema) {
        $contents = file_get_contents($schema);
        if ($contents === false) {
            fwrite(STDERR, "Cannot read {$schema}.\n");
            exit(1);
        }
        $expected["{$packageDir}/schemas/{$profile}/".basename($schema)] = $contents;
    }
}

$superfluous = array_values(array_diff([...filesBelow("{$packageDir}/schemas"), ...filesBelow("{$packageDir}/rules")], array_keys($expected)));

if ($check) {
    $stale = [];
    foreach ($expected as $target => $contents) {
        if (! is_file($target) || file_get_contents($target) !== $contents) {
            $stale[] = $target;
        }
    }
    $stale = [...$stale, ...$superfluous];
    if ($stale !== []) {
        fwrite(STDERR, "Stale, missing or superfluous files in packages/rules-facturx (run composer compile-rules):\n  ".implode("\n  ", $stale)."\n");
        exit(1);
    }
    exit(0);
}

foreach ($expected as $target => $contents) {
    if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0777, true)) {
        fwrite(STDERR, "Cannot create the directory of {$target}.\n");
        exit(1);
    }
    if (file_put_contents($target, $contents) === false) {
        fwrite(STDERR, "Cannot write {$target}.\n");
        exit(1);
    }
    echo "Wrote {$target}\n";
}
foreach ($superfluous as $file) {
    if (! unlink($file)) {
        fwrite(STDERR, "Cannot remove {$file}.\n");
        exit(1);
    }
    echo "Removed {$file}\n";
}

/**
 * Absolute paths of every file below a folder, hidden files (such as .DS_Store) excepted; empty
 * when the folder does not exist.
 *
 * @return list<string>
 */
function filesBelow(string $dir): array
{
    if (! is_dir($dir)) {
        return [];
    }
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && ! str_starts_with($file->getFilename(), '.')) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}
