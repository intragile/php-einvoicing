<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use Closure;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\ChainConflict;

/**
 * A chain store held in memory, for a single process (tests, one-off scripts).
 *
 * PHP runs the build closure without interruption, so a single process needs no further lock.
 */
final class InMemoryChainStore implements ChainStore
{
    /** @var array<string, ChainHead> */
    private array $heads = [];

    /** @var array<string, list<BillingRecord>> */
    private array $records = [];

    public function append(ChainKey $key, Closure $build): BillingRecord
    {
        $id = $key->toString();
        $head = $this->heads[$id] ?? null;

        $record = $build($head);

        $linked = $head === null ? $record->chainLink()->isFirst() : $head->isLinkedBy($record);

        if (! $linked) {
            throw new ChainConflict(sprintf(
                'The record for invoice %s does not link to the head of chain %s.',
                $record->invoiceId()->number,
                $id,
            ));
        }

        $this->heads[$id] = ChainHead::after($record, $head);
        $this->records[$id][] = $record;

        return $record;
    }

    public function head(ChainKey $key): ?ChainHead
    {
        return $this->heads[$key->toString()] ?? null;
    }

    public function seed(ChainKey $key, InvoiceId $last, string $fingerprint): ChainHead
    {
        $id = $key->toString();

        if (isset($this->heads[$id])) {
            throw new ChainConflict(sprintf('Chain %s already has a head and cannot be seeded.', $id));
        }

        return $this->heads[$id] = ChainHead::seeded($last, $fingerprint);
    }

    /**
     * The records appended to the chain, oldest first.
     *
     * @return list<BillingRecord>
     */
    public function records(ChainKey $key): array
    {
        return $this->records[$key->toString()] ?? [];
    }
}
