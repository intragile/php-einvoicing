<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Install;

use Closure;
use IntrAgile\EInvoicing\Schematron\CompiledRuleSetWriter;
use IntrAgile\EInvoicing\Schematron\Compiler;
use IntrAgile\EInvoicing\Schematron\Exception\UnsupportedSchematron;
use IntrAgile\EInvoicing\Validation\Exception\PartialInstall;
use IntrAgile\EInvoicing\Validation\Exception\RuleSourceMismatch;
use IntrAgile\EInvoicing\Validation\Peppol\Downloader;
use InvalidArgumentException;
use RuntimeException;

/**
 * Compiles pinned Schematron files that may not be redistributed into PHP rule files on the
 * user's machine, and optionally copies a pinned tree of companion files (such as XML Schemas)
 * next to them. installFiles() copies a set of pinned files, each with its own download URL,
 * without compiling anything.
 *
 * Every file is downloaded (or read from a local copy for offline use) and checked against its
 * pinned SHA-256 checksum before anything in the target folder is replaced.
 *
 * @internal
 *
 * @phpstan-import-type CompiledRuleSet from Compiler
 */
final class LocalRuleInstaller
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
     * Compiles each source into "<targetDir>/<id>-<version>.php" and copies the verbatim files
     * into "<targetDir>/<verbatimDir>/<path>". No installed file is replaced unless every source,
     * companion and verbatim file matches its checksum and every source compiles. Each compiled
     * file is replaced atomically; the verbatim tree is swapped in by renaming. If moving anything
     * into place fails, the previous rules and tree are restored.
     *
     * @param  list<RuleSource>  $sources
     * @param  string  $baseUrl  download URL that the URL-encoded file paths are appended to
     * @param  ?string  $sourceDir  a folder holding the files at their paths, for air-gapped
     *                              machines; null downloads them from $baseUrl
     * @param  string  $origin  header line of each compiled file; "%s" stands for the source file
     * @param  string  $command  the command that regenerates the compiled files
     * @param  array<string, string>  $verbatim  path => pinned SHA-256 of the files copied unchanged
     * @param  string  $verbatimDir  folder below $targetDir that receives the verbatim files
     * @param  string  $release  the publication the files belong to, for checksum errors
     * @param  string  $family  short name of the sources, for read errors
     * @return list<string> written compiled file paths, in source order
     *
     * @throws RuleSourceMismatch when a file does not have its pinned checksum
     * @throws UnsupportedSchematron when a source file cannot be compiled
     * @throws PartialInstall when a file could not be moved into place and restoring the previous
     *                        rules failed as well
     * @throws InvalidArgumentException|RuntimeException when a file cannot be read or downloaded,
     *                                                   or the target folder cannot be written
     */
    public function install(
        string $targetDir,
        array $sources,
        string $baseUrl,
        ?string $sourceDir,
        string $origin,
        string $command,
        array $verbatim = [],
        string $verbatimDir = '',
        string $release = '',
        string $family = '',
    ): array {
        if ($verbatim !== [] && $verbatimDir === '') {
            throw new InvalidArgumentException('Verbatim files need a folder to be copied into.');
        }

        /** @var array<string, array{string, string}> $pinned path => [contents, SHA-256] */
        $pinned = [];
        foreach ($sources as $source) {
            $pinned[$source->file] = [$this->fetch($baseUrl, $sourceDir, $source->file, $family), $source->sha256];
            foreach ($source->companions as $name => $sha256) {
                $path = self::sibling($source->file, $name);
                $pinned[$path] = [$this->fetch($baseUrl, $sourceDir, $path, $family), $sha256];
            }
        }
        foreach ($verbatim as $path => $sha256) {
            $pinned[$path] = [$this->fetch($baseUrl, $sourceDir, $path, $family), $sha256];
        }

        self::verify($pinned, $release);

        if (! is_dir($targetDir) && ! @mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
            throw new RuntimeException("Cannot create the rules folder \"{$targetDir}\".");
        }

        // Each source is compiled straight into a temporary file, so only one compiled rule set is
        // held in memory; the files replace the installed ones only once all of them have compiled.
        $temporary = [];
        $tree = null;
        try {
            foreach ($sources as $index => $source) {
                $path = self::path($targetDir, $source).'.tmp-'.bin2hex(random_bytes(6));
                $temporary[$index] = $path;
                CompiledRuleSetWriter::write(
                    $this->compile($targetDir, $source, $pinned),
                    $path,
                    sprintf($origin, $source->file),
                    $command,
                );
                unset($pinned[$source->file]);
            }

            if ($verbatim !== []) {
                // Assigned before writing, so the finally block removes a tree that failed halfway.
                $tree = rtrim($targetDir, '/\\').'/'.$verbatimDir.'.tmp-'.bin2hex(random_bytes(6));
                self::writeTree($tree, $verbatim, $pinned);
            }
            unset($pinned);

            $written = $this->replaceAll($targetDir, $sources, $temporary, $verbatimDir, $tree);
        } finally {
            foreach ($temporary as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            if ($tree !== null && is_dir($tree)) {
                self::removeTree($tree);
            }
        }

        return $written;
    }

    /**
     * Copies pinned files, each from its own URL, unchanged into "<targetDir>/<treeDir>/<path>".
     * Every file is fetched and checked against its checksum before anything is written; the new
     * tree is then swapped in by renaming, and if that fails the previous tree is restored.
     *
     * @param  array<string, array{url: string, sha256: string}>  $files  local path => download URL
     *                                                                    and pinned SHA-256
     * @param  ?string  $sourceDir  a folder holding the files at their local paths, for air-gapped
     *                              machines; null downloads each file from its URL
     * @param  string  $treeDir  folder below $targetDir that receives the files
     * @param  string  $release  the publication the files belong to, for checksum errors
     * @param  string  $family  short name of the files, for read errors
     * @return list<string> written file paths, in the order of $files
     *
     * @throws RuleSourceMismatch when a file does not have its pinned checksum
     * @throws PartialInstall when the new tree could not be moved into place and restoring the
     *                        previous one failed as well
     * @throws InvalidArgumentException|RuntimeException when a local path is unsafe, a file cannot
     *                                                   be read or downloaded, or the target folder
     *                                                   cannot be written
     */
    public function installFiles(string $targetDir, array $files, ?string $sourceDir, string $treeDir, string $release, string $family): array
    {
        if ($files === [] || $treeDir === '' || ! self::isSafePath($treeDir)) {
            throw new InvalidArgumentException('Pinned files need at least one file and a folder to be copied into.');
        }
        foreach (array_keys($files) as $path) {
            if (! self::isSafePath($path)) {
                throw new InvalidArgumentException("Unsafe local path for a pinned file: \"{$path}\".");
            }
        }

        /** @var array<string, array{string, string}> $pinned path => [contents, SHA-256] */
        $pinned = [];
        foreach ($files as $path => $file) {
            $pinned[$path] = [
                $sourceDir === null ? $this->downloader->get($file['url']) : $this->fetch('', $sourceDir, $path, $family),
                $file['sha256'],
            ];
        }
        self::verify($pinned, $release);

        if (! is_dir($targetDir) && ! @mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
            throw new RuntimeException("Cannot create the rules folder \"{$targetDir}\".");
        }

        $installed = rtrim($targetDir, '/\\').'/'.$treeDir;
        // Assigned before writing, so the finally block removes a tree that failed halfway.
        $tree = $installed.'.tmp-'.bin2hex(random_bytes(6));
        try {
            $verbatim = array_map(static fn (array $file): string => $file['sha256'], $files);
            self::writeTree($tree, $verbatim, $pinned);
            unset($pinned);
            $backup = $this->swapTree($tree, $installed);
        } finally {
            if (is_dir($tree)) {
                self::removeTree($tree);
            }
        }
        if ($backup !== null) {
            self::removeTree($backup);
        }

        return array_map(static fn (string $path): string => $installed.'/'.$path, array_keys($files));
    }

    /**
     * Path of the compiled file of one source in a rules folder.
     */
    public static function path(string $dir, RuleSource $source): string
    {
        return rtrim($dir, '/\\').'/'.$source->id.'-'.$source->version.'.php';
    }

    /**
     * Compiles one source; its companions are written to a temporary folder that document()
     * resolves against, and removed afterwards.
     *
     * @param  array<string, array{string, string}>  $pinned
     * @return CompiledRuleSet
     */
    private function compile(string $targetDir, RuleSource $source, array $pinned): array
    {
        $contents = $pinned[$source->file][0];
        if ($source->companions === []) {
            return Compiler::compileString($contents, $source->file, $source->id, $source->version, null, $source->options);
        }

        $baseDir = rtrim($targetDir, '/\\').'/'.$source->id.'.src-'.bin2hex(random_bytes(6));
        try {
            if (! @mkdir($baseDir, 0775)) {
                throw new RuntimeException("Cannot create the temporary folder \"{$baseDir}\".");
            }
            foreach (array_keys($source->companions) as $name) {
                if (@file_put_contents($baseDir.'/'.$name, $pinned[self::sibling($source->file, $name)][0]) === false) {
                    throw new RuntimeException("Cannot write \"{$baseDir}/{$name}\".");
                }
            }

            return Compiler::compileString($contents, $source->file, $source->id, $source->version, $baseDir, $source->options);
        } finally {
            self::removeTree($baseDir);
        }
    }

    /**
     * Writes the verbatim files into a temporary folder next to their final place.
     *
     * @param  array<string, string>  $verbatim
     * @param  array<string, array{string, string}>  $pinned
     */
    private static function writeTree(string $tree, array $verbatim, array $pinned): void
    {
        foreach (array_keys($verbatim) as $path) {
            $target = $tree.'/'.$path;
            if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
                throw new RuntimeException('Cannot create the folder "'.dirname($target).'".');
            }
            if (@file_put_contents($target, $pinned[$path][0]) === false) {
                throw new RuntimeException("Cannot write \"{$target}\".");
            }
        }
    }

    /**
     * Swaps the verbatim tree in first, then moves the compiled files into place; if the compiled
     * files cannot be moved, the previous tree is put back as well.
     *
     * @param  list<RuleSource>  $sources
     * @param  array<int, string>  $temporary
     * @return list<string>
     */
    private function replaceAll(string $targetDir, array $sources, array $temporary, string $verbatimDir, ?string $tree): array
    {
        if ($tree === null) {
            return $this->replace($targetDir, $sources, $temporary);
        }

        $installed = rtrim($targetDir, '/\\').'/'.$verbatimDir;
        $backup = $this->swapTree($tree, $installed);
        try {
            $written = $this->replace($targetDir, $sources, $temporary);
        } catch (PartialInstall $exception) {
            $this->unswapTree($installed, $backup, $tree);

            throw $exception;
        } catch (RuntimeException $exception) {
            if (! $this->unswapTree($installed, $backup, $tree)) {
                throw new PartialInstall(
                    $exception->getMessage().' Restoring the previous files failed; this folder now holds the new files or is missing: '.$installed.'.',
                    [$installed],
                    $exception,
                    $backup === null ? [] : [$backup],
                );
            }

            throw $exception;
        }

        if ($backup !== null) {
            self::removeTree($backup);
        }

        return $written;
    }

    /**
     * Renames the installed tree to a backup and the new tree into its place.
     *
     * @return ?string the backup, or null when no tree was installed
     *
     * @throws RuntimeException when the new tree could not be moved and the previous one was restored
     * @throws PartialInstall when restoring the previous tree failed as well
     */
    private function swapTree(string $tree, string $installed): ?string
    {
        $backup = null;
        if (file_exists($installed)) {
            $backup = $installed.'.bak-'.bin2hex(random_bytes(6));
            if (! ($this->rename)($installed, $backup)) {
                throw new RuntimeException("Cannot move the installed folder \"{$installed}\" aside; nothing was changed.");
            }
        }
        if (($this->rename)($tree, $installed)) {
            return $backup;
        }

        $message = "Cannot move the new files to \"{$installed}\".";
        if ($backup !== null && ! ($this->rename)($backup, $installed)) {
            throw new PartialInstall(
                $message.' Restoring the previous files failed; this folder is missing: '.$installed.'. The previous files are left in place at: '.$backup.'.',
                [$installed],
                null,
                [$backup],
            );
        }

        throw new RuntimeException($message.' The previous rules were restored; nothing was changed.');
    }

    /**
     * Moves the new tree back out and the backup back into place; true when that succeeded.
     */
    private function unswapTree(string $installed, ?string $backup, string $tree): bool
    {
        if (! ($this->rename)($installed, $tree)) {
            return false;
        }

        return $backup === null || ($this->rename)($backup, $installed);
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
     * @param  list<RuleSource>  $sources
     * @param  array<int, string>  $temporary  compiled file path per source index
     * @return list<string> written file paths
     *
     * @throws RuntimeException when a file could not be moved and the previous rules were restored
     * @throws PartialInstall when a file could not be moved and restoring the previous rules failed
     */
    private function replace(string $targetDir, array $sources, array $temporary): array
    {
        $targets = [];
        $backups = [];
        // Every backup name is chosen before anything is touched, so that a failure to generate
        // one cannot strike halfway through.
        foreach ($sources as $index => $source) {
            $targets[$index] = self::path($targetDir, $source);
            if (is_file($targets[$index])) {
                $backups[$index] = $targets[$index].'.bak-'.bin2hex(random_bytes(6));
            }
        }

        /** @var array<int, string> $made backup path per source index, for the backups that exist */
        $made = [];
        /** @var list<string> $written */
        $written = [];
        try {
            foreach ($backups as $index => $backup) {
                if (! @link($targets[$index], $backup) && ! @copy($targets[$index], $backup)) {
                    throw new RuntimeException("Cannot back up the installed rule set \"{$targets[$index]}\".");
                }
                $made[$index] = $backup;
            }
            foreach ($targets as $index => $target) {
                if (! ($this->rename)($temporary[$index], $target)) {
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
     * @param  array<int, string>  $targets  installed path per source index
     * @param  array<int, string>  $backups  backup path per source index
     * @param  list<string>  $written  targets that already hold a new file
     * @return array{list<string>, list<string>} the targets that could not be restored, and the
     *                                           backups left behind
     */
    private function restore(array $targets, array $backups, array $written): array
    {
        $failed = [];
        $leftover = [];
        foreach ($targets as $index => $target) {
            $replaced = in_array($target, $written, true);
            $backup = $backups[$index] ?? null;
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

    /**
     * Reads a file from the source folder, or downloads it when there is none.
     */
    private function fetch(string $baseUrl, ?string $sourceDir, string $path, string $family): string
    {
        if ($sourceDir === null) {
            return $this->downloader->get($baseUrl.implode('/', array_map(rawurlencode(...), explode('/', $path))));
        }

        $file = rtrim($sourceDir, '/\\').'/'.$path;
        $contents = is_file($file) ? @file_get_contents($file) : false;
        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot read the %srule source "%s".', $family === '' ? '' : $family.' ', $file));
        }

        return $contents;
    }

    /**
     * Checks every fetched file against its pinned checksum.
     *
     * @param  array<string, array{string, string}>  $pinned  path => [contents, SHA-256]
     *
     * @throws RuleSourceMismatch naming the first file that does not match
     */
    private static function verify(array $pinned, string $release): void
    {
        foreach ($pinned as $path => [$contents, $sha256]) {
            $actual = hash('sha256', $contents);
            if ($actual !== $sha256) {
                throw new RuleSourceMismatch(sprintf(
                    '%s is not the published %s file: expected SHA-256 %s, got %s.',
                    $path,
                    $release === '' ? 'pinned' : $release,
                    $sha256,
                    $actual,
                ));
            }
        }
    }

    /**
     * True for a relative path without empty, "." or ".." segments and without backslashes.
     */
    private static function isSafePath(string $path): bool
    {
        if ($path === '' || str_contains($path, '\\')) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * Path of a file stored in the same folder as another.
     */
    private static function sibling(string $path, string $name): string
    {
        $dir = dirname($path);

        return $dir === '.' ? $name : $dir.'/'.$name;
    }

    private static function removeTree(string $dir): void
    {
        if (! is_dir($dir) || is_link($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) && ! is_link($path) ? self::removeTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
