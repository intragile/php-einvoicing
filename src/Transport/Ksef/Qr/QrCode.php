<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Qr;

use InvalidArgumentException;

/**
 * A verification link and the caption printed under its QR code on the invoice visualisation
 * (empty for no caption). KSeF captions KOD I with the KSeF number or "OFFLINE" and KOD II with
 * "CERTYFIKAT"; the VERI*FACTU QR carries none.
 */
final readonly class QrCode
{
    /**
     * @throws InvalidArgumentException when the URL is not an https URL
     */
    public function __construct(
        public string $url,
        public string $label,
    ) {
        if (! str_starts_with($url, 'https://')) {
            throw new InvalidArgumentException('A verification link is an https URL.');
        }
    }
}
