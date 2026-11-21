<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth;

use Closure;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyRegistry;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyUsage;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\PublicKeyId;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class PublicKeyRegistryTest extends TestCase
{
    use AuthFixtures;

    public function test_registry_picks_latest_valid_key_per_usage(): void
    {
        $recorder = new RecordingHttpClient(self::json(200, [
            self::keyEntry(0, '2025-01-01T00:00:00+00:00', '2027-01-01T00:00:00+00:00', ['SymmetricKeyEncryption']),
            self::keyEntry(1, '2026-06-01T00:00:00.0154302+00:00', '2028-06-01T00:00:00+00:00', ['SymmetricKeyEncryption']),
            // Not valid yet, although its validFrom is the latest.
            self::keyEntry(2, '2026-12-01T00:00:00+00:00', '2029-01-01T00:00:00+00:00', ['SymmetricKeyEncryption', 'KsefTokenEncryption']),
            // Expired, although its validFrom is later than the valid token key's.
            self::keyEntry(3, '2025-06-01T00:00:00+00:00', '2026-01-01T00:00:00+00:00', ['KsefTokenEncryption']),
            self::keyEntry(4, '2024-06-01T00:00:00+00:00', '2028-01-01T00:00:00+00:00', ['KsefTokenEncryption', 'SomeFutureUsage']),
        ]));
        $registry = new PublicKeyRegistry($this->plainApi($recorder, $this->authConfig()), $this->now());

        self::assertSame(PublicKeyId::of(self::mfKey(1)->certificatePem), $registry->keyId(PublicKeyUsage::SymmetricKeyEncryption));
        self::assertSame(self::der(self::mfKey(1)->certificatePem), self::der($registry->certificatePem(PublicKeyUsage::SymmetricKeyEncryption)));
        self::assertSame(PublicKeyId::of(self::mfKey(4)->certificatePem), $registry->keyId(PublicKeyUsage::KsefTokenEncryption));
        self::assertSame(self::der(self::mfKey(4)->certificatePem), self::der($registry->certificatePem(PublicKeyUsage::KsefTokenEncryption)));
        self::assertStringStartsWith("-----BEGIN CERTIFICATE-----\n", $registry->certificatePem(PublicKeyUsage::KsefTokenEncryption));

        $requests = $recorder->requests();
        self::assertCount(1, $requests);
        self::assertSame('GET', $requests[0]->getMethod());
        self::assertSame('https://api-test.ksef.mf.gov.pl/v2/security/public-key-certificates', (string) $requests[0]->getUri());
        self::assertFalse($requests[0]->hasHeader('Authorization'));
    }

    public function test_unknown_key_id_refetches_once(): void
    {
        $recorder = new RecordingHttpClient(
            self::json(200, [self::keyEntry(0, '2025-01-01T00:00:00+00:00', '2027-01-01T00:00:00+00:00', ['SymmetricKeyEncryption'])]),
            self::fixture(400, 'unknown-key-id-21470.json'),
            self::json(200, [self::keyEntry(1, '2026-11-20T00:00:00+00:00', '2028-01-01T00:00:00+00:00', ['SymmetricKeyEncryption'])]),
            self::json(201, ['referenceNumber' => '20251121-SO-1111111111-2222222222-33']),
        );
        $api = $this->plainApi($recorder, $this->authConfig());
        $registry = new PublicKeyRegistry($api, $this->now());

        $result = $registry->using(PublicKeyUsage::SymmetricKeyEncryption, self::openSession($api));

        self::assertSame(['referenceNumber' => '20251121-SO-1111111111-2222222222-33'], $result);
        $requests = $recorder->requests();
        self::assertCount(4, $requests);
        self::assertSame(PublicKeyId::of(self::mfKey(0)->certificatePem), self::sentKeyId($requests[1]));
        self::assertSame('GET', $requests[2]->getMethod());
        self::assertSame(PublicKeyId::of(self::mfKey(1)->certificatePem), self::sentKeyId($requests[3]));
        self::assertSame(self::der(self::mfKey(1)->certificatePem), self::der($registry->certificatePem(PublicKeyUsage::SymmetricKeyEncryption)));
    }

    public function test_unknown_key_id_as_a_later_error_code_also_refetches(): void
    {
        $recorder = new RecordingHttpClient(
            self::json(200, [self::keyEntry(0, '2025-01-01T00:00:00+00:00', '2027-01-01T00:00:00+00:00', ['SymmetricKeyEncryption'])]),
            self::json(400, ['errors' => [
                ['code' => 21999, 'description' => 'Inny błąd.'],
                ['code' => 21470, 'description' => 'Nieznany identyfikator klucza publicznego.'],
            ]]),
            self::json(200, [self::keyEntry(1, '2026-11-20T00:00:00+00:00', '2028-01-01T00:00:00+00:00', ['SymmetricKeyEncryption'])]),
            self::json(201, ['referenceNumber' => '20251121-SO-1111111111-2222222222-33']),
        );
        $api = $this->plainApi($recorder, $this->authConfig());

        $result = (new PublicKeyRegistry($api, $this->now()))->using(PublicKeyUsage::SymmetricKeyEncryption, self::openSession($api));

        self::assertSame(['referenceNumber' => '20251121-SO-1111111111-2222222222-33'], $result);
        self::assertCount(4, $recorder->requests());
    }

    public function test_second_unknown_key_id_is_unexpected_response_naming_the_key(): void
    {
        $recorder = new RecordingHttpClient(
            self::json(200, [self::keyEntry(0, '2025-01-01T00:00:00+00:00', '2027-01-01T00:00:00+00:00', ['SymmetricKeyEncryption'])]),
            self::fixture(400, 'unknown-key-id-21470.json'),
            self::json(200, [self::keyEntry(1, '2026-11-20T00:00:00+00:00', '2028-01-01T00:00:00+00:00', ['SymmetricKeyEncryption'])]),
            self::fixture(400, 'unknown-key-id-21470.json'),
        );
        $api = $this->plainApi($recorder, $this->authConfig());
        $registry = new PublicKeyRegistry($api, $this->now());

        $exception = self::capture(fn () => $registry->using(PublicKeyUsage::SymmetricKeyEncryption, self::openSession($api)));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString(PublicKeyId::of(self::mfKey(1)->certificatePem), $exception->getMessage());
        self::assertStringContainsString('21470', $exception->getMessage());
        self::assertSame(400, $exception->status);
        self::assertCount(4, $recorder->requests());
    }

    public function test_other_errors_are_not_repeated(): void
    {
        $recorder = new RecordingHttpClient(
            self::keysResponse(),
            self::fixture(401, 'unauthorized-problem-details.json'),
        );
        $api = $this->plainApi($recorder, $this->authConfig());
        $registry = new PublicKeyRegistry($api, $this->now());

        $exception = self::capture(fn () => $registry->using(PublicKeyUsage::SymmetricKeyEncryption, self::openSession($api)));

        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertCount(2, $recorder->requests());
    }

    public function test_expired_key_triggers_refetch(): void
    {
        $recorder = new RecordingHttpClient(
            self::json(200, [self::keyEntry(0, '2025-01-01T00:00:00+00:00', '2026-11-22T00:00:00+00:00', ['KsefTokenEncryption'])]),
            self::json(200, [
                self::keyEntry(0, '2025-01-01T00:00:00+00:00', '2026-11-22T00:00:00+00:00', ['KsefTokenEncryption']),
                self::keyEntry(1, '2026-11-21T00:00:00+00:00', '2028-01-01T00:00:00+00:00', ['KsefTokenEncryption']),
            ]),
        );
        $registry = new PublicKeyRegistry($this->plainApi($recorder, $this->authConfig()), $this->now());

        self::assertSame(PublicKeyId::of(self::mfKey(0)->certificatePem), $registry->keyId(PublicKeyUsage::KsefTokenEncryption));
        $this->advanceClock('+1 day');
        self::assertSame(PublicKeyId::of(self::mfKey(1)->certificatePem), $registry->keyId(PublicKeyUsage::KsefTokenEncryption));
        self::assertCount(2, $recorder->requests());
    }

    public function test_no_valid_key_is_unexpected_response(): void
    {
        $recorder = new RecordingHttpClient(
            self::json(200, [self::keyEntry(0, '2020-01-01T00:00:00+00:00', '2021-01-01T00:00:00+00:00', ['SymmetricKeyEncryption'])]),
        );
        $registry = new PublicKeyRegistry($this->plainApi($recorder, $this->authConfig()), $this->now());

        $exception = self::capture(fn () => $registry->certificatePem(PublicKeyUsage::KsefTokenEncryption));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('KsefTokenEncryption', $exception->getMessage());
    }

    public function test_malformed_answer_is_unexpected_response(): void
    {
        $recorder = new RecordingHttpClient(self::json(200, [['certificate' => '%%%', 'validFrom' => 'x', 'validTo' => 'y', 'usage' => ['SymmetricKeyEncryption']]]));
        $registry = new PublicKeyRegistry($this->plainApi($recorder, $this->authConfig()), $this->now());

        self::assertInstanceOf(UnexpectedResponse::class, self::capture(fn () => $registry->keyId(PublicKeyUsage::SymmetricKeyEncryption)));
    }

    public function test_invalidate_refetches(): void
    {
        $recorder = new RecordingHttpClient(self::keysResponse(), self::keysResponse());
        $registry = new PublicKeyRegistry($this->plainApi($recorder, $this->authConfig()), $this->now());

        $registry->keyId(PublicKeyUsage::SymmetricKeyEncryption);
        $registry->keyId(PublicKeyUsage::KsefTokenEncryption);
        $registry->invalidate();
        $registry->keyId(PublicKeyUsage::SymmetricKeyEncryption);

        self::assertCount(2, $recorder->requests());
    }

    private static function sentKeyId(RequestInterface $request): mixed
    {
        $encryption = self::body($request)['encryption'] ?? null;

        return is_array($encryption) ? ($encryption['publicKeyId'] ?? null) : null;
    }

    /**
     * @return Closure(string, string): array<mixed>
     */
    private static function openSession(KsefApi $api): Closure
    {
        return static fn (string $certificatePem, string $keyId): array => $api->json('POST', '/sessions/online', [
            'formCode' => ['systemCode' => 'FA (3)', 'schemaVersion' => '1-0E', 'value' => 'FA'],
            'encryption' => ['encryptedSymmetricKey' => base64_encode(str_repeat("\0", 256)), 'initializationVector' => base64_encode(str_repeat("\0", 16)), 'publicKeyId' => $keyId],
        ], self::BEARER);
    }
}
