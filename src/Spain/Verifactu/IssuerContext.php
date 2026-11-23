<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use InvalidArgumentException;

/**
 * Everything needed to submit records on behalf of one issuer.
 */
final readonly class IssuerContext
{
    /**
     * @throws InvalidArgumentException when the driver is Aeat and no credentials are given
     */
    public function __construct(
        public Nif $nif,
        public string $name,
        public IssuerDriver $driver,
        public VerifactuEnvironment $environment,
        public ?AeatCredentials $credentials = null,
        public ?Representative $representative = null,
    ) {
        if ($driver === IssuerDriver::Aeat && $credentials === null) {
            throw new InvalidArgumentException('The Aeat driver needs credentials.');
        }
    }

    public function header(): SubmissionHeader
    {
        return new SubmissionHeader($this->nif, $this->name, $this->representative);
    }

    public function chainKey(ComputerSystem $system): ChainKey
    {
        return ChainKey::for($this->nif, $system, $this->environment);
    }
}
