<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Inbound;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\CursorWindow;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InMemoryCursorStore;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InvoiceMetadata;
use PHPUnit\Framework\TestCase;

final class CursorWindowTest extends TestCase
{
    private const FROM = '2025-08-28T00:00:00+00:00';

    private const HWM = '2025-08-28T12:00:00.000+00:00';

    public function test_cursor_advances_only_when_window_complete(): void
    {
        $store = new InMemoryCursorStore;
        $window = CursorWindow::start(new DateTimeImmutable(self::FROM))->withResults([
            self::invoice('A', '2025-08-28T09:00:00+00:00'),
            self::invoice('B', '2025-08-28T10:00:00+00:00'),
            self::invoice('C', '2025-08-28T11:00:00+00:00'),
        ], new DateTimeImmutable(self::HWM), false);
        $window = $window->acknowledge('A')->acknowledge('B');
        $store->save('inbox', $window);

        $loaded = $store->window('inbox');
        self::assertNotNull($loaded);
        self::assertFalse($loaded->isComplete());
        self::assertSame(['C'], $loaded->pending());
        $same = $loaded->next();
        self::assertEquals(new DateTimeImmutable(self::FROM), $same->from);
        self::assertSame(['A' => true, 'B' => true, 'C' => false], $same->ids);

        $complete = $loaded->acknowledge('C');
        self::assertTrue($complete->isComplete());
        $next = $complete->next();
        self::assertEquals(new DateTimeImmutable(self::HWM), $next->from);
        self::assertNull($next->hwm);
        self::assertSame([], $next->ids);
        self::assertFalse($next->isComplete());
    }

    public function test_truncated_window_narrows_to_last_date(): void
    {
        $window = CursorWindow::start(new DateTimeImmutable(self::FROM))->withResults([
            self::invoice('A', '2025-08-28T09:00:00.123456+00:00'),
            self::invoice('B', '2025-08-28T10:30:00.654321+00:00'),
        ], new DateTimeImmutable(self::HWM), true);

        self::assertEquals(new DateTimeImmutable('2025-08-28T10:30:00.654321+00:00'), $window->hwm);
        $next = $window->acknowledge('A')->acknowledge('B')->next();
        self::assertEquals(new DateTimeImmutable('2025-08-28T10:30:00.654321+00:00'), $next->from);
    }

    public function test_repeated_results_keep_acknowledgements_and_add_new_ids(): void
    {
        $window = CursorWindow::start(new DateTimeImmutable(self::FROM))
            ->withResults([self::invoice('A', '2025-08-28T09:00:00+00:00')], new DateTimeImmutable(self::HWM), false)
            ->acknowledge('A')
            ->withResults([self::invoice('A', '2025-08-28T09:00:00+00:00'), self::invoice('B', '2025-08-28T12:30:00+00:00')], new DateTimeImmutable('2025-08-28T13:00:00+00:00'), false);

        self::assertSame(['A' => true, 'B' => false], $window->ids);
        self::assertEquals(new DateTimeImmutable('2025-08-28T13:00:00+00:00'), $window->hwm);
        self::assertEquals(new DateTimeImmutable(self::FROM), $window->from);
    }

    public function test_empty_window_with_hwm_is_complete_and_unknown_ids_are_ignored(): void
    {
        $window = CursorWindow::start(new DateTimeImmutable(self::FROM));
        self::assertFalse($window->isComplete());

        $empty = $window->withResults([], new DateTimeImmutable(self::HWM), false)->acknowledge('unknown');
        self::assertSame([], $empty->ids);
        self::assertTrue($empty->isComplete());
    }

    public function test_in_memory_store_keeps_windows_per_key(): void
    {
        $store = new InMemoryCursorStore;
        self::assertNull($store->window('a'));

        $first = CursorWindow::start(new DateTimeImmutable(self::FROM));
        $second = CursorWindow::start(new DateTimeImmutable(self::HWM));
        $store->save('a', $first);
        $store->save('b', $second);

        self::assertSame($first, $store->window('a'));
        self::assertSame($second, $store->window('b'));
    }

    private static function invoice(string $ksefNumber, string $permanentStorageDate): InvoiceMetadata
    {
        return new InvoiceMetadata($ksefNumber, 'FA/'.$ksefNumber, '5555555555', new DateTimeImmutable($permanentStorageDate));
    }
}
