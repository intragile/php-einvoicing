<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\France;

use Closure;
use IntrAgile\EInvoicing\Schematron\CompileOptions;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Validation\Exception\PartialInstall;
use IntrAgile\EInvoicing\Validation\Exception\RuleSourceMismatch;
use IntrAgile\EInvoicing\Validation\Install\LocalRuleInstaller;
use IntrAgile\EInvoicing\Validation\Install\RuleSource;
use IntrAgile\EInvoicing\Validation\Peppol\Downloader;
use InvalidArgumentException;
use RuntimeException;

/**
 * Compiles the FNFE-MPE France_RFE Schematron files into PHP rule files on the user's machine and
 * copies the French XML Schemas next to them.
 *
 * The repository carries no licence that allows redistribution, so this package ships none of its
 * files: they are downloaded from the pinned commit (or read from a local checkout for offline
 * use), checked against the SHA-256 checksums of the committed manifest and written into a folder
 * the user chooses.
 */
final class RuleInstaller
{
    private readonly LocalRuleInstaller $installer;

    /**
     * @param  (Closure(string, string): bool)|null  $rename  replaces rename() in tests
     * @param  (Closure(string): bool)|null  $unlink  replaces unlink() for backups in tests
     */
    public function __construct(?Downloader $downloader = null, ?Closure $rename = null, ?Closure $unlink = null)
    {
        $this->installer = new LocalRuleInstaller($downloader, $rename, $unlink);
    }

    /**
     * Installs the seven French rule sets as "<id>-1.4.0.04.php" and the XML Schemas below
     * "france-rfe-1.4.0.04/". Nothing is replaced unless every file matches its checksum and every
     * rule set compiles; if moving the files into place fails, the previous ones are restored.
     *
     * @param  ?string  $sourceDir  root of a France_RFE checkout, for air-gapped machines; null
     *                              downloads the files from FranceSources::BASE_URL
     * @return list<string> written rule file paths
     *
     * @throws RuleSourceMismatch when a file does not have its pinned checksum
     * @throws UnsupportedSchematron when a source file cannot be compiled
     * @throws PartialInstall when a file could not be moved into place and restoring the previous
     *                        files failed as well
     * @throws InvalidArgumentException|RuntimeException when a file cannot be read or downloaded,
     *                                                   or the target folder cannot be written
     */
    public function install(string $targetDir, ?string $sourceDir = null): array
    {
        $sources = [];
        foreach (FranceSources::ruleSources() as $key => $source) {
            $sources[] = new RuleSource(
                $key,
                $source['file'],
                $source['sha256'],
                $source['id'],
                FranceSources::VERSION,
                self::options($key),
                $source['companions'],
            );
        }

        return $this->installer->install(
            $targetDir,
            $sources,
            FranceSources::BASE_URL,
            $sourceDir,
            'FNFE-MPE France_RFE '.FranceSources::VERSION.' (%s), compiled locally; licence unclear — do not redistribute',
            'vendor/bin/einvoicing-rules install france',
            FranceSources::files('schema'),
            FranceRules::SCHEMA_DIR,
            'FNFE-MPE France_RFE '.FranceSources::VERSION,
            'France_RFE',
        );
    }

    /**
     * The compiler relaxations a source needs: a default flag where an assert carries none, and
     * ids for asserts without one.
     */
    private static function options(string $key): ?CompileOptions
    {
        return match ($key) {
            'extended-ctc-fr-cii' => new CompileOptions(defaultFlag: 'warning', missingIdPrefix: 'FR-REPORT', idFromMessage: true),
            'flux10' => new CompileOptions(missingIdPrefix: 'FR10', idFromMessage: true, normalizeTestWhitespace: true),
            'facturx-extended' => new CompileOptions(defaultFlag: 'fatal', missingIdPrefix: 'FX-REPORT'),
            default => null,
        };
    }
}
