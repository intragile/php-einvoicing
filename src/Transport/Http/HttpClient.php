<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Http;

use Closure;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * Sends HTTP requests through any PSR-18 client and turns client failures into transport
 * exceptions that tell whether a retry is safe.
 *
 * PSR-18 cannot tell a request that never left from one that was sent but not answered (a
 * timeout is a network exception either way), so a failed request that changes state is reported
 * as OutcomeUnknown and only a failed safe request (GET, HEAD, OPTIONS) as TransportUnavailable.
 * A body that cannot be read to the end is mapped the same way: the status line arrived, so the
 * server most likely processed the request.
 * Response bodies are read into memory up to a size cap.
 *
 * Credentials are kept out of exception messages and body excerpts: the Authorization and
 * Proxy-Authorization header values, the user info and query string of the URL, and every secret
 * passed to the constructor, each raw, percent-encoded (rawurlencode) and Base64-encoded. Only
 * whole occurrences are replaced, and a body is redacted before its excerpt is cut, so a secret
 * across the excerpt limit cannot survive in part. Secrets shorter than eight characters are not
 * redacted, because replacing them would garble ordinary text.
 */
final class HttpClient
{
    /** Methods that never change state on the server, so a failed request may be repeated. */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** Headers whose values are credentials. */
    private const SECRET_HEADERS = ['authorization', 'proxy-authorization'];

    private const CHUNK_BYTES = 1_048_576;

    /** Secrets shorter than this are not redacted. */
    private const MIN_SECRET_LENGTH = 8;

    /**
     * The configured secrets, kept in a closure so that they stay out of var_export(), print_r(),
     * var_dump() and json_encode() output.
     *
     * @var Closure(): list<string>
     */
    private readonly Closure $secrets;

    /**
     * @param  list<string>  $secrets  values to keep out of exception messages and body excerpts,
     *                                 such as API keys; values under eight characters are ignored
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly int $maxResponseBytes = 20_000_000,
        #[SensitiveParameter] array $secrets = [],
    ) {
        if ($maxResponseBytes < 1) {
            throw new InvalidArgumentException('The response size cap must be at least one byte.');
        }
        $kept = array_values(array_unique(array_filter(
            $secrets,
            static fn (string $secret): bool => strlen($secret) >= self::MIN_SECRET_LENGTH,
        )));
        $this->secrets = static fn (): array => $kept;
    }

    /**
     * A client with the same settings that also redacts the given secrets.
     *
     * @param  list<string>  $secrets
     */
    public function withSecrets(#[SensitiveParameter] array $secrets): self
    {
        return new self(
            $this->client,
            $this->requests,
            $this->streams,
            $this->maxResponseBytes,
            [...($this->secrets)(), ...$secrets],
        );
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['maxResponseBytes' => (string) $this->maxResponseBytes, 'secrets' => Redactor::REDACTED];
    }

    /**
     * Sends the request and returns the response with its body fully read, whatever its status.
     *
     * @param  array<string, string|list<string>>  $headers
     * @param  int|null  $maxBytes  a size cap for this response; it can only lower the client's cap
     *
     * @throws OutcomeUnknown when a request other than GET, HEAD or OPTIONS failed without a
     *                        response or its response body could not be read
     * @throws TransportUnavailable when a GET, HEAD or OPTIONS request failed without a response
     *                              or its response body could not be read
     * @throws UnexpectedResponse when the response body exceeds the size cap
     */
    public function send(string $method, string $url, #[SensitiveParameter] array $headers = [], ?string $body = null, ?int $maxBytes = null): ResponseInterface
    {
        if ($maxBytes !== null && $maxBytes < 1) {
            throw new InvalidArgumentException('The response size cap must be at least one byte.');
        }
        $cap = min($maxBytes ?? $this->maxResponseBytes, $this->maxResponseBytes);

        $request = $this->requests->createRequest($method, $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request = $request->withBody($this->streams->createStream($body));
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw $this->failure($request, $exception, 'failed without a response');
        }

        try {
            $contents = $this->read($request, $response, $cap);
        } catch (UnexpectedResponse $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            throw $this->failure(
                $request,
                $exception,
                sprintf('was answered with HTTP %d but its body could not be read', $response->getStatusCode()),
            );
        }

        return $response->withBody($this->streams->createStream($contents));
    }

    /**
     * @param  string  $what  what went wrong, phrased to follow "The <method> request to <url>"
     */
    private function failure(RequestInterface $request, Throwable $exception, string $what): OutcomeUnknown|TransportUnavailable
    {
        $method = strtoupper($request->getMethod());
        $target = (string) $request->getUri()->withUserInfo('')->withQuery('')->withFragment('');
        $reason = $this->redact($request, $exception->getMessage());

        if (in_array($method, self::SAFE_METHODS, true)) {
            return new TransportUnavailable(
                sprintf('The %s request to %s %s: %s', $method, $target, $what, $reason),
                $exception,
            );
        }

        return new OutcomeUnknown(
            sprintf(
                'The %s request to %s %s, so the server may or may not have processed it: %s. Check the document list before resending to avoid a duplicate.',
                $method,
                $target,
                $what,
                rtrim($reason, '.'),
            ),
            $exception,
        );
    }

    /**
     * Removes the request's credentials and the configured secrets from text.
     */
    private function redact(RequestInterface $request, string $text): string
    {
        $secrets = $this->secretVariants();
        foreach (self::SECRET_HEADERS as $name) {
            foreach ($request->getHeader($name) as $value) {
                $secrets[] = $value;
                $parts = preg_split('/\s+/', trim($value), 2);
                if ($parts !== false && count($parts) === 2) {
                    $secrets[] = $parts[1];
                }
            }
        }
        $secrets[] = $request->getUri()->getUserInfo();
        $secrets[] = $request->getUri()->getQuery();

        // Longest first, so that a whole header value is replaced before its credential part.
        return Redactor::replace($text, $secrets);
    }

    /**
     * Each configured secret raw, percent-encoded and Base64-encoded.
     *
     * @return list<string>
     */
    private function secretVariants(): array
    {
        return Redactor::variants(($this->secrets)());
    }

    /**
     * The longest text redact() may replace, so that an excerpt cut from enough text past the
     * limit has every secret that starts inside it replaced whole.
     */
    private function longestSecret(RequestInterface $request): int
    {
        $longest = strlen($request->getUri()->getUserInfo()) + strlen($request->getUri()->getQuery());
        foreach ($this->secretVariants() as $secret) {
            $longest = max($longest, strlen($secret));
        }
        foreach (self::SECRET_HEADERS as $name) {
            $longest = max($longest, strlen($request->getHeaderLine($name)));
        }

        return $longest;
    }

    /**
     * @throws UnexpectedResponse when the body exceeds the size cap
     * @throws RuntimeException when the stream fails or stalls before end of file while the body is read
     */
    private function read(RequestInterface $request, ResponseInterface $response, int $cap): string
    {
        $stream = $response->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $declared = $response->getHeaderLine('Content-Length');
        if (ctype_digit($declared) && (int) $declared > $cap) {
            throw $this->tooLarge($request, $response, $cap, self::readUpTo($stream, UnexpectedResponse::EXCERPT_BYTES + $this->longestSecret($request)));
        }

        $body = '';
        $emptyRead = false;
        while (! $stream->eof()) {
            if ($emptyRead) {
                // A read returned nothing and the stream is still not at end of file. Returning
                // what was read so far would pass a truncated body off as complete, and reading
                // on could loop forever.
                throw new RuntimeException(sprintf('The response body stalled after %d bytes without reaching end of file.', strlen($body)));
            }
            $chunk = $stream->read(self::CHUNK_BYTES);
            // Socket streams may report end of file only after a read that returns nothing, so
            // an empty read is a stall only when the next end-of-file check still says no.
            $emptyRead = $chunk === '';
            $body .= $chunk;
            if (strlen($body) > $cap) {
                throw $this->tooLarge($request, $response, $cap, $body);
            }
        }

        return $body;
    }

    /**
     * Up to $bytes bytes from the stream, read until that many have arrived, the stream ends or a
     * read fails or returns nothing. A single read may return fewer bytes than asked for, and a
     * secret cut by such a short read could not be redacted.
     */
    private static function readUpTo(StreamInterface $stream, int $bytes): string
    {
        $data = '';
        try {
            while (strlen($data) < $bytes && ! $stream->eof()) {
                $chunk = $stream->read($bytes - strlen($data));
                if ($chunk === '') {
                    break;
                }
                $data .= $chunk;
            }
        } catch (RuntimeException) {
            // The body is refused for its size anyway; the excerpt keeps what was read.
        }

        return $data;
    }

    /**
     * @param  string  $start  the start of the body, long enough to hold the excerpt and any secret
     *                         that starts inside it
     */
    private function tooLarge(RequestInterface $request, ResponseInterface $response, int $cap, string $start): UnexpectedResponse
    {
        $start = substr($start, 0, UnexpectedResponse::EXCERPT_BYTES + $this->longestSecret($request));

        return UnexpectedResponse::forResponse(
            $response,
            sprintf('The response body exceeds the limit of %d bytes', $cap),
            $this->redact($request, $start),
        );
    }
}
