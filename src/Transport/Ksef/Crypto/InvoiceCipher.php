<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Crypto;

use InvalidArgumentException;

/**
 * AES-256-CBC with PKCS#7 padding, the cipher KSeF uses for invoice files and export packages.
 */
final class InvoiceCipher
{
    private const CIPHER = 'aes-256-cbc';

    /**
     * @return string The raw ciphertext, padded to a multiple of 16 bytes
     */
    public static function encrypt(string $plaintext, SymmetricKey $key): string
    {
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key->key, OPENSSL_RAW_DATA, $key->iv);

        if ($ciphertext === false) {
            throw new InvalidArgumentException('The content could not be AES encrypted.');
        }

        return $ciphertext;
    }

    /**
     * @throws InvalidArgumentException When the ciphertext is malformed or the key does not match
     */
    public static function decrypt(string $ciphertext, SymmetricKey $key): string
    {
        if ($ciphertext === '' || strlen($ciphertext) % 16 !== 0) {
            throw new InvalidArgumentException('The ciphertext is not a whole number of AES blocks.');
        }

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key->key, OPENSSL_RAW_DATA, $key->iv);

        if ($plaintext === false) {
            // Drain the OpenSSL error queue so that the failure does not leak into later calls.
            while (openssl_error_string() !== false) {
            }

            throw new InvalidArgumentException('The content could not be decrypted: wrong key or corrupted data.');
        }

        return $plaintext;
    }
}
