<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Latarnia;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Poland\Offline\Outage;
use IntrAgile\EInvoicing\Poland\Offline\OutageCategory;
use IntrAgile\EInvoicing\Transport\Ksef\Latarnia\LatarniaMessage;
use IntrAgile\EInvoicing\Transport\Ksef\Latarnia\OutageEvents;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OutageEventsTest extends TestCase
{
    public function test_fold_pairs_start_and_end_by_event_id(): void
    {
        $outages = OutageEvents::fold([
            self::message('F/2', 7, OutageCategory::Failure, LatarniaMessage::TYPE_FAILURE_END, '2030-04-09T06:15:00Z', '2030-04-09T13:40:00Z'),
            self::message('F/9', 8, OutageCategory::Failure, LatarniaMessage::TYPE_FAILURE_START, '2030-04-10T08:00:00Z', null),
            // The start message before Latarnia copied the end onto it.
            self::message('F/1', 7, OutageCategory::Failure, LatarniaMessage::TYPE_FAILURE_START, '2030-04-09T06:15:00Z', null),
        ]);

        self::assertEquals([
            new Outage(OutageCategory::Failure, self::at('2030-04-09T06:15:00Z'), self::at('2030-04-09T13:40:00Z'), '7'),
            new Outage(OutageCategory::Failure, self::at('2030-04-10T08:00:00Z'), null, '8'),
        ], $outages);
    }

    public function test_fold_keeps_ongoing_failure_open(): void
    {
        $outages = OutageEvents::fold([
            self::message('T/1', 12, OutageCategory::TotalFailure, LatarniaMessage::TYPE_FAILURE_START, '2030-01-20T10:00:00Z', null),
        ]);

        self::assertEquals([new Outage(OutageCategory::TotalFailure, self::at('2030-01-20T10:00:00Z'), null, '12')], $outages);
    }

    public function test_start_message_with_copied_end_closes_the_outage(): void
    {
        // The end message may already have dropped out while the updated start message remains.
        $outages = OutageEvents::fold([
            self::message('F/1', 7, OutageCategory::Failure, LatarniaMessage::TYPE_FAILURE_START, '2030-04-09T06:15:00Z', '2030-04-09T13:40:00Z'),
        ]);

        self::assertEquals([new Outage(OutageCategory::Failure, self::at('2030-04-09T06:15:00Z'), self::at('2030-04-09T13:40:00Z'), '7')], $outages);
    }

    public function test_end_message_alone_gives_the_whole_outage(): void
    {
        $outages = OutageEvents::fold([
            self::message('F/2', 7, OutageCategory::Failure, LatarniaMessage::TYPE_FAILURE_END, '2030-04-09T06:15:00Z', '2030-04-09T13:40:00Z'),
        ]);

        self::assertEquals([new Outage(OutageCategory::Failure, self::at('2030-04-09T06:15:00Z'), self::at('2030-04-09T13:40:00Z'), '7')], $outages);
    }

    public function test_end_message_without_end_closes_at_its_publication(): void
    {
        $outages = OutageEvents::fold([
            self::message('F/1', 7, OutageCategory::Failure, LatarniaMessage::TYPE_FAILURE_START, '2030-04-09T06:15:00Z', null),
            self::message('F/2', 7, OutageCategory::Failure, LatarniaMessage::TYPE_FAILURE_END, '2030-04-09T06:15:00Z', null, published: '2030-04-09T14:00:00Z'),
        ]);

        self::assertEquals([new Outage(OutageCategory::Failure, self::at('2030-04-09T06:15:00Z'), self::at('2030-04-09T14:00:00Z'), '7')], $outages);
    }

    public function test_maintenance_uses_own_window(): void
    {
        $outages = OutageEvents::fold([
            self::message('M/1', 3, OutageCategory::Maintenance, LatarniaMessage::TYPE_MAINTENANCE_ANNOUNCEMENT, '2030-04-06T22:00:00Z', '2030-04-07T03:30:00Z'),
        ]);

        self::assertEquals([new Outage(OutageCategory::Maintenance, self::at('2030-04-06T22:00:00Z'), self::at('2030-04-07T03:30:00Z'), '3')], $outages);
    }

    public function test_higher_version_wins(): void
    {
        // A maintenance window extended while it ran: version 2 replaces the end of version 1.
        $outages = OutageEvents::fold([
            self::message('M/1', 3, OutageCategory::Maintenance, LatarniaMessage::TYPE_MAINTENANCE_ANNOUNCEMENT, '2030-04-06T22:00:00Z', '2030-04-07T05:00:00Z', version: 2),
            self::message('M/1', 3, OutageCategory::Maintenance, LatarniaMessage::TYPE_MAINTENANCE_ANNOUNCEMENT, '2030-04-06T22:00:00Z', '2030-04-07T03:30:00Z', version: 1),
            self::message('F/2', 7, OutageCategory::Failure, LatarniaMessage::TYPE_FAILURE_END, '2030-04-09T06:15:00Z', '2030-04-09T12:00:00Z', version: 1),
            self::message('F/2', 7, OutageCategory::Failure, LatarniaMessage::TYPE_FAILURE_END, '2030-04-09T06:15:00Z', '2030-04-09T13:40:00Z', version: 3),
            self::message('F/2', 7, OutageCategory::Failure, LatarniaMessage::TYPE_FAILURE_END, '2030-04-09T06:15:00Z', '2030-04-09T12:30:00Z', version: 2),
        ]);

        self::assertEquals([
            new Outage(OutageCategory::Maintenance, self::at('2030-04-06T22:00:00Z'), self::at('2030-04-07T05:00:00Z'), '3'),
            new Outage(OutageCategory::Failure, self::at('2030-04-09T06:15:00Z'), self::at('2030-04-09T13:40:00Z'), '7'),
        ], $outages);
    }

    public function test_outages_are_ordered_by_start(): void
    {
        $outages = OutageEvents::fold([
            self::message('F/9', 8, OutageCategory::Failure, LatarniaMessage::TYPE_FAILURE_START, '2030-04-10T08:00:00Z', null),
            self::message('M/1', 3, OutageCategory::Maintenance, LatarniaMessage::TYPE_MAINTENANCE_ANNOUNCEMENT, '2030-04-06T22:00:00Z', '2030-04-07T03:30:00Z'),
            self::message('T/1', 12, OutageCategory::TotalFailure, LatarniaMessage::TYPE_FAILURE_START, '2030-04-08T10:00:00Z', null),
        ]);

        self::assertSame(['3', '12', '8'], array_map(static fn (Outage $o): ?string => $o->id, $outages));
    }

    public function test_empty_list_folds_to_nothing(): void
    {
        self::assertSame([], OutageEvents::fold([]));
    }

    public function test_message_refuses_an_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::message('F/1', 7, OutageCategory::Failure, 'FAILURE_UPDATE', '2030-04-09T06:15:00Z', null);
    }

    private static function message(
        string $id,
        int $eventId,
        OutageCategory $category,
        string $type,
        string $start,
        ?string $end,
        int $version = 1,
        string $published = '2030-04-01T00:00:00Z',
    ): LatarniaMessage {
        return new LatarniaMessage(
            $id,
            $eventId,
            $category,
            $type,
            'Sample title',
            'Sample text.',
            self::at($start),
            $end === null ? null : self::at($end),
            $version,
            self::at($published),
        );
    }

    private static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time);
    }
}
