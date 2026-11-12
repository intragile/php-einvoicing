<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Lookup;

/**
 * A DNS NAPTR record (RFC 3403).
 */
final readonly class NaptrRecord
{
    public function __construct(
        /** Records with a lower order are tried first. */
        public int $order,
        /** Among records of equal order, those with a lower preference are tried first. */
        public int $preference,
        public string $flags,
        public string $services,
        /** Substitution expression of the form "!pattern!replacement!flags". */
        public string $regexp,
        public string $replacement,
    ) {}
}
