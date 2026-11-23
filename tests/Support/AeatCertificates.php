<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use RuntimeException;

/**
 * Throwaway certificates for the AEAT client tests: a test CA, client certificates it signs, a
 * server certificate for 127.0.0.1 and an already expired certificate. Nothing here is trusted
 * outside the test run; every key is generated in memory.
 */
final class AeatCertificates
{
    private const OPENSSL_CONFIG = <<<'CNF'
        [req]
        distinguished_name = dn
        [dn]
        [v3_ca]
        basicConstraints = critical,CA:TRUE
        keyUsage = critical,keyCertSign,cRLSign
        subjectKeyIdentifier = hash
        [v3_server]
        basicConstraints = CA:FALSE
        keyUsage = critical,digitalSignature,keyEncipherment
        extendedKeyUsage = serverAuth
        subjectAltName = IP:127.0.0.1
        [v3_client]
        basicConstraints = CA:FALSE
        keyUsage = critical,digitalSignature,keyEncipherment
        extendedKeyUsage = clientAuth
        CNF;

    private static ?string $configPath = null;

    /** @var array{certificate: string, key: OpenSSLAsymmetricKey}|null */
    private static ?array $ca = null;

    /**
     * The test CA certificate, PEM-encoded.
     */
    public static function caPem(): string
    {
        return self::ca()['certificate'];
    }

    /**
     * A client certificate signed by the test CA.
     *
     * @param  array<string, string>  $subject  distinguished name fields, e.g. ['commonName' => …]
     * @param  int  $days  validity; 0 gives a certificate that expires at once
     * @return array{certificate: string, privateKey: string}
     */
    public static function client(array $subject, int $days = 365, ?string $password = null): array
    {
        return self::issue($subject, 'v3_client', $days, $password);
    }

    /**
     * A server certificate for 127.0.0.1 signed by the test CA.
     *
     * @return array{certificate: string, privateKey: string}
     */
    public static function server(): array
    {
        return self::issue(['commonName' => '127.0.0.1'], 'v3_server', 2);
    }

    /**
     * A self-signed CA certificate with the given common name, PEM-encoded.
     */
    public static function selfSigned(string $commonName): string
    {
        $key = self::newKey();
        // openssl_csr_new() takes the key by reference; a copy keeps $key typed.
        $requestKey = $key;
        $csr = openssl_csr_new(['commonName' => $commonName], $requestKey, self::options('v3_ca'));
        if ($csr === false || $csr === true) {
            throw new RuntimeException('Cannot create a test certificate request.');
        }
        $certificate = openssl_csr_sign($csr, null, $key, 2, self::options('v3_ca'), random_int(1, PHP_INT_MAX));

        return self::export($certificate);
    }

    /**
     * Exports a certificate and its key as PKCS#12 with the platform's default algorithms.
     */
    public static function pkcs12(string $certificatePem, string $privateKeyPem, ?string $password): string
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false || ! openssl_pkcs12_export($certificatePem, $out, $key, $password ?? '', ['extracerts' => [self::caPem()]]) || ! is_string($out)) {
            throw new RuntimeException('Cannot export a test PKCS#12 file.');
        }

        return $out;
    }

    /**
     * Writes $contents to a fresh file in the temporary folder and returns its path.
     */
    public static function file(string $contents, string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), 'aeat-test-');
        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary file.');
        }
        $target = $path.$suffix;
        rename($path, $target);
        file_put_contents($target, $contents);

        return $target;
    }

    /**
     * @param  array<string, string>  $subject
     * @return array{certificate: string, privateKey: string}
     */
    private static function issue(array $subject, string $extensions, int $days, ?string $password = null): array
    {
        $ca = self::ca();
        $key = self::newKey();
        $requestKey = $key;
        $csr = openssl_csr_new($subject, $requestKey, self::options($extensions));
        if ($csr === false || $csr === true) {
            throw new RuntimeException('Cannot create a test certificate request.');
        }
        $certificate = openssl_csr_sign($csr, $ca['certificate'], $ca['key'], $days, self::options($extensions), random_int(1, PHP_INT_MAX));
        if (! openssl_pkey_export($key, $privateKey, $password, ['config' => self::configPath()]) || ! is_string($privateKey)) {
            throw new RuntimeException('Cannot export a test private key.');
        }

        return ['certificate' => self::export($certificate), 'privateKey' => $privateKey];
    }

    /**
     * @return array{certificate: string, key: OpenSSLAsymmetricKey}
     */
    private static function ca(): array
    {
        if (self::$ca === null) {
            $key = self::newKey();
            $requestKey = $key;
            $csr = openssl_csr_new(['commonName' => 'IntrAgile Throwaway Test CA'], $requestKey, self::options('v3_ca'));
            if ($csr === false || $csr === true) {
                throw new RuntimeException('Cannot create the test CA request.');
            }
            $certificate = openssl_csr_sign($csr, null, $key, 2, self::options('v3_ca'), random_int(1, PHP_INT_MAX));
            self::$ca = ['certificate' => self::export($certificate), 'key' => $key];
        }

        return self::$ca;
    }

    private static function newKey(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => self::configPath()]);
        if ($key === false) {
            throw new RuntimeException('Cannot generate a test key.');
        }

        return $key;
    }

    private static function export(OpenSSLCertificate|false $certificate): string
    {
        if ($certificate === false || ! openssl_x509_export($certificate, $pem) || ! is_string($pem)) {
            throw new RuntimeException('Cannot sign a test certificate.');
        }

        return $pem;
    }

    /**
     * @return array{config: string, digest_alg: string, x509_extensions: string, req_extensions: string}
     */
    private static function options(string $extensions): array
    {
        return ['config' => self::configPath(), 'digest_alg' => 'sha256', 'x509_extensions' => $extensions, 'req_extensions' => $extensions];
    }

    private static function configPath(): string
    {
        if (self::$configPath === null || ! is_file(self::$configPath)) {
            $path = self::$configPath = self::file(self::OPENSSL_CONFIG, '.cnf');
            register_shutdown_function(static function () use ($path): void {
                if (is_file($path)) {
                    unlink($path);
                }
            });
        }

        return self::$configPath;
    }
}
