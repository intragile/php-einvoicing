<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Routing;

/**
 * Lookup in the French directory of e-invoicing recipients (annuaire).
 *
 * Implementations decide how the directory is reached (a platform API, a cached copy, a static
 * list); the router depends on this interface only and never calls the network itself.
 */
interface Annuaire
{
    /**
     * Returns the directory status of the legal entity with the given SIREN; Unknown when the
     * lookup is not possible.
     */
    public function status(string $siren): AnnuaireStatus;
}
