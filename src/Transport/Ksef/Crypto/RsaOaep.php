<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Crypto;

use InvalidArgumentException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PublicKey;
use Throwable;

/**
 * RSAES-OAEP encryption as KSeF requires it: SHA-256 for both the label hash and MGF1, empty label.
 *
 * phpseclib is used on every PHP version because openssl_public_encrypt() can only select an
 * OAEP digest other than SHA-1 from PHP 8.5 on.
 */
final class RsaOaep
{
    /**
     * Encrypts a short secret (such as an AES key or a token) for the holder of the private key.
     *
     * @param  string  $certificateOrPublicKeyPem  An X.509 certificate or an RSA public key, PEM or DER encoded
     * @return string The raw ciphertext, as long as the key modulus
     *
     * @throws InvalidArgumentException When the key cannot be loaded, is not RSA or the plaintext is too long
     */
    public static function encrypt(string $plaintext, string $certificateOrPublicKeyPem): string
    {
        try {
            $key = PublicKeyLoader::loadPublicKey($certificateOrPublicKeyPem);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('The KSeF public key or certificate could not be loaded.', 0, $e);
        }

        if (! $key instanceof PublicKey) {
            throw new InvalidArgumentException('The KSeF public key must be an RSA key.');
        }

        $key = self::publicKey($key->withPadding(RSA::ENCRYPTION_OAEP));
        $key = self::publicKey($key->withHash('sha256'));
        $key = self::publicKey($key->withMGFHash('sha256'));
        $key = self::publicKey($key->withLabel(''));

        try {
            $ciphertext = $key->encrypt($plaintext);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('The plaintext could not be RSA-OAEP encrypted.', 0, $e);
        }

        if (! is_string($ciphertext) || $ciphertext === '') {
            throw new InvalidArgumentException('The plaintext could not be RSA-OAEP encrypted.');
        }

        return $ciphertext;
    }

    /**
     * Narrows the untyped return value of the phpseclib key builder methods.
     */
    private static function publicKey(mixed $key): PublicKey
    {
        if (! $key instanceof PublicKey) {
            throw new InvalidArgumentException('The KSeF public key must be an RSA key.');
        }

        return $key;
    }
}
