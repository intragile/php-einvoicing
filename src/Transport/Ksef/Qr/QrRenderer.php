<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Qr;

use InvalidArgumentException;
use LogicException;

/**
 * Renders a verification link (KSeF, VERI*FACTU) as a QR code image (ISO/IEC 18004) with its caption
 * underneath, if it has one.
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
