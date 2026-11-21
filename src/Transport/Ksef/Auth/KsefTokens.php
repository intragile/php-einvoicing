<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Ksef\KsefAnswer;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use InvalidArgumentException;
use LogicException;

/**
 * Generates and manages KSeF tokens of the authenticated context through the rev2 token API.
 *
 * Generation uses POST /tokens/rev2, available on KSeF TE since API 2.10; PROD runs API 2.9 and
 * offers only the deprecated POST /tokens, which is not implemented, so generation through the API
 * fails there until 2.10 reaches it. Tokens created in the KSeF web application still
 * authenticate, as token login does not use rev2 (verified on TE only). A token generated after
 * KSeF-token authentication must be approved, with a certificate authentication, before it can
 * be used.
 */
final class KsefTokens
{
    /** The description length bounds of GenerateTokenRequestRev2, in characters. */
    public const DESCRIPTION_MIN_LENGTH = 5;

    public const DESCRIPTION_MAX_LENGTH = 256;

    /** A KSeF reference number: letters, digits and hyphens. */
    private const REFERENCE_PATTERN = '/\A[A-Za-z0-9-]{1,64}\z/';

    /**
     * @param  KsefApi  $api  a client with an Authenticator; see KsefApi::withAuthenticator()
     */
    public function __construct(private readonly KsefApi $api) {}

    /**
     * Generates a token with the given permissions.
     *
     * @param  list<TokenPermission>  $permissions  at least one
     * @param  string  $description  5 to 256 characters
     * @param  DateTimeImmutable|null  $validFrom  the start of validity, at most 60 days ahead;
     *                                             the moment of generation when null
     * @param  DateTimeImmutable|null  $validTo  the end of validity, at most 365 days after the
     *                                           start; 90 days after the start when null
     *
     * @throws InvalidArgumentException when there is no permission, or the description (length,
     *                                  UTF-8) or the period is invalid
     * @throws LogicException when the client has no Authenticator
     * @throws TransportException when the request fails or KSeF refuses it
     */
    public function generate(array $permissions, string $description, ?DateTimeImmutable $validFrom = null, ?DateTimeImmutable $validTo = null): GeneratedToken
    {
        if ($permissions === []) {
            throw new InvalidArgumentException('A KSeF token needs at least one permission.');
        }
        $values = [];
        foreach ($permissions as $permission) {
            $values[] = $permission->value;
        }
        if (! mb_check_encoding($description, 'UTF-8')) {
            throw new InvalidArgumentException('A KSeF token description must be valid UTF-8.');
        }
        $length = mb_strlen($description, 'UTF-8');
        if ($length < self::DESCRIPTION_MIN_LENGTH || $length > self::DESCRIPTION_MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'A KSeF token description must be %d to %d characters long, %d given.',
                self::DESCRIPTION_MIN_LENGTH,
                self::DESCRIPTION_MAX_LENGTH,
                $length,
            ));
        }
        if ($validFrom !== null && $validTo !== null && $validTo <= $validFrom) {
            throw new InvalidArgumentException('The end of a KSeF token\'s validity must be after its start.');
        }

        $body = ['permissions' => array_values(array_unique($values)), 'description' => $description];
        if ($validFrom !== null) {
            $body['validFrom'] = self::isoDate($validFrom);
        }
        if ($validTo !== null) {
            $body['validTo'] = self::isoDate($validTo);
        }

        $operation = 'The KSeF request POST /tokens/rev2';
        $answer = $this->api->authorized(fn (string $bearer): array => $this->api->json('POST', '/tokens/rev2', $body, $bearer));

        return new GeneratedToken(
            KsefAnswer::string($answer, ['referenceNumber'], $operation),
            KsefAnswer::string($answer, ['token'], $operation),
        );
    }

    /**
     * The token's status from GET /tokens/rev2/{referenceNumber}: Pending, Active, Revoking,
     * Revoked, Failed, NotYetActive, AwaitingApproval, ApprovalExpired or Expired, or the raw code
     * of a status newer than this client. The legacy GET /tokens/{referenceNumber} is not used:
     * it reports a token awaiting approval as Active.
     *
     * @throws InvalidArgumentException when the reference number is malformed
     * @throws TransportException when the request fails or KSeF refuses it
     */
    public function status(string $referenceNumber): string
    {
        $path = '/tokens/rev2/'.substr(self::path($referenceNumber), strlen('/tokens/'));
        $answer = $this->api->authorized(fn (string $bearer): array => $this->api->json('GET', $path, null, $bearer));
        $operation = 'The KSeF request GET /tokens/rev2/{referenceNumber}';

        $status = $answer['status'] ?? null;
        if (is_array($status)) {
            // rawCode is the stable code, also of a value the API revision calls FutureValue.
            $raw = $status['rawCode'] ?? null;

            return is_string($raw) && trim($raw) !== '' ? trim($raw) : KsefAnswer::string($answer, ['status', 'code'], $operation);
        }

        return KsefAnswer::string($answer, ['status'], $operation);
    }

    /**
     * Approves a token generated after KSeF-token authentication (status AwaitingApproval), so
     * it can be used. KSeF accepts the approval only from a context authenticated otherwise than
     * by a KSeF token, with a certificate (error 26005 otherwise), within 24 hours.
     *
     * @throws InvalidArgumentException when the reference number is malformed
     * @throws TransportException when the request fails or KSeF refuses it
     */
    public function approve(string $referenceNumber): void
    {
        $path = self::path($referenceNumber).'/approve';
        $this->api->authorized(fn (string $bearer): array => $this->api->json('POST', $path, null, $bearer));
    }

    /**
     * Revokes a token; it can no longer authenticate.
     *
     * @throws InvalidArgumentException when the reference number is malformed
     * @throws TransportException when the request fails or KSeF refuses it
     */
    public function revoke(string $referenceNumber): void
    {
        $path = self::path($referenceNumber);
        $this->api->authorized(fn (string $bearer): array => $this->api->json('DELETE', $path, null, $bearer));
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function path(string $referenceNumber): string
    {
        if (preg_match(self::REFERENCE_PATTERN, $referenceNumber) !== 1) {
            // The value is not quoted: a caller may pass the token itself, which starts with the
            // reference number and carries the secret after it.
            throw new InvalidArgumentException(sprintf(
                'The given value (%d characters) is not a KSeF token reference number, which has only letters, digits and hyphens.',
                strlen($referenceNumber),
            ));
        }

        return '/tokens/'.$referenceNumber;
    }

    /**
     * An ISO 8601 date-time in UTC with milliseconds, for example 2026-11-14T09:48:26.870+00:00.
     */
    private static function isoDate(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.vP');
    }
}
