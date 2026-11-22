<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use InvalidArgumentException;

/**
 * KOD I, the KSeF invoice verification link printed as a QR code on an invoice visualisation:
 * "<qr base URL>/invoice/<seller NIP>/<issue date DD-MM-YYYY>/<SHA-256 of the file, Base64URL>".
 *
 * The hash covers the exact bytes of the invoice file as sent to KSeF; any re-serialisation,
 * added newline or byte order mark yields a different link.
 */
final class VerificationLink
{
    private const HASH_PATTERN = '/\A[A-Za-z0-9_-]{43}\z/';

    private function __construct() {}

    /**
     * SHA-256 of the exact bytes, Base64URL encoded without padding (43 characters).
     */
    public static function invoiceHash(string $invoiceXml): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $invoiceXml, true)), '+/', '-_'), '=');
    }

    /**
     * @throws InvalidArgumentException when the seller NIP is ill-formed
     */
    public static function invoice(KsefEnvironment $env, string $sellerNip, DateTimeImmutable $issueDate, string $invoiceXml): string
    {
        return self::invoiceFromHash($env, $sellerNip, $issueDate, self::invoiceHash($invoiceXml));
    }

    /**
     * @throws InvalidArgumentException when the seller NIP or the hash is ill-formed
     */
    public static function invoiceFromHash(KsefEnvironment $env, string $sellerNip, DateTimeImmutable $issueDate, string $hash): string
    {
        if (! Nip::isWellFormed($sellerNip)) {
            throw new InvalidArgumentException(sprintf('Invalid seller NIP "%s" for a KSeF verification link.', $sellerNip));
        }

        if (preg_match(self::HASH_PATTERN, $hash) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid invoice hash "%s": expected 43 Base64URL characters without padding.',
                $hash,
            ));
        }

        return sprintf('%s/invoice/%s/%s/%s', $env->qrBaseUrl(), $sellerNip, $issueDate->format('d-m-Y'), $hash);
    }

    /**
     * Builds the link from an FA(3) invoice, reading the seller NIP
     * (Podmiot1/DaneIdentyfikacyjne/NIP) and the issue date (Fa/P_1) from the document.
     *
     * @throws InvalidArgumentException when either value is missing or ill-formed
     * @throws UnsafeXml when the document carries a DOCTYPE declaration or declares entities
     * @throws UnsupportedDocument when the document is empty or not well-formed XML
     */
    public static function forInvoiceXml(KsefEnvironment $env, string $invoiceXml): string
    {
        $facts = Fa3InvoiceFacts::read($invoiceXml);

        return self::invoice($env, $facts->sellerNip, $facts->issueDate, $invoiceXml);
    }
}
