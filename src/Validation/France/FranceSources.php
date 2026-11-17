<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\France;

use InvalidArgumentException;
use RuntimeException;

/**
 * The official artefacts behind the French e-invoicing format rules: the FNFE-MPE "France_RFE"
 * repository (release 1.4.0.04), pinned to one commit.
 *
 * The repository carries no licence that allows redistribution, so none of its files are committed
 * or shipped. The manifest in resources/france lists only repository paths and SHA-256 sums; the
 * files themselves are downloaded and verified against it.
 */
final class FranceSources
{
    public const VERSION = '1.4.0.04';

    public const COMMIT = '97ba0f3d4ef2abed5780e9490d5c3a2be832ab1d';

    /** Raw file URL base; append the repository path. */
    public const BASE_URL = 'https://raw.githubusercontent.com/fnfempe/France_RFE/'.self::COMMIT.'/';

    private const MANIFEST = __DIR__.'/../../../resources/france/france-rfe-'.self::VERSION.'.sha256';

    private const KINDS = ['rules', 'schema', 'oracle', 'example'];

    private const SCHEMA_ROOTS = [
        'cii' => 'FNFE_RFE_INVOICE/CII/1xsd-CII_D22B_uncoupled',
        'ubl' => 'FNFE_RFE_INVOICE/UBL/1xsd_UBL2.1',
        'cdar' => 'FNFE_RFE_INVOICE/CDAR/1xsd-CDAR_D22B_uncoupled',
        'flux10' => 'FNFE_RFE_INVOICE/Flux10/xsd',
    ];

    /**
     * The Schematron sources the French rule sets are compiled from.
     *
     * @return array<string, array{file: string, sha256: string, id: string, companions: array<string, string>}>
     */
    public static function ruleSources(): array
    {
        return [
            'flux2-cii' => [
                'file' => 'FNFE_RFE_INVOICE/CII/EN16931/schematron/BR-FR-Flux2-Schematron-CII.sch',
                'sha256' => 'f31492eaa6ee5e0b8870128131a1ea2fce21a980d09897bcc386bc83a5e22cc5',
                'id' => 'fr-flux2-cii',
                'companions' => [],
            ],
            'flux2-ubl' => [
                'file' => 'FNFE_RFE_INVOICE/UBL/EN16931/schematron/BR-FR-Flux2-Schematron-UBL.sch',
                'sha256' => 'b680823df7a6704f7e97ed6125f30ce33ca1d8ca7391e0572f8fc9a77fbc5eaf',
                'id' => 'fr-flux2-ubl',
                'companions' => [],
            ],
            'extended-ctc-fr-cii' => [
                'file' => 'FNFE_RFE_INVOICE/CII/EXTENDED-CTC-FR/schematron/EXTENDED-CTC-FR-CII.sch',
                'sha256' => '768cd0610d163df37786984f9b9e52e466d1f987a427964161a7ab301ad41c7b',
                'id' => 'fr-extended-ctc-fr-cii',
                'companions' => [],
            ],
            'extended-ctc-fr-ubl' => [
                'file' => 'FNFE_RFE_INVOICE/UBL/EXTENDED-CTC-FR/schematron/EXTENDED-CTC-FR-UBL.sch',
                'sha256' => 'ba8f8f2611b950519cc1eb88698dec2b1dbf91f7495f55d4b682540f99e6077e',
                'id' => 'fr-extended-ctc-fr-ubl',
                'companions' => [],
            ],
            'cdar' => [
                'file' => 'FNFE_RFE_INVOICE/CDAR/schematron/BR-FR-CDV-Schematron-CDAR.sch',
                'sha256' => '3530c14fb95e0e701faf10beec38fedee6ed324dc2761f40bc855a9a060b090c',
                'id' => 'fr-cdar',
                'companions' => [],
            ],
            'flux10' => [
                'file' => 'FNFE_RFE_INVOICE/Flux10/Flux10.sch',
                'sha256' => 'a7670ca8b522b9b439099b0bd8ac9408ceb6bebefd8fb724350e5a5cc8b07127',
                'id' => 'fr-flux10',
                'companions' => [],
            ],
            'facturx-extended' => [
                'file' => 'FNFE_RFE_INVOICE/Factur-X/EXTENDED/schematron/FACTUR-X_EXTENDED.sch',
                'sha256' => 'fc1f7cfc1dae24f53be07ef142b2e2e64e4ac6b1b54d4d4b9d1c89815e74c800',
                'id' => 'fr-facturx-extended',
                'companions' => [
                    'FACTUR-X_EXTENDED_codedb.xml' => '6e23aedbba0e3b32b3b1211d145186193215f56f755201555306c42327e75fbc',
                ],
            ],
        ];
    }

    /**
     * Manifest entries of one kind: repository path => SHA-256, sorted by path.
     *
     * @param  string  $kind  one of "rules", "schema", "oracle", "example"
     * @return array<string, string>
     */
    public static function files(string $kind): array
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("Unknown France_RFE file kind: {$kind}");
        }

        return self::manifest()[$kind];
    }

    /**
     * Every manifest entry regardless of kind: repository path => SHA-256.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $all = [];
        foreach (self::manifest() as $files) {
            $all += $files;
        }

        return $all;
    }

    /** Repository path of the XSD tree "cii", "ubl", "cdar" or "flux10". */
    public static function schemaRoot(string $tree): string
    {
        return self::SCHEMA_ROOTS[$tree] ?? throw new InvalidArgumentException("Unknown France_RFE schema tree: {$tree}");
    }

    /**
     * Raw download URL of a repository path at the pinned commit; each path segment is URL-encoded.
     */
    public static function url(string $path): string
    {
        return self::BASE_URL.implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    /**
     * @return array<string, array<string, string>> kind => path => SHA-256
     */
    private static function manifest(): array
    {
        $lines = is_file(self::MANIFEST) ? file(self::MANIFEST, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
        if ($lines === false) {
            throw new RuntimeException('Cannot read the France_RFE manifest '.self::MANIFEST);
        }

        $manifest = array_fill_keys(self::KINDS, []);
        foreach ($lines as $line) {
            if (preg_match('/^([0-9a-f]{64})  (rules|schema|oracle|example)  (FNFE_RFE_INVOICE\/\S.*)$/', $line, $match) !== 1
                || str_contains($match[3], '..') || str_contains($match[3], '\\')) {
                throw new RuntimeException('Malformed line in the France_RFE manifest: '.$line);
            }
            $manifest[$match[2]][$match[3]] = $match[1];
        }

        return $manifest;
    }
}
