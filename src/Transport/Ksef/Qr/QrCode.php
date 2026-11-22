<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Qr;

use InvalidArgumentException;

/**
 * A KSeF verification link and the caption printed under its QR code on the invoice
 * visualisation: the KSeF number or "OFFLINE" for KOD I, "CERTYFIKAT" for KOD II.
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
            throw new InvalidArgumentException('A KSeF verification link is an https URL.');
        }
    }
}
