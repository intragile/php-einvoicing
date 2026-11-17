<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\EReporting;

use DateTimeImmutable;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleParty;
use IntrAgile\EInvoicing\France\Lifecycle\PartyRole;
use InvalidArgumentException;

/**
 * A Flux 10 e-reporting transmission (TB-0): the transmission header and exactly one report,
 * either transactions (TB-2) or payments (TB-3).
 *
 * The sender is the approved platform (PA) that transmits the file, identified by its
 * four-character matricule (scheme 0238, role WK), see LifecycleParty::platform().
 */
final readonly class EReport
{
    /** Longest transmission identifier (TT-1) the rules accept. */
    public const MAX_ID_LENGTH = 50;

    /**
     * @param  string  $id  transmission identifier (TT-1)
     * @param  DateTimeImmutable  $issuedAt  creation time (TT-3)
     * @param  LifecycleParty  $platform  sender (TG-3)
     * @param  Declarant  $declarant  declarant (TG-5)
     * @param  string|null  $name  transmission name (TT-2)
     */
    private function __construct(
        public string $id,
        public DateTimeImmutable $issuedAt,
        public ReportType $type,
        public LifecycleParty $platform,
        public Declarant $declarant,
        public ?TransactionsReport $transactions,
        public ?PaymentsReport $payments,
        public ?string $name,
    ) {
        if ($id === '' || mb_strlen($id) > self::MAX_ID_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The transmission identifier (TT-1) must have 1 to %d characters, %d given.',
                self::MAX_ID_LENGTH,
                mb_strlen($id),
            ));
        }
        self::assertPlatform($platform);
    }

    /**
     * A transmission of a transactions report (flows 10.1 and 10.3).
     *
     * @throws InvalidArgumentException when the identifier is empty or longer than 50 characters
     *                                  or the sender is not an approved platform with a name
     */
    public static function transactions(
        string $id,
        DateTimeImmutable $issuedAt,
        ReportType $type,
        LifecycleParty $platform,
        Declarant $declarant,
        TransactionsReport $report,
        ?string $name = null,
    ): self {
        return new self($id, $issuedAt, $type, $platform, $declarant, $report, null, $name);
    }

    /**
     * A transmission of a payments report (flows 10.2 and 10.4).
     *
     * @throws InvalidArgumentException when the identifier is empty or longer than 50 characters
     *                                  or the sender is not an approved platform with a name
     */
    public static function payments(
        string $id,
        DateTimeImmutable $issuedAt,
        ReportType $type,
        LifecycleParty $platform,
        Declarant $declarant,
        PaymentsReport $report,
        ?string $name = null,
    ): self {
        return new self($id, $issuedAt, $type, $platform, $declarant, null, $report, $name);
    }

    private static function assertPlatform(LifecycleParty $platform): void
    {
        $id = $platform->globalId;
        if ($id === null || $id->scheme !== LifecycleParty::MATRICULE_SCHEME) {
            throw new InvalidArgumentException(sprintf(
                'The sender of a transmission (TT-8) is identified by a platform matricule with scheme %s; use LifecycleParty::platform().',
                LifecycleParty::MATRICULE_SCHEME,
            ));
        }
        if (mb_strlen($id->value) !== 4) {
            throw new InvalidArgumentException(sprintf('The platform matricule (TT-8) has 4 characters, "%s" given.', $id->value));
        }
        if ($platform->role !== PartyRole::Platform) {
            throw new InvalidArgumentException(sprintf(
                'The sender of a transmission (TT-10) has role %s, %s given.',
                PartyRole::Platform->value,
                $platform->role->value,
            ));
        }
        if ($platform->name === null || $platform->name === '') {
            throw new InvalidArgumentException('The sender of a transmission needs a name (TT-9).');
        }
    }
}
