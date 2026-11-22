<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Latarnia;

use IntrAgile\EInvoicing\Poland\Offline\Outage;

/**
 * Folds Latarnia messages into outages, one per event.
 *
 * Messages are grouped by event id. Of several revisions of the same message (same id), the one
 * with the highest version counts. A maintenance announcement carries its own window. A failure
 * starts at its start message and ends at the end its end message gives, or else at the end
 * Latarnia copied onto the start message; with neither, it has not ended. An event whose start
 * message has already dropped out of the list is taken from its end message alone, which repeats
 * the start.
 */
final class OutageEvents
{
    /**
     * @param  list<LatarniaMessage>  $messages
     * @return list<Outage> ordered by start, then by event id
     */
    public static function fold(array $messages): array
    {
        /** @var array<string, LatarniaMessage> $latest */
        $latest = [];
        foreach ($messages as $message) {
            $current = $latest[$message->id] ?? null;
            if ($current === null || self::newer($message, $current)) {
                $latest[$message->id] = $message;
            }
        }

        /** @var array<int, array{opening: ?LatarniaMessage, closing: ?LatarniaMessage}> $events */
        $events = [];
        foreach ($latest as $message) {
            $event = $events[$message->eventId] ?? ['opening' => null, 'closing' => null];
            $slot = $message->type->opensEvent() ? 'opening' : 'closing';
            if ($event[$slot] === null || self::newer($message, $event[$slot])) {
                $event[$slot] = $message;
            }
            $events[$message->eventId] = $event;
        }

        $outages = [];
        foreach ($events as $eventId => ['opening' => $opening, 'closing' => $closing]) {
            $first = $opening ?? $closing;
            if ($first === null) {
                continue;
            }
            // An end message always carries an end time; LatarniaMessage refuses one without.
            $end = $closing !== null ? $closing->end : $opening?->end;
            $outages[] = new Outage($first->category, $first->start, $end, (string) $eventId);
        }

        usort($outages, static fn (Outage $a, Outage $b): int => [$a->start, (int) $a->id] <=> [$b->start, (int) $b->id]);

        return $outages;
    }

    /**
     * Whether a message replaces another: a higher version, or the same version published later.
     */
    private static function newer(LatarniaMessage $candidate, LatarniaMessage $current): bool
    {
        return $candidate->version > $current->version
            || ($candidate->version === $current->version && $candidate->published > $current->published);
    }
}
