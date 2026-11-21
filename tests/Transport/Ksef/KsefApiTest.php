<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef;

use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use InvalidArgumentException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class KsefApiTest extends TestCase
{
    use KsefFixtures;

    private const BASE = 'https://api-test.ksef.mf.gov.pl/v2';

    public function test_json_request_sends_the_headers_and_body(): void
    {
        $recorder = new RecordingHttpClient(self::json(201, ['referenceNumber' => 'R-1']));

        $result = $this->api($recorder)->json('POST', '/sessions/online', ['formCode' => ['value' => 'FA']], self::BEARER);

        self::assertSame(['referenceNumber' => 'R-1'], $result);
        $request = $recorder->requests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE.'/sessions/online', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('problem-details', $request->getHeaderLine('X-Error-Format'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('{"formCode":{"value":"FA"}}', (string) $request->getBody());
    }

    public function test_bearer_header_is_sent(): void
    {
        $recorder = new RecordingHttpClient(self::json(200, []), self::json(200, []));
        $api = $this->api($recorder);

        $api->json('GET', '/sessions', bearer: self::BEARER);
        $api->json('GET', '/security/public-key-certificates');

        self::assertSame('Bearer '.self::BEARER, $recorder->requests()[0]->getHeaderLine('Authorization'));
        self::assertFalse($recorder->requests()[1]->hasHeader('Authorization'));
        self::assertFalse($recorder->requests()[1]->hasHeader('Content-Type'));
    }

    public function test_empty_2xx_body_is_an_empty_array(): void
    {
        $recorder = new RecordingHttpClient(new Response(204), new Response(200, [], "  \n"));
        $api = $this->api($recorder);

        self::assertSame([], $api->json('POST', '/sessions/online/R-1/close', bearer: self::BEARER));
        self::assertSame([], $api->json('GET', '/x'));
    }

    public function test_non_json_2xx_body_is_unexpected(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], 'not json '.self::BEARER));

        $exception = self::capture(fn () => $this->api($recorder)->json('GET', '/x', bearer: self::BEARER));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertNoSecret($exception);
    }

    public function test_path_must_start_with_a_slash(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->api(new RecordingHttpClient)->json('GET', 'sessions');
    }

    public function test_error_answer_is_mapped(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(400, 'auth-xades-9105.json'));

        $exception = self::capture(fn () => $this->api($recorder)->raw('POST', '/auth/xades-signature', '<AuthTokenRequest/>', ['Content-Type' => 'application/xml']));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringStartsWith('The KSeF request POST /auth/xades-signature failed', $exception->getMessage());
    }

    public function test_raw_returns_the_response_and_sends_only_given_headers(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, ['Content-Type' => 'application/xml'], '<Faktura/>'));

        $response = $this->api($recorder)->raw('GET', '/invoices/ksef/x', null, ['Accept' => 'application/xml'], self::BEARER);

        self::assertSame('<Faktura/>', (string) $response->getBody());
        $request = $recorder->requests()[0];
        self::assertSame(self::BASE.'/invoices/ksef/x', (string) $request->getUri());
        self::assertSame('application/xml', $request->getHeaderLine('Accept'));
        self::assertSame('Bearer '.self::BEARER, $request->getHeaderLine('Authorization'));
        self::assertFalse($request->hasHeader('X-Error-Format'));
    }

    public function test_absolute_part_url_is_allowed_only_https(): void
    {
        $url = 'https://ksef-te-batch.blob.core.windows.net/parts/1?sv=2025-01-01&sig=c2lnbmF0dXJl';
        $recorder = new RecordingHttpClient(new Response(201));

        $this->api($recorder)->raw('PUT', $url, 'encrypted-part', ['Content-Type' => 'application/octet-stream']);

        $request = $recorder->requests()[0];
        self::assertSame($url, (string) $request->getUri());
        self::assertFalse($request->hasHeader('Authorization'));

        foreach (['http://ksef-te-batch.blob.core.windows.net/parts/1', 'ftp://host/x', 'https:///nohost', '//host/x'] as $bad) {
            $exception = self::capture(fn () => $this->api(new RecordingHttpClient)->raw('PUT', $bad, 'x'));
            self::assertInstanceOf(InvalidArgumentException::class, $exception, $bad);
        }
    }

    public function test_429_on_get_retries_within_budget(): void
    {
        $recorder = new RecordingHttpClient(
            self::fixture(429, 'too-many-requests-problem-details.json', ['Retry-After' => '10']),
            self::fixture(429, 'too-many-requests-problem-details.json', ['Retry-After' => '20']),
            self::fixture(429, 'too-many-requests-problem-details.json', ['Retry-After' => '25']),
            self::json(200, ['ok' => true]),
        );

        $result = $this->api($recorder)->json('GET', '/sessions', bearer: self::BEARER);

        self::assertSame(['ok' => true], $result);
        self::assertCount(4, $recorder->requests());
        self::assertSame([10_000, 20_000, 25_000], $this->sleeps);
        self::assertLessThanOrEqual(60_000, array_sum($this->sleeps));
    }

    public function test_429_on_get_gives_up_after_three_retries(): void
    {
        $recorder = new RecordingHttpClient(
            new Response(429, ['Retry-After' => '1']),
            new Response(429, ['Retry-After' => '1']),
            new Response(429, ['Retry-After' => '1']),
            new Response(429, ['Retry-After' => '2']),
        );

        $exception = self::capture(fn () => $this->api($recorder)->json('GET', '/sessions'));

        self::assertInstanceOf(RateLimited::class, $exception);
        self::assertSame(2, $exception->retryAfterSeconds);
        self::assertCount(4, $recorder->requests());
        self::assertSame([1000, 1000, 1000], $this->sleeps);
    }

    public function test_429_on_get_stops_when_the_budget_is_spent(): void
    {
        $recorder = new RecordingHttpClient(
            new Response(429, ['Retry-After' => '50']),
            new Response(429, ['Retry-After' => '50']),
            new Response(429, ['Retry-After' => '50']),
        );

        $exception = self::capture(fn () => $this->api($recorder)->raw('GET', '/invoices/ksef/x'));

        self::assertInstanceOf(RateLimited::class, $exception);
        self::assertSame(50, $exception->retryAfterSeconds);
        self::assertCount(3, $recorder->requests());
        self::assertSame([50_000, 10_000], $this->sleeps);
    }

    public function test_429_without_retry_after_waits_the_default(): void
    {
        $recorder = new RecordingHttpClient(
            new Response(429),
            new Response(429, ['Date' => 'Fri, 21 Nov 2025 09:00:00 GMT', 'Retry-After' => 'Fri, 21 Nov 2025 09:00:04 GMT']),
            self::json(200, []),
        );

        $this->api($recorder)->json('GET', '/sessions');

        self::assertSame([5000, 4000], $this->sleeps);
    }

    public function test_429_on_post_is_not_retried(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(429, 'too-many-requests-problem-details.json', ['Retry-After' => '30']));

        $exception = self::capture(fn () => $this->api($recorder)->json('POST', '/sessions/online', ['a' => 1], self::BEARER));

        self::assertInstanceOf(RateLimited::class, $exception);
        self::assertSame(30, $exception->retryAfterSeconds);
        self::assertCount(1, $recorder->requests());
        self::assertSame([], $this->sleeps);
    }

    public function test_503_on_get_is_unavailable_and_on_post_unknown(): void
    {
        $recorder = new RecordingHttpClient(new Response(503), new Response(503));
        $api = $this->api($recorder);

        self::assertInstanceOf(TransportUnavailable::class, self::capture(fn () => $api->json('GET', '/sessions')));
        self::assertInstanceOf(OutcomeUnknown::class, self::capture(fn () => $api->json('POST', '/sessions/online', [])));
        self::assertCount(2, $recorder->requests());
        self::assertSame([], $this->sleeps);
    }

    public function test_tokens_never_appear_in_exceptions(): void
    {
        $echo = 'Authorization: Bearer '.self::BEARER.' token='.self::KSEF_TOKEN.' enc='.rawurlencode(self::KSEF_TOKEN);
        $classic = json_encode(['exception' => ['exceptionDetailList' => [[
            'exceptionCode' => 21405,
            'exceptionDescription' => $echo,
            'details' => [$echo],
        ]]]], JSON_THROW_ON_ERROR);
        $network = new class($echo) extends RuntimeException implements NetworkExceptionInterface
        {
            public function getRequest(): RequestInterface
            {
                throw new RuntimeException('not needed');
            }
        };

        $responses = [
            new Response(400, [], $classic),
            new Response(400, [], $classic),
            new Response(401, [], $classic),
            new Response(403, [], $classic),
            new Response(404, [], $echo),
            new Response(418, [], $echo),
            new Response(503, [], $echo),
            new Response(200, [], $echo),
            $network,
            $network,
        ];
        $methods = ['POST', 'GET', 'GET', 'GET', 'GET', 'POST', 'POST', 'GET', 'POST', 'GET'];
        $recorder = new RecordingHttpClient(...$responses);
        $api = $this->api($recorder);

        foreach ($methods as $method) {
            $exception = self::capture(fn () => $api->json($method, '/x', $method === 'POST' ? ['t' => 1] : null, self::BEARER));
            self::assertNoSecret($exception);
        }
        // The last failure had no answer: its cause is kept, as a redacted copy naming the class.
        self::assertInstanceOf(TransportUnavailable::class, $exception);
        self::assertNotNull($exception->getPrevious());
        self::assertStringStartsWith('[RuntimeException@anonymous', $exception->getPrevious()->getMessage());
        self::assertCount(count($responses), $recorder->requests());
    }

    public function test_debug_info_hides_the_secrets(): void
    {
        $api = $this->api(new RecordingHttpClient);

        ob_start();
        var_dump($api);
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString(self::KSEF_TOKEN, $dump);
    }

    public function test_a_clone_is_independent(): void
    {
        $recorder = new RecordingHttpClient(self::json(200, ['a' => 1]), self::json(200, ['b' => 2]));
        $api = $this->api($recorder);
        $clone = clone $api;

        self::assertSame(['a' => 1], $api->json('GET', '/a'));
        self::assertSame(['b' => 2], $clone->json('GET', '/b'));
    }
}
