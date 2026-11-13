<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\FacturX;

use RuntimeException;

/**
 * The official artefacts behind the Factur-X / ZUGFeRD validation: the ZF_250 schema, Schematron
 * and XSLT folders of the Mustang project (Factur-X 1.09.2), pinned to one commit.
 *
 * FeRD places the ZUGFeRD schemas and Schematron under Apache-2.0, and Mustang redistributes them
 * under the same licence. The example files (FeRD ZF252_Beispiele and the other Mustang test PDFs)
 * carry no licence statement of their own: they are only downloaded for the test suite and never
 * committed, and their SHA-256 sums are kept in a manifest that examples() reads.
 */
final class FacturXSources
{
    public const VERSION = '1.09.2';

    public const MUSTANG_COMMIT = 'c766bdd2f1b978c329157affc020eba4bcf51688';

    /** Raw file URL; {commit} is MUSTANG_COMMIT, {path} the URL-encoded repository path. */
    public const RAW_URL = 'https://raw.githubusercontent.com/ZUGFeRD/mustangproject/{commit}/{path}';

    /** Folder of the local copy => repository folder at MUSTANG_COMMIT. */
    public const FOLDERS = [
        'schema' => 'validator/src/main/resources/schema/ZF_250',
        'schematron' => 'validator/src/main/resources/schematron/ZF_250',
        'xslt' => 'validator/src/main/resources/xslt/ZF_250',
    ];

    /**
     * Aggregate SHA-256 per folder: the SHA-256 of the "<sum>  ./<relative path>" lines of all its
     * files in byte order, as printed by `find . -type f -print0 | LC_ALL=C sort -z | xargs -0
     * shasum -a 256 | shasum -a 256` inside the repository folder.
     */
    public const AGGREGATES = [
        'schema' => 'a66ac39854336a759c27a9289319146c4889c1e4acfb5d3e33ea1cc0bcfeba55',
        'schematron' => '84b15df7dbddde67134d073dcc0bfc69ed96b11c97997da5f542f44039899184',
        'xslt' => '2f81544c9b84b19b0fb53f17c0e514ebe22db78fd99a542055c7bfbd33c3459f',
    ];

    /** Repository folders of the FeRD example files (ZUGFeRD 2.5.2), downloaded for the test suite only. */
    public const FERD_EXAMPLES = ['library/src/test/resources/ZF252_Beispiele/'];

    /** Licence files at the repository root that travel with the copy, with their SHA-256 sums. */
    public const LICENCE_FILES = [
        'LICENSE' => 'ba98dd76bda1e82034791438bb931145f85e5db3975852a015344ad5990fa21b',
        'NOTICE' => '9225d07c21aa4a404ac53372f04c9945b8e1e0d47f466036d27a09527373c6cf',
    ];

    /**
     * Raw download URL of a repository path at MUSTANG_COMMIT; each path segment is URL-encoded,
     * because the example paths contain spaces and non-ASCII characters.
     */
    public static function url(string $path): string
    {
        $encoded = implode('/', array_map(rawurlencode(...), explode('/', $path)));

        return strtr(self::RAW_URL, ['{commit}' => self::MUSTANG_COMMIT, '{path}' => $encoded]);
    }

    /**
     * The example files listed in a manifest in sha256sum format ("<sum>  <repository path>" per
     * line): repository path => SHA-256 sum, in manifest order.
     *
     * @return array<string, string>
     */
    public static function examples(string $manifest): array
    {
        $lines = is_file($manifest) ? file($manifest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
        if ($lines === false) {
            throw new RuntimeException("Cannot read {$manifest}");
        }

        $examples = [];
        foreach ($lines as $line) {
            if (preg_match('/^([0-9a-f]{64})  (.+)$/', $line, $match) !== 1) {
                throw new RuntimeException("Malformed line in {$manifest}: {$line}");
            }
            $examples[$match[2]] = $match[1];
        }

        return $examples;
    }
}
