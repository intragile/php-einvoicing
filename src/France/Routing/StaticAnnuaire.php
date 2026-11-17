<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Routing;

/**
 * Directory backed by a fixed SIREN to status map; a SIREN missing from the map is Unknown.
 */
final class StaticAnnuaire implements Annuaire
{
    /**
     * @param  array<int|string, AnnuaireStatus>  $entries  status by SIREN; PHP turns a SIREN
     *                                                      without a leading zero into an
     *                                                      integer key, which still matches
     */
    public function __construct(private readonly array $entries = []) {}

    public function status(string $siren): AnnuaireStatus
    {
        return $this->entries[$siren] ?? AnnuaireStatus::Unknown;
    }
}
