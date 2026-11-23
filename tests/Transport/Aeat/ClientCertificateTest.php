<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Aeat;

use DateTimeImmutable;
use Error;
use IntrAgile\EInvoicing\Spain\CertificateKind;
use IntrAgile\EInvoicing\Spain\Verifactu\AeatCredentials;
use IntrAgile\EInvoicing\Tests\Support\AeatCertificates;
use IntrAgile\EInvoicing\Tests\Support\Process;
use IntrAgile\EInvoicing\Transport\Aeat\ClientCertificate;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class ClientCertificateTest extends TestCase
{
    private const PASSWORD = 's3cret-pass-123';

    private string $directory;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/aeat-cert-test-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function test_pem_and_pkcs12_load_the_same_subject(): void
    {
        $issued = AeatCertificates::client(['commonName' => 'EMPRESA DE PRUEBA S.L.']);
        $fromPem = ClientCertificate::fromPem($issued['certificate'], $issued['privateKey']);
        $fromPkcs12 = ClientCertificate::fromPkcs12(AeatCertificates::pkcs12($issued['certificate'], $issued['privateKey'], self::PASSWORD), self::PASSWORD);

        self::assertStringContainsString('CN=EMPRESA DE PRUEBA S.L.', $fromPem->subject);
        self::assertSame($fromPem->subject, $fromPkcs12->subject);
        self::assertEquals($fromPem->notAfter, $fromPkcs12->notAfter);
        self::assertEquals($fromPem->notBefore, $fromPkcs12->notBefore);
        $expected = openssl_x509_parse($issued['certificate']);
        self::assertIsArray($expected);
        self::assertSame($expected['validTo_time_t'], $fromPem->notAfter->getTimestamp());
    }

    public function test_from_credentials_reads_pkcs12_combined_pem_and_separate_pem(): void
    {
        $issued = AeatCertificates::client(['commonName' => 'CREDENTIALS TEST'], 365, self::PASSWORD);
        $this->files[] = $p12 = AeatCertificates::file(AeatCertificates::pkcs12($issued['certificate'], self::plainKey($issued['privateKey']), self::PASSWORD), '.p12');
        $this->files[] = $combined = AeatCertificates::file($issued['certificate'].$issued['privateKey'], '.pem');
        $this->files[] = $certificate = AeatCertificates::file($issued['certificate'], '.crt');
        $this->files[] = $key = AeatCertificates::file($issued['privateKey'], '.key');

        foreach ([
            new AeatCredentials($p12, self::PASSWORD, null, CertificateKind::Seal),
            new AeatCredentials($combined, self::PASSWORD, null, CertificateKind::Seal),
            new AeatCredentials($certificate, self::PASSWORD, $key, CertificateKind::Personal),
        ] as $credentials) {
            self::assertStringContainsString('CN=CREDENTIALS TEST', ClientCertificate::fromCredentials($credentials)->subject);
        }

        $this->expectException(InvalidArgumentException::class);
        ClientCertificate::fromCredentials(new AeatCredentials($this->directory.'/missing.p12', null, null, CertificateKind::Seal));
    }

    public function test_nif_hint_from_subject(): void
    {
        $vat = AeatCertificates::client(['commonName' => 'SEAL', 'organizationIdentifier' => 'VATES-B64829179', 'serialNumber' => 'IDCES-12345678Z']);
        self::assertSame('B64829179', ClientCertificate::fromPem($vat['certificate'], $vat['privateKey'])->nifHint);

        $personal = AeatCertificates::client(['commonName' => 'PERSON', 'serialNumber' => 'IDCES-12345678Z']);
        self::assertSame('12345678Z', ClientCertificate::fromPem($personal['certificate'], $personal['privateKey'])->nifHint);

        $bare = AeatCertificates::client(['commonName' => 'PERSON', 'serialNumber' => '12345678Z']);
        self::assertSame('12345678Z', ClientCertificate::fromPem($bare['certificate'], $bare['privateKey'])->nifHint);

        $invalid = AeatCertificates::client(['commonName' => 'PERSON', 'serialNumber' => '12345678A']);
        self::assertNull(ClientCertificate::fromPem($invalid['certificate'], $invalid['privateKey'])->nifHint);

        $neither = AeatCertificates::client(['commonName' => 'NOBODY']);
        self::assertNull(ClientCertificate::fromPem($neither['certificate'], $neither['privateKey'])->nifHint);
    }

    public function test_wrong_password_is_named_without_the_password(): void
    {
        $issued = AeatCertificates::client(['commonName' => 'WRONG PASSWORD']);
        $p12 = AeatCertificates::pkcs12($issued['certificate'], $issued['privateKey'], 'the-right-one');

        $exception = self::thrown(static fn () => ClientCertificate::fromPkcs12($p12, self::PASSWORD));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('password', $exception->getMessage());
        self::assertStringNotContainsString(self::PASSWORD, $exception->getMessage());
        self::assertStringNotContainsString(self::PASSWORD, $exception->getTraceAsString());

        $encrypted = AeatCertificates::client(['commonName' => 'WRONG PASSWORD'], 365, 'the-right-one');
        $exception = self::thrown(static fn () => ClientCertificate::fromPem($encrypted['certificate'], $encrypted['privateKey'], self::PASSWORD));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('password', $exception->getMessage());
        self::assertStringNotContainsString(self::PASSWORD, $exception->getMessage());
        self::assertStringNotContainsString(self::PASSWORD, $exception->getTraceAsString());
        self::assertFalse(openssl_error_string(), 'The OpenSSL error queue was left filled.');
    }

    public function test_combined_pem_failures_keep_key_material_out_of_traces(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            $certificate = AeatCertificates::client(['commonName' => 'COMBINED']);
            $other = AeatCertificates::client(['commonName' => 'OTHER'], 365, self::PASSWORD);
            $encrypted = AeatCertificates::client(['commonName' => 'COMBINED'], 365, 'the-right-one');

            $this->files[] = $mismatched = AeatCertificates::file($certificate['certificate'].self::plainKey($other['privateKey']), '.pem');
            $this->files[] = $wrongPassword = AeatCertificates::file($encrypted['certificate'].$encrypted['privateKey'], '.pem');

            foreach ([
                [new AeatCredentials($mismatched, null, null, CertificateKind::Seal), 'does not match'],
                [new AeatCredentials($mismatched, self::PASSWORD, null, CertificateKind::Seal), 'does not match'],
                [new AeatCredentials($wrongPassword, self::PASSWORD, null, CertificateKind::Seal), 'password'],
            ] as [$credentials, $message]) {
                $exception = self::thrown(static fn () => ClientCertificate::fromCredentials($credentials));
                self::assertInstanceOf(InvalidArgumentException::class, $exception);
                self::assertStringContainsString($message, $exception->getMessage());
                self::assertTracesHold($exception, static fn (string $argument): bool => str_contains($argument, 'PRIVATE KEY') || str_contains($argument, self::PASSWORD));
            }

            $direct = self::thrown(static fn () => ClientCertificate::fromPem($certificate['certificate'].$other['privateKey'], $other['privateKey'], self::PASSWORD));
            self::assertTracesHold($direct, static fn (string $argument): bool => str_contains($argument, 'PRIVATE KEY') || str_contains($argument, self::PASSWORD));
        } finally {
            ini_set('zend.exception_ignore_args', $previous === false ? '0' : $previous);
        }
    }

    public function test_pkcs12_failures_keep_the_file_and_password_out_of_traces(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            $issued = AeatCertificates::client(['commonName' => 'PKCS12 TRACE']);
            $bytes = AeatCertificates::pkcs12($issued['certificate'], $issued['privateKey'], 'the-right-one');
            $this->files[] = $p12 = AeatCertificates::file($bytes, '.p12');

            foreach ([
                static fn () => ClientCertificate::fromPkcs12($bytes, self::PASSWORD),
                static fn () => ClientCertificate::fromCredentials(new AeatCredentials($p12, self::PASSWORD, null, CertificateKind::Seal)),
            ] as $callback) {
                $exception = self::thrown($callback);
                self::assertInstanceOf(InvalidArgumentException::class, $exception);
                self::assertTracesHold($exception, static fn (string $argument): bool => $argument === $bytes || str_contains($argument, self::PASSWORD));
            }
        } finally {
            ini_set('zend.exception_ignore_args', $previous === false ? '0' : $previous);
        }
    }

    public function test_mismatched_pem_key_is_refused(): void
    {
        $one = AeatCertificates::client(['commonName' => 'ONE']);
        $two = AeatCertificates::client(['commonName' => 'TWO']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match');

        ClientCertificate::fromPem($one['certificate'], $two['privateKey']);
    }

    public function test_legacy_pkcs12_message_names_the_conversion(): void
    {
        $issued = AeatCertificates::client(['commonName' => 'LEGACY']);
        $this->files[] = $certificate = AeatCertificates::file($issued['certificate'], '.crt');
        $this->files[] = $key = AeatCertificates::file($issued['privateKey'], '.key');
        $this->files[] = $legacy = $this->directory.'/legacy.p12';

        try {
            $result = Process::run(['openssl', 'pkcs12', '-export', '-legacy', '-in', $certificate, '-inkey', $key, '-out', $legacy, '-passout', 'pass:'.self::PASSWORD], 30.0);
        } catch (RuntimeException $exception) {
            self::markTestSkipped('The openssl command line tool is not available: '.$exception->getMessage());
        }
        if ($result['status'] !== 0) {
            self::markTestSkipped('This openssl command line tool cannot write legacy PKCS#12 files.');
        }
        $bytes = (string) file_get_contents($legacy);
        if (@openssl_pkcs12_read($bytes, $ignored, self::PASSWORD)) {
            self::markTestSkipped('This PHP OpenSSL build still reads legacy PKCS#12 files.');
        }
        while (openssl_error_string() !== false) {
            // Drain the probe's errors.
        }

        $exception = self::thrown(static fn () => ClientCertificate::fromPkcs12($bytes, self::PASSWORD));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('openssl pkcs12 -legacy', $exception->getMessage());
        self::assertStringNotContainsString(self::PASSWORD, $exception->getMessage());
    }

    public function test_expired_certificate_loads(): void
    {
        $issued = AeatCertificates::client(['commonName' => 'EXPIRED'], 0);
        $certificate = ClientCertificate::fromPem($issued['certificate'], $issued['privateKey']);

        self::assertLessThan(0, $certificate->daysUntilExpiry(new DateTimeImmutable('+1 day')));
        self::assertSame(-2, $certificate->daysUntilExpiry($certificate->notAfter->modify('+2 days')));
        self::assertSame(30, $certificate->daysUntilExpiry($certificate->notAfter->modify('-30 days')));
    }

    public function test_materialized_files_are_0600_and_disposed(): void
    {
        $issued = AeatCertificates::client(['commonName' => 'MATERIALIZED']);
        $certificate = ClientCertificate::fromPem($issued['certificate'], $issued['privateKey']);

        $materialized = $certificate->materialize($this->directory);
        foreach ([$materialized->certificatePath, $materialized->privateKeyPath] as $path) {
            self::assertFileExists($path);
            self::assertSame(0600, fileperms($path) & 0777);
            self::assertStringStartsWith($this->directory.'/', $path);
        }
        self::assertStringContainsString('BEGIN CERTIFICATE', (string) file_get_contents($materialized->certificatePath));
        self::assertStringContainsString('BEGIN PRIVATE KEY', (string) file_get_contents($materialized->privateKeyPath));

        $materialized->dispose();
        self::assertFileDoesNotExist($materialized->certificatePath);
        self::assertFileDoesNotExist($materialized->privateKeyPath);
        $materialized->dispose();

        $second = $certificate->materialize($this->directory);
        $paths = [$second->certificatePath, $second->privateKeyPath];
        self::assertNotSame($materialized->certificatePath, $second->certificatePath);
        unset($second);
        foreach ($paths as $path) {
            self::assertFileDoesNotExist($path);
        }

        $this->expectException(InvalidArgumentException::class);
        $certificate->materialize($this->directory.'/missing');
    }

    public function test_options_of_a_dropped_temporary_name_deleted_files(): void
    {
        $issued = AeatCertificates::client(['commonName' => 'TEMPORARY']);
        $certificate = ClientCertificate::fromPem($issued['certificate'], $issued['privateKey']);

        $held = $certificate->materialize($this->directory);
        $heldOptions = $held->guzzleOptions();
        self::assertIsString($heldOptions['ssl_key']);
        self::assertFileExists($heldOptions['cert']);
        self::assertFileExists($heldOptions['ssl_key']);

        // The temporary is destroyed at the end of the statement, and its files with it.
        $options = $certificate->materialize($this->directory)->guzzleOptions();
        self::assertIsString($options['ssl_key']);
        self::assertFileDoesNotExist($options['cert']);
        self::assertFileDoesNotExist($options['ssl_key']);
        $stream = $certificate->materialize($this->directory)->streamContextOptions();
        self::assertFileDoesNotExist($stream['local_cert']);

        self::assertFileExists($heldOptions['cert']);
        unset($held);
        self::assertFileDoesNotExist($heldOptions['cert']);
        self::assertFileDoesNotExist($heldOptions['ssl_key']);
    }

    public function test_materialized_certificate_refuses_cloning(): void
    {
        $issued = AeatCertificates::client(['commonName' => 'CLONE']);
        $materialized = ClientCertificate::fromPem($issued['certificate'], $issued['privateKey'])->materialize($this->directory);

        $exception = self::thrown(static fn (): object => clone self::untyped($materialized));
        self::assertInstanceOf(Error::class, $exception);
        self::assertStringContainsString('__clone', $exception->getMessage());
        self::assertFileExists($materialized->certificatePath);
        self::assertFileExists($materialized->privateKeyPath);
    }

    public function test_pkcs12_key_is_written_encrypted_when_the_source_had_a_password(): void
    {
        $issued = AeatCertificates::client(['commonName' => 'ENCRYPTED']);
        $withPassword = ClientCertificate::fromPkcs12(AeatCertificates::pkcs12($issued['certificate'], $issued['privateKey'], self::PASSWORD), self::PASSWORD);
        $materialized = $withPassword->materialize($this->directory);

        $key = (string) file_get_contents($materialized->privateKeyPath);
        self::assertStringContainsString('ENCRYPTED PRIVATE KEY', $key);
        self::assertNotFalse(openssl_pkey_get_private($key, self::PASSWORD));
        self::assertSame([$materialized->privateKeyPath, self::PASSWORD], $materialized->guzzleOptions()['ssl_key']);
        $chain = (string) file_get_contents($materialized->certificatePath);
        self::assertSame(2, substr_count($chain, 'BEGIN CERTIFICATE'), 'The intermediate certificates are written after the leaf.');

        $withoutPassword = ClientCertificate::fromPkcs12(AeatCertificates::pkcs12($issued['certificate'], $issued['privateKey'], null), null);
        $plain = $withoutPassword->materialize($this->directory);
        self::assertStringContainsString('BEGIN PRIVATE KEY', (string) file_get_contents($plain->privateKeyPath));
        self::assertSame($plain->privateKeyPath, $plain->guzzleOptions()['ssl_key']);
        self::assertArrayNotHasKey('passphrase', $plain->streamContextOptions());
    }

    public function test_options_never_disable_verification(): void
    {
        $issued = AeatCertificates::client(['commonName' => 'OPTIONS']);
        $materialized = ClientCertificate::fromPem($issued['certificate'], $issued['privateKey'])->materialize($this->directory);

        $certificate = $materialized->certificatePath;
        $key = $materialized->privateKeyPath;
        $bundle = '/etc/ssl/bundle.pem';

        self::assertSame(
            ['cert' => $certificate, 'ssl_key' => $key, 'verify' => true, 'timeout' => 30.0, 'connect_timeout' => 10.0, 'allow_redirects' => false],
            $materialized->guzzleOptions(),
        );
        self::assertSame(
            ['cert' => $certificate, 'ssl_key' => $key, 'verify' => $bundle, 'timeout' => 12.0, 'connect_timeout' => 10.0, 'allow_redirects' => false],
            $materialized->guzzleOptions($bundle, 12.0),
        );
        self::assertSame(
            ['local_cert' => $certificate, 'local_pk' => $key, 'passphrase' => null, 'cafile' => $bundle, 'verify_peer' => true, 'verify_host' => true, 'timeout' => 30.0, 'max_duration' => 30.0, 'max_redirects' => 0],
            $materialized->symfonyOptions($bundle),
        );
        self::assertSame(
            ['local_cert' => $certificate, 'local_pk' => $key, 'cafile' => $bundle, 'verify_peer' => true, 'verify_peer_name' => true],
            $materialized->streamContextOptions($bundle),
        );
        self::assertSame(
            ['local_cert' => $certificate, 'local_pk' => $key, 'verify_peer' => true, 'verify_peer_name' => true],
            $materialized->streamContextOptions(),
        );

        $this->expectException(InvalidArgumentException::class);
        $materialized->guzzleOptions(null, 0.0);
    }

    public function test_dumps_hold_no_secret(): void
    {
        $issued = AeatCertificates::client(['commonName' => 'DUMPS']);
        $certificate = ClientCertificate::fromPkcs12(AeatCertificates::pkcs12($issued['certificate'], $issued['privateKey'], self::PASSWORD), self::PASSWORD);
        $materialized = $certificate->materialize($this->directory);
        $keyBody = self::keyBody($issued['privateKey']);
        $writtenKeyBody = self::keyBody((string) file_get_contents($materialized->privateKeyPath));

        foreach ([$certificate, $materialized] as $object) {
            ob_start();
            var_dump($object);
            $dumps = [
                (string) ob_get_clean(),
                print_r($object, true),
                var_export($object, true),
                (string) json_encode($object),
                print_r((array) $object, true),
            ];
            foreach ($dumps as $dump) {
                self::assertStringNotContainsString(self::PASSWORD, $dump);
                self::assertStringNotContainsString($keyBody, $dump);
                self::assertStringNotContainsString($writtenKeyBody, $dump);
                self::assertStringNotContainsString('PRIVATE KEY', $dump);
            }

            $exception = self::thrown(static fn () => serialize($object));
            self::assertInstanceOf(LogicException::class, $exception);
        }
        self::assertStringContainsString('[redacted]', print_r($materialized, true));
    }

    private static function keyBody(string $pem): string
    {
        $lines = explode("\n", trim($pem));

        return $lines[3] ?? $lines[1];
    }

    private static function plainKey(string $encrypted): string
    {
        $key = openssl_pkey_get_private($encrypted, self::PASSWORD);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $plain);
        self::assertIsString($plain);

        return $plain;
    }

    /**
     * Fails when any string argument in the traces of $exception and its previous exceptions
     * matches $leaks.
     *
     * @param  callable(string): bool  $leaks
     */
    private static function assertTracesHold(Throwable $exception, callable $leaks): void
    {
        $frames = 0;
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            foreach ($current->getTrace() as $index => $frame) {
                $frames++;
                foreach ($frame['args'] ?? [] as $argument) {
                    if (is_string($argument) && $leaks($argument)) {
                        self::fail(sprintf('Frame %d (%s) carries secret material.', $index, $frame['function']));
                    }
                }
            }
        }
        self::assertGreaterThan(0, $frames);
    }

    /**
     * Hides the static type, so the clone attempt above is analysed as cloning any object.
     */
    private static function untyped(object $value): object
    {
        return $value;
    }

    private static function thrown(callable $callback): Throwable
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            return $exception;
        }
        self::fail('Nothing was thrown.');
    }
}
