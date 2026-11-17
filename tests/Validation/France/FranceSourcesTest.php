<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\France;

use IntrAgile\EInvoicing\Validation\France\FranceSources;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class FranceSourcesTest extends TestCase
{
    public function test_rule_sources_are_pinned(): void
    {
        self::assertSame('1.4.0.04', FranceSources::VERSION);
        self::assertSame('97ba0f3d4ef2abed5780e9490d5c3a2be832ab1d', FranceSources::COMMIT);

        self::assertSame([
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
        ], FranceSources::ruleSources());
    }

    public function test_manifest_kinds_are_complete(): void
    {
        self::assertCount(8, FranceSources::files('rules'));
        self::assertCount(163, FranceSources::files('schema'));
        self::assertCount(8, FranceSources::files('oracle'));

        $examples = FranceSources::files('example');
        self::assertGreaterThanOrEqual(100, count(array_filter(array_keys($examples), static fn (string $path): bool => str_ends_with($path, '.xml'))));
        self::assertGreaterThan(100, count($examples));

        foreach (['rules', 'schema', 'oracle', 'example'] as $kind) {
            foreach (FranceSources::files($kind) as $path => $sha256) {
                self::assertStringStartsWith('FNFE_RFE_INVOICE/', $path);
                self::assertStringNotContainsString('..', $path, $path);
                self::assertStringNotContainsString('\\', $path, $path);
                self::assertFalse(str_starts_with($path, '/'), $path);
                self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $sha256, $path);
            }
        }
    }

    public function test_rule_sources_appear_in_manifest_with_same_hash(): void
    {
        $rules = FranceSources::files('rules');

        foreach (FranceSources::ruleSources() as $key => $source) {
            self::assertSame($source['sha256'], $rules[$source['file']] ?? null, $key);
            foreach ($source['companions'] as $name => $sha256) {
                self::assertSame($sha256, $rules[dirname($source['file']).'/'.$name] ?? null, $key.' '.$name);
            }
        }
    }

    public function test_schema_roots_and_unknown_inputs(): void
    {
        self::assertSame('FNFE_RFE_INVOICE/CII/1xsd-CII_D22B_uncoupled', FranceSources::schemaRoot('cii'));
        self::assertSame('FNFE_RFE_INVOICE/UBL/1xsd_UBL2.1', FranceSources::schemaRoot('ubl'));
        self::assertSame('FNFE_RFE_INVOICE/CDAR/1xsd-CDAR_D22B_uncoupled', FranceSources::schemaRoot('cdar'));
        self::assertSame('FNFE_RFE_INVOICE/Flux10/xsd', FranceSources::schemaRoot('flux10'));

        foreach (FranceSources::files('schema') as $path => $sha256) {
            $inside = false;
            foreach (['cii', 'ubl', 'cdar', 'flux10'] as $tree) {
                $inside = $inside || str_starts_with($path, FranceSources::schemaRoot($tree).'/');
            }
            self::assertTrue($inside, $path);
        }

        $this->expectException(InvalidArgumentException::class);
        FranceSources::schemaRoot('nope');
    }

    /**
     * The manifest is parsed once per process: after the first call, a planted cache value is
     * returned as is, which shows the file is not read again.
     */
    public function test_manifest_is_parsed_once(): void
    {
        $first = FranceSources::files('schema');
        $cache = new ReflectionProperty(FranceSources::class, 'manifest');
        $original = $cache->getValue();
        self::assertIsArray($original);
        self::assertSame($first, $original['schema'] ?? null);

        $planted = ['rules' => [], 'schema' => ['FNFE_RFE_INVOICE/planted.xsd' => str_repeat('0', 64)], 'oracle' => [], 'example' => []];
        $cache->setValue(null, $planted);
        try {
            self::assertSame($planted['schema'], FranceSources::files('schema'));
        } finally {
            $cache->setValue(null, $original);
        }
        self::assertSame($first, FranceSources::files('schema'));
    }

    public function test_unknown_kind_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FranceSources::files('nope');
    }
}
