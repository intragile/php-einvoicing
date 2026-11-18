<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use InvalidArgumentException;

/**
 * The order or contract an advance invoice refers to (Zamowienie).
 */
final readonly class Order
{
    public const MAX_LINES = 10000;

    /** Total value of the order including VAT (WartoscZamowienia). */
    public BigDecimal $value;

    /**
     * @param  BigDecimal|numeric-string|int  $value
     * @param  list<OrderLine>  $lines  Order lines (ZamowienieWiersz), 1 to 10000.
     *
     * @throws InvalidArgumentException when there are no lines or more than 10000
     */
    public function __construct(
        BigDecimal|string|int|float $value,
        public array $lines,
    ) {
        $this->value = Decimal::of($value, 'WartoscZamowienia');
        Limit::count($lines, 'ZamowienieWiersz', self::MAX_LINES, 1);
    }
}
