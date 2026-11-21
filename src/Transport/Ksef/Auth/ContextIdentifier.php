<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

use IntrAgile\EInvoicing\Poland\Nip;
use InvalidArgumentException;

/**
 * The KSeF context a client authenticates for: a NIP, an internal identifier (NIP and a five
 * digit suffix) or a NIP combined with an EU VAT number.
 *
 * The type name is both the element of AuthTokenRequest/ContextIdentifier and the "type" of the
 * JSON contextIdentifier object.
 */
final readonly class ContextIdentifier
{
    /** The TIID pattern of the AuthTokenRequest schema. */
    private const INTERNAL_ID_PATTERN = '/\A[1-9]((\d[1-9])|([1-9]\d))\d{7}-\d{5}\z/';

    /** A NIP, a hyphen, then a two-letter country prefix and the national VAT number. */
    private const NIP_VAT_UE_PATTERN = '/\A[1-9]((\d[1-9])|([1-9]\d))\d{7}-[A-Z]{2}[A-Z0-9+*]{2,12}\z/';

    private function __construct(
        public string $type,
        public string $value,
    ) {}

    /**
     * @throws InvalidArgumentException When the NIP is not ten digits in the schema pattern
     */
    public static function nip(string $nip): self
    {
        if (! Nip::isWellFormed($nip)) {
            throw new InvalidArgumentException("The context NIP \"{$nip}\" is not a well-formed NIP.");
        }

        return new self('Nip', $nip);
    }

    /**
     * @throws InvalidArgumentException When the identifier is not a NIP followed by "-" and five digits
     */
    public static function internalId(string $id): self
    {
        if (preg_match(self::INTERNAL_ID_PATTERN, $id) !== 1) {
            throw new InvalidArgumentException("The internal identifier \"{$id}\" is not a NIP followed by a hyphen and five digits.");
        }

        return new self('InternalId', $id);
    }

    /**
     * The NIP-VAT UE pair; the full per-country VAT number patterns are checked by KSeF.
     *
     * @throws InvalidArgumentException When the identifier is not a NIP, "-" and an EU VAT number
     */
    public static function nipVatUe(string $id): self
    {
        if (preg_match(self::NIP_VAT_UE_PATTERN, $id) !== 1) {
            throw new InvalidArgumentException("The NIP-VAT UE identifier \"{$id}\" is not a NIP followed by a hyphen and an EU VAT number.");
        }

        return new self('NipVatUe', $id);
    }

    /**
     * The local name of the element inside AuthTokenRequest/ContextIdentifier.
     */
    public function xmlElement(): string
    {
        return $this->type;
    }

    /**
     * @return array{type: string, value: string}
     */
    public function json(): array
    {
        return ['type' => $this->type, 'value' => $this->value];
    }
}
