<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

use IntrAgile\EInvoicing\Spain\Verifactu\Amount;

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
    ) {}
}
