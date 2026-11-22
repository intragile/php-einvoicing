<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Qr;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Poland\VerificationLink;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use InvalidArgumentException;
use RuntimeException;

/**
 * The QR codes of an FA(3) invoice visualisation: KOD I (invoice verification) on every invoice
 * and, on an invoice issued in offline mode, KOD II (issuer's certificate verification) as well.
 *
 * KOD I is captioned with the KSeF number once KSeF has assigned one, "OFFLINE" until then;
 * KOD II is always captioned "CERTYFIKAT".
 */
final class KsefQrCodes
{
    public const OFFLINE_LABEL = 'OFFLINE';

    public const CERTIFICATE_LABEL = 'CERTYFIKAT';

    private function __construct() {}

    /**
     * KOD I, with the seller NIP and the issue date read from the invoice.
     *
     * @param  ?string  $ksefNumber  the KSeF number, or null while KSeF has not assigned one
     *
     * @throws InvalidArgumentException when the KSeF number is invalid, or the seller NIP or the
     *                                  issue date is missing or ill-formed
     * @throws UnsafeXml when the invoice carries a DOCTYPE declaration or declares entities
     * @throws UnsupportedDocument when the invoice is empty or not well-formed XML
     */
    public static function online(KsefEnvironment $env, string $invoiceXml, ?string $ksefNumber): QrCode
    {
        $label = $ksefNumber === null ? self::OFFLINE_LABEL : KsefNumber::parse($ksefNumber)->value;

        return new QrCode(VerificationLink::forInvoiceXml($env, $invoiceXml), $label);
    }

    /**
     * KOD I and KOD II of an invoice issued in offline mode. The seller NIP of KOD II is read from
     * Podmiot1 of the invoice, the same as for KOD I.
     *
     * @param  CertificateCredentials  $offlineCertificate  a KSeF certificate of type Offline and its private key
     * @param  ?string  $ksefNumber  the KSeF number once the invoice has been sent, or null before
     * @return array{0: QrCode, 1: QrCode} KOD I, then KOD II
     *
     * @throws InvalidArgumentException when the KSeF number is invalid, the invoice lacks a valid
     *                                  seller NIP or issue date, or the certificate cannot sign
     *                                  the link (see CertificateVerificationLink::fromHash())
     * @throws UnsafeXml when the invoice carries a DOCTYPE declaration or declares entities
     * @throws UnsupportedDocument when the invoice is empty or not well-formed XML
     * @throws RuntimeException when the signature cannot be computed
     */
    public static function offline(
        KsefEnvironment $env,
        ContextIdentifier $ctx,
        string $invoiceXml,
        CertificateCredentials $offlineCertificate,
        ?string $ksefNumber = null,
    ): array {
        $kod1 = self::online($env, $invoiceXml, $ksefNumber);
        $sellerNip = Fa3InvoiceFacts::read($invoiceXml)->sellerNip;
        $kod2 = CertificateVerificationLink::build($env, $ctx, $sellerNip, $invoiceXml, $offlineCertificate);

        return [$kod1, new QrCode($kod2, self::CERTIFICATE_LABEL)];
    }
}
