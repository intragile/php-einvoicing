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

    public function test_key_stays_out_of_var_export_array_cast_and_json(): void
    {
        $secret = 'k3y-material-0123456789abcdefghi';
        $key = new SymmetricKey($secret, str_repeat('i', 16));

        self::assertSame($secret, $key->key());
        self::assertStringNotContainsString($secret, var_export($key, true));
        self::assertStringNotContainsString($secret, var_export((array) $key, true));
        self::assertStringNotContainsString($secret, print_r((array) $key, true));
        self::assertStringNotContainsString($secret, (string) json_encode($key));
        self::assertStringNotContainsString($secret, (string) json_encode((array) $key));
    }

    public function test_symmetric_key_cannot_be_serialized(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be serialized');

        serialize(SymmetricKey::generate());
    }
}
