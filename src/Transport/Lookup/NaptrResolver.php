<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Lookup;

/**
 * Resolves the NAPTR records of a host name. Implement it to plug in a DNS-over-HTTPS or pure-PHP
 * resolver where dns_get_record() is unavailable, slow or unreliable.
 */
interface NaptrResolver
{
    /**
     * The NAPTR records of the host: an empty list when the name has none (or does not exist),
     * null when the lookup itself failed and the answer is unknown.
     *
     * @return list<NaptrRecord>|null
     */
    public function naptr(string $host): ?array;
}
