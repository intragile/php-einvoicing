<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

/**
 * French invoice profile, identified by the specification identifier (BT-24).
 */
enum FrenchProfile: string
{
    case En16931 = 'urn:cen.eu:en16931:2017';
    case ExtendedCtcFr = 'urn:cen.eu:en16931:2017#conformant#urn.cpro.gouv.fr:1p0:extended-ctc-fr';

    /**
     * Returns the profile for a specification identifier, or null when it is not a French profile.
     */
    public static function fromSpecificationIdentifier(string $id): ?self
    {
        return self::tryFrom($id);
    }
}
