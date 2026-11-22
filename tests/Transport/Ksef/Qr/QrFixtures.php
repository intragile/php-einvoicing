<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Qr;

use IntrAgile\EInvoicing\Tests\Support\PolishSamples;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * KSeF-like certificates generated in memory (never written to disk) and FA(3) invoices with a
 * chosen issue date, for the QR code tests.
 */
final class QrFixtures
{
    public const SELLER_NIP = PolishSamples::SELLER_NIP;

    /** The serial number of the kody-qr.md example. */
    public const DOCS_SERIAL = '01F20A5D352AE590';

    /** @var array<string, CertificateCredentials> */
    private static array $cache = [];

    /** @var array<string, OpenSSLAsymmetricKey> */
    private static array $keys = [];

    /**
     * @param  'ec'|'rsa'  $algorithm
     * @param  'offline'|'authentication'|null  $keyUsage  the extension section, or null for none
     */
    public static function certificate(
        string $algorithm = 'ec',
        ?string $keyUsage = 'offline',
        int $days = 30,
        string $serialHex = self::DOCS_SERIAL,
        string $keyName = 'default',
    ): CertificateCredentials {
        $cacheKey = implode('|', [$algorithm, $keyUsage ?? '-', $days, $serialHex, $keyName]);

        return self::$cache[$cacheKey] ??= self::issue($algorithm, $keyUsage, $days, $serialHex, self::key($algorithm, $keyName));
    }

    /**
     * The official FA(3) template with the given issue date (Fa/P_1).
     */
    public static function invoice(string $issueDate): string
    {
        $xml = PolishSamples::official('invoice-template-fa-3.xml');

        return str_replace('<P_1>2025-09-01</P_1>', '<P_1>'.$issueDate.'</P_1>', $xml);
    }

    /**
     * An invoice issued today in Europe/Warsaw, inside the validity of a fresh certificate.
     */
    public static function invoiceIssuedToday(): string
    {
        return self::invoice((new \DateTimeImmutable('now', new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d'));
    }

    private static function key(string $algorithm, string $name): OpenSSLAsymmetricKey
    {
        return self::$keys[$algorithm.'|'.$name] ??= self::newKey($algorithm);
    }

    private static function newKey(string $algorithm): OpenSSLAsymmetricKey
    {
        $options = $algorithm === 'ec'
            ? ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']
            : ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048];
        $key = openssl_pkey_new($options + ['config' => self::config()]);
        if (! $key instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('OpenSSL could not generate the test key.');
        }

        return $key;
    }

    private static function issue(string $algorithm, ?string $keyUsage, int $days, string $serialHex, OpenSSLAsymmetricKey $key): CertificateCredentials
    {
        $options = ['config' => self::config(), 'digest_alg' => 'sha256'];
        if ($keyUsage !== null) {
            $options['x509_extensions'] = $keyUsage;
        }

        $subject = ['countryName' => 'PL', 'organizationName' => 'Testowa Firma', 'commonName' => 'Testowa Firma (offline) '.$algorithm];
        $requestKey = $key;
        $csr = openssl_csr_new($subject, $requestKey, $options);
        if ($csr === false || $csr === true) {
            throw new RuntimeException('OpenSSL could not create the test certificate request.');
        }

        $certificate = openssl_csr_sign($csr, null, $key, $days, $options, (int) hexdec($serialHex));
        if ($certificate === false || ! openssl_x509_export($certificate, $certificatePem) || ! openssl_pkey_export($key, $keyPem, null, ['config' => self::config()])
            || ! is_string($certificatePem) || ! is_string($keyPem)) {
            throw new RuntimeException('OpenSSL could not issue the test certificate.');
        }

        return new CertificateCredentials($certificatePem, $keyPem);
    }

    private static function config(): string
    {
        return __DIR__.'/openssl-test-certificates.cnf';
    }
}
