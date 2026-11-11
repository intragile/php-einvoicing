<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use IntrAgile\EInvoicing\CodeList\IdentifierScheme;

/**
 * Identifier with an optional scheme qualifier.
 *
 * Used for party identifiers (BT-29, BT-46, BT-60 with ISO 6523 schemes), legal registration
 * identifiers (BT-30, BT-47, BT-61), the deliver-to location (BT-71), the item standard
 * identifier (BT-157) and invoiced object identifiers (BT-18, BT-128, UNTDID 1153 schemes).
 * Because these terms draw on different code lists the scheme is stored as its code string;
 * code list conformance is checked by the validator, not here.
 */
final readonly class Identifier
{
    public string $value;

    /** Scheme identifier (BT-18-1, BT-29-1, BT-30-1, BT-46-1, BT-47-1, BT-60-1, BT-61-1, BT-71-1, BT-128-1, BT-157-1). */
    public ?string $scheme;

    public function __construct(string $value, IdentifierScheme|string|null $scheme = null)
    {
        $this->value = $value;
        $this->scheme = $scheme instanceof IdentifierScheme ? $scheme->value : $scheme;
    }
}
