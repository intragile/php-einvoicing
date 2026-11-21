<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Crypto;

use IntrAgile\EInvoicing\Transport\Ksef\Crypto\InvoiceCipher;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\SymmetricKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InvoiceCipherTest extends TestCase
{
    public function test_aes_round_trip(): void
    {
        $key = SymmetricKey::generate();
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Faktura>zażółć gęślą jaźń</Faktura>';

        $ciphertext = InvoiceCipher::encrypt($xml, $key);

        self::assertNotSame($xml, $ciphertext);
        self::assertSame($xml, InvoiceCipher::decrypt($ciphertext, $key));
        self::assertSame($ciphertext, openssl_encrypt($xml, 'aes-256-cbc', $key->key, OPENSSL_RAW_DATA, $key->iv));
    }

    public function test_ciphertext_is_pkcs7_padded(): void
    {
        $key = SymmetricKey::generate();

        self::assertSame(32, strlen(InvoiceCipher::encrypt(str_repeat('a', 31), $key)));
        self::assertSame(48, strlen(InvoiceCipher::encrypt(str_repeat('a', 32), $key)));
        self::assertSame(16, strlen(InvoiceCipher::encrypt('', $key)));
    }

    public function test_wrong_key_decrypt_throws(): void
    {
        $ciphertext = InvoiceCipher::encrypt(str_repeat('invoice ', 20), SymmetricKey::generate());

        // A random key yields invalid padding except with negligible probability; try a few keys
        // so that a single lucky padding byte cannot make the test flaky.
        $failures = 0;
        for ($i = 0; $i < 5; $i++) {
            try {
                InvoiceCipher::decrypt($ciphertext, SymmetricKey::generate());
            } catch (InvalidArgumentException) {
                $failures++;
            }
        }
        self::assertGreaterThanOrEqual(4, $failures);

        $this->expectException(InvalidArgumentException::class);
        InvoiceCipher::decrypt('short', SymmetricKey::generate());
    }

    public function test_key_and_iv_lengths_enforced(): void
    {
        $key = SymmetricKey::generate();
        self::assertSame(32, strlen($key->key));
        self::assertSame(16, strlen($key->iv));
        self::assertNotSame($key->key, SymmetricKey::generate()->key);

        foreach ([[31, 16], [33, 16], [32, 15], [32, 17], [16, 16]] as [$keyLength, $ivLength]) {
            try {
                new SymmetricKey(str_repeat('k', $keyLength), str_repeat('i', $ivLength));
                self::fail("Accepted a {$keyLength}-byte key with a {$ivLength}-byte IV.");
            } catch (InvalidArgumentException $e) {
                self::assertStringNotContainsString(str_repeat('k', $keyLength), $e->getMessage());
            }
        }
    }

    public function test_symmetric_key_debug_info_is_redacted(): void
    {
        $secret = str_repeat("\xAB", 32);
        $key = new SymmetricKey($secret, str_repeat("\x01", 16));

        $dump = print_r($key, true).var_export($key->__debugInfo(), true);
        ob_start();
        var_dump($key);
        $dump .= (string) ob_get_clean();

        self::assertStringNotContainsString($secret, $dump);
        self::assertStringNotContainsString(bin2hex($secret), $dump);
        self::assertStringNotContainsString(base64_encode($secret), $dump);
        self::assertStringContainsString('redacted', $dump);
    }
}
