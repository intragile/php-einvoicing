<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Lookup;

use IntrAgile\EInvoicing\Transport\Lookup\NaptrRecord;
use IntrAgile\EInvoicing\Transport\Lookup\NaptrResolver;

/**
 * A NAPTR resolver that answers every query with the same records (null = resolver failure) and
 * records the queried host names.
 */
final class FakeNaptrResolver implements NaptrResolver
{
    /** @var list<string> */
    public array $queried = [];

    /**
     * @param  list<NaptrRecord>|null  $records
     */
    public function __construct(public ?array $records) {}

    public function naptr(string $host): ?array
    {
        $this->queried[] = $host;

        return $this->records;
    }
}
