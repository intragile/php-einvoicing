<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Qr;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Poland\Nip;
use IntrAgile\EInvoicing\Poland\VerificationLink;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KsefCertificate;
use InvalidArgumentException;
use OpenSSLCertificate;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use RuntimeException;
use Throwable;

/**
 * KOD II, the issuer's certificate verification link printed as a second QR code on invoices
 * issued in offline mode:
 * "https://<qr host>/certificate/<context type>/<context value>/<seller NIP>/<certificate serial>/<invoice hash>/<signature>".
 *
 * The signature covers the link without "https://" and without the signature segment or a
 * trailing slash, and is made with the private key of a KSeF certificate of type Offline:
 * - EC P-256: ECDSA with SHA-256, encoded as IEEE P1363 r‖s (64 bytes), the format the Ministry
 *   of Finance recommends;
 * - RSA (2048 bits or more): RSASSA-PSS with SHA-256, MGF1 with SHA-256 and a 32-byte salt.
 *
 * Both are Base64URL encoded without padding, like the invoice hash (the SHA-256 of the exact
 * invoice bytes, shared with KOD I).
 */
final class CertificateVerificationLink
{
    private const HASH_PATTERN = '/\A[A-Za-z0-9_-]{43}\z/';

    /** The issue date (P_1) is a calendar date in Poland. */
    private const TIMEZONE = 'Europe/Warsaw';

    private const EC_CURVE = 'prime256v1';

    private const EC_FIELD_BYTES = 32;

    private const RSA_MIN_BITS = 2048;

    private const PSS_SALT_LENGTH = 32;

    private function __construct() {}

    /**
     * Builds the link for an FA(3) invoice. The hash covers the exact invoice bytes; the issue
     * date the certificate must be valid on is read from Fa/P_1.
     *
     * @param  CertificateCredentials  $offlineCertificate  a KSeF certificate of type Offline and its private key
     *
     * @throws InvalidArgumentException when the seller NIP is ill-formed, the invoice has no valid
     *                                  Fa/P_1, or the certificate cannot sign the link (see fromHash())
     * @throws UnsafeXml when the invoice carries a DOCTYPE declaration or declares entities
     * @throws UnsupportedDocument when the invoice is empty or not well-formed XML
     * @throws RuntimeException when the signature cannot be computed
     */
    public static function build(
        KsefEnvironment $env,
        ContextIdentifier $context,
        string $sellerNip,
        string $invoiceXml,
        CertificateCredentials $offlineCertificate,
    ): string {
        return self::fromHash(
            $env,
            $context,
            $sellerNip,
            VerificationLink::invoiceHash($invoiceXml),
            Fa3InvoiceFacts::read($invoiceXml)->issueDate,
            $offlineCertificate,
        );
    }

    /**
     * Builds the link from an invoice hash computed elsewhere and the issue date, for documents
     * other than FA(3).
     *
     * @param  string  $invoiceHash  the SHA-256 of the exact invoice bytes, Base64URL without padding
     * @param  DateTimeImmutable  $issueDate  the invoice issue date (P_1); only its calendar date counts
     * @param  CertificateCredentials  $offlineCertificate  a KSeF certificate of type Offline and its private key
     *
     * @throws InvalidArgumentException when the seller NIP or the hash is ill-formed, the key does not
     *                                  match the certificate, the certificate is an Authentication
     *                                  certificate, it is not valid on the issue date, its serial
     *                                  number is not 16 hexadecimal digits, or its key is neither EC
     *                                  P-256 nor RSA of 2048 bits or more
     * @throws RuntimeException when the signature cannot be computed
     */
    public static function fromHash(
        KsefEnvironment $env,
        ContextIdentifier $context,
        string $sellerNip,
        string $invoiceHash,
        DateTimeImmutable $issueDate,
        CertificateCredentials $offlineCertificate,
    ): string {
        if (! Nip::isWellFormed($sellerNip)) {
            throw new InvalidArgumentException(sprintf('Invalid seller NIP "%s" for a KSeF certificate verification link.', $sellerNip));
        }

        if (preg_match(self::HASH_PATTERN, $invoiceHash) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid invoice hash "%s": expected 43 Base64URL characters without padding.',
                $invoiceHash,
            ));
        }

        $certificate = self::readCertificate($offlineCertificate);
        $details = openssl_x509_parse($certificate);
        if ($details === false) {
            throw new InvalidArgumentException('The certificate cannot be parsed.');
        }

        self::assertOfflineType($details);
        self::assertValidOn($details, $issueDate);
        $serial = self::serial($details);

        $signed = sprintf(
            '%s/certificate/%s/%s/%s/%s/%s',
            substr($env->qrBaseUrl(), strlen('https://')),
            $context->type,
            $context->value,
            $sellerNip,
            $serial,
            $invoiceHash,
        );

        return 'https://'.$signed.'/'.self::base64Url(self::sign($signed, $offlineCertificate));
    }

    private static function readCertificate(CertificateCredentials $credentials): OpenSSLCertificate
    {
        $certificate = @openssl_x509_read($credentials->certificatePem);
        if ($certificate === false) {
            self::clearOpenSslErrors();

            throw new InvalidArgumentException('The certificate cannot be read as a PEM-encoded X.509 certificate.');
        }

        // The credentials refuse a foreign key on construction; checked again because a link
        // signed with the wrong key is only rejected once a buyer scans it.
        if (! openssl_x509_check_private_key($certificate, $credentials->privateKey())) {
            self::clearOpenSslErrors();

            throw new InvalidArgumentException('The private key does not match the certificate.');
        }

        return $certificate;
    }

    /**
     * KSeF marks Offline certificates with the Non Repudiation key usage and Authentication
     * certificates with Digital Signature only. A certificate without a keyUsage extension is
     * not known to be either and is left to KSeF to judge.
     *
     * @param  array<array-key, mixed>  $details
     */
    private static function assertOfflineType(array $details): void
    {
        $extensions = $details['extensions'] ?? null;
        $keyUsage = is_array($extensions) && is_string($extensions['keyUsage'] ?? null) ? $extensions['keyUsage'] : null;
        if ($keyUsage === null) {
            return;
        }

        $nonRepudiation = str_contains($keyUsage, 'Non Repudiation') || str_contains($keyUsage, 'Content Commitment');
        if (str_contains($keyUsage, 'Digital Signature') && ! $nonRepudiation) {
            throw new InvalidArgumentException(
                'The certificate is a KSeF certificate of type Authentication (key usage Digital Signature); '
                .'the issuer\'s link (KOD II) must be signed with a certificate of type Offline.',
            );
        }
    }

    /**
     * The certificate must be valid at some moment of the issue date, a calendar day in
     * Europe/Warsaw: a certificate issued on the morning of the issue date is accepted.
     *
     * @param  array<array-key, mixed>  $details
     */
    private static function assertValidOn(array $details, DateTimeImmutable $issueDate): void
    {
        $validFrom = $details['validFrom_time_t'] ?? null;
        $validTo = $details['validTo_time_t'] ?? null;
        if (! is_int($validFrom) || ! is_int($validTo)) {
            throw new InvalidArgumentException('The certificate has no readable validity period.');
        }

        $day = $issueDate->format('Y-m-d');
        $dayStart = new DateTimeImmutable($day.' 00:00:00', new DateTimeZone(self::TIMEZONE));
        $dayEnd = $dayStart->modify('+1 day');

        if ($dayEnd->getTimestamp() <= $validFrom || $dayStart->getTimestamp() > $validTo) {
            throw new InvalidArgumentException(sprintf(
                'The certificate is not valid on the issue date %s: it is valid from %s to %s.',
                $day,
                gmdate('Y-m-d\TH:i:s\Z', $validFrom),
                gmdate('Y-m-d\TH:i:s\Z', $validTo),
            ));
        }
    }

    /**
     * @param  array<array-key, mixed>  $details
     */
    private static function serial(array $details): string
    {
        $serial = $details['serialNumberHex'] ?? null;
        if (! is_string($serial)) {
            throw new InvalidArgumentException('The certificate has no readable serial number.');
        }

        return KsefCertificate::serial($serial);
    }

    private static function sign(string $data, CertificateCredentials $credentials): string
    {
        $key = $credentials->privateKey();
        $details = openssl_pkey_get_details($key);
        if ($details === false) {
            throw new InvalidArgumentException('The private key cannot be inspected.');
        }

        if ($details['type'] === OPENSSL_KEYTYPE_EC) {
            $ec = $details['ec'] ?? null;
            $curve = is_array($ec) ? ($ec['curve_name'] ?? null) : null;
            if ($curve !== self::EC_CURVE) {
                throw new InvalidArgumentException(sprintf(
                    'The EC key of the certificate is on the curve "%s"; KSeF accepts P-256 (prime256v1) only.',
                    is_string($curve) ? $curve : 'unknown',
                ));
            }

            if (! openssl_sign($data, $der, $key, OPENSSL_ALGO_SHA256) || ! is_string($der)) {
                self::clearOpenSslErrors();

                throw new RuntimeException('OpenSSL could not compute the ECDSA signature.');
            }

            return self::derToP1363($der);
        }

        if ($details['type'] === OPENSSL_KEYTYPE_RSA) {
            $bits = is_int($details['bits'] ?? null) ? $details['bits'] : 0;
            if ($bits < self::RSA_MIN_BITS) {
                throw new InvalidArgumentException(sprintf('The RSA key of the certificate has %d bits; KSeF requires at least 2048.', $bits));
            }

            return self::rsaPssSign($data, $credentials);
        }

        throw new InvalidArgumentException('The certificate key is neither EC P-256 nor RSA; KSeF accepts only these two.');
    }

    /**
     * RSASSA-PSS with SHA-256, MGF1-SHA-256 and a 32-byte salt; OpenSSL's PHP binding offers
     * PKCS#1 v1.5 only, which KSeF rejects.
     */
    private static function rsaPssSign(string $data, CertificateCredentials $credentials): string
    {
        $passphrase = $credentials->passphrase();
        try {
            $key = $passphrase === null
                ? PublicKeyLoader::loadPrivateKey($credentials->privateKeyPem())
                : PublicKeyLoader::loadPrivateKey($credentials->privateKeyPem(), $passphrase);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('The private key cannot be loaded as an RSA key.', 0, $e);
        }

        $key = self::rsaKey($key);
        $key = self::rsaKey($key->withPadding(RSA::SIGNATURE_PSS));
        $key = self::rsaKey($key->withHash('sha256'));
        $key = self::rsaKey($key->withMGFHash('sha256'));
        $key = self::rsaKey($key->withSaltLength(self::PSS_SALT_LENGTH));

        $signature = $key->sign($data);
        if ($signature === '') {
            throw new RuntimeException('phpseclib could not compute the RSASSA-PSS signature.');
        }

        return $signature;
    }

    /**
     * Narrows the untyped return value of the phpseclib key builder methods.
     */
    private static function rsaKey(mixed $key): RSA\PrivateKey
    {
        if (! $key instanceof RSA\PrivateKey) {
            throw new InvalidArgumentException('The private key cannot be loaded as an RSA key.');
        }

        return $key;
    }

    /**
     * Converts an ASN.1 DER ECDSA-Sig-Value (SEQUENCE of two INTEGERs) to IEEE P1363 r‖s, each
     * a 32-byte big-endian unsigned integer.
     */
    private static function derToP1363(string $der): string
    {
        $offset = 0;
        if (self::byte($der, $offset++) !== 0x30) {
            throw new RuntimeException('The ECDSA signature is not a DER SEQUENCE.');
        }
        $length = self::derLength($der, $offset);
        if ($offset + $length !== strlen($der)) {
            throw new RuntimeException('The ECDSA signature has an inconsistent DER length.');
        }

        $result = '';
        for ($i = 0; $i < 2; $i++) {
            if (self::byte($der, $offset++) !== 0x02) {
                throw new RuntimeException('The ECDSA signature does not hold two DER INTEGERs.');
            }
            $intLength = self::derLength($der, $offset);
            $integer = ltrim(substr($der, $offset, $intLength), "\x00");
            $offset += $intLength;
            if (strlen($integer) > self::EC_FIELD_BYTES) {
                throw new RuntimeException('The ECDSA signature value is longer than the P-256 field.');
            }
            $result .= str_pad($integer, self::EC_FIELD_BYTES, "\x00", STR_PAD_LEFT);
        }

        return $result;
    }

    private static function derLength(string $der, int &$offset): int
    {
        $first = self::byte($der, $offset++);
        if ($first < 0x80) {
            return $first;
        }

        $length = 0;
        for ($count = $first & 0x7F; $count > 0; $count--) {
            $length = ($length << 8) | self::byte($der, $offset++);
        }

        return $length;
    }

    private static function byte(string $der, int $offset): int
    {
        if ($offset >= strlen($der)) {
            throw new RuntimeException('The ECDSA signature is truncated.');
        }

        return ord($der[$offset]);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Drains OpenSSL's error queue, so a failure here is not reported later by an unrelated call.
     */
    private static function clearOpenSslErrors(): void
    {
        while (openssl_error_string() !== false) {
            // Discard.
        }
    }
}
