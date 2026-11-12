<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Directory;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\JsonResponse;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Searches the Peppol Directory, the public index of participant business cards.
 *
 * The Directory is informative only: its data is copied from SMP business cards and may be stale
 * or incomplete. Use it to find a participant identifier, never to decide whether or where to
 * send; route with ParticipantLookup (SML and SMP) instead.
 *
 * The Directory allows two queries per second. This client waits so that its own requests are at
 * least 500 ms apart; instances do not coordinate, so share one per process.
 */
final class DirectoryClient
{
    /** Minimum gap between two requests, in microseconds. */
    public const MIN_INTERVAL_MICROSECONDS = 500_000;

    /** @var Closure(): float */
    private readonly Closure $clock;

    /** @var Closure(int): void */
    private readonly Closure $sleep;

    /** Clock reading when the previous request was sent, in seconds. */
    private ?float $lastRequestAt = null;

    /**
     * @param  (Closure(): float)|null  $clock  current time in seconds; defaults to microtime(true)
     * @param  (Closure(int): void)|null  $sleep  waits the given microseconds; defaults to usleep()
     */
    public function __construct(
        private readonly HttpClient $http,
        private readonly DirectoryEnvironment $env = DirectoryEnvironment::Production,
        ?Closure $clock = null,
        ?Closure $sleep = null,
    ) {
        $this->clock = $clock ?? static fn (): float => microtime(true);
        $this->sleep = $sleep ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
    }

    /**
     * One page of the participants matching every criterion of the query.
     *
     * @throws RateLimited when the Directory answers 429
     * @throws TransportUnavailable when the Directory cannot be reached
     * @throws UnexpectedResponse when the Directory answers another error status or a body that
     *                            is not a search result
     */
    public function search(DirectoryQuery $q): DirectoryResult
    {
        $url = $this->env->value.'/search/1.0/json?'.http_build_query($q->parameters(), '', '&', PHP_QUERY_RFC3986);

        $this->throttle();
        $response = $this->http->send('GET', $url, ['Accept' => 'application/json']);

        $status = $response->getStatusCode();
        if ($status === 429) {
            throw new RateLimited(
                'The Peppol Directory refused the request because too many were made (HTTP 429).',
                self::retryAfter($response),
            );
        }
        if ($status !== 200) {
            throw UnexpectedResponse::forResponse($response, 'The Peppol Directory answered with an error status');
        }

        return self::result($response, JsonResponse::decode($response));
    }

    /**
     * The Directory entry of the participant, or null when it is not listed.
     *
     * @throws RateLimited when the Directory answers 429
     * @throws TransportUnavailable when the Directory cannot be reached
     * @throws UnexpectedResponse when the Directory answers another error status or a body that
     *                            is not a search result
     */
    public function find(ParticipantId $id): ?DirectoryEntry
    {
        foreach ($this->search(new DirectoryQuery(participant: $id))->entries as $entry) {
            if ($entry->participant->equals($id)) {
                return $entry;
            }
        }

        return null;
    }

    private function throttle(): void
    {
        $now = ($this->clock)();
        if ($this->lastRequestAt !== null) {
            $wait = self::MIN_INTERVAL_MICROSECONDS - (int) floor(($now - $this->lastRequestAt) * 1_000_000);
            if ($wait > 0) {
                ($this->sleep)($wait);
                $now += $wait / 1_000_000;
            }
        }
        $this->lastRequestAt = $now;
    }

    /**
     * Retry-After in delay-seconds form; an HTTP date or a malformed value gives null.
     */
    private static function retryAfter(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));

        return ctype_digit($value) ? (int) $value : null;
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws UnexpectedResponse when the body is not a search result
     */
    private static function result(ResponseInterface $response, array $data): DirectoryResult
    {
        $total = $data['total-result-count'] ?? null;
        $matches = $data['matches'] ?? null;
        if (! is_int($total) || ! is_array($matches) || ! array_is_list($matches)) {
            throw UnexpectedResponse::forResponse($response, 'The Peppol Directory response lacks "total-result-count" or a "matches" list');
        }

        $entries = [];
        foreach ($matches as $match) {
            $entry = is_array($match) ? self::entry($match) : null;
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return new DirectoryResult($total, $entries);
    }

    /**
     * The entry of a match, or null when its participant is not a valid iso6523-actorid-upis
     * identifier.
     *
     * @param  array<mixed>  $match
     */
    private static function entry(array $match): ?DirectoryEntry
    {
        $id = self::map($match['participantID'] ?? null);
        $scheme = self::string($id['scheme'] ?? null);
        $value = self::string($id['value'] ?? null);
        if ($scheme === null || $value === null || strcasecmp($scheme, ParticipantId::SCHEME) !== 0) {
            return null;
        }
        try {
            $participant = ParticipantId::parse($value);
        } catch (InvalidArgumentException) {
            return null;
        }

        $documentTypes = [];
        foreach (self::list($match['docTypes'] ?? null) as $docType) {
            $docType = self::map($docType);
            $docScheme = self::string($docType['scheme'] ?? null);
            $docValue = self::string($docType['value'] ?? null);
            if ($docScheme !== null && $docValue !== null) {
                $documentTypes[] = $docScheme.'::'.$docValue;
            }
        }

        $names = [];
        $country = null;
        $identifiers = [];
        $registeredOn = null;
        foreach (self::list($match['entities'] ?? null) as $entity) {
            $entity = self::map($entity);
            foreach (self::list($entity['name'] ?? null) as $name) {
                $name = self::string(self::map($name)['name'] ?? null);
                if ($name !== null && ! in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
            $country ??= self::string($entity['countryCode'] ?? null);
            foreach (self::list($entity['identifiers'] ?? null) as $identifier) {
                $identifier = self::map($identifier);
                $idScheme = self::string($identifier['scheme'] ?? null);
                $idValue = self::string($identifier['value'] ?? null);
                if ($idScheme !== null && $idValue !== null) {
                    $identifiers[] = ['scheme' => $idScheme, 'value' => $idValue];
                }
            }
            $date = self::date($entity['regDate'] ?? null);
            if ($date !== null && ($registeredOn === null || $date < $registeredOn)) {
                $registeredOn = $date;
            }
        }

        return new DirectoryEntry(
            $participant,
            $names,
            $country,
            array_values(array_unique($documentTypes)),
            $identifiers,
            $registeredOn,
        );
    }

    /**
     * @return array<mixed>
     */
    private static function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value): array
    {
        return is_array($value) && array_is_list($value) ? $value : [];
    }

    private static function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function date(mixed $value): ?DateTimeImmutable
    {
        $value = self::string($value);
        if ($value === null) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
