<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Peppol;

use Closure;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Validation\Exception\PartialInstall;
use IntrAgile\EInvoicing\Validation\Exception\RuleSourceMismatch;
use IntrAgile\EInvoicing\Validation\Install\LocalRuleInstaller;
use IntrAgile\EInvoicing\Validation\Install\RuleSource;
use InvalidArgumentException;
use RuntimeException;

/**
 * Compiles the official Peppol BIS Billing Schematron files into PHP rule files on the user's
 * machine.
 *
 * OpenPeppol publishes no licence for its rules, so this package ships none of them: the sources
 * are downloaded from the pinned release (or read from a local copy for offline use), checked
 * against their pinned SHA-256 checksums and compiled into a folder the user chooses.
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
     * Installs the five rule files: the invoice rules and the envelope rules. No installed file is
     * replaced unless every source matches its checksum and compiles. Each file is replaced
     * atomically, so the target never goes missing; if moving the files into place fails, the
     * previous rules are restored.
     *
     * @param  ?string  $sourceDir  a folder holding the five .sch files, for air-gapped machines;
     *                              null downloads them from PeppolSources::BASE_URL
     * @return list<string> written file paths
     *
     * @throws RuleSourceMismatch when a source file does not have its pinned checksum
     * @throws UnsupportedSchematron when a source file cannot be compiled
     * @throws PartialInstall when a file could not be moved into place and restoring the previous
     *                        rules failed as well
     * @throws InvalidArgumentException|RuntimeException when a source cannot be read or downloaded,
     *                                                   or the target folder cannot be written
     */
    public function install(string $targetDir, ?string $sourceDir = null): array
    {
        $sources = [];
        foreach (PeppolSources::all() as $key => $source) {
            $sources[] = new RuleSource($key, $source['file'], $source['sha256'], $source['id'], PeppolSources::VERSION);
        }

        return $this->installer->install(
            $targetDir,
            $sources,
            PeppolSources::BASE_URL,
            $sourceDir,
            'OpenPeppol BIS Billing '.PeppolSources::VERSION.' (%s), compiled locally; OpenPeppol AISBL holds the copyright — do not redistribute',
            'vendor/bin/einvoicing-rules install peppol',
            release: 'Peppol BIS '.PeppolSources::VERSION,
            family: 'Peppol',
        );
    }
}
