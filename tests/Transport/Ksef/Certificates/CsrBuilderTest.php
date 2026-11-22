<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Certificates;

use IntrAgile\EInvoicing\Transport\Ksef\Certificates\CsrBuilder;
use IntrAgile\EInvoicing\Transport\Ksef\Certificates\KeyAlgorithm;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use phpseclib3\File\X509;
use PHPUnit\Framework\TestCase;

final class CsrBuilderTest extends TestCase
{
    use CertificateFixtures;

    public function test_csr_subject_equals_enrollment_data_in_order(): void
    {
        $csr = CsrBuilder::build(self::SEAL_DATA, KeyAlgorithm::Ec);

        self::assertSame(self::SEAL_DATA, openssl_csr_get_subject(self::csrPem($csr['csrDer']), false));
        self::assertSignatureValid($csr['csrDer']);

        // A person: UTF-8 values, a PrintableString serialNumber, and the order KSeF gave.
        $person = [
            'countryName' => 'PL',
            'surname' => 'Żółkiewska-Łęcka',
            'givenName' => 'Ćma',
            'serialNumber' => 'PNOPL-90010112345',
            'commonName' => 'Ćma Żółkiewska-Łęcka',
        ];
        $csr = CsrBuilder::build($person, KeyAlgorithm::Ec);
        self::assertSame($person, openssl_csr_get_subject(self::csrPem($csr['csrDer']), false));
        self::assertSignatureValid($csr['csrDer']);
    }

    public function test_unique_identifier_is_x500_unique_identifier(): void
    {
        $csr = CsrBuilder::build(['commonName' => 'Testowa Firma', 'countryName' => 'PL', 'uniqueIdentifier' => 'KSeF-ABC-123'], KeyAlgorithm::Ec);

        // OID 2.5.4.45, which OpenSSL names x500UniqueIdentifier; its own "uniqueIdentifier" is
        // the LDAP uid attribute (0.9.2342.19200300.100.1.1).
        self::assertSame(
            ['commonName' => 'Testowa Firma', 'countryName' => 'PL', 'x500UniqueIdentifier' => 'KSeF-ABC-123'],
            openssl_csr_get_subject(self::csrPem($csr['csrDer']), false),
        );
    }

    public function test_ec_csr_uses_p256(): void
    {
        $csr = CsrBuilder::build(self::SEAL_DATA, KeyAlgorithm::Ec);

        $public = self::publicKeyDetails($csr['csrDer']);
        self::assertSame(OPENSSL_KEYTYPE_EC, $public['type']);
        self::assertIsArray($public['ec']);
        self::assertSame('prime256v1', $public['ec']['curve_name']);
        self::assertKeyPairs($csr['privateKeyPem'], $public);
        self::assertSignatureValid($csr['csrDer']);
        self::assertSame('ecdsa-with-SHA256', self::signatureAlgorithm($csr['csrDer']));
    }

    public function test_rsa_csr_is_2048(): void
    {
        $csr = CsrBuilder::build(self::SEAL_DATA, KeyAlgorithm::Rsa);

        $public = self::publicKeyDetails($csr['csrDer']);
        self::assertSame(OPENSSL_KEYTYPE_RSA, $public['type']);
        self::assertSame(2048, $public['bits']);
        self::assertKeyPairs($csr['privateKeyPem'], $public);
        self::assertSignatureValid($csr['csrDer']);
        self::assertSame('sha256WithRSAEncryption', self::signatureAlgorithm($csr['csrDer']));
    }

    public function test_given_name_list_is_repeated(): void
    {
        $data = [
            'countryName' => 'PL',
            'givenName' => ['Jan', 'Maria'],
            'surname' => 'Kowalski',
            'serialNumber' => 'TINPL-7767012903',
            'commonName' => 'Jan Maria Kowalski',
        ];

        $csr = CsrBuilder::build($data, KeyAlgorithm::Ec);

        // One attribute per name, in the given order, between countryName and surname.
        self::assertSame($data, openssl_csr_get_subject(self::csrPem($csr['csrDer']), false));
        self::assertSignatureValid($csr['csrDer']);
    }

    public function test_each_key_is_fresh(): void
    {
        $first = CsrBuilder::build(self::SEAL_DATA, KeyAlgorithm::Ec);
        $second = CsrBuilder::build(self::SEAL_DATA, KeyAlgorithm::Ec);

        self::assertNotSame($first['privateKeyPem'], $second['privateKeyPem']);
        self::assertStringStartsWith('-----BEGIN PRIVATE KEY-----', $first['privateKeyPem']);
    }

    public function test_unusable_enrollment_data_is_refused(): void
    {
        $cases = [
            'unknown attribute' => ['commonName' => 'Firma', 'countryName' => 'PL', 'localityName' => 'Warszawa'],
            'no attribute' => [],
            'empty value' => ['commonName' => '', 'countryName' => 'PL'],
            'non-string value' => ['commonName' => 12, 'countryName' => 'PL'],
            'empty list' => ['commonName' => 'Jan', 'givenName' => []],
            'invalid UTF-8' => ['commonName' => "Firma \xFF", 'countryName' => 'PL'],
            'country not two letters' => ['commonName' => 'Firma', 'countryName' => 'POL'],
            'list for a single attribute' => ['commonName' => ['A', 'B'], 'countryName' => 'PL'],
        ];
        foreach ($cases as $label => $data) {
            $exception = self::capture(static fn () => CsrBuilder::build($data, KeyAlgorithm::Ec));
            self::assertInstanceOf(InvalidArgumentException::class, $exception, $label);
        }
    }

    private static function assertSignatureValid(string $der): void
    {
        $x509 = new X509;
        self::assertNotFalse($x509->loadCSR(self::csrPem($der)), 'phpseclib cannot read the CSR.');
        self::assertTrue($x509->validateSignature(), 'The CSR signature does not verify.');
    }

    private static function signatureAlgorithm(string $der): string
    {
        $x509 = new X509;
        $csr = $x509->loadCSR(self::csrPem($der));
        self::assertIsArray($csr);
        $identifier = $csr['signatureAlgorithm'] ?? null;
        self::assertIsArray($identifier);
        $algorithm = $identifier['algorithm'] ?? null;
        self::assertIsString($algorithm);

        return $algorithm;
    }

    /**
     * @return array<mixed>
     */
    private static function publicKeyDetails(string $der): array
    {
        $key = openssl_csr_get_public_key(self::csrPem($der));
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);

        return $details;
    }

    /**
     * @param  array<mixed>  $public
     */
    private static function assertKeyPairs(string $privateKeyPem, array $public): void
    {
        $private = openssl_pkey_get_private($privateKeyPem);
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $private);
        $details = openssl_pkey_get_details($private);
        self::assertIsArray($details);
        self::assertSame($public['key'], $details['key'], 'The private key does not belong to the CSR.');
    }
}
