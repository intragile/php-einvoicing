<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Poland\Offline;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Poland\Offline\DeadlineReason;
use IntrAgile\EInvoicing\Poland\Offline\OfflineDeadline;
use IntrAgile\EInvoicing\Poland\Offline\OfflineDeadlines;
use IntrAgile\EInvoicing\Poland\Offline\OfflineMode;
use IntrAgile\EInvoicing\Poland\Offline\Outage;
use IntrAgile\EInvoicing\Poland\Offline\OutageCategory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OfflineDeadlinesTest extends TestCase
{
    public function test_offline24_next_working_day(): void
    {
        // Friday -> Monday.
        self::assertDeadline('2026-11-09', DeadlineReason::NextWorkingDay, OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Offline24));
        // The day before Independence Day (Wed 11 Nov) -> Thursday.
        self::assertDeadline('2026-11-12', DeadlineReason::NextWorkingDay, OfflineDeadlines::for(self::at('2026-11-10'), OfflineMode::Offline24));
        // The day before Christmas Eve -> past 24-26 Dec and the weekend.
        self::assertDeadline('2026-12-28', DeadlineReason::NextWorkingDay, OfflineDeadlines::for(self::at('2026-12-23'), OfflineMode::Offline24));
        // Good Friday -> past Easter Monday.
        self::assertDeadline('2026-04-07', DeadlineReason::NextWorkingDay, OfflineDeadlines::for(self::at('2026-04-03'), OfflineMode::Offline24));
    }

    public function test_unavailability_next_working_day_after_end(): void
    {
        $friday = new Outage(OutageCategory::Maintenance, self::at('2026-11-06 18:00'), self::at('2026-11-06 22:00'));
        $saturday = new Outage(OutageCategory::Maintenance, self::at('2026-11-06 22:00'), self::at('2026-11-07 03:00'));

        self::assertDeadline('2026-11-09', DeadlineReason::AfterUnavailability, OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Unavailability, cause: $friday));
        self::assertDeadline('2026-11-09', DeadlineReason::AfterUnavailability, OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Unavailability, cause: $saturday));
    }

    public function test_failure_seventh_working_day_after_end(): void
    {
        $beforeChristmas = new Outage(OutageCategory::Failure, self::at('2026-12-16 09:00'), self::at('2026-12-17 15:00'));
        $newYearsEve = new Outage(OutageCategory::Failure, self::at('2026-12-31 08:00'), self::at('2026-12-31 12:00'));

        self::assertDeadline('2026-12-30', DeadlineReason::AfterFailure, OfflineDeadlines::for(self::at('2026-12-16'), OfflineMode::Failure, cause: $beforeChristmas));
        self::assertDeadline('2027-01-13', DeadlineReason::AfterFailure, OfflineDeadlines::for(self::at('2026-12-31'), OfflineMode::Failure, cause: $newYearsEve));
    }

    public function test_documented_example_offline24_moved_by_failure(): void
    {
        // tryby-offline.md: offline24 invoice on Wed 2025-07-08, failure announced Thu 2025-07-09,
        // failure removed Sat 2025-07-12, deadline Tue 2025-07-22.
        $failure = new Outage(OutageCategory::Failure, self::at('2025-07-09 10:00'), self::at('2025-07-12 14:00'), 'evt-1');

        self::assertDeadline('2025-07-09', DeadlineReason::NextWorkingDay, OfflineDeadlines::for(self::at('2025-07-08'), OfflineMode::Offline24));
        self::assertDeadline('2025-07-22', DeadlineReason::AfterFailure, OfflineDeadlines::for(self::at('2025-07-08'), OfflineMode::Offline24, [$failure]));
    }

    public function test_failure_outside_the_window_does_not_move_the_deadline(): void
    {
        $before = new Outage(OutageCategory::Failure, self::at('2026-11-02 10:00'), self::at('2026-11-03 10:00'));
        $after = new Outage(OutageCategory::Failure, self::at('2026-11-10 00:00'), self::at('2026-11-10 10:00'));

        self::assertDeadline('2026-11-09', DeadlineReason::NextWorkingDay, OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Offline24, [$before, $after]));
    }

    public function test_maintenance_in_the_list_does_not_move_the_deadline(): void
    {
        $maintenance = new Outage(OutageCategory::Maintenance, self::at('2026-11-07 10:00'), self::at('2026-11-07 12:00'));

        self::assertDeadline('2026-11-09', DeadlineReason::NextWorkingDay, OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Offline24, [$maintenance]));
    }

    public function test_unavailability_moved_by_failure(): void
    {
        $unavailability = new Outage(OutageCategory::Maintenance, self::at('2026-11-05 18:00'), self::at('2026-11-05 22:00'));
        $failure = new Outage(OutageCategory::Failure, self::at('2026-11-06 08:00'), self::at('2026-11-06 12:00'));

        // 7th working day after Fri 6 Nov, skipping 11 Nov.
        self::assertDeadline('2026-11-18', DeadlineReason::AfterFailure, OfflineDeadlines::for(self::at('2026-11-05'), OfflineMode::Unavailability, [$failure], $unavailability));
    }

    public function test_failure_in_unavailability_window_replaces_even_an_earlier_deadline(): void
    {
        // The unavailability alone would give Mon 2026-11-23; the failure rule gives Fri 2026-11-13
        // (7 working days after Tue 3 Nov, skipping 11 Nov) and replaces it.
        $unavailability = new Outage(OutageCategory::Maintenance, self::at('2026-11-02 08:00'), self::at('2026-11-20 10:00'));
        $failure = new Outage(OutageCategory::Failure, self::at('2026-11-03 09:00'), self::at('2026-11-03 12:00'));

        self::assertDeadline('2026-11-23', DeadlineReason::AfterUnavailability, OfflineDeadlines::for(self::at('2026-11-02'), OfflineMode::Unavailability, cause: $unavailability));
        self::assertDeadline('2026-11-13', DeadlineReason::AfterFailure, OfflineDeadlines::for(self::at('2026-11-02'), OfflineMode::Unavailability, [$failure], $unavailability));
    }

    public function test_overlapping_failure_still_ongoing_leaves_no_deadline(): void
    {
        $ongoing = new Outage(OutageCategory::Failure, self::at('2026-11-09 09:00'), null, 'evt-1');
        $short = new Outage(OutageCategory::Failure, self::at('2026-11-09 12:00'), self::at('2026-11-09 14:00'), 'evt-2');

        $result = OfflineDeadlines::for(self::at('2026-11-09'), OfflineMode::Offline24, [$ongoing, $short]);

        self::assertNull($result->deadline);
        self::assertSame(DeadlineReason::FailureOngoing, $result->reason);
    }

    public function test_overlapping_failures_count_from_the_latest_end(): void
    {
        // The later-announced failure ends first; the deadline counts from Fri 2026-11-20, when the
        // earlier one ends, not from the end of the last announced one.
        $long = new Outage(OutageCategory::Failure, self::at('2026-11-09 09:00'), self::at('2026-11-20 10:00'), 'evt-1');
        $short = new Outage(OutageCategory::Failure, self::at('2026-11-09 12:00'), self::at('2026-11-09 14:00'), 'evt-2');

        self::assertDeadline('2026-12-01', DeadlineReason::AfterFailure, OfflineDeadlines::for(self::at('2026-11-09'), OfflineMode::Offline24, [$long, $short]));
        self::assertDeadline('2026-12-01', DeadlineReason::AfterFailure, OfflineDeadlines::for(self::at('2026-11-09'), OfflineMode::Offline24, [$short, $long]));
    }

    public function test_restart_never_counts_from_before_the_cause_ended(): void
    {
        // A second failure announced while the cause is still running but ending before it.
        $cause = new Outage(OutageCategory::Failure, self::at('2026-11-04 09:00'), self::at('2026-11-05 11:00'), 'evt-1');
        $inside = new Outage(OutageCategory::Failure, self::at('2026-11-04 10:00'), self::at('2026-11-04 11:00'), 'evt-2');

        self::assertDeadline('2026-11-17', DeadlineReason::AfterFailure, OfflineDeadlines::for(self::at('2026-11-04'), OfflineMode::Failure, [$cause, $inside], $cause));
    }

    public function test_unavailability_ongoing_has_no_deadline(): void
    {
        $ongoing = new Outage(OutageCategory::Maintenance, self::at('2026-11-06 09:00'), null);

        $result = OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Unavailability, cause: $ongoing);

        self::assertNull($result->deadline);
        self::assertSame(DeadlineReason::UnavailabilityOngoing, $result->reason);
    }

    public function test_second_failure_restarts_count(): void
    {
        $first = new Outage(OutageCategory::Failure, self::at('2026-11-04 09:00'), self::at('2026-11-05 11:00'), 'evt-1');
        $second = new Outage(OutageCategory::Failure, self::at('2026-11-12 09:00'), self::at('2026-11-13 11:00'), 'evt-2');
        $tooLate = new Outage(OutageCategory::Failure, self::at('2026-11-25 09:00'), self::at('2026-11-26 11:00'), 'evt-3');

        self::assertDeadline('2026-11-17', DeadlineReason::AfterFailure, OfflineDeadlines::for(self::at('2026-11-04'), OfflineMode::Failure, [$first], $first));
        self::assertDeadline('2026-11-24', DeadlineReason::AfterFailure, OfflineDeadlines::for(self::at('2026-11-04'), OfflineMode::Failure, [$first, $second, $tooLate], $first));
    }

    public function test_failure_ongoing_has_no_deadline(): void
    {
        $ongoing = new Outage(OutageCategory::Failure, self::at('2026-11-06 09:00'), null);

        $result = OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Failure, cause: $ongoing);
        self::assertNull($result->deadline);
        self::assertSame(DeadlineReason::FailureOngoing, $result->reason);

        $result = OfflineDeadlines::for(self::at('2026-11-05'), OfflineMode::Offline24, [$ongoing]);
        self::assertNull($result->deadline);
        self::assertSame(DeadlineReason::FailureOngoing, $result->reason);
    }

    public function test_total_failure_removes_duty(): void
    {
        $result = OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::TotalFailure);
        self::assertNull($result->deadline);
        self::assertSame(DeadlineReason::TotalFailure, $result->reason);

        $total = new Outage(OutageCategory::TotalFailure, self::at('2026-11-07 09:00'), self::at('2026-11-08 09:00'));
        $result = OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Offline24, [$total]);
        self::assertNull($result->deadline);
        self::assertSame(DeadlineReason::TotalFailure, $result->reason);

        // A total failure announced during the extended window of a failure also removes the duty.
        $failure = new Outage(OutageCategory::Failure, self::at('2026-11-04 09:00'), self::at('2026-11-05 11:00'));
        $laterTotal = new Outage(OutageCategory::TotalFailure, self::at('2026-11-16 09:00'), null);
        $result = OfflineDeadlines::for(self::at('2026-11-04'), OfflineMode::Failure, [$laterTotal], $failure);
        self::assertNull($result->deadline);
        self::assertSame(DeadlineReason::TotalFailure, $result->reason);
    }

    public function test_total_failure_during_ongoing_failure_removes_duty(): void
    {
        $ongoing = new Outage(OutageCategory::Failure, self::at('2026-11-06 09:00'), null);
        $total = new Outage(OutageCategory::TotalFailure, self::at('2026-11-20 09:00'), null);

        $result = OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Failure, [$total], $ongoing);

        self::assertNull($result->deadline);
        self::assertSame(DeadlineReason::TotalFailure, $result->reason);
    }

    public function test_deadline_is_end_of_day_warsaw(): void
    {
        // Thursday 23:30 UTC is Friday 00:30 in Warsaw, so the invoice day is Friday.
        $issued = new DateTimeImmutable('2026-11-05 23:30:00', new DateTimeZone('UTC'));

        $deadline = OfflineDeadlines::for($issued, OfflineMode::Offline24)->deadline;

        self::assertNotNull($deadline);
        self::assertSame('2026-11-09 23:59:59.999999', $deadline->format('Y-m-d H:i:s.u'));
        self::assertSame('Europe/Warsaw', $deadline->getTimezone()->getName());
    }

    public function test_cause_is_required_and_must_match_the_mode_and_year_is_checked(): void
    {
        $failure = new Outage(OutageCategory::Failure, self::at('2026-11-06 09:00'), self::at('2026-11-06 10:00'));
        $maintenance = new Outage(OutageCategory::Maintenance, self::at('2026-11-06 09:00'), self::at('2026-11-06 10:00'));

        foreach ([
            static fn (): mixed => OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Unavailability),
            static fn (): mixed => OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Failure),
            static fn (): mixed => OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Unavailability, cause: $failure),
            static fn (): mixed => OfflineDeadlines::for(self::at('2026-11-06'), OfflineMode::Failure, cause: $maintenance),
            static fn (): mixed => OfflineDeadlines::for(self::at('2024-12-31'), OfflineMode::TotalFailure),
            static fn (): mixed => OfflineDeadlines::for(self::at('2100-01-04'), OfflineMode::Offline24),
        ] as $index => $call) {
            try {
                $call();
                self::fail(sprintf('Call #%d did not throw.', $index));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private static function assertDeadline(string $expectedDay, DeadlineReason $expectedReason, OfflineDeadline $actual): void
    {
        self::assertSame($expectedReason, $actual->reason);
        self::assertNotNull($actual->deadline);
        self::assertSame($expectedDay.' 23:59:59.999999', $actual->deadline->format('Y-m-d H:i:s.u'));
    }

    private static function at(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('Europe/Warsaw'));
    }
}
