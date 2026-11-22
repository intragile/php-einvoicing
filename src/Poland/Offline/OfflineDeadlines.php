<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland\Offline;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;

/**
 * Computes the deadline for sending an offline invoice to KSeF.
 *
 * The rules follow the Ministry of Finance's KSeF 2.0 documentation of the offline modes; all
 * days are taken in Europe/Warsaw, and a deadline is the last instant of its day:
 *
 * - Offline24: the first working day after the issue date (P_1).
 * - Unavailability: the first working day after the day the unavailability ended; while it has
 *   not ended the deadline is unknown (UnavailabilityOngoing).
 * - Failure: the 7th working day after the day the failure ended; while it has not ended the
 *   deadline is unknown (FailureOngoing).
 * - A failure announced (started) while the invoice is pending, that is between the start of the
 *   issue day and the current deadline, replaces the deadline with the failure rule, even when
 *   that gives an earlier day (the deadline "is moved and counted from the end of the last
 *   announced failure"). The failures taken into account are every failure overlapping the
 *   pending window, including the cause in Failure mode: if any of them has not ended, the
 *   deadline is unknown (FailureOngoing); otherwise the 7 working days count from the latest end
 *   among them, so overlapping failures never yield a deadline before an announced failure is
 *   over. This repeats while further failures start inside the extended window, so a new
 *   failure announcement restarts the count.
 * - A total failure, as the mode or announced while the invoice is pending, removes the duty to
 *   send (TotalFailure).
 *
 * A failure that was already running before the issue day is out of scope for an offline24
 * invoice on its own, because an invoice issued during an announced failure is a Failure-mode
 * invoice; it only counts when another failure is announced inside the pending window.
 *
 * "Announced" is read as the outage's start (Outage::$start), not the time Latarnia published
 * the message: a failure that starts inside the pending window counts even when it was published
 * later, and one that started before the issue day does not, even when it was published inside
 * the window.
 *
 * The issue date is an instant, converted to Europe/Warsaw before its day is taken. Pass P_1 as
 * midnight Europe/Warsaw: an instant late in the evening UTC can fall on the next day in Warsaw,
 * while the QR links use the calendar date of the value as given.
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
     *                              Unavailability (a Maintenance outage) and Failure
     *                              (a Failure outage) modes, ignored otherwise
     *
     * @throws InvalidArgumentException when the cause is missing or does not match the mode, or the
     *                                  issue date or a day counted falls outside the supported years
     */
    public static function for(
        DateTimeImmutable $issueDate,
        OfflineMode $mode,
        array $outages = [],
        ?Outage $cause = null,
    ): OfflineDeadline {
        $windowStart = $issueDate->setTimezone(new DateTimeZone('Europe/Warsaw'))->setTime(0, 0);
        PolishWorkingDays::assertSupportedYear((int) $windowStart->format('Y'));

        if ($mode === OfflineMode::TotalFailure) {
            return new OfflineDeadline(null, DeadlineReason::TotalFailure);
        }

        $result = match ($mode) {
            OfflineMode::Offline24 => self::endOfDay(PolishWorkingDays::addWorkingDays($issueDate, 1), DeadlineReason::NextWorkingDay),
            OfflineMode::Unavailability => self::afterUnavailability(self::requireCause($mode, $cause, OutageCategory::Maintenance)),
            OfflineMode::Failure => self::afterFailure(self::requireCause($mode, $cause, OutageCategory::Failure)),
        };

        $failures = array_values(array_filter(
            $cause !== null && $cause->category === OutageCategory::Failure ? [$cause, ...$outages] : $outages,
            static fn (Outage $outage): bool => $outage->category === OutageCategory::Failure,
        ));

        while ($result->deadline !== null) {
            $deadline = $result->deadline;

            foreach ($outages as $outage) {
                if ($outage->category === OutageCategory::TotalFailure && $outage->start >= $windowStart && $outage->start <= $deadline) {
                    return new OfflineDeadline(null, DeadlineReason::TotalFailure);
                }
            }

            $announced = array_filter(
                $failures,
                static fn (Outage $failure): bool => $failure != $cause
                    && $failure->start >= $windowStart
                    && $failure->start <= $deadline,
            );

            if ($announced === []) {
                return $result;
            }

            // Every announced failure overlaps the window, so this list is never empty.
            $overlapping = array_values(array_filter(
                $failures,
                static fn (Outage $failure): bool => $failure->start <= $deadline
                    && ($failure->end === null || $failure->end >= $windowStart),
            ));
            $next = self::afterLatestEnd($overlapping);

            if ($next->deadline !== null && $result->reason === DeadlineReason::AfterFailure && $next->deadline == $deadline) {
                return $result;
            }

            $result = $next;
        }

        // While the deadline is unknown the pending window has no end, so any later total failure counts.
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
            return new OfflineDeadline(null, DeadlineReason::UnavailabilityOngoing);
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

    /**
     * The failure rule counted from the latest end among overlapping failures.
     *
     * @param  list<Outage>  $failures
     */
    private static function afterLatestEnd(array $failures): OfflineDeadline
    {
        $latest = null;

        foreach ($failures as $failure) {
            if ($failure->end === null) {
                return new OfflineDeadline(null, DeadlineReason::FailureOngoing);
            }

            if ($latest === null || $failure->end > $latest->end) {
                $latest = $failure;
            }
        }

        if ($latest === null) {
            throw new LogicException('No failure to count the deadline from.');
        }

        return self::afterFailure($latest);
    }

    private static function endOfDay(DateTimeImmutable $day, DeadlineReason $reason): OfflineDeadline
    {
        return new OfflineDeadline($day->setTime(23, 59, 59, 999999), $reason);
    }
}
