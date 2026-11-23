<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use Closure;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\ChainConflict;

/**
 * Persists VERI*FACTU chains, one per ChainKey.
 *
 * Implementations guarantee that:
 * - `$build` runs under an exclusive lock on the chain, so no other writer appends meanwhile;
 * - the record and the new head are persisted atomically;
 * - a record that does not link to the head passed to `$build` (or is not a first record on an
 *   empty chain) throws ChainConflict and nothing is stored;
 * - an exception thrown by `$build` propagates and nothing is stored;
 * - stored records are never rewritten, deleted or reordered.
 */
interface ChainStore
{
    /**
     * Builds the next record from the current head and appends it.
     *
     * @param  Closure(?ChainHead): BillingRecord  $build
     *
     * @throws ChainConflict when the built record does not link to the head
     */
    public function append(ChainKey $key, Closure $build): BillingRecord;

    /** The current head, or null for a chain that has neither records nor a seed. */
    public function head(ChainKey $key): ?ChainHead;

    /**
     * Starts the chain from the last record of a chain produced elsewhere.
     *
     * @throws ChainConflict when the chain already has a head
     */
    public function seed(ChainKey $key, InvoiceId $last, string $fingerprint): ChainHead;
}
