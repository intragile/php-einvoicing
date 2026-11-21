<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Ksef\Crypto\RsaOaep;
use IntrAgile\EInvoicing\Transport\Ksef\KsefAnswer;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use JsonException;
use LogicException;
use SensitiveParameter;

/**
 * Authenticates a KSeF context and keeps its access token fresh.
 *
 * Authentication follows the KSeF API 2.0 flow: POST /auth/challenge; then either
 * POST /auth/xades-signature with the XAdES-signed AuthTokenRequest (certificate credentials) or
 * POST /auth/ksef-token with the KSeF token encrypted as "token|timestampMs" (KSeF token
 * credentials, timestamp taken from the challenge); then GET /auth/{referenceNumber} with the
 * authentication token until the status is no longer 100, within the configured attempts; and
 * finally POST /auth/token/redeem for the access and refresh tokens.
 *
 * accessToken() hands out a token valid for at least another minute: it refreshes the access token
 * with POST /auth/token/refresh when it is about to expire, and authenticates again when the
 * refresh token has expired or KSeF refuses it.
 */
final class Authenticator
{
    /** Seconds a token handed out must still be valid. */
    private const VALIDITY_MARGIN_SECONDS = 60;

    /** The authentication status code of an operation still in progress. */
    private const STATUS_IN_PROGRESS = 100;

    /** The authentication status code of a successful operation. */
    private const STATUS_SUCCESS = 200;

    private readonly XadesSigner $signer;

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $now;

    private ?AccessTokens $tokens = null;

    /**
     * @param  KsefApi  $api  a client without an authenticator; the authentication endpoints take
     *                        no access token
     * @param  KsefConfig  $config  the context, the credentials and the polling bounds
     * @param  PublicKeyRegistry  $keys  the key that encrypts a KSeF token
     * @param  XadesSigner|null  $signer  signs the AuthTokenRequest for certificate credentials
     * @param  (Closure(): DateTimeImmutable)|null  $now  the current time; the system clock when null
     */
    public function __construct(
        private readonly KsefApi $api,
        private readonly KsefConfig $config,
        private readonly PublicKeyRegistry $keys,
        ?XadesSigner $signer = null,
        ?Closure $now = null,
    ) {
        $this->signer = $signer ?? new XadesSigner;
        $this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable;
    }

    /**
     * Runs a full authentication and keeps its tokens for accessToken().
     *
     * @throws AuthenticationFailed when KSeF ends the authentication with a status other than 200
     *                              (450 bad token, 460 certificate error, 470 or 480 blocked, ...),
     *                              when the status stays 100 for all configured attempts, or when
     *                              KSeF refuses a request
     * @throws TransportException when a request fails or an answer cannot be read
     */
    public function authenticate(): AccessTokens
    {
        $this->tokens = null;

        $challenge = $this->api->json('POST', '/auth/challenge');
        $challengeValue = KsefAnswer::string($challenge, ['challenge'], 'The KSeF request POST /auth/challenge');

        $credentials = $this->config->credentials;
        if ($credentials instanceof CertificateCredentials) {
            $init = $this->signedRequest($challengeValue, $credentials);
            $operation = 'The KSeF request POST /auth/xades-signature';
        } elseif ($credentials instanceof TokenCredentials) {
            $timestampMs = KsefAnswer::int($challenge, ['timestampMs'], 'The KSeF request POST /auth/challenge');
            $init = $this->tokenRequest($challengeValue, $timestampMs, $credentials);
            $operation = 'The KSeF request POST /auth/ksef-token';
        } else {
            throw new LogicException(sprintf('KSeF credentials of type %s are not supported.', $credentials::class));
        }

        $reference = KsefAnswer::string($init, ['referenceNumber'], $operation);
        $authenticationToken = KsefAnswer::string($init, ['authenticationToken', 'token'], $operation);
        $this->await($reference, $authenticationToken);

        $operation = 'The KSeF request POST /auth/token/redeem';
        $redeemed = $this->api->json('POST', '/auth/token/redeem', null, $authenticationToken);

        return $this->tokens = new AccessTokens(
            KsefAnswer::string($redeemed, ['accessToken', 'token'], $operation),
            KsefAnswer::dateTime($redeemed, ['accessToken', 'validUntil'], $operation),
            KsefAnswer::string($redeemed, ['refreshToken', 'token'], $operation),
            KsefAnswer::dateTime($redeemed, ['refreshToken', 'validUntil'], $operation),
        );
    }

    /**
     * Obtains a new access token with the refresh token and keeps the result for accessToken().
     * The refresh token stays the same.
     *
     * @throws AuthenticationFailed when KSeF refuses the refresh token
     * @throws TransportException when the request fails or the answer cannot be read
     */
    public function refresh(AccessTokens $tokens): AccessTokens
    {
        $operation = 'The KSeF request POST /auth/token/refresh';
        $refreshed = $this->api->json('POST', '/auth/token/refresh', null, $tokens->refreshToken());

        return $this->tokens = $tokens->withAccessToken(
            KsefAnswer::string($refreshed, ['accessToken', 'token'], $operation),
            KsefAnswer::dateTime($refreshed, ['accessToken', 'validUntil'], $operation),
        );
    }

    /**
     * An access token valid for at least another minute: the kept one, a refreshed one, or one
     * from a new authentication.
     *
     * @throws TransportException when neither a refresh nor an authentication succeeds
     */
    public function accessToken(): string
    {
        if ($this->tokens !== null && $this->tokens->accessValidUntil > $this->horizon()) {
            return $this->tokens->accessToken();
        }

        return $this->renew();
    }

    /**
     * Replaces the access token even when it still looks valid, as after KSeF refused it: by a
     * refresh while the refresh token is valid, and by a new authentication when there is no
     * refresh token or KSeF refuses it.
     *
     * @throws TransportException when neither a refresh nor an authentication succeeds
     */
    public function renew(): string
    {
        if ($this->tokens !== null && $this->tokens->refreshValidUntil > $this->horizon()) {
            try {
                return $this->refresh($this->tokens)->accessToken();
            } catch (AuthenticationFailed|UnexpectedResponse) {
                // A refused, revoked or unreadable refresh: a full authentication follows.
            }
        }

        return $this->authenticate()->accessToken();
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'config' => $this->config,
            'tokens' => $this->tokens,
        ];
    }

    /**
     * @return array<mixed>
     *
     * @throws TransportException
     */
    private function signedRequest(string $challenge, CertificateCredentials $credentials): array
    {
        $xml = $this->signer->sign(AuthTokenRequest::xml($challenge, $this->config->context), $credentials);
        $response = $this->api->raw('POST', '/auth/xades-signature', $xml, [
            'Content-Type' => 'application/xml',
            'Accept' => 'application/json',
            'X-Error-Format' => 'problem-details',
        ]);

        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }
        if (! is_array($data)) {
            throw new UnexpectedResponse('The KSeF request POST /auth/xades-signature was answered with a body that is not a JSON object.', $response->getStatusCode());
        }

        return $data;
    }

    /**
     * @return array<mixed>
     *
     * @throws TransportException
     */
    private function tokenRequest(string $challenge, int $timestampMs, TokenCredentials $credentials): array
    {
        return $this->keys->using(
            PublicKeyUsage::KsefTokenEncryption,
            fn (string $certificatePem, string $keyId): array => $this->api->json('POST', '/auth/ksef-token', [
                'challenge' => $challenge,
                'contextIdentifier' => $this->config->context->json(),
                // The challenge's server timestamp, never the local clock.
                'encryptedToken' => base64_encode(RsaOaep::encrypt($credentials->token().'|'.$timestampMs, $certificatePem)),
                'publicKeyId' => $keyId,
            ]),
        );
    }

    /**
     * Polls the authentication status until it is no longer "in progress".
     *
     * @throws AuthenticationFailed|TransportException
     */
    private function await(string $reference, #[SensitiveParameter] string $authenticationToken): void
    {
        $path = '/auth/'.rawurlencode($reference);
        $operation = 'The KSeF request GET /auth/{referenceNumber}';
        $attempts = $this->config->authPollAttempts;
        for ($attempt = 1; ; $attempt++) {
            $answer = $this->api->json('GET', $path, null, $authenticationToken);
            $code = KsefAnswer::int($answer, ['status', 'code'], $operation);
            if ($code !== self::STATUS_IN_PROGRESS) {
                break;
            }
            if ($attempt >= $attempts) {
                throw new AuthenticationFailed(sprintf(
                    'The KSeF authentication %s was still in progress (status 100) after %d status checks.',
                    $reference,
                    $attempts,
                ));
            }
            $this->config->sleep($this->config->authPollWaitMs);
        }
        if ($code === self::STATUS_SUCCESS) {
            return;
        }

        $redactor = new Redactor([...$this->config->secrets(), $authenticationToken]);
        $status = is_array($answer['status'] ?? null) ? $answer['status'] : [];
        $description = $status['description'] ?? null;
        $details = $status['details'] ?? null;
        $text = is_string($description) ? trim($description) : '';
        $details = is_array($details) ? array_filter(array_map(static fn (mixed $d): string => is_string($d) ? trim($d) : '', $details)) : [];
        if ($details !== []) {
            $text .= ($text === '' ? '' : ' — ').implode('; ', $details);
        }

        throw new AuthenticationFailed(sprintf(
            'The KSeF authentication %s failed with status %d%s.',
            $reference,
            $code,
            $text === '' ? '' : ': '.$redactor->redact(rtrim($text, '. ')),
        ));
    }

    private function horizon(): DateTimeImmutable
    {
        return ($this->now)()->modify(sprintf('+%d seconds', self::VALIDITY_MARGIN_SECONDS));
    }
}
