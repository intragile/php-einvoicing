<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\UnknownIssuer;
use InvalidArgumentException;

/**
 * A resolver over a fixed list of issuers, enumerated in the order given.
 */
final class InMemoryIssuerResolver implements VerifactuIssuerResolver
{
    /** @var array<string, IssuerContext> */
    private array $issuers = [];

    /**
     * @throws InvalidArgumentException when two contexts share a NIF
     */
    public function __construct(IssuerContext ...$issuers)
    {
        foreach ($issuers as $issuer) {
            if (isset($this->issuers[$issuer->nif->value])) {
                throw new InvalidArgumentException(sprintf('The issuer %s is listed twice.', $issuer->nif->value));
            }
            $this->issuers[$issuer->nif->value] = $issuer;
        }
    }

    public function resolve(Nif $issuer): IssuerContext
    {
        return $this->issuers[$issuer->value]
            ?? throw new UnknownIssuer(sprintf('No issuer data for %s.', $issuer->value));
    }

    public function issuers(): array
    {
        return array_values(array_map(static fn (IssuerContext $c): Nif => $c->nif, $this->issuers));
    }
}
