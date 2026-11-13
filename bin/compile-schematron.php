<?php

declare(strict_types=1);

/*
 * Compiles the official CEN EN 16931 preprocessed Schematron into the PHP rule files of the
 * EUPL-1.2 package in packages/rules-en16931/rules/, one file per syntax and release. 1.3.17 is
 * the default release; 1.3.16 is the one the KoSIT XRechnung configuration is built on.
 *
 * Run bin/fetch-official.php first. Usage:
 *
 *   php bin/compile-schematron.php           writes packages/rules-en16931/rules/*.php
 *   php bin/compile-schematron.php --check   exits 1 when a generated file is missing or stale
 *
 * The release list is IntrAgile\EInvoicing\Rules\En16931\RuleFiles::VERSIONS; the sources of a
 * release are read from tests/fixtures/official/cen-<version>/.
 */

use IntrAgile\EInvoicing\Rules\En16931\RuleFiles;
use IntrAgile\EInvoicing\Schematron\CompiledRuleSetWriter;
use IntrAgile\EInvoicing\Schematron\Compiler;

require dirname(__DIR__).'/vendor/autoload.php';

const TARGET_DIR = 'packages/rules-en16931/rules';
const SCHEMATRON = [
    'ubl' => 'ubl/schematron/preprocessed/EN16931-UBL-validation-preprocessed.sch',
    'cii' => 'cii/schematron/preprocessed/EN16931-CII-validation-preprocessed.sch',
];

$root = dirname(__DIR__);
$check = in_array('--check', array_slice($argv ?? [], 1), true);
$stale = [];

foreach (RuleFiles::VERSIONS as $version) {
    foreach (SCHEMATRON as $syntax => $file) {
        $id = "en16931-{$syntax}";
        $target = "{$root}/".TARGET_DIR."/{$id}-{$version}.php";
        $origin = "CEN EN 16931 validation artefacts {$version} (EUPL-1.2)";
        $source = "{$root}/tests/fixtures/official/cen-{$version}/{$file}";
        $contents = CompiledRuleSetWriter::render(Compiler::compile($source, $id, $version), $origin);

        if ($check) {
            if (! is_file($target) || file_get_contents($target) !== $contents) {
                $stale[] = $target;
            }

            continue;
        }

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
}

if ($stale !== []) {
    fwrite(STDERR, "Stale or missing generated rule files (run composer compile-rules):\n  ".implode("\n  ", $stale)."\n");
    exit(1);
}
