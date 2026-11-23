<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * The last record of a chain, as far as the next record needs to know it.
 *
 * A seeded head continues a chain started elsewhere: it has sequence 0, no generation timestamp,
 * and the first record appended to it is not checked against a timestamp.
 */
final readonly class ChainHead
{
    private function __construct(
        public InvoiceId $invoiceId,
        public string $fingerprint,
        public ?DateTimeImmutable $generatedAt,
        public int $sequence,
        public bool $seeded,
    ) {}

    /** The head after appending the record to the chain whose head was $previous. */
    public static function after(BillingRecord $record, ?self $previous): self
    {
        return new self(
            $record->invoiceId(),
            $record->fingerprint(),
            $record->generatedAt()->value,
            $previous === null ? 1 : $previous->sequence + 1,
            false,
        );
    }

    /** A head that continues a chain whose last record was produced by another system. */
    public static function seeded(InvoiceId $last, string $fingerprint): self
    {
        // Validates the fingerprint format eagerly.
        ChainLink::after($last, $fingerprint);

        return new self($last, $fingerprint, null, 0, true);
    }

    /**
     * Rebuilds a head that a store persisted.
     *
     * A seeded head has sequence 0 and no timestamp; any other head has a timestamp and a
     * sequence of at least 1.
     */
    public static function restore(
        InvoiceId $invoiceId,
        string $fingerprint,
        ?DateTimeImmutable $generatedAt,
        int $sequence,
        bool $seeded,
    ): self {
        if ($seeded && ($sequence !== 0 || $generatedAt !== null)) {
            throw new InvalidRecord('A seeded chain head has sequence 0 and no generation timestamp.');
        }

        if (! $seeded && ($sequence < 1 || $generatedAt === null)) {
            throw new InvalidRecord('A chain head that is not seeded needs a generation timestamp and a sequence of at least 1.');
        }

        return new self($invoiceId, $fingerprint, $generatedAt, $sequence, $seeded);
    }

    /** The link the next record carries (RegistroAnterior). */
    public function link(): ChainLink
    {
        return ChainLink::after($this->invoiceId, $this->fingerprint);
    }

    /** Whether the record links to this head. */
    public function isLinkedBy(BillingRecord $record): bool
    {
        $link = $record->chainLink();
        $previous = $link->previous();

        return $previous !== null
            && $previous->equals($this->invoiceId)
            && $link->previousFingerprint() === $this->fingerprint;
    }
}
