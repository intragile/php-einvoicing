<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\XPath;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\XPath\Canonical;
use IntrAgile\EInvoicing\XPath\Thunk;
use IntrAgile\EInvoicing\XPath\Value\IntegerValue;
use IntrAgile\EInvoicing\XPath\XPathError;
use PHPUnit\Framework\TestCase;

final class ThunkTest extends TestCase
{
    public function test_computes_once(): void
    {
        $count = 0;
        $thunk = new Thunk(static function () use (&$count): array {
            $count++;

            return [new IntegerValue(BigDecimal::of($count))];
        });

        self::assertSame(0, $count);
        $first = $thunk->value();
        $second = $thunk->value();
        self::assertSame(1, $count);
        self::assertSame('(xs:integer:1)', Canonical::of($second));
        self::assertSame($first, $second);
    }

    public function test_caches_the_error_and_rethrows_the_same_one(): void
    {
        $count = 0;
        $thunk = new Thunk(static function () use (&$count): array {
            $count++;

            throw new XPathError('FORG0001', 'Cannot convert.');
        });

        $errors = [];
        for ($i = 0; $i < 2; $i++) {
            try {
                $thunk->value();
                self::fail('Expected FORG0001.');
            } catch (XPathError $error) {
                $errors[] = $error;
            }
        }

        self::assertSame(1, $count);
        self::assertSame($errors[0], $errors[1]);
        self::assertSame('FORG0001', $errors[0]->errorCode);
    }

    public function test_circular_reference_is_a_non_recoverable_error(): void
    {
        $thunk = null;
        $thunk = new Thunk(static function () use (&$thunk): array {
            self::assertInstanceOf(Thunk::class, $thunk);

            return $thunk->value();
        });

        try {
            $thunk->value();
            self::fail('Expected XTDE0640.');
        } catch (XPathError $error) {
            self::assertSame('XTDE0640', $error->errorCode);
            self::assertFalse($error->isRecoverable());
        }
    }
}
