<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\CertificateKind;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Secret;
use SensitiveParameter;

/**
 * The client certificate location and password for the AEAT web services. This is a value object
 * only: the certificate file is not read here. The password is held in a Secret, so it stays out
 * of var_export(), print_r() and var_dump() output, also after an array cast, and the object
 * refuses serialization while a password is set.
 */
final readonly class AeatCredentials
{
    private ?Secret $secret;

    /**
     * @param  string|null  $password  the password of the PKCS#12 file or the private key; null when there is none
     * @param  string|null  $privateKeyPath  the private key file when the certificate is a separate PEM
     */
    public function __construct(
        public string $certificatePath,
        #[SensitiveParameter] ?string $password,
        public ?string $privateKeyPath,
        public CertificateKind $kind,
    ) {
        $this->secret = $password === null ? null : new Secret($password);
    }

    /**
     * The password itself, for the code that opens the certificate.
     */
    public function password(): ?string
    {
        return $this->secret?->reveal();
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'certificatePath' => $this->certificatePath,
            'password' => $this->secret === null ? null : Redactor::REDACTED,
            'privateKeyPath' => $this->privateKeyPath,
            'kind' => $this->kind,
        ];
    }
}
