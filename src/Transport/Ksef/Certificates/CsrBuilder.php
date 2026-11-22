<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Certificates;

use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Generates a key pair and a PKCS#10 certificate request (CSR) for a KSeF certificate.
 *
 * KSeF issues a certificate only for a subject that equals the data of
 * GET /certificates/enrollments/data, so the subject is built from that data exactly: one
 * attribute per value, in the order given, with the OIDs of the KSeF documentation, each in its
 * own relative distinguished name. givenName may hold a list of names, which become consecutive
 * attributes. countryName, and serialNumber when it fits, are PrintableString values as X.520
 * requires; every other value is a UTF8String.
 *
 * The request is DER-encoded here rather than by openssl_csr_new(), which cannot repeat an
 * attribute: a list of given names becomes the literal value "Array". The OIDs are explicit
 * because OpenSSL's own "uniqueIdentifier" is a different attribute (0.9.2342.19200300.100.1.44)
 * from the KSeF one (2.5.4.45, x500UniqueIdentifier). X.520 types x500UniqueIdentifier as a BIT
 * STRING; it is encoded as a UTF8String here, which is unverified against KSeF because KSeF TE
 * has never returned that attribute. The key is EC on P-256 or RSA-2048, and the request is signed with SHA-256
 * (ecdsa-with-SHA256 or sha256WithRSAEncryption). The key is generated in memory and returned
 * once; it is never written anywhere.
 */
final class CsrBuilder
{
    /** The attributes KSeF may return, by name, with their OIDs. */
    private const ATTRIBUTES = [
        'commonName' => '2.5.4.3',
        'surname' => '2.5.4.4',
        'serialNumber' => '2.5.4.5',
        'countryName' => '2.5.4.6',
        'organizationName' => '2.5.4.10',
        'givenName' => '2.5.4.42',
        'uniqueIdentifier' => '2.5.4.45',
        'organizationIdentifier' => '2.5.4.97',
    ];

    /** The only attribute KSeF returns as a list of values. */
    private const MULTI_VALUED = 'givenName';

    private const OID_ECDSA_WITH_SHA256 = '1.2.840.10045.4.3.2';

    private const OID_SHA256_WITH_RSA = '1.2.840.113549.1.1.11';

    private const RSA_BITS = 2048;

    private const EC_CURVE = 'prime256v1';

    /** The characters of an X.680 PrintableString. */
    private const PRINTABLE = '/\A[A-Za-z0-9 \'()+,\-.\/:=?]+\z/';

    private function __construct() {}

    /**
     * @param  array<mixed>  $enrollmentData  the answer of GET /certificates/enrollments/data,
     *                                        attribute name => value or list of values; null
     *                                        values are skipped
     * @return array{csrDer: string, privateKeyPem: string} the DER-encoded request and its private
     *                                                      key, PEM-encoded (PKCS#8, unencrypted)
     *
     * @throws InvalidArgumentException when the data has no attribute, an attribute KSeF does not
     *                                  document, an empty or non-UTF-8 value, a list for an attribute
     *                                  other than givenName, or a country that is not two letters
     * @throws RuntimeException when OpenSSL cannot generate the key or sign the request
     */
    public static function build(array $enrollmentData, KeyAlgorithm $algorithm): array
    {
        $subject = self::subject($enrollmentData);

        $options = [
            // A configuration of its own, so that a missing or unusual system openssl.cnf cannot
            // break key generation.
            'config' => dirname(__DIR__, 4).'/resources/poland/ksef-testing/openssl.cnf',
        ] + match ($algorithm) {
            KeyAlgorithm::Ec => ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => self::EC_CURVE],
            KeyAlgorithm::Rsa => ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => self::RSA_BITS],
        };
        $key = openssl_pkey_new($options);
        if (! $key instanceof OpenSSLAsymmetricKey) {
            self::fail('generate the key');
        }
        $details = openssl_pkey_get_details($key);
        $publicKeyPem = is_array($details) ? ($details['key'] ?? null) : null;
        if (! is_string($publicKeyPem)) {
            self::fail('read the public key');
        }

        $requestInfo = self::sequence(
            "\x02\x01\x00",
            $subject,
            self::pemToDer($publicKeyPem),
            // An empty attribute set: [0] IMPLICIT SET OF Attribute.
            "\xA0\x00",
        );
        if (! openssl_sign($requestInfo, $signature, $key, OPENSSL_ALGO_SHA256) || ! is_string($signature)) {
            self::fail('sign the certificate request');
        }
        $signatureAlgorithm = match ($algorithm) {
            KeyAlgorithm::Ec => self::sequence(self::oid(self::OID_ECDSA_WITH_SHA256)),
            KeyAlgorithm::Rsa => self::sequence(self::oid(self::OID_SHA256_WITH_RSA), "\x05\x00"),
        };

        if (! openssl_pkey_export($key, $privateKeyPem, null, $options) || ! is_string($privateKeyPem)) {
            self::fail('export the private key');
        }

        return [
            'csrDer' => self::sequence($requestInfo, $signatureAlgorithm, self::tlv(0x03, "\x00".$signature)),
            'privateKeyPem' => $privateKeyPem,
        ];
    }

    /**
     * The DER-encoded Name of the enrolment data.
     *
     * @param  array<mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    private static function subject(array $data): string
    {
        $names = [];
        foreach ($data as $attribute => $value) {
            if ($value === null) {
                continue;
            }
            $oid = is_string($attribute) ? (self::ATTRIBUTES[$attribute] ?? null) : null;
            if ($oid === null) {
                throw new InvalidArgumentException(sprintf(
                    'The KSeF enrolment data holds the attribute "%s", which is not one KSeF documents (%s).',
                    (string) $attribute,
                    implode(', ', array_keys(self::ATTRIBUTES)),
                ));
            }
            if (is_array($value)) {
                if ($attribute !== self::MULTI_VALUED || $value === [] || ! array_is_list($value)) {
                    throw new InvalidArgumentException(sprintf('The KSeF enrolment attribute "%s" must be a single value; only %s may be a non-empty list.', $attribute, self::MULTI_VALUED));
                }
                $values = $value;
            } else {
                $values = [$value];
            }
            foreach ($values as $single) {
                $names[] = self::set(self::sequence(self::oid($oid), self::value($attribute, $single)));
            }
        }
        if ($names === []) {
            throw new InvalidArgumentException('The KSeF enrolment data holds no subject attribute.');
        }

        return self::sequence(...$names);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function value(string $attribute, mixed $value): string
    {
        if (! is_string($value) || $value === '' || ! mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidArgumentException(sprintf('The KSeF enrolment attribute "%s" must be a non-empty UTF-8 string.', $attribute));
        }
        if ($attribute === 'countryName') {
            if (preg_match('/\A[A-Z]{2}\z/', $value) !== 1) {
                throw new InvalidArgumentException('The KSeF enrolment attribute "countryName" must be a two-letter country code.');
            }

            return self::tlv(0x13, $value);
        }
        if ($attribute === 'serialNumber' && preg_match(self::PRINTABLE, $value) === 1) {
            return self::tlv(0x13, $value);
        }

        return self::tlv(0x0C, $value);
    }

    private static function oid(string $dotted): string
    {
        $arcs = array_map('intval', explode('.', $dotted));
        $bytes = chr((40 * $arcs[0] + $arcs[1]) & 0xFF);
        foreach (array_slice($arcs, 2) as $arc) {
            $encoded = chr($arc & 0x7F);
            for ($arc >>= 7; $arc > 0; $arc >>= 7) {
                $encoded = chr(0x80 | ($arc & 0x7F)).$encoded;
            }
            $bytes .= $encoded;
        }

        return self::tlv(0x06, $bytes);
    }

    private static function sequence(string ...$elements): string
    {
        return self::tlv(0x30, implode('', $elements));
    }

    private static function set(string ...$elements): string
    {
        return self::tlv(0x31, implode('', $elements));
    }

    private static function tlv(int $tag, string $contents): string
    {
        $length = strlen($contents);
        if ($length < 0x80) {
            return chr($tag & 0xFF).chr($length & 0xFF).$contents;
        }
        $lengthBytes = ltrim(pack('N', $length), "\x00");

        return chr($tag & 0xFF).chr((0x80 | strlen($lengthBytes)) & 0xFF).$lengthBytes.$contents;
    }

    private static function pemToDer(string $pem): string
    {
        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $pem), true);
        if ($der === false || $der === '') {
            self::fail('encode the public key');
        }

        return $der;
    }

    private static function fail(string $step): never
    {
        $errors = [];
        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        throw new RuntimeException(sprintf('OpenSSL could not %s for the KSeF certificate request%s', $step, $errors === [] ? '.' : ': '.implode('; ', $errors)));
    }
}
