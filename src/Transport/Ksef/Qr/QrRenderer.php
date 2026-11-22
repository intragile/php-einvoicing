<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Qr;

use InvalidArgumentException;
use LogicException;

/**
 * Renders a KSeF verification link as a QR code image (ISO/IEC 18004) with its caption underneath.
 */
interface QrRenderer
{
    /**
     * An SVG document, scalable, on a white background.
     */
    public function svg(QrCode $code): string;

    /**
     * PNG bytes.
     *
     * @param  int  $pixelsPerModule  the size of one QR module in pixels; the Ministry of Finance
     *                                samples use 5
     *
     * @throws InvalidArgumentException when the module size is out of range
     * @throws LogicException when the renderer's prerequisites for raster output are missing
     */
    public function png(QrCode $code, int $pixelsPerModule = 5): string;
}
