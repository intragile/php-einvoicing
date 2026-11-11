<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Model;

use Closure;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Price;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FloatInputTest extends TestCase
{
    /**
     * @return array<string, array{string, Closure(): mixed}>
     */
    public static function nonStrictFloatCalls(): array
    {
        /** @var array<string, array{string, Closure(): mixed}> $calls */
        $calls = require __DIR__.'/../fixtures/php/non-strict-float-calls.php';

        return $calls;
    }

    /**
     * @param  Closure(): mixed  $call
     */
    #[DataProvider('nonStrictFloatCalls')]
    public function test_a_coercive_mode_caller_cannot_pass_a_float(string $term, Closure $call): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($term.' must be given as a string, int or BigDecimal, float given.');

        $call();
    }

    public function test_fixture_really_runs_in_coercive_mode(): void
    {
        $source = (string) file_get_contents(__DIR__.'/../fixtures/php/non-strict-float-calls.php');

        self::assertStringNotContainsString('strict_types=1);', $source);
    }

    public function test_a_strict_mode_caller_cannot_pass_a_float(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BT-152 must be given as a string, int or BigDecimal, float given.');

        $rate = 27.5;
        new LineVat(VatCategory::StandardRate, $rate);
    }

    public function test_exact_inputs_are_kept_exactly(): void
    {
        $price = new Price('0.1', null, '1.10', 3);

        self::assertSame('0.1', (string) $price->netPrice);
        self::assertSame('1.10', (string) $price->grossPrice);
        self::assertSame('3', (string) $price->baseQuantity);
    }
}
