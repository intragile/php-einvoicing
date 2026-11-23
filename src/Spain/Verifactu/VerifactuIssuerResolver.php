<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\UnknownIssuer;

/**
 * Supplies the runtime data of an issuer (name, driver, environment, credentials) by NIF, so that
 * one installation can serve several obligated issuers.
 */
interface VerifactuIssuerResolver
{
    /**
     * @throws UnknownIssuer when the resolver has no data for the NIF
     */
    public function resolve(Nif $issuer): IssuerContext;

    /**
     * @return list<Nif> the issuers the resolver can enumerate; may be empty
     */
    public function issuers(): array;
}
