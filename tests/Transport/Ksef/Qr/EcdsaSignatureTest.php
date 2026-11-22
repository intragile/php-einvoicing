<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Qr;

use IntrAgile\EInvoicing\Transport\Ksef\Qr\EcdsaSignature;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EcdsaSignatureTest extends TestCase
{
    public function test_short_r_is_left_padded_and_sign_byte_of_s_is_dropped(): void
    {
        // r has 31 significant bytes (its leading zero byte is not encoded); s has the high bit
        // set, so DER prefixes it with a 0x00 sign byte (33 bytes).
        $r = str_repeat("\x11", 31);
        $s = "\x80".str_repeat("\x22", 31);
        $der = hex2bin('3044'.'021f'.bin2hex($r).'0221'.'00'.bin2hex($s));
        self::assertIsString($der);

        $p1363 = EcdsaSignature::derToP1363($der, 32);

        self::assertSame(64, strlen($p1363));
        self::assertSame("\x00".$r.$s, $p1363);
    }

    public function test_very_short_s_is_left_padded(): void
    {
        $r = "\x7F".str_repeat("\x33", 31);
        $der = hex2bin('3025'.'0220'.bin2hex($r).'0201'.'05');
        self::assertIsString($der);

        self::assertSame($r.str_repeat("\x00", 31)."\x05", EcdsaSignature::derToP1363($der, 32));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'not a sequence' => ['3102020101020101'];
        yield 'inconsistent length' => ['3007020101020101'];
        yield 'not an integer' => ['3006030101020101'];
        yield 'truncated integer' => ['3006020101020501'];
        yield 'one integer only' => ['3003020101'];
        yield 'value longer than the field' => ['3026'.'0221'.'01'.str_repeat('11', 32).'020101'];
    }

    #[DataProvider('malformed')]
    public function test_malformed_der_is_refused(string $hex): void
    {
        $der = hex2bin($hex);
        self::assertIsString($der);

        $this->expectException(RuntimeException::class);

        EcdsaSignature::derToP1363($der, 32);
    }
}
