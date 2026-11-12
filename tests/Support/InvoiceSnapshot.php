<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use BackedEnum;
use Brick\Math\BigDecimal;
use DateTimeInterface;
use IntrAgile\EInvoicing\Model\Invoice;

/**
 * Flattens an invoice into "dotted.path" => value pairs so two models can be compared and their
 * differences named precisely.
 *
 * Decimals are compared by value ("19.9" equals "19.90"), dates by their calendar day and enums by
 * their code. Null values are left out, so an absent group and an empty term compare alike.
 */
final class InvoiceSnapshot
{
    /**
     * @return array<string, scalar>
     */
    public static function of(Invoice $invoice): array
    {
        $flat = [];
        self::flatten($invoice, '', $flat);
        ksort($flat);

        return $flat;
    }

    /**
     * @param  array<string, scalar>  $flat
     */
    private static function flatten(mixed $value, string $path, array &$flat): void
    {
        if ($value === null) {
            return;
        }
        if (is_scalar($value)) {
            $flat[$path] = $value;

            return;
        }
        if ($value instanceof BigDecimal) {
            $flat[$path] = (string) $value->strippedOfTrailingZeros();

            return;
        }
        if ($value instanceof DateTimeInterface) {
            $flat[$path] = $value->format('Y-m-d');

            return;
        }
        if ($value instanceof BackedEnum) {
            $flat[$path] = $value->value;

            return;
        }

        $children = is_array($value) ? $value : (is_object($value) ? get_object_vars($value) : []);
        $prefix = $path === '' ? '' : $path.'.';
        foreach ($children as $key => $child) {
            self::flatten($child, $prefix.$key, $flat);
        }
    }
}
