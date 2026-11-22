<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Certificates;

use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use InvalidArgumentException;
use LogicException;
use SensitiveParameter;

/**
 * A certificate KSeF has just issued, with the private key generated for it.
 *
 * The private key exists nowhere else: KSeF never receives it and cannot issue it again, so the
 * caller must store it securely. It stays out of var_export(), print_r(), var_dump(),
 * json_encode() and array-cast output, and the object refuses to be serialized, so that it does
 * not end up in a log, a cache, a queued job or a session by accident.
 */
final readonly class EnrolledCertificate
{
    private CertificateCredentials $credentials;

    /**
     * @param  string  $privateKeyPem  the private key of the certificate, PEM-encoded
     *
     * @throws InvalidArgumentException when the key cannot be read or does not belong to the certificate
     */
    public function __construct(public KsefCertificate $certificate, #[SensitiveParameter] string $privateKeyPem)
    {
        $this->credentials = new CertificateCredentials($certificate->certificatePem, $privateKeyPem);
    }

    /**
     * The private key, PEM-encoded (PKCS#8, unencrypted), to be stored by the caller.
     */
    public function privateKeyPem(): string
    {
        return $this->credentials->privateKeyPem();
    }

    /**
     * The certificate and its key as credentials: for a XAdES authentication with an
     * Authentication certificate, or for signing the issuer's link with an Offline one.
     */
    public function credentials(): CertificateCredentials
    {
        return $this->credentials;
    }

    /**
     * @return array{certificate: KsefCertificate, privateKeyPem: string}
     */
    public function __debugInfo(): array
    {
        return ['certificate' => $this->certificate, 'privateKeyPem' => Redactor::REDACTED];
    }

    /**
     * @throws LogicException Always: a private key must not be written to a cache, queue or session
     */
    public function __serialize(): never
    {
        throw new LogicException('An enrolled KSeF certificate holds its private key and cannot be serialized.');
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws LogicException Always
     */
    public function __unserialize(array $data): never
    {
        throw new LogicException('An enrolled KSeF certificate holds its private key and cannot be serialized.');
    }
}
