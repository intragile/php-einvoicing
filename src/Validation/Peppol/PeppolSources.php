<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Peppol;

/**
 * The official Peppol BIS Billing 3.0 Schematron files, pinned by release tag and SHA-256: the
 * invoice rules and the rules for the Peppol Business Message Envelope (SBDH) around an invoice.
 *
 * OpenPeppol publishes no licence for these files, so they are never redistributed: they are
 * downloaded from the official repository and verified against the checksums below before use.
 */
final class PeppolSources
{
    public const VERSION = '3.0.22';

    public const BASE_URL = 'https://raw.githubusercontent.com/OpenPEPPOL/peppol-bis-invoice-3/3.0.22/rules/sch/';

    /**
     * The five rule files: OpenPeppol's copy of the CEN rules and the Peppol rules, per syntax,
     * then the envelope rules.
     *
     * @return array<string, array{file: string, sha256: string, id: string}> keyed 'cen-ubl', 'cen-cii', 'peppol-ubl', 'peppol-cii', 'envelope'
     */
    public static function all(): array
    {
        return [
            'cen-ubl' => [
                'file' => 'CEN-EN16931-UBL.sch',
                'sha256' => '268d4f7a2688676695e6c69cba6fba69a6802604fee12cb544a6b30ff09555a3',
                'id' => 'peppol-cen-ubl',
            ],
            'cen-cii' => [
                'file' => 'CEN-EN16931-CII.sch',
                'sha256' => '54e0dc6d06cd7f17d268bb9696ff56f58d386ee28961c9bbef0a56718c400c89',
                'id' => 'peppol-cen-cii',
            ],
            'peppol-ubl' => [
                'file' => 'PEPPOL-EN16931-UBL.sch',
                'sha256' => 'f637444fab75a6da7e5004f94a6de8e71383ef5658dafe6c68925103b17ea148',
                'id' => 'peppol-ubl',
            ],
            'peppol-cii' => [
                'file' => 'PEPPOL-EN16931-CII.sch',
                'sha256' => '7678c3cd4a219095ef5d4f2ece17e5d447eb92655e57a7d52290b35acfdc9dd8',
                'id' => 'peppol-cii',
            ],
            'envelope' => [
                'file' => 'PeppolBIS-Envelope-Rules.sch',
                'sha256' => '96648791fb824b6b58ddae9cc5575e8298e0061fc43dd0934a0b1459396d76f9',
                'id' => 'peppol-envelope',
            ],
        ];
    }
}
