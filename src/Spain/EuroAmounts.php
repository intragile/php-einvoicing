<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * The EUR amounts of an invoice issued in another currency.
 *
 * VERI*FACTU records are written in euro only; the breakdown is keyed like
 * {@see SpanishInvoice::$breakdown}.
 */
final readonly class EuroAmounts
{
    /**
     * @param  array<string, array{base: Amount, quota: ?Amount}>  $breakdown
     */
    public function __construct(
        public Amount $totalTax,
        public Amount $total,
        public array $breakdown,
    ) {
        foreach ($breakdown as $key => $entry) {
            SpanishInvoice::assertBreakdownKey((string) $key);
            self::assertEntry($key, $entry);
        }
    }

    /**
     * Guards callers that bypass the static array shape.
     */
    private static function assertEntry(int|string $key, mixed $entry): void
    {
        if (! is_array($entry)
            || ! array_key_exists('base', $entry)
            || ! array_key_exists('quota', $entry)
            || ! $entry['base'] instanceof Amount
            || ($entry['quota'] !== null && ! $entry['quota'] instanceof Amount)) {
            throw new InvalidRecord(sprintf('The EUR breakdown "%s" needs a base amount and a quota amount or null.', $key));
        }
    }
}
