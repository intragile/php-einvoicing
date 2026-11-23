<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use IntrAgile\EInvoicing\Spain\CertificateKind;
use IntrAgile\EInvoicing\Spain\Verifactu\IssuerContext;
use IntrAgile\EInvoicing\Spain\Verifactu\IssuerDriver;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;
use InvalidArgumentException;

/**
 * Where and how the AEAT VERI*FACTU SOAP service is reached: the environment, the port (personal
 * and seal certificates use different hosts), the installed schema set and the largest response
 * body accepted.
 */
final readonly class AeatConfig
{
    /**
     * @param  int  $maxResponseBytes  responses larger than this are refused unread
     *
     * @throws InvalidArgumentException when $maxResponseBytes is below 1
     */
    public function __construct(
        public VerifactuEnvironment $environment,
        public CertificateKind $port,
        public VerifactuSchemas $schemas,
        public int $maxResponseBytes = 5_000_000,
    ) {
        if ($maxResponseBytes < 1) {
            throw new InvalidArgumentException('The response size limit must be at least 1 byte.');
        }
    }

    /**
     * The configuration for an issuer that submits directly to the AEAT; the port follows the kind
     * of its certificate.
     *
     * @throws InvalidArgumentException when the issuer submits through B2Brouter or has no credentials
     */
    public static function forIssuer(IssuerContext $issuer, VerifactuSchemas $schemas): self
    {
        if ($issuer->driver === IssuerDriver::B2Brouter) {
            throw new InvalidArgumentException('The issuer submits through B2Brouter, not directly to the AEAT.');
        }
        if ($issuer->credentials === null) {
            throw new InvalidArgumentException('The issuer has no AEAT credentials.');
        }

        return new self($issuer->environment, $issuer->credentials->kind, $schemas);
    }

    /**
     * The SOAP endpoint URL for this environment and port.
     */
    public function endpoint(): string
    {
        return $this->environment->soapEndpoint($this->port);
    }
}
