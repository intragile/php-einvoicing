<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FiredRuleTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function flags(): array
    {
        return [
            'fatal' => ['fatal', true],
            'warning' => ['warning', false],
            'information' => ['information', false],
            'missing' => ['', true],
            'unknown' => ['error', true],
            'wrong case' => ['Warning', true],
        ];
    }

    #[DataProvider('flags')]
    public function test_only_explicit_non_fatal_flags_are_tolerated(string $flag, bool $fatal): void
    {
        self::assertSame($fatal, (new FiredRule('BR-01', $flag, '/'))->isFatal());
    }
}
