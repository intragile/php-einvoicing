<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Crypto;

use InvalidArgumentException;

/**
 * The identifier KSeF gives a public key: the Base64 SHA-256 digest of its DER SubjectPublicKeyInfo.
 */
final class PublicKeyId
{
    /**
     * @throws InvalidArgumentException When the certificate cannot be read
     */
    public static function of(string $certificatePem): string
    {
        $key = @openssl_pkey_get_public($certificatePem);
        $details = $key === false ? false : openssl_pkey_get_details($key);

        if ($details === false || ! is_string($details['key'] ?? null)) {
            while (openssl_error_string() !== false) {
            }

            throw new InvalidArgumentException('The certificate public key could not be read.');
        }

        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $details['key']), true);

        if ($der === false || $der === '') {
            throw new InvalidArgumentException('The certificate public key could not be read.');
        }

        return base64_encode(hash('sha256', $der, true));
    }
}
