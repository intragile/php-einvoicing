<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Testing;

use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Poland\Nip;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Authenticator;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyRegistry;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use InvalidArgumentException;

/**
 * The test-data endpoints of the KSeF TEST environment (/testdata/*), which set up synthetic
 * subjects, permissions and limits. They exist only on TE, so any other environment is refused.
 *
 * The subject, attachment and permission endpoints take no authentication. The rate-limit
 * endpoints act on the context of the configuration and authenticate with its credentials.
 */
final class KsefTestData
{
    /** The subject types POST /testdata/subject accepts (SubjectType). */
    public const SUBJECT_TYPES = ['EnforcementAuthority', 'VatGroup', 'JST'];

    /** The categories of a rate-limit override (ApiRateLimitsOverride), every one required. */
    public const RATE_LIMIT_CATEGORIES = [
        'onlineSession', 'batchSession', 'invoiceSend', 'invoiceStatus', 'sessionList', 'sessionInvoiceList',
        'sessionMisc', 'invoiceMetadata', 'invoiceExport', 'invoiceExportStatus', 'invoiceDownload',
        'collectiveIdentifier', 'other',
    ];

    /** The periods of one rate-limit category. */
    public const RATE_LIMIT_PERIODS = ['perSecond', 'perMinute', 'perHour'];

    private const DESCRIPTION_MIN_LENGTH = 5;

    private const DESCRIPTION_MAX_LENGTH = 256;

    private readonly KsefApi $api;

    private readonly KsefApi $authorized;

    /**
     * @param  (Closure(): DateTimeImmutable)|null  $now  the current time; the system clock when null
     *
     * @throws InvalidArgumentException when the configuration is not for the KSeF TEST environment
     */
    public function __construct(KsefConfig $config, HttpClient $http, ?Closure $now = null)
    {
        if ($config->environment !== KsefEnvironment::Test) {
            throw new InvalidArgumentException(sprintf(
                'The KSeF test-data endpoints exist only on the TEST environment (TE); the configuration is for %s.',
                $config->environment->name,
            ));
        }
        $this->api = new KsefApi($config, $http);
        $this->authorized = $this->api->withAuthenticator(
            new Authenticator($this->api, $config, new PublicKeyRegistry($this->api, $now), now: $now),
        );
    }

    /**
     * Allows the subject to send invoices with attachments (POST /testdata/attachment).
     *
     * @throws InvalidArgumentException when the NIP is not well-formed
     * @throws TransportException when the request fails or KSeF refuses it
     */
    public function enableAttachments(string $nip): void
    {
        $this->api->json('POST', '/testdata/attachment', ['nip' => self::nip($nip)]);
    }

    /**
     * Creates a test subject of the given type (POST /testdata/subject).
     *
     * @param  string  $type  EnforcementAuthority, VatGroup or JST
     * @param  string  $description  5 to 256 characters
     *
     * @throws InvalidArgumentException when the NIP, the type or the description is invalid
     * @throws TransportException when the request fails or KSeF refuses it
     */
    public function createSubject(string $nip, string $type, string $description): void
    {
        if (! in_array($type, self::SUBJECT_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('A KSeF test subject type is one of %s.', implode(', ', self::SUBJECT_TYPES)));
        }

        $this->api->json('POST', '/testdata/subject', [
            'subjectNip' => self::nip($nip),
            'subjectType' => $type,
            'description' => self::description($description),
        ]);
    }

    /**
     * Overrides the request limits of the configuration's context (POST /testdata/rate-limits).
     * KSeF requires every category in the override, so the current limits (GET /rate-limits) are
     * read first and the given values replace theirs; a category or period not given keeps its
     * current value.
     *
     * @param  array<string, array<string, int>>  $limits  periods (perSecond, perMinute, perHour)
     *                                                     by category, for example
     *                                                     ['invoiceMetadata' => ['perMinute' => 1]]
     *
     * @throws InvalidArgumentException when a category or a period is unknown or a value not positive
     * @throws TransportException when a request fails or KSeF refuses it
     */
    public function setRateLimits(array $limits): void
    {
        self::checkLimits($limits);

        $current = $this->authorized->authorized(fn (string $bearer): array => $this->authorized->json('GET', '/rate-limits', null, $bearer));
        $merged = [];
        foreach (self::RATE_LIMIT_CATEGORIES as $category) {
            $values = $current[$category] ?? null;
            $row = [];
            foreach (self::RATE_LIMIT_PERIODS as $period) {
                $value = $limits[$category][$period] ?? (is_array($values) ? ($values[$period] ?? null) : null);
                if (! is_int($value)) {
                    throw new UnexpectedResponse(sprintf('The KSeF request GET /rate-limits was answered without the %s limit of %s.', $period, $category));
                }
                $row[$period] = $value;
            }
            $merged[$category] = $row;
        }

        $body = ['rateLimits' => $merged];
        $this->authorized->authorized(fn (string $bearer): array => $this->authorized->json('POST', '/testdata/rate-limits', $body, $bearer));
    }

    /**
     * Restores the TE default request limits of the configuration's context
     * (DELETE /testdata/rate-limits).
     *
     * @throws TransportException when the request fails or KSeF refuses it
     */
    public function resetRateLimits(): void
    {
        $this->authorized->authorized(fn (string $bearer): array => $this->authorized->json('DELETE', '/testdata/rate-limits', null, $bearer));
    }

    /**
     * Grants permissions to a test subject or person (POST /testdata/permissions). The body is a
     * TestDataPermissionsGrantRequest, sent as given: contextIdentifier, authorizedIdentifier and
     * a list of permissions with permissionType and description.
     *
     * @param  array<string, mixed>  $body
     *
     * @throws InvalidArgumentException when a required member is missing
     * @throws TransportException when the request fails or KSeF refuses it
     */
    public function grantPermissions(array $body): void
    {
        foreach (['contextIdentifier', 'authorizedIdentifier', 'permissions'] as $member) {
            if (! is_array($body[$member] ?? null)) {
                throw new InvalidArgumentException(sprintf('A KSeF test permission grant needs "%s".', $member));
            }
        }

        $this->api->json('POST', '/testdata/permissions', $body);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function nip(string $nip): string
    {
        if (! Nip::isWellFormed($nip)) {
            throw new InvalidArgumentException("The NIP \"{$nip}\" is not a well-formed NIP.");
        }

        return $nip;
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function description(string $description): string
    {
        $length = mb_strlen($description, 'UTF-8');
        if ($length < self::DESCRIPTION_MIN_LENGTH || $length > self::DESCRIPTION_MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'A KSeF test-data description must be %d to %d characters long, %d given.',
                self::DESCRIPTION_MIN_LENGTH,
                self::DESCRIPTION_MAX_LENGTH,
                $length,
            ));
        }

        return $description;
    }

    /**
     * @param  array<mixed>  $limits
     *
     * @throws InvalidArgumentException
     */
    private static function checkLimits(array $limits): void
    {
        if ($limits === []) {
            throw new InvalidArgumentException('A KSeF rate-limit override needs at least one category.');
        }
        foreach ($limits as $category => $values) {
            if (! in_array($category, self::RATE_LIMIT_CATEGORIES, true)) {
                throw new InvalidArgumentException(sprintf('"%s" is not a KSeF rate-limit category; use one of %s.', $category, implode(', ', self::RATE_LIMIT_CATEGORIES)));
            }
            if (! is_array($values) || $values === []) {
                throw new InvalidArgumentException(sprintf('The KSeF rate limits of %s must map periods to values.', $category));
            }
            foreach ($values as $period => $value) {
                if (! in_array($period, self::RATE_LIMIT_PERIODS, true)) {
                    throw new InvalidArgumentException(sprintf('"%s" is not a KSeF rate-limit period; use %s.', $period, implode(', ', self::RATE_LIMIT_PERIODS)));
                }
                if (! is_int($value) || $value < 1) {
                    throw new InvalidArgumentException(sprintf('The KSeF rate limit %s of %s must be a positive integer.', $period, $category));
                }
            }
        }
    }
}
