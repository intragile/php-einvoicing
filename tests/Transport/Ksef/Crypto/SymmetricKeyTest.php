<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Crypto;

use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\SymmetricKey;
use LogicException;
use PHPUnit\Framework\TestCase;

final class SymmetricKeyTest extends TestCase
{
    public function test_debug_info_is_redacted(): void
    {
        $key = SymmetricKey::generate();

        self::assertSame(['key' => Redactor::REDACTED, 'iv' => base64_encode($key->iv)], $key->__debugInfo());
    }

    public function test_symmetric_key_cannot_be_serialized(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be serialized');

        serialize(SymmetricKey::generate());
    }
}
