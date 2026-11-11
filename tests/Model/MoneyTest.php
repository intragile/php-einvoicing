<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Model;

use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\Model\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_addition_is_exact(): void
    {
        $sum = (new Money('0.1', CurrencyCode::EUR))->plus(new Money('0.2', CurrencyCode::EUR));

        self::assertTrue($sum->isEqualTo(new Money('0.3', CurrencyCode::EUR)));
        self::assertSame('0.3 EUR', (string) $sum);
    }

    public function test_rounding_is_half_up_to_two_decimals(): void
    {
        self::assertSame('2.35', (string) (new Money('2.345', CurrencyCode::HUF))->rounded()->amount);
        self::assertSame('-2.35', (string) (new Money('-2.345', CurrencyCode::HUF))->rounded()->amount);
        self::assertTrue((new Money('1.00', CurrencyCode::HUF))->minus(new Money(1, CurrencyCode::HUF))->isZero());
    }

    public function test_refuses_to_mix_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Money(1, CurrencyCode::EUR))->plus(new Money(1, CurrencyCode::HUF));
    }

    public function test_equality_includes_currency(): void
    {
        self::assertFalse((new Money(1, CurrencyCode::EUR))->isEqualTo(new Money(1, CurrencyCode::HUF)));
    }
}
