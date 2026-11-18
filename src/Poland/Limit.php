<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use InvalidArgumentException;

/**
 * Occurrence checks shared by the FA(3) value objects, mirroring the minOccurs and maxOccurs of
 * the schema.
 *
 * @internal
 */
final class Limit
{
    /**
     * @param  array<mixed>  $items
     * @param  string  $element  FA(3) element named in the error message
     *
     * @throws InvalidArgumentException when the list holds fewer than $min or more than $max items
     */
    public static function count(array $items, string $element, int $max, int $min = 0): void
    {
        $count = count($items);
        if ($count < $min || $count > $max) {
            throw new InvalidArgumentException(sprintf(
                '%s occurs %d times; FA(3) allows %s.',
                $element,
                $count,
                $min === 0 ? 'at most '.$max : sprintf('%d to %d', $min, $max),
            ));
        }
    }

    private function __construct() {}
}
