<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Spain;

use InvalidArgumentException;
use RuntimeException;

/**
 * The official files behind VERI*FACTU validation: the AEAT "tikeV1.0" web-service schemas, WSDL
 * and error catalogue, the W3C XML Signature schema they import, and the AEAT example record.
 *
 * AEAT publishes no licence that allows redistribution, so none of these files is committed or
 * shipped. The manifest in resources/spain lists, per file, its SHA-256 sum, its kind, its local
 * name and its absolute download URL; the files are downloaded on the user's machine and verified
 * against it. Kind "runtime" is installed for validation, kind "example" is used by tests only.
 * An example whose URL reads "zip:<name>" is taken from the listed archive of that name.
 */
final class VerifactuSources
{
    public const RELEASE = 'tikeV1.0';

    private const MANIFEST = __DIR__.'/../../../resources/spain/verifactu-'.self::RELEASE.'.sha256';

    private const KINDS = ['runtime', 'example'];

    /** @var array<string, array<string, array{url: string, sha256: string}>>|null parsed manifest */
    private static ?array $manifest = null;

    /**
     * Manifest entries of one kind, in manifest order: local name => URL and SHA-256.
     *
     * @param  string  $kind  "runtime" or "example"
     * @return array<string, array{url: string, sha256: string}>
     */
    public static function files(string $kind): array
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("Unknown VERI*FACTU file kind: {$kind}");
        }

        return self::manifest()[$kind];
    }

    /**
     * Parses manifest text: one "<sha256>  <kind>  <local name>  <url>" line per file. The URL is
     * an absolute https:// URL, or "zip:<name>" naming an archive listed on an earlier line; local
     * names are single path segments.
     *
     * @return array<string, array<string, array{url: string, sha256: string}>> kind => name => entry
     *
     * @throws RuntimeException when a line does not follow that form
     *
     * @internal
     */
    public static function parseManifest(string $contents): array
    {
        $manifest = array_fill_keys(self::KINDS, []);
        $names = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            if (preg_match('/^([0-9a-f]{64})  (runtime|example)  ([A-Za-z0-9][A-Za-z0-9._-]*)  (https:\/\/[^\s\/]+\/\S+|zip:\S+)$/', $line, $match) !== 1
                || str_contains($match[3], '..')
                || isset($names[$match[3]])
                || (str_starts_with($match[4], 'zip:') && ! self::isArchive(substr($match[4], 4), $manifest['example']))) {
                throw new RuntimeException('Malformed line in the VERI*FACTU manifest: '.$line);
            }
            $names[$match[3]] = true;
            $manifest[$match[2]][$match[3]] = ['url' => $match[4], 'sha256' => $match[1]];
        }

        return $manifest;
    }

    /**
     * @param  array<string, array{url: string, sha256: string}>  $examples
     */
    private static function isArchive(string $name, array $examples): bool
    {
        return str_ends_with($name, '.zip') && isset($examples[$name]) && str_starts_with($examples[$name]['url'], 'https://');
    }

    /**
     * @return array<string, array<string, array{url: string, sha256: string}>>
     */
    private static function manifest(): array
    {
        if (self::$manifest === null) {
            $contents = is_file(self::MANIFEST) ? @file_get_contents(self::MANIFEST) : false;
            if ($contents === false) {
                throw new RuntimeException('Cannot read the VERI*FACTU manifest '.self::MANIFEST);
            }
            self::$manifest = self::parseManifest($contents);
        }

        return self::$manifest;
    }
}
