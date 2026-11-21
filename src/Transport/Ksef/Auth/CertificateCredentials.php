<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

use IntrAgile\EInvoicing\Transport\Http\Redactor;
use InvalidArgumentException;
use LogicException;
use OpenSSLAsymmetricKey;
use SensitiveParameter;

/**
 * A certificate (qualified seal, qualified signature or KSeF certificate) and its private key,
 * for XAdES authentication.
 *
 * The private key and its passphrase stay out of var_export(), print_r(), var_dump(),
 * json_encode() and array-cast output, and the object refuses to be serialized. The certificate
 * is public and stays readable.
 */
final readonly class CertificateCredentials implements Credentials
{
    private Secret $privateKeyPem;

    private ?Secret $passphrase;

    /**
     * @param  string  $certificatePem  the X.509 certificate, PEM-encoded
     * @param  string  $privateKeyPem  its private key, PEM-encoded, optionally encrypted
     * @param  ?string  $passphrase  the passphrase of an encrypted private key
     *
     * @throws InvalidArgumentException When the certificate or the key cannot be read, or the key
     *                                  does not belong to the certificate
     */
    public function __construct(
        public string $certificatePem,
        #[SensitiveParameter] string $privateKeyPem,
        #[SensitiveParameter] ?string $passphrase = null,
    ) {
        $certificate = @openssl_x509_read($certificatePem);
        if ($certificate === false) {
            self::clearOpenSslErrors();

            throw new InvalidArgumentException('The certificate cannot be read as a PEM-encoded X.509 certificate.');
        }

        $key = @openssl_pkey_get_private($privateKeyPem, $passphrase);
        if ($key === false) {
            self::clearOpenSslErrors();

            // The key and the passphrase are never quoted.
            throw new InvalidArgumentException('The private key cannot be read: it is not a PEM-encoded private key, or the passphrase is wrong.');
        }

        if (! openssl_x509_check_private_key($certificate, $key)) {
            self::clearOpenSslErrors();

            throw new InvalidArgumentException('The private key does not match the certificate.');
        }

        $this->privateKeyPem = new Secret($privateKeyPem);
        $this->passphrase = $passphrase === null ? null : new Secret($passphrase);
    }

    /**
     * The private key as given, for signing.
     */
    public function privateKeyPem(): string
    {
        return $this->privateKeyPem->reveal();
    }

    /**
     * The passphrase of the private key, or null when the key is not encrypted.
     */
    public function passphrase(): ?string
    {
        return $this->passphrase?->reveal();
    }

    /**
     * The private key, opened for openssl_sign().
     *
     * @throws InvalidArgumentException When the key can no longer be opened
     */
    public function privateKey(): OpenSSLAsymmetricKey
    {
        $key = @openssl_pkey_get_private($this->privateKeyPem(), $this->passphrase());
        if ($key === false) {
            self::clearOpenSslErrors();

            throw new InvalidArgumentException('The private key cannot be read.');
        }

        return $key;
    }

    /**
     * @return array{certificatePem: string, privateKeyPem: string, passphrase: ?string}
     */
    public function __debugInfo(): array
    {
        return [
            'certificatePem' => $this->certificatePem,
            'privateKeyPem' => Redactor::REDACTED,
            'passphrase' => $this->passphrase === null ? null : Redactor::REDACTED,
        ];
    }

    /**
     * @throws LogicException Always: a private key must not be written to a cache, queue or session
     */
    public function __serialize(): never
    {
        throw new LogicException('KSeF certificate credentials cannot be serialized.');
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws LogicException Always
     */
    public function __unserialize(array $data): never
    {
        throw new LogicException('KSeF certificate credentials cannot be serialized.');
    }

    /**
     * Drains OpenSSL's error queue, so a failure here is not reported later by an unrelated call.
     */
    private static function clearOpenSslErrors(): void
    {
        while (openssl_error_string() !== false) {
            // Discard.
        }
    }
}
