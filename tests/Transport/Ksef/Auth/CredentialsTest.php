<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth;

use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Credentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Testing\SelfSignedCertificate;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class CredentialsTest extends TestCase
{
    private const TOKEN = '20251010-EC-1A2B3C4D5E-F6A7B8C9D0-12|nip-5265877635|0123456789abcdef0123456789abcdef';

    public function test_certificate_credentials_keep_the_material(): void
    {
        $seal = XadesSignerTest::seal();
        $credentials = new CertificateCredentials($seal->certificatePem, $seal->privateKeyPem());

        self::assertInstanceOf(Credentials::class, $credentials);
        self::assertSame($seal->certificatePem, $credentials->certificatePem);
        self::assertSame($seal->privateKeyPem(), $credentials->privateKeyPem());
        self::assertNull($credentials->passphrase());
    }

    public function test_encrypted_private_key_is_accepted_with_its_passphrase(): void
    {
        $seal = XadesSignerTest::seal();
        $encrypted = self::encrypted($seal->privateKeyPem(), 'correct horse');

        $credentials = new CertificateCredentials($seal->certificatePem, $encrypted, 'correct horse');
        self::assertSame('correct horse', $credentials->passphrase());

        $this->expectException(InvalidArgumentException::class);
        new CertificateCredentials($seal->certificatePem, $encrypted, 'wrong');
    }

    public function test_mismatched_key_is_refused(): void
    {
        $other = SelfSignedCertificate::seal(SelfSignedCertificate::randomNip());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match');

        new CertificateCredentials(XadesSignerTest::seal()->certificatePem, $other->privateKeyPem());
    }

    public function test_unreadable_certificate_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CertificateCredentials("-----BEGIN CERTIFICATE-----\nbm90IGEgY2VydA==\n-----END CERTIFICATE-----", XadesSignerTest::seal()->privateKeyPem());
    }

    public function test_unreadable_private_key_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CertificateCredentials(XadesSignerTest::seal()->certificatePem, 'not a key');
    }

    public function test_token_credentials_keep_the_token(): void
    {
        $credentials = new TokenCredentials(self::TOKEN);

        self::assertInstanceOf(Credentials::class, $credentials);
        self::assertSame(self::TOKEN, $credentials->token());
    }

    public function test_blank_token_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TokenCredentials('  ');
    }

    public function test_credentials_debug_info_is_redacted(): void
    {
        $seal = XadesSignerTest::seal();
        $encrypted = self::encrypted($seal->privateKeyPem(), 'correct horse');
        $certificate = new CertificateCredentials($seal->certificatePem, $encrypted, 'correct horse');
        $token = new TokenCredentials(self::TOKEN);

        self::assertSame(Redactor::REDACTED, $certificate->__debugInfo()['privateKeyPem']);
        self::assertSame(Redactor::REDACTED, $certificate->__debugInfo()['passphrase']);
        self::assertSame(Redactor::REDACTED, $token->__debugInfo()['token']);
        self::assertNull((new CertificateCredentials($seal->certificatePem, $seal->privateKeyPem()))->__debugInfo()['passphrase']);

        $secrets = [
            'BEGIN ENCRYPTED PRIVATE KEY', 'BEGIN PRIVATE KEY', self::body($encrypted), self::body($seal->privateKeyPem()),
            'correct horse', self::TOKEN, 'nip-5265877635',
        ];
        foreach ([$certificate, $token, $seal] as $credentials) {
            ob_start();
            var_dump($credentials);
            $dump = (string) ob_get_clean();
            $outputs = [
                'var_dump' => $dump,
                'print_r' => print_r($credentials, true),
                'var_export' => var_export($credentials, true),
                'json_encode' => (string) json_encode($credentials),
                'array cast, print_r' => print_r((array) $credentials, true),
                'array cast, var_export' => var_export((array) $credentials, true),
            ];
            foreach ($outputs as $how => $output) {
                foreach ($secrets as $secret) {
                    self::assertStringNotContainsString($secret, $output, $how);
                }
            }
        }
    }

    public function test_certificate_credentials_cannot_be_serialized(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be serialized');

        serialize(XadesSignerTest::seal());
    }

    public function test_token_credentials_cannot_be_serialized(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be serialized');

        serialize(new TokenCredentials(self::TOKEN));
    }

    /**
     * The private key re-exported, encrypted with the passphrase.
     */
    private static function encrypted(string $keyPem, string $passphrase): string
    {
        $key = openssl_pkey_get_private($keyPem);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $encrypted, $passphrase, ['config' => dirname(__DIR__, 4).'/resources/poland/ksef-testing/openssl.cnf']));
        self::assertIsString($encrypted);

        return $encrypted;
    }

    /**
     * A distinctive slice of the Base64 body of a PEM block.
     */
    private static function body(string $pem): string
    {
        return substr((string) preg_replace('/-----[^-]+-----|\s/', '', $pem), 40, 48);
    }
}
