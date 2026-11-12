<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Peppol;

use IntrAgile\EInvoicing\Schematron\CompiledRuleSetWriter;
use IntrAgile\EInvoicing\Schematron\Compiler;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Validation\Exception\PartialInstall;
use IntrAgile\EInvoicing\Validation\Exception\RuleSourceMismatch;
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
    private readonly Downloader $downloader;

    public function __construct(?Downloader $downloader = null)
    {
        $this->downloader = $downloader ?? new Downloader;
    }

    /**
     * Installs the four rule files. No installed file is replaced unless every source matches its
     * checksum and compiles; if moving the files into place fails, the previous rules are restored.
     *
     * @param  ?string  $sourceDir  a folder holding the four .sch files, for air-gapped machines;
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
        $contents = [];
        foreach (PeppolSources::all() as $key => $source) {
            $contents[$key] = $sourceDir === null
                ? $this->downloader->get(PeppolSources::BASE_URL.$source['file'])
                : self::read(rtrim($sourceDir, '/\\').'/'.$source['file']);
        }

        foreach (PeppolSources::all() as $key => $source) {
            $actual = hash('sha256', $contents[$key]);
            if ($actual !== $source['sha256']) {
                throw new RuleSourceMismatch(sprintf(
                    '%s is not the published Peppol BIS %s file: expected SHA-256 %s, got %s.',
                    $source['file'],
                    PeppolSources::VERSION,
                    $source['sha256'],
                    $actual,
                ));
            }
        }

        if (! is_dir($targetDir) && ! @mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
            throw new RuntimeException("Cannot create the rules folder \"{$targetDir}\".");
        }

        // Each source is compiled straight into a temporary file, so only one compiled rule set is
        // held in memory; the files replace the installed ones only once all four have compiled.
        $temporary = [];
        try {
            foreach (PeppolSources::all() as $key => $source) {
                $path = PeppolRules::path($targetDir, $source['id']).'.tmp-'.bin2hex(random_bytes(6));
                $temporary[$key] = $path;
                $origin = sprintf(
                    'OpenPeppol BIS Billing %s (%s), compiled locally; OpenPeppol AISBL holds the copyright — do not redistribute',
                    PeppolSources::VERSION,
                    $source['file'],
                );
                CompiledRuleSetWriter::write(
                    Compiler::compileString($contents[$key], $source['file'], $source['id'], PeppolSources::VERSION),
                    $path,
                    $origin,
                    'vendor/bin/einvoicing-rules install peppol',
                );
                unset($contents[$key]);
            }

            $written = self::replace($targetDir, $temporary);
        } finally {
            foreach ($temporary as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        return $written;
    }

    /**
     * Moves the compiled files into place. Every installed file is first renamed to a backup, so a
     * failed move can put all of them back; the backups are deleted once every file is in place.
     *
     * @param  array<string, string>  $temporary  compiled file path per source key
     * @return list<string> written file paths
     *
     * @throws RuntimeException when a file could not be moved and the previous rules were restored
     * @throws PartialInstall when a file could not be moved and restoring the previous rules failed
     */
    private static function replace(string $targetDir, array $temporary): array
    {
        $targets = [];
        foreach (PeppolSources::all() as $key => $source) {
            $targets[$key] = PeppolRules::path($targetDir, $source['id']);
        }

        /** @var array<string, string> $backups backup path per source key */
        $backups = [];
        /** @var list<string> $written */
        $written = [];
        try {
            foreach ($targets as $key => $target) {
                if (! is_file($target)) {
                    continue;
                }
                $backup = $target.'.bak-'.bin2hex(random_bytes(6));
                if (! @rename($target, $backup)) {
                    throw new RuntimeException("Cannot back up the installed rule set \"{$target}\".");
                }
                $backups[$key] = $backup;
            }
            foreach ($targets as $key => $target) {
                if (! @rename($temporary[$key], $target)) {
                    throw new RuntimeException("Cannot move the compiled rule set to \"{$target}\".");
                }
                $written[] = $target;
            }
        } catch (RuntimeException $exception) {
            $notRestored = self::restore($targets, $backups, $written);
            if ($notRestored !== []) {
                throw new PartialInstall(
                    $exception->getMessage().' Restoring the previous rules failed; these files now hold the new rules or are missing.',
                    $notRestored,
                    $exception,
                );
            }

            throw new RuntimeException($exception->getMessage().' The previous rules were restored; nothing was changed.', 0, $exception);
        }

        foreach ($backups as $backup) {
            @unlink($backup);
        }

        return $written;
    }

    /**
     * Puts every backup back in place and removes the new files that had no previous version.
     *
     * @param  array<string, string>  $targets  installed path per source key
     * @param  array<string, string>  $backups  backup path per source key
     * @param  list<string>  $written  targets that already hold a new file
     * @return list<string> targets that could not be restored
     */
    private static function restore(array $targets, array $backups, array $written): array
    {
        $failed = [];
        foreach ($targets as $key => $target) {
            if (isset($backups[$key])) {
                if (! @rename($backups[$key], $target)) {
                    $failed[] = $target;
                }
            } elseif (in_array($target, $written, true) && ! @unlink($target)) {
                $failed[] = $target;
            }
        }

        return $failed;
    }

    private static function read(string $path): string
    {
        $contents = is_file($path) ? @file_get_contents($path) : false;
        if ($contents === false) {
            throw new RuntimeException("Cannot read the Peppol rule source \"{$path}\".");
        }

        return $contents;
    }
}
