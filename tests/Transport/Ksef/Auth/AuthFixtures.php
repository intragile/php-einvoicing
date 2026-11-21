<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth;

use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Tests\Transport\Ksef\KsefFixtures;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Authenticator;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\CertificateCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Credentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyRegistry;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenCredentials;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\PublicKeyId;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use IntrAgile\EInvoicing\Transport\Ksef\Testing\SelfSignedCertificate;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Canned KSeF authentication answers, a fixed clock and in-memory key pairs standing in for the
 * Ministry of Finance encryption certificates (GET /security/public-key-certificates,
 * PublicKeyCertificate schema).
 */
trait AuthFixtures
{
    use KsefFixtures;

    private const CHALLENGE = '20250514-CR-226FB7B000-3ACF9BE4C0-10';

    private const CHALLENGE_TIMESTAMP_MS = 1752236636015;

    private const AUTH_REFERENCE = '20250514-AU-2DFC46C000-3AC6D5877F-D4';

    private const AUTH_TOKEN = 'eyJhbGciOiJIUzI1NiJ9.eyJ0b2tlbi10eXBlIjoiT3BlcmF0aW9uVG9rZW4ifQ.YXV0aGVudGljYXRpb24tdG9rZW4tc2ln';

    private const ACCESS_TOKEN = 'eyJhbGciOiJIUzI1NiJ9.eyJ0b2tlbi10eXBlIjoiQ29udGV4dFRva2VuIn0.Zmlyc3QtYWNjZXNzLXRva2VuLXNpZ25hdHVyZQ';

    private const SECOND_ACCESS_TOKEN = 'eyJhbGciOiJIUzI1NiJ9.eyJ0b2tlbi10eXBlIjoiQ29udGV4dFRva2VuIn0.c2Vjb25kLWFjY2Vzcy10b2tlbi1zaWduYXR1cmU';

    private const REFRESH_TOKEN = 'eyJhbGciOiJIUzI1NiJ9.eyJ0b2tlbi10eXBlIjoiUmVmcmVzaFRva2VuIn0.cmVmcmVzaC10b2tlbi1zaWduYXR1cmUtdmFsdWU';

    /** The fixed "now" of the tests. */
    private const NOW = '2026-11-21T10:00:00+00:00';

    /** @var array<int, CertificateCredentials> */
    private static array $mfKeys = [];

    private ?DateTimeImmutable $clock = null;

    /**
     * An RSA-2048 key pair with a self-signed certificate, generated once per test run.
     */
    private static function mfKey(int $index): CertificateCredentials
    {
        return self::$mfKeys[$index] ??= SelfSignedCertificate::seal('5265877635', 'Ministry Test Key '.$index);
    }

    private static function der(string $certificatePem): string
    {
        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $certificatePem), true);
        self::assertIsString($der);

        return $der;
    }

    /**
     * One PublicKeyCertificate entry of GET /security/public-key-certificates.
     *
     * @param  list<string>  $usage
     * @return array<string, mixed>
     */
    private static function keyEntry(int $index, string $validFrom, string $validTo, array $usage): array
    {
        $pem = self::mfKey($index)->certificatePem;

        return [
            'certificate' => base64_encode(self::der($pem)),
            'certificateId' => base64_encode(hash('sha256', self::der($pem), true)),
            'publicKeyId' => PublicKeyId::of($pem),
            'validFrom' => $validFrom,
            'validTo' => $validTo,
            'usage' => $usage,
        ];
    }

    /**
     * The usual answer: key 0 for KSeF tokens, key 1 for symmetric keys, both valid now.
     */
    private static function keysResponse(): Response
    {
        return self::json(200, [
            self::keyEntry(0, '2025-09-29T06:03:19+00:00', '2027-09-29T06:03:18+00:00', ['KsefTokenEncryption']),
            self::keyEntry(1, '2025-09-29T06:17:45+00:00', '2027-09-29T06:17:44+00:00', ['SymmetricKeyEncryption']),
        ]);
    }

    private static function challengeResponse(): Response
    {
        return self::json(200, [
            'challenge' => self::CHALLENGE,
            'timestamp' => '2025-07-11T12:23:56.0154302+00:00',
            'timestampMs' => self::CHALLENGE_TIMESTAMP_MS,
            'clientIp' => '127.0.0.1',
        ]);
    }

    private static function initResponse(): Response
    {
        return self::json(202, [
            'referenceNumber' => self::AUTH_REFERENCE,
            'authenticationToken' => ['token' => self::AUTH_TOKEN, 'validUntil' => '2026-11-21T10:45:00.0154302+00:00'],
        ]);
    }

    /**
     * @param  list<string>|null  $details
     */
    private static function statusResponse(int $code, string $description, ?array $details = null): Response
    {
        $status = ['code' => $code, 'description' => $description];
        if ($details !== null) {
            $status['details'] = $details;
        }

        return self::json(200, [
            'startDate' => '2026-11-21T10:00:00.0000000+00:00',
            'authenticationMethod' => 'Token',
            'authenticationMethodInfo' => ['category' => 'Token', 'code' => 'token.ksef', 'displayName' => 'Token KSeF'],
            'status' => $status,
        ]);
    }

    private static function successStatus(): Response
    {
        return self::statusResponse(200, 'Uwierzytelnianie zakończone sukcesem');
    }

    private static function redeemResponse(string $access = self::ACCESS_TOKEN, string $refresh = self::REFRESH_TOKEN): Response
    {
        return self::json(200, [
            'accessToken' => ['token' => $access, 'validUntil' => '2026-11-21T10:15:00.0154302+00:00'],
            'refreshToken' => ['token' => $refresh, 'validUntil' => '2026-11-28T10:00:00.0154302+00:00'],
        ]);
    }

    private static function refreshResponse(string $access = self::SECOND_ACCESS_TOKEN): Response
    {
        return self::json(200, [
            'accessToken' => ['token' => $access, 'validUntil' => '2026-11-21T10:30:00.0154302+00:00'],
        ]);
    }

    /**
     * The answers of a full KSeF-token authentication, the public keys included.
     *
     * @return list<Response>
     */
    private static function tokenAuthResponses(string $access = self::ACCESS_TOKEN, bool $withKeys = true): array
    {
        $responses = [self::challengeResponse()];
        if ($withKeys) {
            $responses[] = self::keysResponse();
        }

        return [...$responses, self::initResponse(), self::successStatus(), self::redeemResponse($access)];
    }

    /**
     * @return Closure(): DateTimeImmutable
     */
    private function now(): Closure
    {
        return function (): DateTimeImmutable {
            return $this->clock ??= new DateTimeImmutable(self::NOW);
        };
    }

    private function advanceClock(string $modifier): void
    {
        $this->clock = ($this->clock ?? new DateTimeImmutable(self::NOW))->modify($modifier);
    }

    private function authConfig(?Credentials $credentials = null, int $pollAttempts = 30, int $pollWaitMs = 1000): KsefConfig
    {
        return new KsefConfig(
            KsefEnvironment::Test,
            ContextIdentifier::nip('5265877635'),
            $credentials ?? new TokenCredentials(self::KSEF_TOKEN),
            authPollAttempts: $pollAttempts,
            authPollWaitMs: $pollWaitMs,
            sleep: function (int $ms): void {
                $this->sleeps[] = $ms;
            },
        );
    }

    private function plainApi(RecordingHttpClient $recorder, KsefConfig $config): KsefApi
    {
        $factory = new Psr17Factory;

        return new KsefApi($config, new HttpClient($recorder, $factory, $factory));
    }

    private function authenticator(RecordingHttpClient $recorder, ?KsefConfig $config = null): Authenticator
    {
        $config ??= $this->authConfig();
        $api = $this->plainApi($recorder, $config);

        return new Authenticator($api, $config, new PublicKeyRegistry($api, $this->now()), now: $this->now());
    }

    /**
     * A client that authenticates with the KSeF token on its first authorized call.
     */
    private function authorizedApi(RecordingHttpClient $recorder, ?KsefConfig $config = null): KsefApi
    {
        $config ??= $this->authConfig();
        $api = $this->plainApi($recorder, $config);

        return $api->withAuthenticator(new Authenticator($api, $config, new PublicKeyRegistry($api, $this->now()), now: $this->now()));
    }

    private static function oaepDecrypt(string $ciphertext, CertificateCredentials $key): string
    {
        $private = PublicKeyLoader::load($key->privateKeyPem());
        self::assertInstanceOf(PrivateKey::class, $private);
        foreach ([static fn (PrivateKey $k): mixed => $k->withPadding(RSA::ENCRYPTION_OAEP), static fn (PrivateKey $k): mixed => $k->withHash('sha256'), static fn (PrivateKey $k): mixed => $k->withMGFHash('sha256')] as $step) {
            $private = $step($private);
            self::assertInstanceOf(PrivateKey::class, $private);
        }
        $plaintext = $private->decrypt($ciphertext);
        self::assertIsString($plaintext);

        return $plaintext;
    }

    /**
     * @return array<mixed>
     */
    private static function body(RequestInterface $request): array
    {
        /** @var array<mixed> */
        return json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function assertNoAuthToken(Throwable $exception): void
    {
        self::assertNoSecret($exception);
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            foreach ([self::AUTH_TOKEN, self::ACCESS_TOKEN, self::SECOND_ACCESS_TOKEN, self::REFRESH_TOKEN] as $secret) {
                self::assertStringNotContainsString($secret, $current->getMessage());
            }
        }
    }
}
