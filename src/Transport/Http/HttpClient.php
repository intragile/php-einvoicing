<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Http;

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

/**
 * Sends HTTP requests through any PSR-18 client and turns client failures into transport
 * exceptions that tell whether a retry is safe.
 *
 * PSR-18 cannot tell a request that never left from one that was sent but not answered (a
 * timeout is a network exception either way), so a failed request that changes state is reported
 * as OutcomeUnknown and only a failed safe request (GET, HEAD, OPTIONS) as TransportUnavailable.
 * Response bodies are read into memory up to a size cap. Credentials in headers, user info and
 * query strings are kept out of exception messages.
 */
final class HttpClient
{
    /** Methods that never change state on the server, so a failed request may be repeated. */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** Headers whose values are credentials. */
    private const SECRET_HEADERS = ['authorization', 'proxy-authorization'];

    private const CHUNK_BYTES = 1_048_576;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly int $maxResponseBytes = 20_000_000,
    ) {
        if ($maxResponseBytes < 1) {
            throw new InvalidArgumentException('The response size cap must be at least one byte.');
        }
    }

    /**
     * Sends the request and returns the response with its body fully read, whatever its status.
     *
     * @param  array<string, string|list<string>>  $headers
     *
     * @throws OutcomeUnknown when a request other than GET, HEAD or OPTIONS failed without a response
     * @throws TransportUnavailable when a GET, HEAD or OPTIONS request failed without a response
     * @throws UnexpectedResponse when the response body exceeds the size cap
     */
    public function send(string $method, string $url, array $headers = [], ?string $body = null): ResponseInterface
    {
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
            throw $this->failure($request, $exception);
        }

        return $response->withBody($this->streams->createStream($this->read($response)));
    }

    private function failure(RequestInterface $request, ClientExceptionInterface $exception): OutcomeUnknown|TransportUnavailable
    {
        $method = strtoupper($request->getMethod());
        $target = (string) $request->getUri()->withUserInfo('')->withQuery('')->withFragment('');
        $reason = $this->redact($request, $exception->getMessage());

        if (in_array($method, self::SAFE_METHODS, true)) {
            return new TransportUnavailable(
                sprintf('The %s request to %s failed without a response: %s', $method, $target, $reason),
                $exception,
            );
        }

        return new OutcomeUnknown(
            sprintf(
                'The %s request to %s failed without a response, so the server may or may not have processed it: %s. Check the document list before resending to avoid a duplicate.',
                $method,
                $target,
                rtrim($reason, '.'),
            ),
            $exception,
        );
    }

    /**
     * Removes the request's credentials from a client exception message.
     */
    private function redact(RequestInterface $request, string $message): string
    {
        $secrets = [];
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
        $secrets = array_filter($secrets, static fn (string $secret): bool => trim($secret) !== '');
        // Longest first, so that a whole header value is replaced before its credential part.
        usort($secrets, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return str_replace($secrets, '[redacted]', $message);
    }

    /**
     * @throws UnexpectedResponse when the body exceeds the size cap
     */
    private function read(ResponseInterface $response): string
    {
        $stream = $response->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $declared = $response->getHeaderLine('Content-Length');
        if (ctype_digit($declared) && (int) $declared > $this->maxResponseBytes) {
            throw $this->tooLarge($response, $stream->read(UnexpectedResponse::EXCERPT_BYTES));
        }

        $body = '';
        while (! $stream->eof()) {
            $body .= $stream->read(self::CHUNK_BYTES);
            if (strlen($body) > $this->maxResponseBytes) {
                throw $this->tooLarge($response, $body);
            }
        }

        return $body;
    }

    private function tooLarge(ResponseInterface $response, string $start): UnexpectedResponse
    {
        return UnexpectedResponse::forResponse(
            $response,
            sprintf('The response body exceeds the limit of %d bytes', $this->maxResponseBytes),
            $start,
        );
    }
}
