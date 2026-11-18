<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\France;

use Closure;
use IntrAgile\EInvoicing\France\Routing\Annuaire;
use IntrAgile\EInvoicing\France\Routing\AnnuaireStatus;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\France\Siret;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\JsonResponse;
use IntrAgile\EInvoicing\Transport\Http\ReadFailure;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * The French directory of e-invoicing recipients (Annuaire), looked up live through B2Brouter
 * (GET /directory/fr/{scheme}/{value}).
 *
 * B2Brouter answers a lookup it has not resolved yet with HTTP 202; the same request is then
 * repeated, by default up to four attempts three seconds apart, so a cold lookup that keeps
 * answering 202 may block for about nine seconds; a lookup that hangs costs one HTTP timeout
 * before it reads as Unknown. The polling URL of a 202 answer is not followed: the request is
 * repeated against the configured base URL.
 *
 * Statuses:
 * - FR_ASSUJETTI_ACTIVE is Active, FR_ASSUJETTI_INACTIVE is Inactive;
 * - a 404 resource_missing answer is NotListed;
 * - anything that is not a definite answer is Unknown: FR_ASSUJETTI_UNKNOWN (B2Brouter cannot
 *   tell whether the company is in the Annuaire), still processing after the last attempt,
 *   HTTP 424 (an upstream directory unavailable), no answer, an unreadable body, no flag, an
 *   unknown flag or conflicting flags, or any other status. The router then keeps the e-invoice
 *   and asks for a directory check instead of e-reporting a B2B sale as B2C.
 *
 * A refused API key (HTTP 401 or 403) is thrown as AuthenticationFailed rather than hidden as
 * Unknown, and an identifier B2Brouter refuses (HTTP 422) as an InvalidArgumentException.
 */
final class B2BrouterAnnuaire implements Annuaire
{
    /** The identifier schemes the French directory is looked up by: SIREN, SIRET and the electronic address. */
    public const SCHEMES = [Siren::SCHEME, Siret::SCHEME, self::ELECTRONIC_ADDRESS_SCHEME];

    private const ELECTRONIC_ADDRESS_SCHEME = '0225';

    private const FLAG_STATUSES = [
        'FR_ASSUJETTI_ACTIVE' => AnnuaireStatus::Active,
        'FR_ASSUJETTI_INACTIVE' => AnnuaireStatus::Inactive,
        'FR_ASSUJETTI_UNKNOWN' => AnnuaireStatus::Unknown,
    ];

    /** A routing code or address suffix of a 0225 electronic address. */
    private const SUFFIX_PATTERN = '/\A[A-Za-z0-9_.\-\/]{1,100}\z/';

    /** The SIREN of La Poste, whose establishment numbers follow their own check. */
    private const LA_POSTE_SIREN = '356000000';

    /** Bytes of a provider error message quoted in an exception message. */
    private const QUOTED_BYTES = 300;

    private readonly HttpClient $http;

    /** @var Closure(int): mixed */
    private readonly Closure $sleep;

    /**
     * @param  int  $attempts  requests made at most while B2Brouter answers HTTP 202, at least one
     * @param  int  $retryDelaySeconds  the wait between two attempts, zero or more
     * @param  (Closure(int): mixed)|null  $sleep  waits the given number of seconds; sleep() by default
     *
     * @throws InvalidArgumentException when the attempts or the delay are out of range
     */
    public function __construct(
        private readonly B2BrouterConfig $config,
        HttpClient $http,
        private readonly int $attempts = 4,
        private readonly int $retryDelaySeconds = 3,
        ?Closure $sleep = null,
    ) {
        if ($attempts < 1) {
            throw new InvalidArgumentException('The Annuaire lookup needs at least one attempt.');
        }
        if ($retryDelaySeconds < 0) {
            throw new InvalidArgumentException('The delay between Annuaire lookup attempts cannot be negative.');
        }
        $this->http = $http->withSecrets($config->secrets());
        $this->sleep = $sleep ?? static fn (int $seconds): int => sleep($seconds);
    }

    /**
     * The directory status of the legal entity with the given SIREN.
     *
     * ASCII spaces and no-break spaces (U+00A0) are removed first, so "380 129 866" is looked up
     * as 380129866. A SIREN that then fails the local check (nine digits with a valid check digit)
     * is Unknown without a request, as the Annuaire contract asks for a lookup that is not
     * possible; lookup() refuses both instead.
     *
     * @throws InvalidArgumentException when B2Brouter refuses the SIREN (HTTP 422)
     * @throws AuthenticationFailed when B2Brouter refuses the API key
     */
    public function status(string $siren): AnnuaireStatus
    {
        $siren = str_replace([' ', "\u{00A0}"], '', $siren);
        if (! self::isSiren($siren)) {
            return AnnuaireStatus::Unknown;
        }

        return $this->lookup(Siren::SCHEME, $siren)->status;
    }

    /**
     * Looks up an identifier: a SIREN (0002), a SIRET (0009) or an electronic address (0225:
     * SIREN, SIREN_SIRET, SIREN_SIRET_ROUTINGCODE or SIREN_SUFFIX).
     *
     * @throws InvalidArgumentException when the scheme is not supported, the identifier is
     *                                  malformed (format and check digit) or B2Brouter refuses
     *                                  it; no request is made for a malformed identifier
     * @throws AuthenticationFailed when B2Brouter refuses the API key
     */
    public function lookup(string $scheme, string $value): AnnuaireEntry
    {
        self::assertIdentifier($scheme, $value);
        $url = sprintf('%s/directory/fr/%s/%s', $this->config->baseUrl, $scheme, rawurlencode($value));

        for ($attempt = 1; ; $attempt++) {
            try {
                $response = $this->http->send('GET', $url, $this->config->headers());
            } catch (TransportUnavailable|UnexpectedResponse) {
                return self::unknown();
            }
            if ($response->getStatusCode() !== 202) {
                return $this->entry($response, $scheme, $value);
            }
            if ($attempt >= $this->attempts) {
                return self::unknown();
            }
            ($this->sleep)($this->retryDelaySeconds);
        }
    }

    private function entry(ResponseInterface $response, string $scheme, string $value): AnnuaireEntry
    {
        $status = $response->getStatusCode();

        if ($status === 401 || $status === 403) {
            throw ReadFailure::of($response, 'B2Brouter', sprintf('Looking up %s:%s in the French directory', $scheme, $value), $this->config->redactor());
        }
        if ($status === 422) {
            throw new InvalidArgumentException(sprintf(
                'B2Brouter refused the identifier %s:%s for the French directory (HTTP 422): %s',
                $scheme,
                $value,
                $this->quote(self::error($response)['message'] ?? null),
            ));
        }
        if ($status === 404) {
            $code = self::error($response)['code'] ?? null;

            return $code === 'resource_missing'
                ? new AnnuaireEntry(AnnuaireStatus::NotListed, null, null, null, null, [])
                : self::unknown();
        }
        if ($status !== 200) {
            return self::unknown();
        }

        $company = self::company($response);
        if ($company === null) {
            return self::unknown();
        }
        $flags = self::flags($company['information_flags'] ?? null);
        $invoice = $company['invoice'] ?? null;

        return new AnnuaireEntry(
            self::statusOf($flags),
            self::text($company['name'] ?? null),
            self::text($company['pin_scheme'] ?? null),
            self::text($company['pin_value'] ?? null),
            is_array($invoice) ? self::text($invoice['document_type_code'] ?? null) : null,
            $flags ?? [],
        );
    }

    /**
     * The company object of a 200 answer: unwrapped from external_company when the body has one
     * (the shape the French guide shows), otherwise the body itself (the shape the API returns).
     *
     * @return array<mixed>|null
     */
    private static function company(ResponseInterface $response): ?array
    {
        try {
            $body = JsonResponse::decode($response);
        } catch (UnexpectedResponse) {
            return null;
        }
        if (array_key_exists('external_company', $body)) {
            $body = $body['external_company'];
        }

        return is_array($body) && $body !== [] && ! array_is_list($body) ? $body : null;
    }

    /**
     * The string flags of the list, or null when there is no list.
     *
     * @return list<string>|null
     */
    private static function flags(mixed $flags): ?array
    {
        if (! is_array($flags) || ! array_is_list($flags)) {
            return null;
        }

        return array_values(array_filter($flags, is_string(...)));
    }

    /**
     * The status the French flags stand for; Unknown without exactly one known flag.
     *
     * @param  list<string>|null  $flags
     */
    private static function statusOf(?array $flags): AnnuaireStatus
    {
        $statuses = [];
        foreach ($flags ?? [] as $flag) {
            if (isset(self::FLAG_STATUSES[$flag])) {
                $statuses[$flag] = self::FLAG_STATUSES[$flag];
            }
        }

        return count($statuses) === 1 ? array_values($statuses)[0] : AnnuaireStatus::Unknown;
    }

    private static function unknown(): AnnuaireEntry
    {
        return new AnnuaireEntry(AnnuaireStatus::Unknown, null, null, null, null, []);
    }

    /**
     * The error object of an error answer, or an empty array when there is none.
     *
     * @return array<mixed>
     */
    private static function error(ResponseInterface $response): array
    {
        try {
            $error = JsonResponse::decode($response)['error'] ?? null;
        } catch (UnexpectedResponse) {
            return [];
        }

        return is_array($error) ? $error : [];
    }

    /**
     * The provider's error message in quotes, redacted and shortened, or a note that there is none.
     */
    private function quote(mixed $message): string
    {
        $message = self::text($message);
        if ($message === null) {
            return 'no details given.';
        }
        $message = $this->config->redactor()->redact($message);
        if (strlen($message) > self::QUOTED_BYTES) {
            $message = mb_strcut($message, 0, self::QUOTED_BYTES, 'UTF-8').'...';
        }

        return '"'.$message.'"';
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * @throws InvalidArgumentException when the scheme is not supported or the identifier is malformed
     */
    private static function assertIdentifier(string $scheme, string $value): void
    {
        $valid = match ($scheme) {
            Siren::SCHEME => self::isSiren($value),
            Siret::SCHEME => self::isSiret($value),
            self::ELECTRONIC_ADDRESS_SCHEME => self::isElectronicAddress($value),
            default => throw new InvalidArgumentException(sprintf(
                'The French directory is looked up by scheme %s, not "%s".',
                implode(', ', self::SCHEMES),
                $scheme,
            )),
        };
        if (! $valid) {
            throw new InvalidArgumentException(sprintf('Invalid identifier "%s" for scheme %s: %s', $value, $scheme, match ($scheme) {
                Siren::SCHEME => 'expected a SIREN, 9 digits with a valid check digit.',
                Siret::SCHEME => 'expected a SIRET, 14 digits with a valid check digit.',
                default => 'expected SIREN, SIREN_SIRET, SIREN_SIRET_ROUTINGCODE or SIREN_SUFFIX, with a valid SIREN, a SIRET of that SIREN and a routing code or suffix of 1 to 100 characters of A-Z, a-z, 0-9, "_", ".", "-" and "/".',
            }));
        }
    }

    private static function isSiren(string $value): bool
    {
        return Siren::isValid($value) && self::luhn($value);
    }

    private static function isSiret(string $value): bool
    {
        if (! Siret::isValid($value) || ! self::isSiren(substr($value, 0, 9))) {
            return false;
        }
        if (self::luhn($value)) {
            return true;
        }

        // La Poste establishments: the sum of the digits is a multiple of five.
        return str_starts_with($value, self::LA_POSTE_SIREN) && array_sum(str_split($value)) % 5 === 0;
    }

    private static function isElectronicAddress(string $value): bool
    {
        $parts = explode('_', $value, 2);
        $siren = $parts[0];
        if (! self::isSiren($siren)) {
            return false;
        }
        if (! isset($parts[1])) {
            return true;
        }
        $rest = $parts[1];
        if (preg_match('/\A([0-9]{14})(?:_(.*))?\z/s', $rest, $match) !== 1) {
            return preg_match(self::SUFFIX_PATTERN, $rest) === 1;
        }
        if (! self::isSiret($match[1]) || ! str_starts_with($match[1], $siren)) {
            return false;
        }

        return ! isset($match[2]) || preg_match(self::SUFFIX_PATTERN, $match[2]) === 1;
    }

    /**
     * Whether the digits pass the Luhn check.
     */
    private static function luhn(string $digits): bool
    {
        $sum = 0;
        foreach (str_split(strrev($digits)) as $position => $digit) {
            $value = (int) $digit * ($position % 2 === 1 ? 2 : 1);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        return $sum % 10 === 0;
    }
}
