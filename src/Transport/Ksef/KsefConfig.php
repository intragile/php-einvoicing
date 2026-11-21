<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef;

use Closure;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Credentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use InvalidArgumentException;

/**
 * Connection settings for the KSeF API 2.0: the environment, the context and credentials to
 * authenticate with, and the bounds of every polling loop.
 *
 * Polling is always bounded: each loop makes at most the configured number of attempts and waits
 * the configured time between them. The wait goes through an injectable sleep closure, so tests
 * do not have to wait. Timeouts of a single request belong to the injected PSR-18 client.
 */
final readonly class KsefConfig
{
    /** The API base URL of the environment, without a trailing slash. */
    public string $baseUrl;

    /** @var Closure(int): void */
    private Closure $sleeper;

    /**
     * @param  int  $batchPartBytes  the largest batch part before encryption, at least one byte
     * @param  (Closure(int $ms): void)|null  $sleep  waits the given number of milliseconds; usleep() when null
     *
     * @throws InvalidArgumentException when an attempt count, a wait or the batch part size is not positive
     */
    public function __construct(
        public KsefEnvironment $environment,
        public ContextIdentifier $context,
        public Credentials $credentials,
        public int $authPollAttempts = 30,
        public int $authPollWaitMs = 1000,
        public int $statusPollAttempts = 30,
        public int $statusPollWaitMs = 2000,
        public int $exportPollAttempts = 60,
        public int $exportPollWaitMs = 2000,
        public int $batchPartBytes = 100_000_000,
        ?Closure $sleep = null,
    ) {
        $bounds = [
            'authPollAttempts' => $authPollAttempts,
            'authPollWaitMs' => $authPollWaitMs,
            'statusPollAttempts' => $statusPollAttempts,
            'statusPollWaitMs' => $statusPollWaitMs,
            'exportPollAttempts' => $exportPollAttempts,
            'exportPollWaitMs' => $exportPollWaitMs,
            'batchPartBytes' => $batchPartBytes,
        ];
        foreach ($bounds as $name => $value) {
            if ($value < 1) {
                throw new InvalidArgumentException(sprintf('The KSeF setting %s must be a positive integer, %d given.', $name, $value));
            }
        }

        $this->baseUrl = $environment->apiBaseUrl();
        $this->sleeper = $sleep ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    /**
     * Waits the given number of milliseconds; zero or less returns at once.
     */
    public function sleep(int $ms): void
    {
        if ($ms > 0) {
            ($this->sleeper)($ms);
        }
    }

    /**
     * The credential values an HttpClient must keep out of exception messages and body
     * excerpts: the KSeF token, or the private key and its passphrase.
     *
     * @return list<string>
     */
    public function secrets(): array
    {
        if ($this->credentials instanceof TokenCredentials) {
            return [$this->credentials->token()];
        }
        if ($this->credentials instanceof CertificateCredentials) {
            $passphrase = $this->credentials->passphrase();

            return $passphrase === null ? [$this->credentials->privateKeyPem()] : [$this->credentials->privateKeyPem(), $passphrase];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'environment' => $this->environment,
            'baseUrl' => $this->baseUrl,
            'context' => $this->context,
            'credentials' => Redactor::REDACTED,
            'authPollAttempts' => $this->authPollAttempts,
            'authPollWaitMs' => $this->authPollWaitMs,
            'statusPollAttempts' => $this->statusPollAttempts,
            'statusPollWaitMs' => $this->statusPollWaitMs,
            'exportPollAttempts' => $this->exportPollAttempts,
            'exportPollWaitMs' => $this->exportPollWaitMs,
            'batchPartBytes' => $this->batchPartBytes,
        ];
    }
}
