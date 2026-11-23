<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\AeatCredentials;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Secret;
use InvalidArgumentException;
use LogicException;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use RuntimeException;
use SensitiveParameter;

/**
 * The client certificate for the AEAT web services, loaded from PEM or PKCS#12 and held in memory.
 *
 * The private key is kept as PEM, encrypted with the source password when there was one, and
 * together with that password stays out of var_export(), print_r(), var_dump(), json_encode() and
 * array-cast output; the object refuses to be serialized. An expired certificate still loads, so
 * its state can be reported; daysUntilExpiry() turns negative. materialize() writes the files an
 * HTTP client needs.
 */
final class ClientCertificate
{
    private const PEM_CERTIFICATE = '/-----BEGIN CERTIFICATE-----\s.+?-----END CERTIFICATE-----/s';

    private const PEM_PRIVATE_KEY = '/-----BEGIN ((?:ENCRYPTED |RSA |EC |DSA )?PRIVATE KEY)-----\s.+?-----END \1-----/s';

    /** The certificate in OpenSSL's one-line form, e.g. "/C=ES/O=…/CN=…". */
    public readonly string $subject;

    /** The NIF the subject names, when it names one; a hint only, never checked against the AEAT. */
    public readonly ?string $nifHint;

    public readonly DateTimeImmutable $notBefore;

    public readonly DateTimeImmutable $notAfter;

    /** The leaf certificate followed by any intermediate certificates, PEM-encoded. */
    private readonly string $chainPem;

    private readonly Secret $privateKeyPem;

    private readonly ?Secret $password;

    /**
     * @param  list<string>  $intermediates  PEM-encoded certificates that follow the leaf
     *
     * @throws InvalidArgumentException when the key cannot be exported
     */
    private function __construct(OpenSSLCertificate $certificate, array $intermediates, OpenSSLAsymmetricKey $key, #[SensitiveParameter] ?string $password)
    {
        $password = $password === '' ? null : $password;
        $parsed = openssl_x509_parse($certificate);
        $name = $parsed === false ? null : ($parsed['name'] ?? null);
        $subject = $parsed === false ? null : ($parsed['subject'] ?? null);
        $validFrom = $parsed === false ? null : ($parsed['validFrom_time_t'] ?? null);
        $validTo = $parsed === false ? null : ($parsed['validTo_time_t'] ?? null);
        if (! is_string($name) || ! is_array($subject) || ! is_int($validFrom) || ! is_int($validTo)
            || ! openssl_x509_export($certificate, $leafPem) || ! is_string($leafPem)) {
            self::clearOpenSslErrors();

            throw new InvalidArgumentException('The certificate cannot be read.');
        }
        if (! @openssl_pkey_export($key, $keyPem, $password, ['encrypt_key_cipher' => OPENSSL_CIPHER_AES_256_CBC]) || ! is_string($keyPem)) {
            self::clearOpenSslErrors();

            throw new InvalidArgumentException('The private key cannot be exported.');
        }

        $this->subject = $name;
        $this->nifHint = self::nifHint($subject);
        $this->notBefore = new DateTimeImmutable('@'.$validFrom);
        $this->notAfter = new DateTimeImmutable('@'.$validTo);
        $this->chainPem = rtrim($leafPem)."\n".implode('', array_map(static fn (string $pem): string => rtrim($pem)."\n", $intermediates));
        $this->privateKeyPem = new Secret($keyPem);
        $this->password = $password === null ? null : new Secret($password);
    }

    /**
     * Loads the certificate the credentials point at. With a private key path, both files are PEM.
     * Without one, the certificate file is read as a single PEM holding certificate and key when
     * it starts with "-----BEGIN", and as PKCS#12 otherwise.
     *
     * @throws InvalidArgumentException when a file cannot be read, or its contents cannot be loaded
     */
    public static function fromCredentials(AeatCredentials $credentials): self
    {
        $certificate = self::read($credentials->certificatePath);
        if ($credentials->privateKeyPath !== null) {
            return self::fromPem($certificate, self::read($credentials->privateKeyPath), $credentials->password());
        }
        if (str_starts_with(ltrim($certificate), '-----BEGIN')) {
            // A combined file: only the certificate blocks travel as the certificate argument,
            // so the key never sits in an argument that is not marked sensitive.
            preg_match_all(self::PEM_CERTIFICATE, $certificate, $certificates);
            $privateKey = preg_match(self::PEM_PRIVATE_KEY, $certificate, $key) === 1 ? $key[0] : '';

            return self::fromPem(implode("\n", $certificates[0]), $privateKey, $credentials->password());
        }

        return self::fromPkcs12($certificate, $credentials->password());
    }

    /**
     * Loads a PKCS#12 (.p12 / .pfx) file.
     *
     * @param  string  $bytes  the file contents
     * @param  ?string  $password  its password; null when it has none
     *
     * @throws InvalidArgumentException when the file cannot be opened; the password is never quoted
     */
    public static function fromPkcs12(#[SensitiveParameter] string $bytes, #[SensitiveParameter] ?string $password): self
    {
        if (! @openssl_pkcs12_read($bytes, $contents, $password ?? '')) {
            $errors = self::drainOpenSslErrors();
            if (str_contains(strtolower($errors), 'unsupported')) {
                throw new InvalidArgumentException(
                    'The PKCS#12 file uses a legacy algorithm (such as RC2 or 3DES) that this OpenSSL build does not enable. '
                    .'Convert it with "openssl pkcs12 -legacy -in old.p12 -out tmp.pem" followed by '
                    .'"openssl pkcs12 -export -in tmp.pem -out new.p12", then delete tmp.pem.',
                );
            }

            throw new InvalidArgumentException('The PKCS#12 file cannot be read: it is not a PKCS#12 file, or the password is wrong.');
        }

        $certificatePem = is_array($contents) ? ($contents['cert'] ?? null) : null;
        $keyPem = is_array($contents) ? ($contents['pkey'] ?? null) : null;
        $certificate = is_string($certificatePem) ? @openssl_x509_read($certificatePem) : false;
        $key = is_string($keyPem) ? @openssl_pkey_get_private($keyPem) : false;
        if (! is_array($contents) || $certificate === false || $key === false) {
            self::clearOpenSslErrors();

            throw new InvalidArgumentException('The PKCS#12 file holds no usable certificate and private key.');
        }
        if (! openssl_x509_check_private_key($certificate, $key)) {
            self::clearOpenSslErrors();

            throw new InvalidArgumentException('The private key does not match the certificate.');
        }

        $intermediates = [];
        $extras = $contents['extracerts'] ?? [];
        foreach (is_array($extras) ? $extras : [] as $extra) {
            if (is_string($extra)) {
                $intermediates[] = $extra;
            }
        }

        return new self($certificate, $intermediates, $key, $password);
    }

    /**
     * Loads a PEM certificate and its private key. The certificate text may hold intermediate
     * certificates; the one matching the key becomes the leaf, the others follow it in order.
     *
     * The certificate argument is marked sensitive as well, because callers may pass text that
     * also holds the key.
     *
     * @param  ?string  $password  the password of an encrypted private key
     *
     * @throws InvalidArgumentException when the certificate or the key cannot be read, or the key
     *                                  belongs to none of the certificates; the password is never quoted
     */
    public static function fromPem(#[SensitiveParameter] string $certificatePem, #[SensitiveParameter] string $privateKeyPem, #[SensitiveParameter] ?string $password = null): self
    {
        preg_match_all(self::PEM_CERTIFICATE, $certificatePem, $matches);
        $certificates = [];
        foreach ($matches[0] as $pem) {
            $certificate = @openssl_x509_read($pem);
            if ($certificate === false) {
                self::clearOpenSslErrors();

                throw new InvalidArgumentException('The certificate cannot be read as a PEM-encoded X.509 certificate.');
            }
            $certificates[] = [$pem, $certificate];
        }
        if ($certificates === []) {
            throw new InvalidArgumentException('The certificate cannot be read as a PEM-encoded X.509 certificate.');
        }

        $key = @openssl_pkey_get_private($privateKeyPem, $password);
        if ($key === false) {
            self::clearOpenSslErrors();

            throw new InvalidArgumentException('The private key cannot be read: it is not a PEM-encoded private key, or the password is wrong.');
        }

        foreach ($certificates as $index => [, $certificate]) {
            if (openssl_x509_check_private_key($certificate, $key)) {
                $intermediates = [];
                foreach ($certificates as $other => [$pem]) {
                    if ($other !== $index) {
                        $intermediates[] = $pem;
                    }
                }
                self::clearOpenSslErrors();

                return new self($certificate, $intermediates, $key, $password);
            }
        }
        self::clearOpenSslErrors();

        throw new InvalidArgumentException('The private key does not match the certificate.');
    }

    /**
     * Whole days from $now until the certificate expires, rounded down; negative once it has expired.
     */
    public function daysUntilExpiry(DateTimeImmutable $now): int
    {
        return (int) floor(($this->notAfter->getTimestamp() - $now->getTimestamp()) / 86400);
    }

    /**
     * Writes the certificate chain and the private key into $directory as two new files with
     * permissions 0600. The key file is encrypted with the source password when there was one.
     *
     * @throws InvalidArgumentException when $directory is not a writable directory
     * @throws RuntimeException when a file cannot be written
     */
    public function materialize(string $directory): MaterializedCertificate
    {
        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a writable directory.', $directory));
        }
        $base = rtrim($directory, '/\\').'/aeat-'.bin2hex(random_bytes(12));

        $certificatePath = self::writePrivately($base.'-certificate.pem', $this->chainPem);
        try {
            $privateKeyPath = self::writePrivately($base.'-key.pem', $this->privateKeyPem->reveal());
        } catch (RuntimeException $exception) {
            @unlink($certificatePath);

            throw $exception;
        }

        return new MaterializedCertificate($certificatePath, $privateKeyPath, $this->password);
    }

    /**
     * @return array{subject: string, nifHint: ?string, notBefore: string, notAfter: string, privateKey: string, password: ?string}
     */
    public function __debugInfo(): array
    {
        return [
            'subject' => $this->subject,
            'nifHint' => $this->nifHint,
            'notBefore' => $this->notBefore->format(DATE_ATOM),
            'notAfter' => $this->notAfter->format(DATE_ATOM),
            'privateKey' => Redactor::REDACTED,
            'password' => $this->password === null ? null : Redactor::REDACTED,
        ];
    }

    /**
     * @throws LogicException Always: a private key must not be written to a cache, queue or session
     */
    public function __serialize(): never
    {
        throw new LogicException('An AEAT client certificate cannot be serialized.');
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws LogicException Always
     */
    public function __unserialize(array $data): never
    {
        throw new LogicException('An AEAT client certificate cannot be serialized.');
    }

    /**
     * The NIF named by the subject: the organizationIdentifier "VATES-<nif>" first, then the
     * serialNumber "IDCES-<nif>", then a bare serialNumber that is a valid NIF.
     *
     * @param  array<mixed>  $subject
     */
    private static function nifHint(array $subject): ?string
    {
        $organizationIdentifiers = array_filter((array) ($subject['organizationIdentifier'] ?? []), is_string(...));
        $serialNumbers = array_filter((array) ($subject['serialNumber'] ?? []), is_string(...));

        foreach ($organizationIdentifiers as $value) {
            if (preg_match('/\AVATES-([0-9A-Z]+)\z/', $value, $match) === 1) {
                return $match[1];
            }
        }
        foreach ($serialNumbers as $value) {
            if (preg_match('/\AIDCES-([0-9A-Z]+)\z/', $value, $match) === 1) {
                return $match[1];
            }
        }
        foreach ($serialNumbers as $value) {
            if (Nif::isValid($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @throws InvalidArgumentException when the file cannot be read
     */
    private static function read(string $path): string
    {
        $contents = is_file($path) && is_readable($path) ? @file_get_contents($path) : false;
        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('The certificate file "%s" cannot be read.', $path));
        }

        return $contents;
    }

    /**
     * Creates $path exclusively, readable and writable by the owner only from the first moment,
     * and writes $contents into it.
     *
     * @throws RuntimeException when the file cannot be created or written
     */
    private static function writePrivately(string $path, #[SensitiveParameter] string $contents): string
    {
        // The umask keeps the file private from creation, so no other user can open it before chmod.
        $umask = umask(0077);
        try {
            $handle = @fopen($path, 'xb');
        } finally {
            umask($umask);
        }
        if ($handle === false) {
            throw new RuntimeException(sprintf('The file "%s" cannot be created.', $path));
        }

        $written = @chmod($path, 0600) ? @fwrite($handle, $contents) : false;
        fclose($handle);
        if ($written !== strlen($contents)) {
            @unlink($path);

            throw new RuntimeException(sprintf('The file "%s" cannot be written.', $path));
        }

        return $path;
    }

    private static function drainOpenSslErrors(): string
    {
        $errors = '';
        while (($error = openssl_error_string()) !== false) {
            $errors .= $error."\n";
        }

        return $errors;
    }

    private static function clearOpenSslErrors(): void
    {
        self::drainOpenSslErrors();
    }
}
