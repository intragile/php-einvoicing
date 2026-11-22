<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland\Offline;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Computes the deadline for sending an offline invoice to KSeF.
 *
 * The rules follow the Ministry of Finance's KSeF 2.0 documentation of the offline modes; all
 * days are taken in Europe/Warsaw, and a deadline is the last instant of its day:
 *
 * - Offline24: the first working day after the issue date (P_1).
 * - Unavailability: the first working day after the day the unavailability ended.
 * - Failure: the 7th working day after the day the failure ended.
 * - A failure announced (started) while the invoice is pending, that is between the issue date
 *   and the current deadline, moves the deadline to the failure rule counted from the end of the
 *   last such failure; this repeats as long as further failures fall inside the extended window.
 *   A failure never brings a deadline forward: when the failure rule gives an earlier day than
 *   the deadline it would replace, the later deadline is kept.
 * - A failure that has not ended yet leaves the deadline unknown (FailureOngoing).
 * - A total failure, as the mode or announced while the invoice is pending, removes the duty to
 *   send (TotalFailure).
 *
 * This is a calculation aid, not legal advice.
 */
final class OfflineDeadlines
{
    private const FAILURE_WORKING_DAYS = 7;

    /**
     * @param  list<Outage>  $outages  announced outages, for example folded from Latarnia messages;
     *                                 maintenance entries are ignored
     * @param  Outage|null  $cause  the outage the invoice was issued under: required for the
     *                              Unavailability (a Maintenance outage that has ended) and Failure
     *                              (a Failure outage) modes, ignored otherwise
     *
     * @throws InvalidArgumentException when the cause is missing or does not match the mode, or a
     *                                  day falls outside the supported years
     */
    public static function for(
        DateTimeImmutable $issueDate,
        OfflineMode $mode,
        array $outages = [],
        ?Outage $cause = null,
    ): OfflineDeadline {
        if ($mode === OfflineMode::TotalFailure) {
            return new OfflineDeadline(null, DeadlineReason::TotalFailure);
        }

        $windowStart = $issueDate->setTimezone(new DateTimeZone('Europe/Warsaw'))->setTime(0, 0);

        $result = match ($mode) {
            OfflineMode::Offline24 => self::endOfDay(PolishWorkingDays::addWorkingDays($issueDate, 1), DeadlineReason::NextWorkingDay),
            OfflineMode::Unavailability => self::afterUnavailability(self::requireCause($mode, $cause, OutageCategory::Maintenance)),
            OfflineMode::Failure => self::afterFailure(self::requireCause($mode, $cause, OutageCategory::Failure)),
        };

        while ($result->deadline !== null) {
            $deadline = $result->deadline;
            $pending = array_values(array_filter(
                $outages,
                static fn (Outage $outage): bool => $outage != $cause
                    && $outage->start >= $windowStart
                    && $outage->start <= $deadline,
            ));

            foreach ($pending as $outage) {
                if ($outage->category === OutageCategory::TotalFailure) {
                    return new OfflineDeadline(null, DeadlineReason::TotalFailure);
                }
            }

            $last = null;

            foreach ($pending as $outage) {
                if ($outage->category === OutageCategory::Failure && ($last === null || $outage->start > $last->start)) {
                    $last = $outage;
                }
            }

            if ($last === null) {
                return $result;
            }

            $next = self::afterFailure($last);

            if ($next->deadline === null || $next->deadline > $deadline) {
                $result = $next;

                continue;
            }

            return $result;
        }

        // While a failure is ongoing the pending window has no end, so any later total failure counts.
        foreach ($outages as $outage) {
            if ($outage->category === OutageCategory::TotalFailure && $outage->start >= $windowStart) {
                return new OfflineDeadline(null, DeadlineReason::TotalFailure);
            }
        }

        return $result;
    }

    private static function requireCause(OfflineMode $mode, ?Outage $cause, OutageCategory $category): Outage
    {
        if ($cause === null || $cause->category !== $category) {
            throw new InvalidArgumentException(sprintf(
                'The %s mode needs the %s outage the invoice was issued under as its cause.',
                $mode->name,
                $category->name,
            ));
        }

        return $cause;
    }

    private static function afterUnavailability(Outage $unavailability): OfflineDeadline
    {
        if ($unavailability->end === null) {
            throw new InvalidArgumentException('The unavailability has not ended, so no deadline can be computed yet.');
        }

        return self::endOfDay(
            PolishWorkingDays::addWorkingDays($unavailability->end, 1),
            DeadlineReason::AfterUnavailability,
        );
    }

    private static function afterFailure(Outage $failure): OfflineDeadline
    {
        if ($failure->end === null) {
            return new OfflineDeadline(null, DeadlineReason::FailureOngoing);
        }

        return self::endOfDay(
            PolishWorkingDays::addWorkingDays($failure->end, self::FAILURE_WORKING_DAYS),
            DeadlineReason::AfterFailure,
        );
    }

    private static function endOfDay(DateTimeImmutable $day, DeadlineReason $reason): OfflineDeadline
    {
        return new OfflineDeadline($day->setTime(23, 59, 59, 999999), $reason);
    }
}
