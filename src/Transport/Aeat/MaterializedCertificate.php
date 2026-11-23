<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Secret;
use InvalidArgumentException;
use LogicException;

/**
 * A client certificate written to disk for an HTTP client that only takes file paths.
 *
 * The certificate chain and the private key are separate files with permissions 0600; the key is
 * encrypted whenever its source had a password. The option arrays configure the PSR-18 client the
 * integrator builds (Guzzle, Symfony HttpClient or PHP streams); every one of them verifies the
 * server certificate and none follows redirects. dispose(), or the destructor, deletes both files;
 * neither touches the network.
 */
final class MaterializedCertificate
{
    /** Seconds allowed for the TCP and TLS connection set-up. */
    private const CONNECT_TIMEOUT = 10.0;

    private bool $disposed = false;

    /**
     * @internal created by ClientCertificate::materialize()
     */
    public function __construct(
        public readonly string $certificatePath,
        public readonly string $privateKeyPath,
        private readonly ?Secret $password,
    ) {}

    /**
     * Cloning is refused: a copy would delete the original's files when destroyed. The method is
     * private rather than throwing, because a clone that throws is still destroyed, and its
     * destructor would run.
     */
    private function __clone() {}

    public function __destruct()
    {
        $this->dispose();
    }

    /**
     * Request options for Guzzle.
     *
     * @param  string|null  $caBundle  a PEM bundle to verify the server against; null uses the system store
     * @param  float  $timeoutSeconds  the whole request, connection included
     * @return array{cert: string, ssl_key: string|array{0: string, 1: string}, verify: string|true, timeout: float, connect_timeout: float, allow_redirects: false}
     *
     * @throws InvalidArgumentException when the timeout is not positive
     */
    public function guzzleOptions(?string $caBundle = null, float $timeoutSeconds = 30.0): array
    {
        self::assertTimeout($timeoutSeconds);
        $password = $this->password?->reveal();

        return [
            'cert' => $this->certificatePath,
            'ssl_key' => $password === null ? $this->privateKeyPath : [$this->privateKeyPath, $password],
            'verify' => $caBundle ?? true,
            'timeout' => $timeoutSeconds,
            'connect_timeout' => min(self::CONNECT_TIMEOUT, $timeoutSeconds),
            'allow_redirects' => false,
        ];
    }

    /**
     * Request options for Symfony HttpClient. Symfony cannot set a separate connect timeout: its
     * "timeout" is the idle timeout and "max_duration" bounds the whole request; both get the argument.
     *
     * @param  string|null  $caBundle  a PEM bundle to verify the server against; null uses the system store
     * @param  float  $timeoutSeconds  the whole request, connection included
     * @return array{local_cert: string, local_pk: string, passphrase: ?string, cafile: ?string, verify_peer: true, verify_host: true, timeout: float, max_duration: float, max_redirects: 0}
     *
     * @throws InvalidArgumentException when the timeout is not positive
     */
    public function symfonyOptions(?string $caBundle = null, float $timeoutSeconds = 30.0): array
    {
        self::assertTimeout($timeoutSeconds);

        return [
            'local_cert' => $this->certificatePath,
            'local_pk' => $this->privateKeyPath,
            'passphrase' => $this->password?->reveal(),
            'cafile' => $caBundle,
            'verify_peer' => true,
            'verify_host' => true,
            'timeout' => $timeoutSeconds,
            'max_duration' => $timeoutSeconds,
            'max_redirects' => 0,
        ];
    }

    /**
     * Options for the "ssl" stream context of PHP's stream wrappers. Timeouts and redirects are
     * "http" context options and stay with the client that builds the context.
     *
     * @param  string|null  $caBundle  a PEM bundle to verify the server against; null uses the system store
     * @return array{local_cert: string, local_pk: string, passphrase?: string, cafile?: string, verify_peer: true, verify_peer_name: true}
     */
    public function streamContextOptions(?string $caBundle = null): array
    {
        $options = ['local_cert' => $this->certificatePath, 'local_pk' => $this->privateKeyPath];
        $password = $this->password?->reveal();
        if ($password !== null) {
            $options['passphrase'] = $password;
        }
        if ($caBundle !== null) {
            $options['cafile'] = $caBundle;
        }
        $options['verify_peer'] = true;
        $options['verify_peer_name'] = true;

        return $options;
    }

    /**
     * Deletes both files. Safe to call more than once.
     */
    public function dispose(): void
    {
        if ($this->disposed) {
            return;
        }
        foreach ([$this->privateKeyPath, $this->certificatePath] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->disposed = ! is_file($this->privateKeyPath) && ! is_file($this->certificatePath);
    }

    /**
     * @return array{certificatePath: string, privateKeyPath: string, password: ?string}
     */
    public function __debugInfo(): array
    {
        return [
            'certificatePath' => $this->certificatePath,
            'privateKeyPath' => $this->privateKeyPath,
            'password' => $this->password === null ? null : Redactor::REDACTED,
        ];
    }

    /**
     * @throws LogicException Always: the files belong to this process only
     */
    public function __serialize(): never
    {
        throw new LogicException('A materialized client certificate cannot be serialized.');
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws LogicException Always
     */
    public function __unserialize(array $data): never
    {
        throw new LogicException('A materialized client certificate cannot be serialized.');
    }

    private static function assertTimeout(float $timeoutSeconds): void
    {
        if (! ($timeoutSeconds > 0.0)) {
            throw new InvalidArgumentException('The timeout must be a positive number of seconds.');
        }
    }
}
