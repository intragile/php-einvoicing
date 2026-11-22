<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Qr;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Poland\VerificationLink;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\CertificateVerificationLink;
use InvalidArgumentException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use PHPUnit\Framework\TestCase;

final class CertificateVerificationLinkTest extends TestCase
{
    private const DOCS_HASH = 'UtQp9Gpc51y-u3xApZjIjgkpZ01js-J8KflSPW8WzIE';

    private const LINK_PATTERN = '#\Ahttps://(qr-test\.ksef\.mf\.gov\.pl/certificate/Nip/(\d{10})/(\d{10})/([0-9A-F]{16})/([A-Za-z0-9_-]{43}))/([A-Za-z0-9_-]+)\z#';

    public function test_signed_string_has_no_scheme_or_trailing_slash(): void
    {
        $certificate = QrFixtures::certificate('ec');
        $xml = QrFixtures::invoiceIssuedToday();

        $link = CertificateVerificationLink::build(KsefEnvironment::Test, ContextIdentifier::nip(QrFixtures::SELLER_NIP), QrFixtures::SELLER_NIP, $xml, $certificate);

        [$signed, $signature] = self::split($link);
        self::assertStringStartsWith('qr-test.ksef.mf.gov.pl/certificate/', $signed);
        self::assertStringEndsNotWith('/', $signed);

        $der = self::p1363ToDer($signature);
        $public = openssl_pkey_get_public($certificate->certificatePem);
        self::assertNotFalse($public);
        self::assertSame(1, openssl_verify($signed, $der, $public, OPENSSL_ALGO_SHA256));
        self::assertSame(0, openssl_verify('https://'.$signed, $der, $public, OPENSSL_ALGO_SHA256));
        self::assertSame(0, openssl_verify($signed.'/', $der, $public, OPENSSL_ALGO_SHA256));
    }

    public function test_ec_signature_is_64_byte_p1363_and_verifies(): void
    {
        $certificate = QrFixtures::certificate('ec');
        $xml = QrFixtures::invoiceIssuedToday();

        $link = CertificateVerificationLink::build(KsefEnvironment::Test, ContextIdentifier::nip(QrFixtures::SELLER_NIP), QrFixtures::SELLER_NIP, $xml, $certificate);

        [$signed, $signature] = self::split($link);
        self::assertSame(86, strlen(basename($link)));
        self::assertSame(64, strlen($signature));

        $public = openssl_pkey_get_public($certificate->certificatePem);
        self::assertNotFalse($public);
        self::assertSame(1, openssl_verify($signed, self::p1363ToDer($signature), $public, OPENSSL_ALGO_SHA256));

        // The hash segment is the KOD I hash of the exact invoice bytes.
        self::assertSame(VerificationLink::invoiceHash($xml), self::segments($link)[4]);
    }

    public function test_rsa_pss_verifies_with_phpseclib(): void
    {
        $certificate = QrFixtures::certificate('rsa');
        $xml = QrFixtures::invoiceIssuedToday();

        $link = CertificateVerificationLink::build(KsefEnvironment::Test, ContextIdentifier::nip(QrFixtures::SELLER_NIP), QrFixtures::SELLER_NIP, $xml, $certificate);

        [$signed, $signature] = self::split($link);
        self::assertSame(256, strlen($signature));

        $verifier = self::rsaPublicKey($certificate)->withPadding(RSA::SIGNATURE_PSS);
        self::assertInstanceOf(RSA\PublicKey::class, $verifier);
        $verifier = $verifier->withHash('sha256');
        self::assertInstanceOf(RSA\PublicKey::class, $verifier);
        $verifier = $verifier->withMGFHash('sha256');
        self::assertInstanceOf(RSA\PublicKey::class, $verifier);
        $verifier = $verifier->withSaltLength(32);
        self::assertInstanceOf(RSA\PublicKey::class, $verifier);
        self::assertTrue($verifier->verify($signed, $signature));
    }

    public function test_encrypted_rsa_key_is_opened_with_its_passphrase(): void
    {
        $plain = QrFixtures::certificate('rsa');
        self::assertTrue(openssl_pkey_export($plain->privateKey(), $encrypted, 'test-passphrase', ['config' => __DIR__.'/openssl-test-certificates.cnf']));
        self::assertIsString($encrypted);
        $certificate = new CertificateCredentials($plain->certificatePem, $encrypted, 'test-passphrase');

        $link = CertificateVerificationLink::build(KsefEnvironment::Test, ContextIdentifier::nip(QrFixtures::SELLER_NIP), QrFixtures::SELLER_NIP, QrFixtures::invoiceIssuedToday(), $certificate);

        [$signed, $signature] = self::split($link);
        $verifier = self::rsaPublicKey($certificate)->withPadding(RSA::SIGNATURE_PSS);
        self::assertInstanceOf(RSA\PublicKey::class, $verifier);
        $verifier = $verifier->withHash('sha256');
        self::assertInstanceOf(RSA\PublicKey::class, $verifier);
        $verifier = $verifier->withMGFHash('sha256');
        self::assertInstanceOf(RSA\PublicKey::class, $verifier);
        $verifier = $verifier->withSaltLength(32);
        self::assertInstanceOf(RSA\PublicKey::class, $verifier);
        self::assertTrue($verifier->verify($signed, $signature));
    }

    public function test_pkcs1_is_never_produced(): void
    {
        $certificate = QrFixtures::certificate('rsa');
        $xml = QrFixtures::invoiceIssuedToday();

        $first = CertificateVerificationLink::build(KsefEnvironment::Test, ContextIdentifier::nip(QrFixtures::SELLER_NIP), QrFixtures::SELLER_NIP, $xml, $certificate);
        $second = CertificateVerificationLink::build(KsefEnvironment::Test, ContextIdentifier::nip(QrFixtures::SELLER_NIP), QrFixtures::SELLER_NIP, $xml, $certificate);

        // RSASSA-PKCS1-v1_5 is deterministic; PSS is salted, so two signatures differ.
        self::assertNotSame($first, $second);

        [$signed, $signature] = self::split($first);
        $public = openssl_pkey_get_public($certificate->certificatePem);
        self::assertNotFalse($public);
        self::assertSame(0, openssl_verify($signed, $signature, $public, OPENSSL_ALGO_SHA256));

        $pkcs1 = self::rsaPublicKey($certificate)->withPadding(RSA::SIGNATURE_PKCS1);
        self::assertInstanceOf(RSA\PublicKey::class, $pkcs1);
        $pkcs1 = $pkcs1->withHash('sha256');
        self::assertInstanceOf(RSA\PublicKey::class, $pkcs1);
        self::assertFalse($pkcs1->verify($signed, $signature));
    }

    public function test_mismatched_key_refused(): void
    {
        $certificate = QrFixtures::certificate('ec');
        $otherKey = QrFixtures::certificate('ec', keyName: 'other')->privateKeyPem();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match');

        CertificateVerificationLink::build(
            KsefEnvironment::Test,
            ContextIdentifier::nip(QrFixtures::SELLER_NIP),
            QrFixtures::SELLER_NIP,
            QrFixtures::invoiceIssuedToday(),
            new CertificateCredentials($certificate->certificatePem, $otherKey),
        );
    }

    public function test_authentication_type_certificate_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Authentication');

        CertificateVerificationLink::build(
            KsefEnvironment::Test,
            ContextIdentifier::nip(QrFixtures::SELLER_NIP),
            QrFixtures::SELLER_NIP,
            QrFixtures::invoiceIssuedToday(),
            QrFixtures::certificate('ec', 'authentication'),
        );
    }

    public function test_certificate_without_key_usage_is_accepted(): void
    {
        $link = CertificateVerificationLink::build(
            KsefEnvironment::Test,
            ContextIdentifier::nip(QrFixtures::SELLER_NIP),
            QrFixtures::SELLER_NIP,
            QrFixtures::invoiceIssuedToday(),
            QrFixtures::certificate('ec', null),
        );

        self::assertMatchesRegularExpression(self::LINK_PATTERN, $link);
    }

    public function test_expired_certificate_refused(): void
    {
        $issueDate = (new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw')))->modify('+40 days')->format('Y-m-d');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not valid on the issue date '.$issueDate);

        CertificateVerificationLink::build(
            KsefEnvironment::Test,
            ContextIdentifier::nip(QrFixtures::SELLER_NIP),
            QrFixtures::SELLER_NIP,
            QrFixtures::invoice($issueDate),
            QrFixtures::certificate('ec', days: 30),
        );
    }

    public function test_certificate_not_yet_valid_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not valid on the issue date 2025-09-01');

        CertificateVerificationLink::build(
            KsefEnvironment::Test,
            ContextIdentifier::nip(QrFixtures::SELLER_NIP),
            QrFixtures::SELLER_NIP,
            QrFixtures::invoice('2025-09-01'),
            QrFixtures::certificate('ec'),
        );
    }

    public function test_link_matches_documented_shape(): void
    {
        $certificate = QrFixtures::certificate('ec', serialHex: QrFixtures::DOCS_SERIAL);
        $issueDate = new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw'));

        $link = CertificateVerificationLink::fromHash(
            KsefEnvironment::Test,
            ContextIdentifier::nip('1111111111'),
            '1111111111',
            self::DOCS_HASH,
            $issueDate,
            $certificate,
        );

        $documented = 'https://qr-test.ksef.mf.gov.pl/certificate/Nip/1111111111/1111111111/01F20A5D352AE590/UtQp9Gpc51y-u3xApZjIjgkpZ01js-J8KflSPW8WzIE/mSkm_XmM9fq7PgAJwiL32L9ujhyguOEV48cDB0ncemD2r9TMGa3lr0iRoFk588agCi8QPsOuscUY1rZ7ff76STbGquO-gZtQys5_fHdf2HUfDqPqVTnUS6HknBu0zLkyf9ygoW7WbH06Ty_8BgQTlOmJFzNWSt9WZa7tAGuAE9JOooNps-KG2PYkkIP4q4jPMp3FKypAygHVnXtS0RDGgOxhhM7LWtFP7D-dWINbh5yXD8Lr-JVbeOpyQjHa6WmMYavCDQJ3X_Z-iS01LZu2s1B3xuOykl1h0sLObCdADrbxOONsXrvQa61Xt_rxyprVraj2Uf9pANQgR4-12HEcMw';

        self::assertSame(dirname($documented), dirname($link));
        self::assertMatchesRegularExpression(self::LINK_PATTERN, $link);
    }

    public function test_context_types_and_environment_host(): void
    {
        $certificate = QrFixtures::certificate('ec');
        $xml = QrFixtures::invoiceIssuedToday();
        $hash = VerificationLink::invoiceHash($xml);

        $internal = CertificateVerificationLink::build(KsefEnvironment::Demo, ContextIdentifier::internalId(QrFixtures::SELLER_NIP.'-00001'), QrFixtures::SELLER_NIP, $xml, $certificate);
        self::assertStringStartsWith('https://qr-demo.ksef.mf.gov.pl/certificate/InternalId/'.QrFixtures::SELLER_NIP.'-00001/'.QrFixtures::SELLER_NIP.'/01F20A5D352AE590/'.$hash.'/', $internal);

        $vatUe = CertificateVerificationLink::build(KsefEnvironment::Production, ContextIdentifier::nipVatUe(QrFixtures::SELLER_NIP.'-DE123456789'), QrFixtures::SELLER_NIP, $xml, $certificate);
        self::assertStringStartsWith('https://qr.ksef.mf.gov.pl/certificate/NipVatUe/'.QrFixtures::SELLER_NIP.'-DE123456789/'.QrFixtures::SELLER_NIP.'/01F20A5D352AE590/'.$hash.'/', $vatUe);
    }

    public function test_ill_formed_seller_nip_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('seller NIP');

        CertificateVerificationLink::build(KsefEnvironment::Test, ContextIdentifier::nip(QrFixtures::SELLER_NIP), '12345', QrFixtures::invoiceIssuedToday(), QrFixtures::certificate('ec'));
    }

    public function test_invoice_without_issue_date_refused(): void
    {
        $xml = str_replace('<P_1>2025-09-01</P_1>', '', QrFixtures::invoice('2025-09-01'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('P_1');

        CertificateVerificationLink::build(KsefEnvironment::Test, ContextIdentifier::nip(QrFixtures::SELLER_NIP), QrFixtures::SELLER_NIP, $xml, QrFixtures::certificate('ec'));
    }

    /**
     * @return array{0: string, 1: string} the signed string and the raw signature
     */
    private static function split(string $link): array
    {
        if (preg_match(self::LINK_PATTERN, $link, $m) !== 1) {
            self::fail('Not a KOD II link: '.$link);
        }

        $signature = base64_decode(strtr($m[6], '-_', '+/'), true);
        self::assertIsString($signature);

        return [$m[1], $signature];
    }

    /**
     * @return list<string>
     */
    private static function segments(string $link): array
    {
        return explode('/', substr($link, strlen('https://qr-test.ksef.mf.gov.pl/certificate/')));
    }

    private static function rsaPublicKey(CertificateCredentials $certificate): RSA\PublicKey
    {
        $public = openssl_pkey_get_public($certificate->certificatePem);
        self::assertNotFalse($public);
        $details = openssl_pkey_get_details($public);
        self::assertIsArray($details);
        self::assertIsString($details['key']);

        $key = PublicKeyLoader::loadPublicKey($details['key']);
        self::assertInstanceOf(RSA\PublicKey::class, $key);

        return $key;
    }

    /**
     * IEEE P1363 r‖s (32 bytes each) to an ASN.1 DER ECDSA-Sig-Value, for openssl_verify().
     */
    private static function p1363ToDer(string $signature): string
    {
        self::assertSame(64, strlen($signature));
        $integer = static function (string $bytes): string {
            $bytes = ltrim($bytes, "\x00");
            if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
                $bytes = "\x00".$bytes;
            }

            return "\x02".chr(strlen($bytes)).$bytes;
        };
        $body = $integer(substr($signature, 0, 32)).$integer(substr($signature, 32));

        return "\x30".chr(strlen($body)).$body;
    }
}
