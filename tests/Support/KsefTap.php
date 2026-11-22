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
 * every KSeF host except the three of TE (the API, the QR verification pages and Latarnia), keeps
 * the answers of the TE API in memory so a test can read observed shapes, counts the requests to
 * each host, and can rewrite an answer before the library sees it.
 *
 * The kept bodies include access and refresh tokens; a test reads only the fields it reports and
 * never prints a whole body.
 */
final class KsefTap implements ClientInterface
{
    /**
     * The only *.ksef.mf.gov.pl hosts a live test may reach: the TE API, the TE QR verification
     * pages and the TE Latarnia. DEMO and PROD (api-demo, qr-demo, api.ksef, qr.ksef,
     * api-latarnia.ksef, ...) are refused.
     */
    public const ALLOWED_KSEF_HOSTS = [KsefLive::HOST, KsefLive::QR_HOST, KsefLive::LATARNIA_HOST];

    /** @var list<array{method: string, path: string, status: int, body: string}> */
    private array $answers = [];

    /** @var array<string, int> requests sent, by host */
    private array $hosts = [];

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
        $refusal = self::refusal($uri->getScheme(), $uri->getHost());
        if ($refusal !== null) {
            throw new LogicException($refusal);
        }
        $host = strtolower($uri->getHost());
        $this->hosts[$host] = ($this->hosts[$host] ?? 0) + 1;

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
     * Why a request to the scheme and host is refused, or null when it may be sent: every
     * *.ksef.mf.gov.pl host outside ALLOWED_KSEF_HOSTS is refused, and so is every scheme but
     * https. Other hosts (the storage URLs the TE API returns) are allowed.
     */
    public static function refusal(string $scheme, string $host): ?string
    {
        $host = strtolower(rtrim($host, '.'));
        if (str_ends_with($host, 'ksef.mf.gov.pl') && ! in_array($host, self::ALLOWED_KSEF_HOSTS, true)) {
            return sprintf('The KSeF live tests reach only the TE environment; a request to %s was refused.', $host);
        }
        if (strtolower($scheme) !== 'https') {
            return 'The KSeF live tests use https only.';
        }

        return null;
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

    /**
     * The number of requests sent to the host so far.
     */
    public function countFor(string $host): int
    {
        return $this->hosts[strtolower($host)] ?? 0;
    }
}
