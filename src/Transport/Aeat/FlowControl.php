<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;

/**
 * The AEAT flow-control policy (Orden HAC/1177/2024 art. 16.2; TiempoEsperaEnvio).
 *
 * The first send of a throttle key is allowed at once. Afterwards the next send is allowed when the
 * wait has elapsed, or at once when a full submission (1,000 records) is pending. The wait is 60
 * seconds until a response states one. The policy is pure: it never sleeps and performs no I/O; the
 * caller supplies the state and applies the decision.
 */
final class FlowControl
{
    public const INITIAL_WAIT_SECONDS = 60;

    /** The most records one submission may carry. */
    public const MAX_RECORDS = 1000;

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $clock;

    /**
     * @param  (Closure(): DateTimeImmutable)|null  $clock  defaults to the system clock
     */
    public function __construct(?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable;
    }

    public function decide(ThrottleState $state, int $pending): SendDecision
    {
        if ($pending <= 0) {
            return new SendDecision(false, null, 0);
        }

        $batch = min($pending, self::MAX_RECORDS);
        $next = $state->nextSendAt();

        if ($next === null || $pending >= self::MAX_RECORDS || ($this->clock)() >= $next) {
            return new SendDecision(true, null, $batch);
        }

        return new SendDecision(false, $next, 0);
    }

    /** The state after AEAT answered: the wait it asked for starts from the send. */
    public function afterResponse(ThrottleState $state, SubmissionResponse $response, DateTimeImmutable $sentAt): ThrottleState
    {
        return new ThrottleState($sentAt, $response->waitSeconds);
    }

    /** The state after a fault or an unknown outcome: the last wait is kept and restarts from the attempt. */
    public function afterFailure(ThrottleState $state, DateTimeImmutable $attemptedAt): ThrottleState
    {
        return new ThrottleState($attemptedAt, $state->waitSeconds);
    }

    /**
     * Groups pending records by issuer (in the order each issuer first appears), keeps the given
     * order inside a group and cuts each group into submissions of at most 1,000 records.
     *
     * @param  list<BillingRecord>  $records
     * @return list<list<BillingRecord>>
     */
    public static function batches(array $records): array
    {
        /** @var array<string, list<BillingRecord>> $groups */
        $groups = [];
        foreach ($records as $record) {
            $groups[$record->invoiceId()->issuer->value][] = $record;
        }

        $batches = [];
        foreach ($groups as $group) {
            foreach (array_chunk($group, self::MAX_RECORDS) as $chunk) {
                $batches[] = $chunk;
            }
        }

        return $batches;
    }
}
