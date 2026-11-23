<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\ComputerSystem;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use InvalidArgumentException;

/**
 * Identifies one send throttle: "<url-encoded installation number>|<environment>", prefixed with the
 * issuer NIF when the throttle is scoped per issuer.
 */
final readonly class ThrottleKey
{
    private function __construct(private string $value) {}

    /**
     * @throws InvalidArgumentException when the issuer scope is requested without an issuer
     */
    public static function for(
        ComputerSystem $system,
        VerifactuEnvironment $environment,
        ThrottleScope $scope = ThrottleScope::Installation,
        ?Nif $issuer = null,
    ): self {
        $installation = rawurlencode($system->installationNumber).'|'.$environment->value;

        if ($scope === ThrottleScope::Installation) {
            return new self($installation);
        }

        if ($issuer === null) {
            throw new InvalidArgumentException('The issuer throttle scope needs an issuer.');
        }

        return new self($issuer->value.'|'.$installation);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
