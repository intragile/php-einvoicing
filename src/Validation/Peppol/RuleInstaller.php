<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Peppol;

use Closure;
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

    /** @var Closure(string, string): bool */
    private readonly Closure $rename;

    /** @var Closure(string): bool */
    private readonly Closure $unlink;

    /**
     * @param  (Closure(string, string): bool)|null  $rename  replaces rename() in tests
     * @param  (Closure(string): bool)|null  $unlink  replaces unlink() for backups in tests
     */
    public function __construct(?Downloader $downloader = null, ?Closure $rename = null, ?Closure $unlink = null)
    {
        $this->downloader = $downloader ?? new Downloader;
        $this->rename = $rename ?? static fn (string $from, string $to): bool => @rename($from, $to);
        $this->unlink = $unlink ?? static fn (string $path): bool => @unlink($path);
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
        // held in memory; the files replace the installed ones only once all of them have compiled.
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

            $written = $this->replace($targetDir, $temporary);
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
     * Moves the compiled files into place without a moment in which an installed file is missing.
     *
     * Every installed file first gets a backup: a hard link to it, or a copy where hard links are
     * not supported. Each compiled file is then renamed over its target, which replaces the target
     * atomically. If a move fails, every replaced target gets its backup renamed back over it, the
     * same atomic way, and the new files that had no previous version are removed. The backups
     * are deleted once every file is in place; one that cannot be deleted is left behind.
     *
     * @param  array<string, string>  $temporary  compiled file path per source key
     * @return list<string> written file paths
     *
     * @throws RuntimeException when a file could not be moved and the previous rules were restored
     * @throws PartialInstall when a file could not be moved and restoring the previous rules failed
     */
    private function replace(string $targetDir, array $temporary): array
    {
        $targets = [];
        $backups = [];
        // Every backup name is chosen before anything is touched, so that a failure to generate
        // one cannot strike halfway through.
        foreach (PeppolSources::all() as $key => $source) {
            $targets[$key] = PeppolRules::path($targetDir, $source['id']);
            if (is_file($targets[$key])) {
                $backups[$key] = $targets[$key].'.bak-'.bin2hex(random_bytes(6));
            }
        }

        /** @var array<string, string> $made backup path per source key, for the backups that exist */
        $made = [];
        /** @var list<string> $written */
        $written = [];
        try {
            foreach ($backups as $key => $backup) {
                if (! @link($targets[$key], $backup) && ! @copy($targets[$key], $backup)) {
                    throw new RuntimeException("Cannot back up the installed rule set \"{$targets[$key]}\".");
                }
                $made[$key] = $backup;
            }
            foreach ($targets as $key => $target) {
                if (! ($this->rename)($temporary[$key], $target)) {
                    throw new RuntimeException("Cannot move the compiled rule set to \"{$target}\".");
                }
                $written[] = $target;
            }
        } catch (RuntimeException $exception) {
            [$notRestored, $leftover] = $this->restore($targets, $made, $written);
            $backupNote = $leftover === []
                ? ''
                : ' These backups could not be removed and are left in place: '.implode(', ', $leftover).'.';
            if ($notRestored !== []) {
                throw new PartialInstall(
                    $exception->getMessage().' Restoring the previous rules failed; these files now hold the new rules or are missing: '
                    .implode(', ', $notRestored).'.'.$backupNote,
                    $notRestored,
                    $exception,
                    $leftover,
                );
            }

            throw new RuntimeException($exception->getMessage().' The previous rules were restored; nothing was changed.'.$backupNote, 0, $exception);
        }

        foreach ($made as $backup) {
            ($this->unlink)($backup);
        }

        return $written;
    }

    /**
     * Puts every backup back over a replaced target, removes the new files that had no previous
     * version, and deletes the backups of the targets that were never replaced.
     *
     * @param  array<string, string>  $targets  installed path per source key
     * @param  array<string, string>  $backups  backup path per source key
     * @param  list<string>  $written  targets that already hold a new file
     * @return array{list<string>, list<string>} the targets that could not be restored, and the
     *                                           backups left behind
     */
    private function restore(array $targets, array $backups, array $written): array
    {
        $failed = [];
        $leftover = [];
        foreach ($targets as $key => $target) {
            $replaced = in_array($target, $written, true);
            $backup = $backups[$key] ?? null;
            if ($backup === null) {
                if ($replaced && ! @unlink($target)) {
                    $failed[] = $target;
                }

                continue;
            }
            if ($replaced) {
                // Renamed over the new file, so the target is never missing.
                if (! ($this->rename)($backup, $target)) {
                    $failed[] = $target;
                    $leftover[] = $backup;
                }
            } elseif (! ($this->unlink)($backup)) {
                // The target still holds the previous rules; only the backup is in the way. A
                // rename would do nothing here, because a hard link and its target are one file.
                $leftover[] = $backup;
            }
        }

        return [$failed, $leftover];
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
