<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\QrCode;

/**
 * The AEAT verification QR of a VERI*FACTU invoice: a link to the ValidarQR service carrying the
 * issuer NIF, the invoice number, the issue date and the invoice total.
 */
final class VerifactuQr
{
    /**
     * Text printed above the QR code. Placement: at the top of the first page of the invoice,
     * the code 30 to 40 mm wide with a margin of at least 2 mm; the label's font size must not
     * be smaller than that of the invoice data (QR specification v0.5.0).
     */
    public const LABEL_ABOVE = 'QR tributario:';

    /**
     * Text printed below the QR code, with the same placement and font size rules as
     * LABEL_ABOVE.
     */
    public const LABEL_BELOW = 'VERI*FACTU';

    private function __construct() {}

    /**
     * The verification URL: "<base>?nif=...&numserie=...&fecha=...&importe=...", each value
     * percent-encoded, so that a "&" or "/" in the series cannot alter the query string.
     */
    public static function url(RegistrationRecord $record, VerifactuEnvironment $environment): string
    {
        $id = $record->invoiceId();

        return sprintf(
            '%s?nif=%s&numserie=%s&fecha=%s&importe=%s',
            $environment->qrBaseUrl(),
            rawurlencode($id->issuer->value),
            rawurlencode($id->number),
            rawurlencode($id->dateText()),
            rawurlencode($record->draft->total->text),
        );
    }

    /**
     * The QR code for the shared renderer. The captions are printed by the invoice layout, not
     * by the image, so the label is empty.
     */
    public static function code(RegistrationRecord $record, VerifactuEnvironment $environment): QrCode
    {
        return new QrCode(self::url($record, $environment), '');
    }
}
