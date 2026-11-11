<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Model;

use ArrayObject;
use IntrAgile\EInvoicing\Model\Extensions;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ExtensionsTest extends TestCase
{
    public function test_with_is_immutable_and_replaces_same_class(): void
    {
        $first = new stdClass;
        $second = new stdClass;
        $empty = Extensions::none();
        $one = $empty->with($first);
        $replaced = $one->with($second);

        self::assertFalse($empty->has(stdClass::class));
        self::assertSame($first, $one->get(stdClass::class));
        self::assertSame($second, $replaced->get(stdClass::class));
        self::assertCount(1, $replaced->all());
    }

    public function test_holds_one_extension_per_class_and_can_remove(): void
    {
        $bag = Extensions::none()->with(new stdClass)->with(new ArrayObject);

        self::assertCount(2, $bag->all());
        self::assertInstanceOf(ArrayObject::class, $bag->get(ArrayObject::class));
        self::assertNull($bag->without(ArrayObject::class)->get(ArrayObject::class));
        self::assertTrue($bag->has(stdClass::class));
    }
}
