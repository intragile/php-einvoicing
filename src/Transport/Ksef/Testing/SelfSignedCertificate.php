<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Testing;

use IntrAgile\EInvoicing\Poland\Nip;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Self-signed certificates for the KSeF TEST environment, which accepts them in place of a
 * qualified seal or signature. KSeF refuses them in every other environment.
 *
 * Each call generates a fresh RSA-2048 key in memory and a certificate valid for 30 days with a
 * random serial number and no extensions. The subject follows the qualified-certificate profile
 * KSeF reads the identity from: organizationIdentifier=VATPL-<NIP> for a seal,
 * serialNumber=TINPL-<NIP> or PNOPL-<PESEL> for a person.
 */
final class SelfSignedCertificate
{
    private const VALID_DAYS = 30;

    private const KEY_BITS = 2048;

    /** The NIP check digit weights. */
    private const NIP_WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    private function __construct() {}

    /**
     * A company seal for the given NIP: C=PL, O, organizationIdentifier=VATPL-<NIP>, CN.
     *
     * @throws InvalidArgumentException When the NIP is not well-formed
     */
    public static function seal(string $nip, string $organizationName = 'Test Organization'): CertificateCredentials
    {
        if (! Nip::isWellFormed($nip)) {
            throw new InvalidArgumentException("The NIP \"{$nip}\" is not a well-formed NIP.");
        }

        return self::issue([
            'countryName' => 'PL',
            'organizationName' => $organizationName,
            'organizationIdentifier' => 'VATPL-'.$nip,
            'commonName' => $organizationName,
        ]);
    }

    /**
     * A personal certificate: C=PL, givenName, surname, serialNumber, CN. A ten digit identifier is
     * taken as a NIP (serialNumber=TINPL-<NIP>), an eleven digit one as a PESEL
     * (serialNumber=PNOPL-<PESEL>).
     *
     * @throws InvalidArgumentException When the identifier is neither a well-formed NIP nor eleven digits
     */
    public static function person(string $nipOrPesel, string $givenName = 'Jan', string $surname = 'Testowy'): CertificateCredentials
    {
        if (Nip::isWellFormed($nipOrPesel)) {
            $serialNumber = 'TINPL-'.$nipOrPesel;
        } elseif (preg_match('/\A\d{11}\z/', $nipOrPesel) === 1) {
            $serialNumber = 'PNOPL-'.$nipOrPesel;
        } else {
            throw new InvalidArgumentException("\"{$nipOrPesel}\" is neither a well-formed NIP nor an eleven digit PESEL.");
        }

        return self::issue([
            'countryName' => 'PL',
            'givenName' => $givenName,
            'surname' => $surname,
            'serialNumber' => $serialNumber,
            'commonName' => $givenName.' '.$surname,
        ]);
    }

    /**
     * A random NIP with a valid check digit, for synthetic test subjects.
     */
    public static function randomNip(): string
    {
        while (true) {
            $digits = [random_int(1, 9)];
            for ($i = 1; $i < 9; $i++) {
                $digits[] = random_int(0, 9);
            }

            $sum = 0;
            foreach (self::NIP_WEIGHTS as $i => $weight) {
                $sum += $weight * $digits[$i];
            }
            // A remainder of 10 has no check digit; such a NIP is never issued.
            if ($sum % 11 === 10) {
                continue;
            }

            $nip = implode('', $digits).($sum % 11);
            // The schema pattern also excludes "00" in the second and third position.
            if (Nip::isValid($nip)) {
                return $nip;
            }
        }
    }

    /**
     * @param  array<string, string>  $subject  attribute long name => value, in certificate order
     */
    private static function issue(array $subject): CertificateCredentials
    {
        // A configuration without default subject attributes, so the system openssl.cnf cannot
        // add any.
        $options = [
            'config' => dirname(__DIR__, 4).'/resources/poland/ksef-testing/openssl.cnf',
            'digest_alg' => 'sha256',
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => self::KEY_BITS,
        ];

        $key = openssl_pkey_new($options);
        if (! $key instanceof OpenSSLAsymmetricKey) {
            self::fail('generate the key');
        }

        // openssl_csr_new() takes the key by reference; a copy keeps $key typed.
        $requestKey = $key;
        $csr = openssl_csr_new($subject, $requestKey, $options);
        if ($csr === false || $csr === true) {
            self::fail('create the certificate request');
        }

        $certificate = openssl_csr_sign($csr, null, $key, self::VALID_DAYS, $options, random_int(1, PHP_INT_MAX));
        if ($certificate === false) {
            self::fail('sign the certificate');
        }

        if (! openssl_x509_export($certificate, $certificatePem) || ! openssl_pkey_export($key, $keyPem, null, $options)
            || ! is_string($certificatePem) || ! is_string($keyPem)) {
            self::fail('export the certificate and key');
        }

        return new CertificateCredentials($certificatePem, $keyPem);
    }

    private static function fail(string $step): never
    {
        $errors = [];
        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        throw new RuntimeException("OpenSSL could not {$step}".($errors === [] ? '.' : ': '.implode('; ', $errors)));
    }
}
