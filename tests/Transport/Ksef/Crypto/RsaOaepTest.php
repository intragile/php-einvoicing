<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Crypto;

use IntrAgile\EInvoicing\Transport\Ksef\Crypto\RsaOaep;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RsaOaepTest extends TestCase
{
    private static string $privatePem = '';

    private static string $publicPem = '';

    private static string $certificatePem = '';

    public static function setUpBeforeClass(): void
    {
        // Key material is generated in memory for every run and never written to disk.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false || ! openssl_pkey_export($key, $privatePem) || ! is_string($privatePem)) {
            throw new RuntimeException('Could not generate a test RSA key.');
        }

        $details = openssl_pkey_get_details($key);
        $csrKey = $key;
        $csr = openssl_csr_new(['commonName' => 'Synthetic Test'], $csrKey, ['digest_alg' => 'sha256']);
        $certificate = is_bool($csr) ? false : openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        if ($details === false || ! is_string($details['key'] ?? null) || $certificate === false
            || ! openssl_x509_export($certificate, $certificatePem) || ! is_string($certificatePem)) {
            throw new RuntimeException('Could not prepare the test key material.');
        }

        self::$privatePem = $privatePem;
        self::$publicPem = $details['key'];
        self::$certificatePem = $certificatePem;
    }

    public static function tearDownAfterClass(): void
    {
        self::$privatePem = '';
    }

    public function test_oaep_round_trip_with_phpseclib_private_key(): void
    {
        $plaintext = random_bytes(32);

        foreach ([self::$certificatePem, self::$publicPem] as $pem) {
            $ciphertext = RsaOaep::encrypt($plaintext, $pem);

            self::assertSame($plaintext, $this->phpseclibPrivateKey()->decrypt($ciphertext));
        }
    }

    public function test_oaep_ciphertext_is_modulus_length(): void
    {
        self::assertSame(256, strlen(RsaOaep::encrypt(random_bytes(32), self::$certificatePem)));
    }

    #[RequiresPhp('>=8.5')]
    public function test_openssl_85_decrypts_phpseclib_output(): void
    {
        $plaintext = random_bytes(32);
        $ciphertext = RsaOaep::encrypt($plaintext, self::$certificatePem);

        // The digest argument of openssl_private_decrypt() exists from PHP 8.5 on.
        $decrypted = '';
        $ok = openssl_private_decrypt($ciphertext, $decrypted, $this->opensslPrivateKey(), OPENSSL_PKCS1_OAEP_PADDING, 'sha256');

        self::assertTrue($ok);
        self::assertSame($plaintext, $decrypted);
    }

    public function test_openssl_sha1_oaep_cannot_decrypt(): void
    {
        $ciphertext = RsaOaep::encrypt(random_bytes(32), self::$certificatePem);

        $decrypted = null;
        self::assertFalse(@openssl_private_decrypt($ciphertext, $decrypted, $this->opensslPrivateKey(), OPENSSL_PKCS1_OAEP_PADDING));
        while (openssl_error_string() !== false) {
        }

        // SHA-256 for the label hash but SHA-1 for MGF1 must not decrypt either.
        $mixed = $this->phpseclibPrivateKey()->withMGFHash('sha1');
        self::assertInstanceOf(PrivateKey::class, $mixed);
        $this->expectException(RuntimeException::class);
        $mixed->decrypt($ciphertext);
    }

    public function test_invalid_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RsaOaep::encrypt('x', 'not a key');
    }

    private function opensslPrivateKey(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_get_private(self::$privatePem);
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $key);

        return $key;
    }

    private function phpseclibPrivateKey(): PrivateKey
    {
        $key = PublicKeyLoader::loadPrivateKey(self::$privatePem);
        foreach ([
            static fn (PrivateKey $k): mixed => $k->withPadding(RSA::ENCRYPTION_OAEP),
            static fn (PrivateKey $k): mixed => $k->withHash('sha256'),
            static fn (PrivateKey $k): mixed => $k->withMGFHash('sha256'),
            static fn (PrivateKey $k): mixed => $k->withLabel(''),
        ] as $configure) {
            self::assertInstanceOf(PrivateKey::class, $key);
            $key = $configure($key);
        }
        self::assertInstanceOf(PrivateKey::class, $key);

        return $key;
    }
}
