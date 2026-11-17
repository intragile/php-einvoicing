<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

use IntrAgile\EInvoicing\France\Siren;
use InvalidArgumentException;

/**
 * The taxable person a Flux 10 transmission is declared for (TG-5), identified by its SIREN.
 */
final readonly class Declarant
{
    /**
     * @param  string  $siren  SIREN (TT-13), written with scheme 0002
     * @param  string  $name  company name (TT-14)
     * @param  string|null  $uri  electronic address (TT-16)
     *
     * @throws InvalidArgumentException when the SIREN is not nine digits
     */
    public function __construct(
        public string $siren,
        public string $name,
        public DeclarantRole $role,
        public ?string $uri = null,
    ) {
        if (! Siren::isValid($siren)) {
            throw new InvalidArgumentException(sprintf('Invalid declarant SIREN "%s": expected exactly 9 digits.', $siren));
        }
    }
}
