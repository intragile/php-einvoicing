<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use Closure;
use LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The PSR-18 client of the KSeF live tests: a StreamHttpClient with a fixed timeout that refuses
 * every KSeF host except TE, keeps the answers of the TE API in memory so a test can read
 * observed shapes, and can rewrite an answer before the library sees it.
 *
 * The kept bodies include access and refresh tokens; a test reads only the fields it reports and
 * never prints a whole body.
 */
final class KsefTap implements ClientInterface
{
    /** @var list<array{method: string, path: string, status: int, body: string}> */
    private array $answers = [];

    private readonly StreamHttpClient $client;

    /**
     * @param  (Closure(RequestInterface, ResponseInterface): ResponseInterface)|null  $rewrite
     */
    public function __construct(private ?Closure $rewrite = null, float $timeoutSeconds = 20.0)
    {
        $this->client = new StreamHttpClient($timeoutSeconds);
    }

    /**
     * @param  (Closure(RequestInterface, ResponseInterface): ResponseInterface)|null  $rewrite
     */
    public function rewriteWith(?Closure $rewrite): void
    {
        $this->rewrite = $rewrite;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $uri = $request->getUri();
        $host = strtolower($uri->getHost());
        // DEMO and PROD (api-demo.ksef.mf.gov.pl, api.ksef.mf.gov.pl, ...) are never reached.
        if (str_ends_with($host, 'ksef.mf.gov.pl') && $host !== KsefLive::HOST) {
            throw new LogicException('The KSeF live tests reach only the TE environment; a request to another KSeF host was refused.');
        }
        if ($uri->getScheme() !== 'https') {
            throw new LogicException('The KSeF live tests use https only.');
        }

        $response = $this->client->sendRequest($request);
        if ($this->rewrite !== null) {
            $response = ($this->rewrite)($request, $response);
        }
        if ($host === KsefLive::HOST) {
            $body = (string) $response->getBody();
            $response->getBody()->rewind();
            $this->answers[] = [
                'method' => $request->getMethod(),
                'path' => substr($uri->getPath(), strlen('/v2')),
                'status' => $response->getStatusCode(),
                'body' => $body,
            ];
        }

        return $response;
    }

    /**
     * The last kept answer to a request with the method whose path (under /v2) matches the
     * pattern, or null.
     *
     * @return array{method: string, path: string, status: int, body: string}|null
     */
    public function last(string $method, string $pathPattern): ?array
    {
        for ($i = count($this->answers) - 1; $i >= 0; $i--) {
            $answer = $this->answers[$i];
            if ($answer['method'] === $method && preg_match($pathPattern, $answer['path']) === 1) {
                return $answer;
            }
        }

        return null;
    }

    /**
     * Every kept answer to a request with the method whose path (under /v2) matches the pattern,
     * in order.
     *
     * @return list<array{method: string, path: string, status: int, body: string}>
     */
    public function all(string $method, string $pathPattern): array
    {
        return array_values(array_filter(
            $this->answers,
            static fn (array $answer): bool => $answer['method'] === $method && preg_match($pathPattern, $answer['path']) === 1,
        ));
    }

    /**
     * The decoded JSON body of last(), or null when there is none or it is not JSON.
     *
     * @return array<mixed>|null
     */
    public function lastJson(string $method, string $pathPattern): ?array
    {
        $answer = $this->last($method, $pathPattern);
        if ($answer === null) {
            return null;
        }
        $data = json_decode($answer['body'], true);

        return is_array($data) ? $data : null;
    }

    /**
     * The number of requests sent to the TE API so far.
     */
    public function count(): int
    {
        return count($this->answers);
    }
}
