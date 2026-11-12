<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Peppol;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Fetches a file over HTTPS with PHP streams, so the core needs no HTTP client dependency.
 *
 * TLS peers are verified, every hop of a redirect chain must stay on HTTPS, and the download is
 * bounded in time, size and number of redirects.
 */
final class Downloader
{
    private const MAX_REDIRECTS = 3;

    private const CHUNK_BYTES = 65_536;

    private readonly StreamOpener $opener;

    /** @var Closure(): float */
    private readonly Closure $clock;

    /**
     * @param  ?Closure(): float  $clock  the current time in seconds; defaults to microtime(true)
     */
    public function __construct(
        private readonly int $timeoutSeconds = 30,
        private readonly int $maxBytes = 5_000_000,
        ?StreamOpener $opener = null,
        ?Closure $clock = null,
    ) {
        $this->opener = $opener ?? new PhpStreamOpener;
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    /**
     * @throws InvalidArgumentException when the URL, or a redirect target, is not an https:// URL
     * @throws RuntimeException when the download fails, times out or exceeds the size limit
     */
    public function get(string $url): string
    {
        $current = $url;
        for ($redirects = 0; ; $redirects++) {
            self::requireHttps($current);
            [$status, $location, $body] = $this->fetch($current, $url);
            if ($status >= 300 && $status < 400 && $location !== null) {
                if ($redirects >= self::MAX_REDIRECTS) {
                    throw new RuntimeException("Downloading \"{$url}\" failed: more than ".self::MAX_REDIRECTS.' redirects.');
                }
                $current = self::resolve($current, $location);

                continue;
            }
            if ($status !== 200) {
                throw new RuntimeException("Downloading \"{$url}\" failed: HTTP status {$status}.");
            }

            return $body;
        }
    }

    /**
     * One request without following redirects.
     *
     * @return array{int, ?string, string} status code, Location header, body
     */
    private function fetch(string $current, string $url): array
    {
        try {
            $response = $this->opener->open($current, $this->timeoutSeconds);
        } catch (RuntimeException $exception) {
            throw new RuntimeException("Downloading \"{$url}\" failed: ".$exception->getMessage(), 0, $exception);
        }
        if ($response === false) {
            throw new RuntimeException("Downloading \"{$url}\" failed: the connection could not be opened.");
        }
        [$headers, $stream] = $response;

        try {
            [$status, $location] = self::parseHeaders($headers);
            if ($status >= 300 && $status < 400) {
                return [$status, $location, ''];
            }

            return [$status, $location, $this->readBody($stream, $url)];
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     */
    private function readBody($stream, string $url): string
    {
        $deadline = ($this->clock)() + $this->timeoutSeconds;
        $body = '';
        while (! feof($stream)) {
            $chunk = fread($stream, self::CHUNK_BYTES);
            if ($chunk === false) {
                throw new RuntimeException("Downloading \"{$url}\" failed while reading the response.");
            }
            $body .= $chunk;
            if (strlen($body) > $this->maxBytes) {
                throw new RuntimeException("Downloading \"{$url}\" was aborted: the file is larger than {$this->maxBytes} bytes.");
            }
            if (stream_get_meta_data($stream)['timed_out'] || ($this->clock)() > $deadline) {
                throw new RuntimeException("Downloading \"{$url}\" timed out after {$this->timeoutSeconds} seconds.");
            }
        }

        return $body;
    }

    /**
     * The status code and Location header of the last response in the wrapper data.
     *
     * @param  list<string>  $headers
     * @return array{int, ?string}
     */
    private static function parseHeaders(array $headers): array
    {
        $status = 0;
        $location = null;
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match) === 1) {
                $status = (int) $match[1];
                $location = null;
            } elseif (stripos($header, 'location:') === 0) {
                $location = trim(substr($header, 9));
            }
        }

        return [$status, $location];
    }

    /**
     * Resolves a Location header against the URL that returned it (absolute URLs and
     * absolute paths; anything else is taken as given and must itself be https://).
     */
    private static function resolve(string $base, string $location): string
    {
        if (str_starts_with($location, '/') && ! str_starts_with($location, '//')) {
            $parts = parse_url($base);
            $host = is_array($parts) ? ($parts['host'] ?? '') : '';
            $port = is_array($parts) && isset($parts['port']) ? ":{$parts['port']}" : '';

            return "https://{$host}{$port}{$location}";
        }

        return $location;
    }

    private static function requireHttps(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($scheme) || strtolower($scheme) !== 'https' || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException("Only https:// URLs are downloaded; \"{$url}\" is not one.");
        }
    }
}
