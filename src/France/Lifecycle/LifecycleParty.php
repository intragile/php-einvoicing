<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\Model\Identifier;
use InvalidArgumentException;

/**
 * A party of a lifecycle message: its sender, issuer or a recipient, or a recipient of the
 * referenced invoice.
 */
final readonly class LifecycleParty
{
    /** Scheme of an approved platform's or the public portal's matricule. */
    public const MATRICULE_SCHEME = '0238';

    /** Matricule of the public portal (PPF). */
    public const PPF_MATRICULE = '0000';

    public function __construct(
        /** Global identifier (MDT-19, MDT-38, MDT-57), such as a SIREN (0002) or a matricule (0238). */
        public ?Identifier $globalId,
        /** Role (MDT-21, MDT-40, MDT-59, MDT-158). */
        public PartyRole $role,
        /** Name (MDT-20, MDT-39, MDT-58). */
        public ?string $name = null,
        /** Electronic address (MDT-73); the scheme may be absent. */
        public ?Identifier $uri = null,
    ) {}

    /**
     * An approved platform (role WK) identified by its matricule.
     */
    public static function platform(string $matricule, ?string $name = null): self
    {
        return new self(new Identifier($matricule, self::MATRICULE_SCHEME), PartyRole::Platform, $name);
    }

    /**
     * The public invoicing portal (role DFH).
     */
    public static function ppf(): self
    {
        return new self(new Identifier(self::PPF_MATRICULE, self::MATRICULE_SCHEME), PartyRole::PublicPortal, 'PPF');
    }

    /**
     * The seller (role SE) identified by its SIREN.
     *
     * @throws InvalidArgumentException When the SIREN is not nine digits.
     */
    public static function seller(string $siren, ?Identifier $uri = null, ?string $name = null): self
    {
        return new self(Siren::identifier($siren), PartyRole::Seller, $name, $uri);
    }

    /**
     * The buyer (role BY) identified by its SIREN.
     *
     * @throws InvalidArgumentException When the SIREN is not nine digits.
     */
    public static function buyer(string $siren, ?Identifier $uri = null, ?string $name = null): self
    {
        return new self(Siren::identifier($siren), PartyRole::Buyer, $name, $uri);
    }
}
