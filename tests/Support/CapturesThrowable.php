<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use PHPUnit\Framework\Assert;
use Throwable;

/**
 * Runs an action that is expected to throw and returns what it threw, so that a test can assert
 * on the exception's type, message and fields.
 */
trait CapturesThrowable
{
    private static function capture(callable $action): Throwable
    {
        try {
            $action();
        } catch (Throwable $exception) {
            return $exception;
        }
        Assert::fail('No exception was thrown.');
    }
}
