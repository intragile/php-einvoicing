<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation;

use IntrAgile\EInvoicing\Rules\En16931\RuleFiles;
use IntrAgile\EInvoicing\Schematron\RuleSet;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuleFilesTest extends TestCase
{
    /**
     * Assert counts of the compiled rule sets, keyed by version and syntax.
     */
    private const ASSERTS = [
        '1.3.16' => ['ubl' => 979, 'cii' => 806],
        '1.3.17' => ['ubl' => 982, 'cii' => 810],
    ];

    public function test_default_version_is_1317(): void
    {
        self::assertSame('1.3.17', RuleFiles::VERSION);
        self::assertSame(['1.3.16', '1.3.17'], RuleFiles::VERSIONS);
        self::assertSame(RuleFiles::ubl('1.3.17'), RuleFiles::ubl());
        self::assertSame(RuleFiles::cii('1.3.17'), RuleFiles::cii());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function builds(): iterable
    {
        foreach (['1.3.16', '1.3.17'] as $version) {
            foreach (['ubl', 'cii'] as $syntax) {
                yield "{$syntax} {$version}" => [$syntax, $version];
            }
        }
    }

    #[DataProvider('builds')]
    public function test_both_versions_exist_and_load(string $syntax, string $version): void
    {
        $path = $syntax === 'ubl' ? RuleFiles::ubl($version) : RuleFiles::cii($version);
        self::assertFileExists($path);

        $rules = RuleSet::fromFile($path);

        self::assertSame("en16931-{$syntax}", $rules->id());
        self::assertSame($version, $rules->version());
        self::assertSame(self::ASSERTS[$version][$syntax], self::countAsserts($rules));
    }

    public function test_unknown_version_lists_known_ones(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('1.3.16, 1.3.17');

        RuleFiles::ubl('1.3.15');
    }

    public function test_unknown_version_is_rejected_for_cii_too(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RuleFiles::cii('1.3.18');
    }

    public function test_1316_and_1317_differ(): void
    {
        foreach (['ubl', 'cii'] as $syntax) {
            $old = $syntax === 'ubl' ? RuleFiles::ubl('1.3.16') : RuleFiles::cii('1.3.16');
            $new = $syntax === 'ubl' ? RuleFiles::ubl('1.3.17') : RuleFiles::cii('1.3.17');

            self::assertNotSame(file_get_contents($old), file_get_contents($new));
            self::assertLessThan(self::countAsserts(RuleSet::fromFile($new)), self::countAsserts(RuleSet::fromFile($old)));
        }
    }

    private static function countAsserts(RuleSet $rules): int
    {
        $count = 0;
        foreach ($rules->patterns() as $pattern) {
            foreach ($pattern['rules'] as $rule) {
                $count += count($rule['asserts']);
            }
        }

        return $count;
    }
}
