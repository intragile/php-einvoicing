<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef;

use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SensitiveParameter;

/**
 * The low-level KSeF API 2.0 client: builds requests against the environment's base URL, sends
 * them through HttpClient and maps unsuccessful answers with KsefErrors.
 *
 * A GET answered with 429 is repeated after the Retry-After delay, at most three times and with at
 * most 60 seconds of waiting in total; the last refusal is then thrown as RateLimited. Requests
 * with any other method are never repeated, because a repeat could duplicate their effect.
 *
 * The configured credentials and the bearer token of each call are kept out of every exception
 * message, body excerpt and error list. The client holds no mutable state, so a clone is
 * independent of the original.
 */
final class KsefApi
{
    /** Repeats of a GET refused with 429. */
    private const MAX_RETRIES = 3;

    /** Total milliseconds a call may wait for 429 repeats. */
    private const RETRY_BUDGET_MS = 60_000;

    /** Seconds waited after a 429 that has no readable Retry-After header. */
    private const DEFAULT_RETRY_AFTER_SECONDS = 5;

    private readonly HttpClient $http;

    public function __construct(private readonly KsefConfig $config, HttpClient $http)
    {
        $this->http = $http->withSecrets($config->secrets());
    }

    /**
     * Sends a JSON request to a path under the base URL and returns the decoded JSON answer; an
     * empty successful answer is an empty array.
     *
     * The request carries "Accept: application/json" and "X-Error-Format: problem-details", and
     * "Content-Type: application/json" with a body. An empty body array is sent as "{}".
     *
     * @param  string  $path  the path under the base URL, starting with "/", for example "/auth/challenge"
     * @param  array<mixed>|null  $body
     * @return array<mixed>
     *
     * @throws InvalidArgumentException when the path does not start with a single "/"
     * @throws TransportException when the request fails or KSeF refuses it; see KsefErrors
     * @throws UnexpectedResponse when a successful answer is not a JSON object or array
     */
    public function json(string $method, string $path, ?array $body = null, #[SensitiveParameter] ?string $bearer = null): array
    {
        $headers = ['Accept' => 'application/json', 'X-Error-Format' => 'problem-details'];
        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = $body === [] ? '{}' : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $response = $this->send($method, $this->url($path, false), $payload, $headers, $bearer);
        $contents = (string) $response->getBody();
        if (trim($contents) === '') {
            return [];
        }
        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }
        if (! is_array($data)) {
            throw $this->redactor($bearer)->unexpected($response, sprintf('The KSeF request %s %s was answered with a body that is not a JSON object or array', strtoupper($method), $this->describe($path)));
        }

        return $data;
    }

    /**
     * Sends a request with exactly the given headers (plus the bearer, when given) and returns the
     * successful response, its body fully read. Use it for XML and binary bodies, and for the
     * absolute part upload and download URLs KSeF hands out.
     *
     * @param  string  $pathOrAbsoluteUrl  a path under the base URL starting with "/", or an
     *                                     absolute https:// URL
     * @param  array<string, string|list<string>>  $headers
     *
     * @throws InvalidArgumentException when the target is neither such a path nor an https:// URL
     *                                  with a host and without user info
     * @throws TransportException when the request fails or KSeF refuses it; see KsefErrors
     */
    public function raw(string $method, string $pathOrAbsoluteUrl, ?string $body = null, array $headers = [], #[SensitiveParameter] ?string $bearer = null): ResponseInterface
    {
        return $this->send($method, $this->url($pathOrAbsoluteUrl, true), $body, $headers, $bearer);
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     *
     * @throws OutcomeUnknown|TransportUnavailable when the request fails without an answer
     * @throws RateLimited when a 429 is not or no longer repeated
     * @throws TransportException for any other unsuccessful answer
     */
    private function send(string $method, string $url, ?string $body, array $headers, #[SensitiveParameter] ?string $bearer): ResponseInterface
    {
        $method = strtoupper($method);
        $http = $this->http;
        if ($bearer !== null) {
            $headers['Authorization'] = 'Bearer '.$bearer;
            $http = $http->withSecrets([$bearer]);
        }
        $operation = sprintf('The KSeF request %s %s', $method, $this->describe($url));

        $retries = 0;
        $waitedMs = 0;
        while (true) {
            try {
                $response = $http->send($method, $url, $headers, $body);
            } catch (OutcomeUnknown|TransportUnavailable $exception) {
                throw $this->scrubbed($exception, $bearer);
            }
            $status = $response->getStatusCode();
            if ($status >= 200 && $status <= 299) {
                return $response;
            }
            if ($status === 429 && $method === 'GET' && $retries < self::MAX_RETRIES && $waitedMs < self::RETRY_BUDGET_MS) {
                $waitMs = min(
                    (KsefErrors::retryAfter($response) ?? self::DEFAULT_RETRY_AFTER_SECONDS) * 1000,
                    self::RETRY_BUDGET_MS - $waitedMs,
                );
                $this->config->sleep($waitMs);
                $waitedMs += $waitMs;
                $retries++;

                continue;
            }

            throw (new KsefErrors($this->redactor($bearer)))->exception($response, $operation, $method);
        }
    }

    /**
     * The full URL of a target.
     *
     * @throws InvalidArgumentException
     */
    private function url(string $target, bool $allowAbsolute): string
    {
        if (str_starts_with($target, '/') && ! str_starts_with($target, '//')) {
            return $this->config->baseUrl.$target;
        }
        if (! $allowAbsolute) {
            throw new InvalidArgumentException('A KSeF API path must start with a single "/".');
        }
        $parts = parse_url($target);
        if (
            $parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') === ''
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            // The URL itself is not quoted: a part URL may carry a signature in its query.
            throw new InvalidArgumentException('A KSeF request target must be a path starting with "/" or an https:// URL with a host and without user info.');
        }

        return $target;
    }

    /**
     * The URL for messages: a path under the base URL as the path, any other URL without its
     * query and fragment, which may carry a signature.
     */
    private function describe(string $url): string
    {
        if (str_starts_with($url, $this->config->baseUrl.'/')) {
            $url = substr($url, strlen($this->config->baseUrl));
        }
        $cut = strcspn($url, '?#');

        return substr($url, 0, $cut);
    }

    /**
     * The failure with its message as HttpClient redacted it, and its chain of previous
     * exceptions replaced by copies whose messages are redacted too: a PSR-18 client may quote
     * request headers, and with them the bearer token, in its own exception messages.
     */
    private function scrubbed(OutcomeUnknown|TransportUnavailable $exception, #[SensitiveParameter] ?string $bearer): OutcomeUnknown|TransportUnavailable
    {
        $redactor = $this->redactor($bearer);
        $chain = [];
        for ($previous = $exception->getPrevious(); $previous !== null; $previous = $previous->getPrevious()) {
            $chain[] = $previous;
        }
        $copy = null;
        foreach (array_reverse($chain) as $previous) {
            $copy = new RuntimeException(
                sprintf('[%s] %s', $previous::class, $redactor->redact($previous->getMessage())),
                (int) $previous->getCode(),
                $copy,
            );
        }
        $message = $redactor->redact($exception->getMessage());

        return $exception instanceof OutcomeUnknown ? new OutcomeUnknown($message, $copy) : new TransportUnavailable($message, $copy);
    }

    private function redactor(#[SensitiveParameter] ?string $bearer): Redactor
    {
        $secrets = $this->config->secrets();
        if ($bearer !== null) {
            $secrets[] = $bearer;
        }

        return new Redactor($secrets);
    }
}
