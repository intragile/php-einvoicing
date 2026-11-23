<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

use RuntimeException;

/**
 * Reads the text of the QR code in an image.
 */
interface QrDecoder
{
    /**
     * @param  string  $png  the image bytes
     *
     * @throws RuntimeException when the image holds no readable QR code
     */
    public function decode(string $png): string;
}
