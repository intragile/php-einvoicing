<?php

declare(strict_types=1);

/*
 * Builds the Apache-2.0 package in packages/rules-xrechnung/ from the KoSIT artefacts:
 *
 *   - rules/xrechnung-{ubl,cii}-<version>.php: the XRechnung Schematron, compiled with its include;
 *   - rules/scenarios-<configuration>.php: the scenario table compiled from scenarios.xml;
 *   - schemas/: the XML Schemas the scenario table names and every file they import or include,
 *     copied byte for byte from the configuration's resources/ folder, at the same relative paths.
 *
 * Usage:
 *
 *   php bin/compile-xrechnung.php           writes the files above
 *   php bin/compile-xrechnung.php --check   exits 1 when a generated or copied file is missing,
 *                                           stale or no longer part of the set
 *
 * The sources are the committed fixtures in tests/fixtures/official/xrechnung/.
 */

use IntrAgile\EInvoicing\Rules\XRechnung\RuleFiles;
use IntrAgile\EInvoicing\Schematron\CompiledRuleSetWriter;
use IntrAgile\EInvoicing\Schematron\Compiler;
use IntrAgile\EInvoicing\Validation\XRechnung\ScenarioTableCompiler;

require dirname(__DIR__).'/vendor/autoload.php';

const SOURCE_DIR = 'tests/fixtures/official/xrechnung';
const SCHEMATRON = [
    'ubl' => 'ubl/XRechnung-UBL-validation.sch',
    'cii' => 'cii/XRechnung-CII-validation.sch',
];

$root = dirname(__DIR__);
$check = in_array('--check', array_slice($argv ?? [], 1), true);
$schematronDir = "{$root}/".SOURCE_DIR.'/schematron-'.RuleFiles::VERSION;
$configurationDir = "{$root}/".SOURCE_DIR.'/configuration-'.RuleFiles::CONFIGURATION;

/** @var array<string, string> $expected absolute target path => contents */
$expected = [];

foreach (SCHEMATRON as $syntax => $file) {
    $target = $syntax === 'ubl' ? RuleFiles::ubl() : RuleFiles::cii();
    $origin = 'KoSIT XRechnung Schematron '.RuleFiles::VERSION.' (Apache-2.0)';
    $compiled = Compiler::compile("{$schematronDir}/{$file}", "xrechnung-{$syntax}", RuleFiles::VERSION, $schematronDir);
    $expected[$target] = CompiledRuleSetWriter::render($compiled, $origin);
}

$scenariosXml = file_get_contents("{$configurationDir}/scenarios.xml");
if ($scenariosXml === false) {
    fwrite(STDERR, "Cannot read {$configurationDir}/scenarios.xml.\n");
    exit(1);
}
$table = ScenarioTableCompiler::compile($scenariosXml);
$expected[RuleFiles::scenarios()] = "<?php\n\n"
    .'// Generated from the KoSIT validator configuration for XRechnung '.RuleFiles::CONFIGURATION." (Apache-2.0) — do not edit.\n"
    .'// Source: scenarios.xml (SHA-256 '.hash('sha256', $scenariosXml).").\n"
    ."// Regenerate with: composer compile-rules\n\n"
    ."declare(strict_types=1);\n\n"
    .'return '.var_export($table, true).";\n";

$resourcesDir = "{$configurationDir}/resources";
$schemaDir = RuleFiles::schemaDir();
foreach (schemaClosure($resourcesDir, array_column($table['scenarios'], 'schema')) as $relative) {
    $contents = file_get_contents("{$resourcesDir}/{$relative}");
    if ($contents === false) {
        fwrite(STDERR, "Cannot read {$resourcesDir}/{$relative}.\n");
        exit(1);
    }
    $expected["{$schemaDir}/{$relative}"] = $contents;
}

$superfluous = array_values(array_diff(filesBelow($schemaDir), array_keys($expected)));

if ($check) {
    $stale = [];
    foreach ($expected as $target => $contents) {
        if (! is_file($target) || file_get_contents($target) !== $contents) {
            $stale[] = $target;
        }
    }
    $stale = [...$stale, ...$superfluous];
    if ($stale !== []) {
        fwrite(STDERR, "Stale, missing or superfluous files in packages/rules-xrechnung (run composer compile-rules):\n  ".implode("\n  ", $stale)."\n");
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
 * The given schemas and every schema they import, include or redefine, transitively, as paths
 * relative to $resourcesDir, sorted. Every reference must be a relative path that stays inside
 * $resourcesDir; anything else stops the build, because the shipped set must be closed.
 *
 * @param  list<string>  $schemas  paths relative to $resourcesDir
 * @return list<string>
 */
function schemaClosure(string $resourcesDir, array $schemas): array
{
    $found = [];
    $queue = $schemas;
    while ($queue !== []) {
        $relative = array_shift($queue);
        if (isset($found[$relative])) {
            continue;
        }
        $path = "{$resourcesDir}/{$relative}";
        $document = new DOMDocument;
        if (! is_file($path) || ! $document->load($path, LIBXML_NONET)) {
            fwrite(STDERR, "Cannot load the schema {$path}.\n");
            exit(1);
        }
        $found[$relative] = true;

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
        $references = $xpath->query('/xs:schema/xs:import/@schemaLocation | /xs:schema/xs:include/@schemaLocation | /xs:schema/xs:redefine/@schemaLocation');
        foreach ($references === false ? [] : $references as $reference) {
            $location = trim((string) $reference->nodeValue);
            $resolved = normalise(dirname($relative).'/'.$location);
            if (preg_match('~^[a-z][a-z0-9+.-]*:|^/~i', $location) === 1 || $resolved === null) {
                fwrite(STDERR, "The schema {$relative} references \"{$location}\", which is not a relative path inside resources/.\n");
                exit(1);
            }
            $queue[] = $resolved;
        }
    }
    $paths = array_keys($found);
    sort($paths, SORT_STRING);

    return $paths;
}

/**
 * Resolves "." and ".." segments; null when the path leaves its root.
 */
function normalise(string $path): ?string
{
    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            if ($segments === []) {
                return null;
            }
            array_pop($segments);

            continue;
        }
        $segments[] = $segment;
    }

    return implode('/', $segments);
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
