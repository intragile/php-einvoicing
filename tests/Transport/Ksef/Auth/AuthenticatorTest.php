<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth;

use DateTimeImmutable;
use DOMDocument;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\AccessTokens;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\AuthTokenRequest;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\XadesSigner;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\PublicKeyId;
use IntrAgile\EInvoicing\Transport\Ksef\Testing\SelfSignedCertificate;
use LogicException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class AuthenticatorTest extends TestCase
{
    use AuthFixtures;

    private const BASE = 'https://api-test.ksef.mf.gov.pl/v2';

    private const THIRD_ACCESS_TOKEN = 'eyJhbGciOiJIUzI1NiJ9.eyJ0b2tlbi10eXBlIjoiQ29udGV4dFRva2VuIn0.dGhpcmQtYWNjZXNzLXRva2VuLXNpZ25hdHVyZQ';

    public function test_xades_flow_posts_signed_xml_and_redeems(): void
    {
        $config = $this->authConfig(SelfSignedCertificate::seal('5265877635'));
        $recorder = new RecordingHttpClient(self::challengeResponse(), self::initResponse(), self::successStatus(), self::redeemResponse());

        $tokens = $this->authenticator($recorder, $config)->authenticate();

        self::assertSame(self::ACCESS_TOKEN, $tokens->accessToken());
        self::assertSame(self::REFRESH_TOKEN, $tokens->refreshToken());
        self::assertEquals(new DateTimeImmutable('2026-11-21T10:15:00.015430+00:00'), $tokens->accessValidUntil);
        self::assertEquals(new DateTimeImmutable('2026-11-28T10:00:00.015430+00:00'), $tokens->refreshValidUntil);

        $requests = $recorder->requests();
        self::assertSame(
            ['POST /auth/challenge', 'POST /auth/xades-signature', 'GET /auth/'.self::AUTH_REFERENCE, 'POST /auth/token/redeem'],
            array_map(static fn ($r): string => $r->getMethod().' '.substr((string) $r->getUri(), strlen(self::BASE)), $requests),
        );
        self::assertFalse($requests[0]->hasHeader('Authorization'));

        $xades = $requests[1];
        self::assertSame('application/xml', $xades->getHeaderLine('Content-Type'));
        self::assertFalse($xades->hasHeader('Authorization'));
        $document = new DOMDocument;
        self::assertTrue($document->loadXML((string) $xades->getBody()));
        self::assertSame(AuthTokenRequest::XML_NAMESPACE, $document->documentElement?->namespaceURI);
        self::assertSame(self::CHALLENGE, $document->getElementsByTagNameNS(AuthTokenRequest::XML_NAMESPACE, 'Challenge')->item(0)?->textContent);
        self::assertSame('5265877635', $document->getElementsByTagNameNS(AuthTokenRequest::XML_NAMESPACE, 'Nip')->item(0)?->textContent);
        self::assertSame(1, $document->getElementsByTagNameNS(XadesSigner::DS_NAMESPACE, 'Signature')->length);

        self::assertSame('Bearer '.self::AUTH_TOKEN, $requests[2]->getHeaderLine('Authorization'));
        self::assertSame('Bearer '.self::AUTH_TOKEN, $requests[3]->getHeaderLine('Authorization'));
        self::assertSame([], $this->sleeps);
    }

    public function test_token_flow_encrypts_token_with_server_timestamp(): void
    {
        // The local clock is far from the challenge time: only the server timestamp may be used.
        $recorder = new RecordingHttpClient(...self::tokenAuthResponses());

        $tokens = $this->authenticator($recorder)->authenticate();

        self::assertSame(self::ACCESS_TOKEN, $tokens->accessToken());
        $requests = $recorder->requests();
        self::assertSame(self::BASE.'/security/public-key-certificates', (string) $requests[1]->getUri());
        $request = $requests[2];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE.'/auth/ksef-token', (string) $request->getUri());
        self::assertFalse($request->hasHeader('Authorization'));

        $body = self::body($request);
        self::assertSame(['challenge', 'contextIdentifier', 'encryptedToken', 'publicKeyId'], array_keys($body));
        self::assertSame(self::CHALLENGE, $body['challenge']);
        self::assertSame(['type' => 'Nip', 'value' => '5265877635'], $body['contextIdentifier']);
        self::assertSame(PublicKeyId::of(self::mfKey(0)->certificatePem), $body['publicKeyId']);
        self::assertIsString($body['encryptedToken']);
        $ciphertext = base64_decode($body['encryptedToken'], true);
        self::assertIsString($ciphertext);
        self::assertSame(self::KSEF_TOKEN.'|'.self::CHALLENGE_TIMESTAMP_MS, self::oaepDecrypt($ciphertext, self::mfKey(0)));
        self::assertStringNotContainsString(self::KSEF_TOKEN, (string) $request->getBody());
    }

    public function test_status_450_raises_authentication_failed_with_code(): void
    {
        $recorder = new RecordingHttpClient(
            self::challengeResponse(),
            self::keysResponse(),
            self::initResponse(),
            self::statusResponse(450, 'Uwierzytelnianie zakończone niepowodzeniem z powodu błędnego tokenu', ['Nieprawidłowy token']),
        );

        $exception = self::capture(fn () => $this->authenticator($recorder)->authenticate());

        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertStringContainsString('450', $exception->getMessage());
        self::assertStringContainsString('Uwierzytelnianie zakończone niepowodzeniem z powodu błędnego tokenu', $exception->getMessage());
        self::assertStringContainsString('Nieprawidłowy token', $exception->getMessage());
        self::assertNoAuthToken($exception);
        self::assertCount(4, $recorder->requests());
    }

    public function test_status_460_names_the_certificate_failure(): void
    {
        $config = $this->authConfig(SelfSignedCertificate::seal('5265877635'));
        $recorder = new RecordingHttpClient(
            self::challengeResponse(),
            self::initResponse(),
            self::statusResponse(460, 'Uwierzytelnianie zakończone niepowodzeniem z powodu błędu certyfikatu', ['Certyfikat odwołany']),
        );

        $exception = self::capture(fn () => $this->authenticator($recorder, $config)->authenticate());

        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertStringContainsString('status 460', $exception->getMessage());
        self::assertStringContainsString('Certyfikat odwołany', $exception->getMessage());
    }

    public function test_auth_poll_is_bounded(): void
    {
        $config = $this->authConfig(pollAttempts: 3, pollWaitMs: 250);
        $pending = static fn (): Response => self::statusResponse(100, 'Uwierzytelnianie w toku');
        $recorder = new RecordingHttpClient(self::challengeResponse(), self::keysResponse(), self::initResponse(), $pending(), $pending(), $pending());

        $exception = self::capture(fn () => $this->authenticator($recorder, $config)->authenticate());

        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertStringContainsString('3 status checks', $exception->getMessage());
        self::assertSame([250, 250], $this->sleeps);
        self::assertCount(6, $recorder->requests());
        self::assertNoAuthToken($exception);
    }

    public function test_pending_then_success_polls_with_the_configured_wait(): void
    {
        $config = $this->authConfig(pollWaitMs: 400);
        $recorder = new RecordingHttpClient(
            self::challengeResponse(),
            self::keysResponse(),
            self::initResponse(),
            self::statusResponse(100, 'Uwierzytelnianie w toku'),
            self::successStatus(),
            self::redeemResponse(),
        );

        $this->authenticator($recorder, $config)->authenticate();

        self::assertSame([400], $this->sleeps);
    }

    public function test_malformed_redeem_answer_is_unexpected_without_quoting_it(): void
    {
        $recorder = new RecordingHttpClient(
            self::challengeResponse(),
            self::keysResponse(),
            self::initResponse(),
            self::successStatus(),
            self::json(200, ['accessToken' => ['token' => self::ACCESS_TOKEN]]),
        );

        $exception = self::capture(fn () => $this->authenticator($recorder)->authenticate());

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertSame('', $exception->bodyExcerpt);
        self::assertNoAuthToken($exception);
    }

    public function test_access_token_is_reused_while_valid(): void
    {
        $recorder = new RecordingHttpClient(...self::tokenAuthResponses());
        $authenticator = $this->authenticator($recorder);

        self::assertSame(self::ACCESS_TOKEN, $authenticator->accessToken());
        $this->advanceClock('+13 minutes');
        self::assertSame(self::ACCESS_TOKEN, $authenticator->accessToken());
        self::assertCount(5, $recorder->requests());
    }

    public function test_access_token_close_to_expiry_is_refreshed_before_the_call(): void
    {
        $recorder = new RecordingHttpClient(...[...self::tokenAuthResponses(), self::refreshResponse()]);
        $authenticator = $this->authenticator($recorder);
        self::assertSame(self::ACCESS_TOKEN, $authenticator->accessToken());

        // Valid until 10:15:00; at 10:14:01 less than 60 seconds are left.
        $this->advanceClock('+14 minutes +1 second');

        self::assertSame(self::SECOND_ACCESS_TOKEN, $authenticator->accessToken());
        $refresh = $recorder->requests()[5];
        self::assertSame('POST', $refresh->getMethod());
        self::assertSame(self::BASE.'/auth/token/refresh', (string) $refresh->getUri());
        self::assertSame('Bearer '.self::REFRESH_TOKEN, $refresh->getHeaderLine('Authorization'));
    }

    public function test_refresh_keeps_the_refresh_token(): void
    {
        $recorder = new RecordingHttpClient(...[...self::tokenAuthResponses(), self::refreshResponse()]);
        $authenticator = $this->authenticator($recorder);
        $tokens = $authenticator->authenticate();

        $refreshed = $authenticator->refresh($tokens);

        self::assertSame(self::SECOND_ACCESS_TOKEN, $refreshed->accessToken());
        self::assertEquals(new DateTimeImmutable('2026-11-21T10:30:00.015430+00:00'), $refreshed->accessValidUntil);
        self::assertSame(self::REFRESH_TOKEN, $refreshed->refreshToken());
        self::assertEquals($tokens->refreshValidUntil, $refreshed->refreshValidUntil);
    }

    public function test_expired_refresh_token_authenticates_again(): void
    {
        $recorder = new RecordingHttpClient(...[...self::tokenAuthResponses(), ...self::tokenAuthResponses(self::SECOND_ACCESS_TOKEN, withKeys: false)]);
        $authenticator = $this->authenticator($recorder);
        $authenticator->accessToken();

        $this->advanceClock('+8 days');

        self::assertSame(self::SECOND_ACCESS_TOKEN, $authenticator->accessToken());
        self::assertSame(self::BASE.'/auth/challenge', (string) $recorder->requests()[5]->getUri());
        self::assertCount(9, $recorder->requests());
    }

    public function test_expired_access_token_is_refreshed_once_and_call_repeated(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::fixture(401, 'unauthorized-problem-details.json'),
            self::refreshResponse(),
            self::json(200, ['ok' => true]),
        ]);
        $api = $this->authorizedApi($recorder);
        $bearers = [];

        $result = $api->authorized(function (string $bearer) use ($api, &$bearers): array {
            $bearers[] = $bearer;

            return $api->json('GET', '/sessions', bearer: $bearer);
        });

        self::assertSame(['ok' => true], $result);
        self::assertSame([self::ACCESS_TOKEN, self::SECOND_ACCESS_TOKEN], $bearers);
        $requests = $recorder->requests();
        self::assertCount(8, $requests);
        self::assertSame(self::BASE.'/auth/token/refresh', (string) $requests[6]->getUri());
        self::assertSame('Bearer '.self::REFRESH_TOKEN, $requests[6]->getHeaderLine('Authorization'));
        self::assertSame('Bearer '.self::SECOND_ACCESS_TOKEN, $requests[7]->getHeaderLine('Authorization'));
    }

    public function test_failed_refresh_falls_back_to_full_authentication(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::fixture(401, 'unauthorized-problem-details.json'),
            self::fixture(401, 'unauthorized-problem-details.json'),
            ...self::tokenAuthResponses(self::THIRD_ACCESS_TOKEN, withKeys: false),
            self::json(200, ['ok' => true]),
        ]);
        $api = $this->authorizedApi($recorder);
        $bearers = [];

        $result = $api->authorized(function (string $bearer) use ($api, &$bearers): array {
            $bearers[] = $bearer;

            return $api->json('GET', '/sessions', bearer: $bearer);
        });

        self::assertSame(['ok' => true], $result);
        self::assertSame([self::ACCESS_TOKEN, self::THIRD_ACCESS_TOKEN], $bearers);
        $paths = array_map(static fn ($r): string => substr((string) $r->getUri(), strlen(self::BASE)), $recorder->requests());
        self::assertSame([
            '/auth/challenge', '/security/public-key-certificates', '/auth/ksef-token', '/auth/'.self::AUTH_REFERENCE, '/auth/token/redeem',
            '/sessions',
            '/auth/token/refresh',
            '/auth/challenge', '/auth/ksef-token', '/auth/'.self::AUTH_REFERENCE, '/auth/token/redeem',
            '/sessions',
        ], $paths);
    }

    public function test_second_401_after_the_repeat_is_thrown(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::fixture(401, 'unauthorized-problem-details.json'),
            self::refreshResponse(),
            self::fixture(401, 'unauthorized-problem-details.json'),
        ]);
        $api = $this->authorizedApi($recorder);

        $exception = self::capture(fn () => $api->authorized(fn (string $bearer): array => $api->json('GET', '/sessions', bearer: $bearer)));

        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertCount(8, $recorder->requests());
        self::assertNoAuthToken($exception);
    }

    public function test_403_is_not_refreshed(): void
    {
        $recorder = new RecordingHttpClient(...[...self::tokenAuthResponses(), self::fixture(403, 'forbidden-problem-details.json')]);
        $api = $this->authorizedApi($recorder);

        $exception = self::capture(fn () => $api->authorized(fn (string $bearer): array => $api->json('GET', '/sessions', bearer: $bearer)));

        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertCount(6, $recorder->requests());
    }

    public function test_failed_authentication_is_not_repeated_by_authorized(): void
    {
        $recorder = new RecordingHttpClient(
            self::challengeResponse(),
            self::keysResponse(),
            self::initResponse(),
            self::fixture(401, 'unauthorized-problem-details.json'),
        );
        $api = $this->authorizedApi($recorder);

        $exception = self::capture(fn () => $api->authorized(static fn (string $bearer): string => $bearer));

        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertCount(4, $recorder->requests());
    }

    public function test_authorized_without_authenticator_is_a_logic_error(): void
    {
        $api = $this->plainApi(new RecordingHttpClient, $this->authConfig());

        $this->expectException(LogicException::class);

        $api->authorized(static fn (string $bearer): string => $bearer);
    }

    public function test_with_authenticator_returns_a_copy(): void
    {
        $recorder = new RecordingHttpClient;
        $config = $this->authConfig();
        $plain = $this->plainApi($recorder, $config);

        $authorized = $plain->withAuthenticator($this->authenticator($recorder, $config));

        self::assertNotSame($plain, $authorized);
        self::assertInstanceOf(LogicException::class, self::capture(fn () => $plain->authorized(static fn (string $b): string => $b)));
    }

    public function test_access_tokens_are_redacted_and_not_serializable(): void
    {
        $tokens = new AccessTokens(self::ACCESS_TOKEN, new DateTimeImmutable(self::NOW), self::REFRESH_TOKEN, new DateTimeImmutable(self::NOW));

        foreach ([print_r($tokens, true), var_export($tokens, true), (string) json_encode($tokens), print_r((array) $tokens, true)] as $dump) {
            self::assertStringNotContainsString(self::ACCESS_TOKEN, $dump);
            self::assertStringNotContainsString(self::REFRESH_TOKEN, $dump);
        }
        ob_start();
        var_dump($tokens);
        $dump = (string) ob_get_clean();
        self::assertStringNotContainsString(self::ACCESS_TOKEN, $dump);
        self::assertStringContainsString('[redacted]', $dump);

        $this->expectException(LogicException::class);
        serialize($tokens);
    }

    public function test_authenticator_debug_info_hides_tokens(): void
    {
        $recorder = new RecordingHttpClient(...self::tokenAuthResponses());
        $authenticator = $this->authenticator($recorder);
        $authenticator->accessToken();

        $dump = print_r($authenticator, true);

        self::assertStringNotContainsString(self::ACCESS_TOKEN, $dump);
        self::assertStringNotContainsString(self::REFRESH_TOKEN, $dump);
        self::assertStringNotContainsString(self::KSEF_TOKEN, $dump);
    }
}
