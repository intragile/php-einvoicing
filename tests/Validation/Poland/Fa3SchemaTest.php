<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Poland;

use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use IntrAgile\EInvoicing\Validation\Poland\Fa3Schema;
use PHPUnit\Framework\TestCase;

final class Fa3SchemaTest extends TestCase
{
    /**
     * The bundle is byte-identical to the Ministry of Finance files pinned in SOURCE.md, so no
     * schema file was edited to make it resolve offline.
     */
    public function test_bundled_schemas_match_pinned_hashes(): void
    {
        $dir = dirname(Fa3Schema::path());
        $files = Fa3Schema::files();

        self::assertSame([
            'schemat_FA(3)_v1-0E.xsd' => 'b646b6b525f51adf1bb2545f111fc8ca6e7aa6dd2f98948f1667d3695c06d958',
            'StrukturyDanych_v10-0E.xsd' => 'cb08348374598e1e716e086c40d740390fb9e1bfa3aba1f4ec4cba0e1ef6d60f',
            'ElementarneTypyDanych_v10-0E.xsd' => '8daf4d3771de200b26b697294cc906a2add3de9acfbbd97f4b1bd4fc0e5ecb2f',
            'KodyKrajow_v10-0E.xsd' => '48be2a9f181d7ff80f185c62491ba12604c5cacbbe21af8e2aaaf2c585bbd214',
            'LICENSE.txt' => 'f813e0850875814fd117d10803221c6032ef32fe3415e9f1d37fb1a115c3c07b',
        ], $files);

        foreach ($files as $file => $sha256) {
            self::assertFileExists($dir.'/'.$file);
            self::assertSame($sha256, hash_file('sha256', $dir.'/'.$file), $file);
            self::assertStringContainsString($sha256, (string) file_get_contents($dir.'/SOURCE.md'), $file);
        }
    }

    public function test_main_schema_and_prefix_map(): void
    {
        self::assertSame('schemat_FA(3)_v1-0E.xsd', basename(Fa3Schema::path()));
        self::assertTrue(str_starts_with(Fa3Schema::path(), '/') || preg_match('~^[A-Za-z]:~', Fa3Schema::path()) === 1);
        self::assertStringNotContainsString('..', Fa3Schema::path());
        self::assertSame([Fa3Format::ETD_NS => dirname(Fa3Schema::path())], Fa3Schema::localPrefixes());
    }

    /**
     * The one absolute schemaLocation of the bundle lies below the mapped prefix, and the main
     * schema targets the namespace the writer and reader use.
     */
    public function test_the_absolute_import_lies_below_the_mapped_prefix(): void
    {
        $main = (string) file_get_contents(Fa3Schema::path());

        self::assertStringContainsString('targetNamespace="'.Fa3Format::NS.'"', $main);
        self::assertSame(1, preg_match_all('~schemaLocation="(https?://[^"]+)"~', $main, $locations));
        self::assertSame(Fa3Format::ETD_NS.'StrukturyDanych_v10-0E.xsd', $locations[1][0]);
    }
}
