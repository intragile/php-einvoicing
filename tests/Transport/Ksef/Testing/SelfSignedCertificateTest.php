<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Testing;

use IntrAgile\EInvoicing\Poland\Nip;
use IntrAgile\EInvoicing\Transport\Ksef\Testing\SelfSignedCertificate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SelfSignedCertificateTest extends TestCase
{
    public function test_seal_subject_has_vatpl_organization_identifier(): void
    {
        $credentials = SelfSignedCertificate::seal('5265877635', 'Firma Testowa Sp. z o.o.');
        $parsed = self::parse($credentials->certificatePem);

        // Exactly these attributes, in this order: no defaults from a system OpenSSL configuration.
        self::assertSame([
            'C' => 'PL',
            'O' => 'Firma Testowa Sp. z o.o.',
            'organizationIdentifier' => 'VATPL-5265877635',
            'CN' => 'Firma Testowa Sp. z o.o.',
        ], $parsed['subject']);
        self::assertSame($parsed['subject'], $parsed['issuer']);
        self::assertSame('/C=PL/O=Firma Testowa Sp. z o.o./organizationIdentifier=VATPL-5265877635/CN=Firma Testowa Sp. z o.o.', $parsed['name']);
        $default = self::parse(SelfSignedCertificate::seal('5265877635')->certificatePem)['subject'];
        self::assertIsArray($default);
        self::assertSame('Test Organization', $default['O']);
    }

    public function test_seal_is_rsa_2048_valid_for_30_days(): void
    {
        $credentials = SelfSignedCertificate::seal(SelfSignedCertificate::randomNip());
        $parsed = self::parse($credentials->certificatePem);

        self::assertIsInt($parsed['validTo_time_t']);
        self::assertIsInt($parsed['validFrom_time_t']);
        self::assertSame(30 * 86400, $parsed['validTo_time_t'] - $parsed['validFrom_time_t']);
        self::assertEqualsWithDelta(time(), $parsed['validFrom_time_t'], 300);
        self::assertSame('RSA-SHA256', $parsed['signatureTypeSN']);
        self::assertSame(2, $parsed['version']);

        $key = openssl_pkey_get_private($credentials->privateKeyPem());
        self::assertNotFalse($key);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertSame(OPENSSL_KEYTYPE_RSA, $details['type']);
        self::assertSame(2048, $details['bits']);
        self::assertNull($credentials->passphrase());
        self::assertTrue(openssl_x509_check_private_key($credentials->certificatePem, $key));
    }

    public function test_person_with_pesel_uses_pnopl(): void
    {
        $parsed = self::parse(SelfSignedCertificate::person('44051401359')->certificatePem);

        self::assertSame([
            'C' => 'PL',
            'GN' => 'Jan',
            'SN' => 'Testowy',
            'serialNumber' => 'PNOPL-44051401359',
            'CN' => 'Jan Testowy',
        ], $parsed['subject']);
    }

    public function test_person_with_nip_uses_tinpl(): void
    {
        $parsed = self::parse(SelfSignedCertificate::person('5265877635', 'Anna', 'Nowak')->certificatePem);

        self::assertSame([
            'C' => 'PL',
            'GN' => 'Anna',
            'SN' => 'Nowak',
            'serialNumber' => 'TINPL-5265877635',
            'CN' => 'Anna Nowak',
        ], $parsed['subject']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedPersonIdentifiers(): iterable
    {
        yield 'nine digits' => ['526587763'];
        yield 'twelve digits' => ['440514013591'];
        yield 'letters' => ['4405140135X'];
        yield 'malformed NIP' => ['0265877635'];
    }

    #[DataProvider('malformedPersonIdentifiers')]
    public function test_malformed_person_identifier_is_refused(string $identifier): void
    {
        $this->expectException(InvalidArgumentException::class);

        SelfSignedCertificate::person($identifier);
    }

    public function test_malformed_seal_nip_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SelfSignedCertificate::seal('PL5265877635');
    }

    public function test_random_nip_is_valid(): void
    {
        $seen = [];
        for ($i = 0; $i < 1000; $i++) {
            $nip = SelfSignedCertificate::randomNip();
            self::assertTrue(Nip::isValid($nip), $nip);
            $seen[$nip] = true;
        }

        // Ten random digits: a repeat within 1000 draws is practically impossible.
        self::assertGreaterThan(990, count($seen));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function parse(string $pem): array
    {
        $parsed = openssl_x509_parse($pem);
        self::assertIsArray($parsed);

        return $parsed;
    }
}
