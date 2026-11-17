<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\France;

use IntrAgile\EInvoicing\Validation\France\FranceSources;
use PHPUnit\Framework\TestCase;

/**
 * The Flux 10 schemas of France_RFE are byte-identical to the e-reporting schemas the DGFiP
 * publishes in its external specifications (DSE v3.2), so validating against the pinned France_RFE
 * files is validating against the administration's own schemas.
 */
final class Flux10SchemaIdentityTest extends TestCase
{
    /** SHA-256 of the XML Schemas of the DGFiP DSE v3.2 e-reporting package. */
    private const DGFIP_DSE_3_2 = [
        'ereporting.xsd' => 'b6e355094f08e9d90dd6bfaed42319ef490f4dc1a11b1b6b7473b9c35b27d546',
        'parametre.xsd' => '5971f3f2080e7057a4034121703ebdf403dda0d8f0e41e33987dc432c8671bad',
        'payment.xsd' => 'bf41d73aad46efdf8a26699f6dc7777f54051d8795f85faf1272cd2095b9a048',
        'report.xsd' => '059cea2615bca67b07df167d3e5b65d8658477f8a12a8723d3fc227f35cb8403',
        'transaction.xsd' => 'ab5139608ec5754227c37b471cdec3b7434dcb21340d9b8c8d98bfc60e6ec841',
    ];

    public function test_flux10_schemas_are_the_dgfip_dse_3_2_files(): void
    {
        $prefix = FranceSources::schemaRoot('flux10').'/';
        $pinned = [];
        foreach (FranceSources::files('schema') as $path => $sha256) {
            if (str_starts_with($path, $prefix)) {
                $pinned[substr($path, strlen($prefix))] = $sha256;
            }
        }
        ksort($pinned, SORT_STRING);

        self::assertSame(self::DGFIP_DSE_3_2, $pinned);
    }
}
