<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use Closure;
use IntrAgile\EInvoicing\Spain\CertificateKind;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use SensitiveParameter;

/**
 * The client certificate location and password for the AEAT web services. This is a value object
 * only: the certificate file is not read here. The password is kept inside a closure so that it
 * stays out of var_export(), print_r() and var_dump() output.
 */
final readonly class AeatCredentials
{
    /** @var Closure(): ?string */
    private Closure $password;

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
        $this->password = static fn (): ?string => $password;
    }

    /**
     * The password itself, for the code that opens the certificate.
     */
    public function password(): ?string
    {
        return ($this->password)();
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'certificatePath' => $this->certificatePath,
            'password' => $this->password() === null ? null : Redactor::REDACTED,
            'privateKeyPath' => $this->privateKeyPath,
            'kind' => $this->kind,
        ];
    }
}
