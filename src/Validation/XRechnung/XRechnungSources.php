<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\XRechnung;

/**
 * The official artefacts behind the XRechnung validation: the KoSIT Schematron, the KoSIT
 * validator configuration (scenarios, schemas), the KoSIT test suite, the CEN rules the KoSIT
 * configuration is built on, and the KoSIT validator itself, each pinned by release URL and
 * SHA-256.
 *
 * All of it is Apache-2.0 (KoSIT) or EUPL-1.2 (CEN). The validator jar is only listed here, as
 * the reference implementation for the conformance run; it is never downloaded by the fetch script
 * and never committed.
 */
final class XRechnungSources
{
    public const SCHEMATRON_VERSION = '2.6.0';

    public const CONFIGURATION_VERSION = '2026-08-31';

    public const XRECHNUNG_VERSION = '3.0.2';

    public const CEN_VERSION = '1.3.16';

    public const VALIDATOR_VERSION = '1.6.3';

    /**
     * @return array<string, array{url: string, sha256: string}> keyed 'schematron', 'configuration', 'testsuite', 'schematron-source', 'configuration-source', 'cen-ubl', 'cen-cii', 'validator-jar'
     */
    public static function archives(): array
    {
        return [
            'schematron' => [
                'url' => 'https://github.com/itplr-kosit/xrechnung-schematron/releases/download/v2.6.0/xrechnung-3.0.2-schematron-2.6.0.zip',
                'sha256' => 'ca5e07afd04e72cd283d581590ffff3a15f4aac9d2f40c993345d78e67ea22b4',
            ],
            'configuration' => [
                'url' => 'https://github.com/itplr-kosit/validator-configuration-xrechnung/releases/download/v2026-08-31/xrechnung-3.0.2-validator-configuration-2026-08-31.zip',
                'sha256' => '2530cd107c414511c5d0462ec10f886910395abfca820db82e83d70bf01221a8',
            ],
            'testsuite' => [
                'url' => 'https://github.com/itplr-kosit/xrechnung-testsuite/releases/download/v2026-08-31/xrechnung-3.0.2-testsuite-2026-08-31.zip',
                'sha256' => '1e81ef7563e0fa04c0e0b0631318a7e0843a3c0beb57d83ef95ac0c4a095e3d7',
            ],
            'schematron-source' => [
                'url' => 'https://codeload.github.com/itplr-kosit/xrechnung-schematron/tar.gz/refs/tags/v2.6.0',
                'sha256' => '895872383bd380c9edcbe4c4ff973645df68102e3a797b84cd6b80280a3dd7f9',
            ],
            'configuration-source' => [
                'url' => 'https://codeload.github.com/itplr-kosit/validator-configuration-xrechnung/tar.gz/refs/tags/v2026-08-31',
                'sha256' => 'e4a8f51f2c691a612fcf76f3ccadc5ea933da958e196dc732b35aa97623be9e1',
            ],
            'cen-ubl' => [
                'url' => 'https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/download/validation-1.3.16/en16931-ubl-1.3.16.zip',
                'sha256' => 'bafada015efbc5248bf5e05ad2191e1d9833ef96e9dd5f4bce420a747342da85',
            ],
            'cen-cii' => [
                'url' => 'https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/download/validation-1.3.16/en16931-cii-1.3.16.zip',
                'sha256' => '1cd53cb8a84d38aedc82c0caede217da983a7934dd663f793a092fd66443c561',
            ],
            'validator-jar' => [
                'url' => 'https://github.com/itplr-kosit/validator/releases/download/v1.6.3/validator-1.6.3-standalone.jar',
                'sha256' => '799e64befca97d4080e03608c80b85dd5a5ecc5f4ae4f35d1116ec2855b9a7c9',
            ],
        ];
    }
}
