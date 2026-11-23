<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu\Exception;

use InvalidArgumentException;

/**
 * An EN 16931 invoice that cannot be mapped to a VERI*FACTU registration record without guessing.
 *
 * Every problem found is listed at once, each as "<AEAT field or business term>: <reason>".
 */
final class MappingRefused extends InvalidArgumentException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct("The invoice cannot be mapped to a VERI*FACTU registration record:\n- ".implode("\n- ", $problems));
    }
}
