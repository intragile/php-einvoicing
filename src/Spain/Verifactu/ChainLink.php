<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * The link of a record to its predecessor in the chain (Encadenamiento).
 */
final readonly class ChainLink
{
    private function __construct(
        private ?InvoiceId $previous,
        private string $fingerprint,
    ) {}

    public static function first(): self
    {
        return new self(null, '');
    }

    public static function after(InvoiceId $previous, string $fingerprint): self
    {
        if (preg_match('/\A[0-9A-F]{64}\z/', $fingerprint) !== 1) {
            throw new InvalidRecord('The previous fingerprint must be 64 upper-case hexadecimal characters.');
        }

        return new self($previous, $fingerprint);
    }

    public function isFirst(): bool
    {
        return $this->previous === null;
    }

    public function previous(): ?InvoiceId
    {
        return $this->previous;
    }

    /** The previous record's fingerprint, or '' for the first record of a chain. */
    public function previousFingerprint(): string
    {
        return $this->fingerprint;
    }
}
