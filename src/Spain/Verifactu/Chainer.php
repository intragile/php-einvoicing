<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\ChainConflict;

/**
 * Turns drafts into chained, fingerprinted billing records.
 *
 * Each record links to the head it is appended to. A generation timestamp earlier than the head's
 * is refused; records generated within the same second are ordered by their sequence.
 */
final class Chainer
{
    /** @var Closure(): DateTimeImmutable */
    private Closure $clock;

    /**
     * @param  (Closure(): DateTimeImmutable)|null  $clock  the current instant; the system clock by default
     * @param  DateTimeZone  $zone  the zone FechaHoraHusoGenRegistro is written in
     */
    public function __construct(
        private readonly ComputerSystem $system,
        ?Closure $clock = null,
        private readonly DateTimeZone $zone = new DateTimeZone('Europe/Madrid'),
    ) {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable;
    }

    /**
     * Builds the record that follows the head, without storing anything.
     *
     * @return array{BillingRecord, ChainHead}
     *
     * @throws ChainConflict when $now is earlier than the head's generation timestamp
     */
    public function append(RegistrationDraft|CancellationDraft $draft, ?ChainHead $head, DateTimeImmutable $now): array
    {
        $at = RecordTimestamp::of($now, $this->zone);

        if ($head?->generatedAt !== null && $at->value < $head->generatedAt) {
            throw new ChainConflict(sprintf(
                'The generation timestamp %s is earlier than the chain head\'s %s.',
                $at->text,
                $head->generatedAt->format('Y-m-d\TH:i:sP'),
            ));
        }

        $link = $head?->link() ?? ChainLink::first();

        $record = $draft instanceof RegistrationDraft
            ? RegistrationRecord::create($draft, $link, $this->system, $at)
            : CancellationRecord::create($draft, $link, $this->system, $at);

        return [$record, ChainHead::after($record, $head)];
    }

    /**
     * Builds the next record of the chain under the store's lock and stores it.
     *
     * @throws ChainConflict when the draft's issuer or this system's installation is not the key's
     */
    public function appendTo(ChainStore $store, ChainKey $key, RegistrationDraft|CancellationDraft $draft): BillingRecord
    {
        $issuer = $draft instanceof RegistrationDraft ? $draft->id->issuer : $draft->annulled->issuer;

        if ($issuer->value !== $key->issuer->value) {
            throw new ChainConflict(sprintf(
                'The invoice issuer %s does not own chain %s.',
                $issuer->value,
                $key->toString(),
            ));
        }

        if ($this->system->installationNumber !== $key->installationNumber) {
            throw new ChainConflict(sprintf(
                'The installation number "%s" of this computer system does not match chain %s.',
                $this->system->installationNumber,
                $key->toString(),
            ));
        }

        return $store->append($key, fn (?ChainHead $head): BillingRecord => $this->append($draft, $head, ($this->clock)())[0]);
    }
}
