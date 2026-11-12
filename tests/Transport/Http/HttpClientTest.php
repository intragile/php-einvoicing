<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Http;

use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\JsonResponse;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class HttpClientTest extends TestCase
{
    private const AUTHORIZATION = 'Basic a2V5LWlkOnRvcC1zZWNyZXQ=';

    public function test_sends_method_url_headers_and_body(): void
    {
        $recorder = new RecordingHttpClient(new Response(201, [], '{"ok":true}'));

        $response = self::client($recorder)->send('POST', 'https://ap.example/api/send', ['Content-Type' => 'application/json'], '{"a":1}');

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('{"ok":true}', (string) $response->getBody());
        $request = $recorder->requests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://ap.example/api/send', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('{"a":1}', (string) $request->getBody());
    }

    /**
     * @return iterable<string, array{string, class-string<TransportException>, bool, string}>
     */
    public static function failures(): iterable
    {
        foreach (['network', 'request'] as $kind) {
            foreach (['POST', 'PUT', 'DELETE'] as $method) {
                yield "$kind exception on $method" => [$method, OutcomeUnknown::class, false, $kind];
            }
            yield "$kind exception on GET" => ['GET', TransportUnavailable::class, true, $kind];
        }
    }

    /**
     * @param  class-string<TransportException>  $expected
     */
    #[DataProvider('failures')]
    public function test_client_exceptions_are_mapped_by_method(string $method, string $expected, bool $retryable, string $kind): void
    {
        $request = new Request($method, 'https://ap.example/api/documents');
        $failure = $kind === 'network'
            ? self::networkException($request, 'Operation timed out after 30001 milliseconds')
            : self::requestException($request, 'Malformed request');

        $exception = self::capture(fn () => self::client(new RecordingHttpClient($failure))
            ->send($method, 'https://ap.example/api/documents', ['Authorization' => self::AUTHORIZATION]));

        self::assertInstanceOf($expected, $exception);
        self::assertSame($retryable, $exception->isRetryable());
        self::assertSame($failure, $exception->getPrevious());
        self::assertStringContainsString($method, $exception->getMessage());
        self::assertStringContainsString('https://ap.example/api/documents', $exception->getMessage());
    }

    public function test_outcome_unknown_tells_the_caller_to_check_before_resending(): void
    {
        $request = new Request('POST', 'https://ap.example/api/send');

        $exception = self::capture(fn () => self::client(new RecordingHttpClient(self::networkException($request, 'timeout')))
            ->send('POST', 'https://ap.example/api/send'));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('check', strtolower($exception->getMessage()));
        self::assertStringContainsString('before resending', $exception->getMessage());
    }

    public function test_authorization_never_appears_in_exception_messages(): void
    {
        $leaky = 'Request with Authorization: '.self::AUTHORIZATION.' failed';
        $secretPart = 'a2V5LWlkOnRvcC1zZWNyZXQ=';
        $headers = ['Authorization' => self::AUTHORIZATION];
        $huge = str_repeat('x', 1_001);

        $exceptions = [];
        foreach (['POST', 'GET'] as $method) {
            $request = new Request($method, 'https://user:pw@ap.example/api?token=t0ps3cret');
            foreach ([self::networkException($request, $leaky), self::requestException($request, $leaky)] as $failure) {
                $exceptions[] = self::capture(fn () => self::client(new RecordingHttpClient($failure))
                    ->send($method, 'https://user:pw@ap.example/api?token=t0ps3cret', $headers));
            }
        }
        $exceptions[] = self::capture(fn () => (new HttpClient(new RecordingHttpClient(new Response(200, [], $huge)), new Psr17Factory, new Psr17Factory, 1_000))
            ->send('GET', 'https://ap.example/api', $headers));
        $exceptions[] = self::capture(fn () => JsonResponse::decode(
            self::client(new RecordingHttpClient(new Response(200, [], 'not json')))->send('GET', 'https://ap.example/api', $headers),
        ));

        self::assertCount(6, $exceptions);
        foreach ($exceptions as $exception) {
            self::assertInstanceOf(TransportException::class, $exception);
            self::assertStringNotContainsString($secretPart, $exception->getMessage());
            self::assertStringNotContainsString('pw@', $exception->getMessage());
            self::assertStringNotContainsString('t0ps3cret', $exception->getMessage());
        }
    }

    public function test_body_over_the_cap_is_rejected(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::largeBody(25_000_000)));

        $exception = self::capture(fn () => self::client($recorder)->send('GET', 'https://ap.example/api/documents'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertFalse($exception->isRetryable());
        self::assertStringContainsString('20000000', $exception->getMessage());
        self::assertSame(200, $exception->status);
        self::assertLessThanOrEqual(500, strlen($exception->bodyExcerpt));
    }

    public function test_body_over_the_cap_without_content_length_is_rejected(): void
    {
        $response = (new Response(200, [], str_repeat('a', 2_001)))->withoutHeader('Content-Length');

        $exception = self::capture(fn () => (new HttpClient(new RecordingHttpClient($response), new Psr17Factory, new Psr17Factory, 2_000))
            ->send('GET', 'https://ap.example/api'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
    }

    /**
     * @return iterable<string, array{string, class-string<TransportException>, bool}>
     */
    public static function readFailures(): iterable
    {
        yield 'POST' => ['POST', OutcomeUnknown::class, false];
        yield 'GET' => ['GET', TransportUnavailable::class, true];
    }

    /**
     * @param  class-string<TransportException>  $expected
     */
    #[DataProvider('readFailures')]
    public function test_body_read_failure_is_mapped_by_method(string $method, string $expected, bool $retryable): void
    {
        $url = 'https://user:pw@ap.example/api/send?token=t0ps3cret';
        $failure = new RuntimeException('Connection reset while reading; Authorization: '.self::AUTHORIZATION.' at '.$url);
        $response = (new Response(200))->withBody(self::brokenStream($failure));

        $exception = self::capture(fn () => self::client(new RecordingHttpClient($response))
            ->send($method, $url, ['Authorization' => self::AUTHORIZATION]));

        self::assertInstanceOf($expected, $exception);
        self::assertSame($retryable, $exception->isRetryable());
        self::assertSame($failure, $exception->getPrevious());
        self::assertStringContainsString($method, $exception->getMessage());
        self::assertStringContainsString('HTTP 200', $exception->getMessage());
        self::assertStringNotContainsString('a2V5LWlkOnRvcC1zZWNyZXQ=', $exception->getMessage());
        self::assertStringNotContainsString('pw@', $exception->getMessage());
        self::assertStringNotContainsString('t0ps3cret', $exception->getMessage());
    }

    public function test_stream_that_stalls_without_eof_ends_the_read(): void
    {
        $response = (new Response(200))->withBody(self::brokenStream(null));

        $result = self::client(new RecordingHttpClient($response))->send('GET', 'https://ap.example/api');

        self::assertSame('', (string) $result->getBody());
    }

    public function test_declared_length_over_the_cap_is_rejected_before_reading(): void
    {
        $response = new Response(200, ['Content-Length' => '99999999'], 'short');

        $exception = self::capture(fn () => self::client(new RecordingHttpClient($response))->send('GET', 'https://ap.example/api'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertSame('short', $exception->bodyExcerpt);
    }

    public function test_body_at_the_cap_is_accepted(): void
    {
        $response = (new HttpClient(new RecordingHttpClient(new Response(200, [], str_repeat('a', 2_000))), new Psr17Factory, new Psr17Factory, 2_000))
            ->send('GET', 'https://ap.example/api');

        self::assertSame(2_000, strlen((string) $response->getBody()));
    }

    public function test_json_response_decodes_objects_and_lists(): void
    {
        self::assertSame(['a' => 1, 'b' => [true]], JsonResponse::decode(new Response(200, [], '{"a":1,"b":[true]}')));
        self::assertSame([1, 2], JsonResponse::decode(new Response(200, [], '[1,2]')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidJson(): iterable
    {
        yield 'malformed' => ['{"a":'];
        yield 'empty' => [''];
        yield 'scalar' => ['42'];
    }

    #[DataProvider('invalidJson')]
    public function test_json_response_rejects_invalid_json(string $body): void
    {
        $exception = self::capture(fn () => JsonResponse::decode(new Response(502, [], $body)));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertSame(502, $exception->status);
    }

    private static function client(RecordingHttpClient $recorder): HttpClient
    {
        $factory = new Psr17Factory;

        return new HttpClient($recorder, $factory, $factory);
    }

    private static function capture(callable $action): \Throwable
    {
        try {
            $action();
        } catch (\Throwable $exception) {
            return $exception;
        }
        self::fail('No exception was thrown.');
    }

    /**
     * A body of the given size that php://temp keeps on disk, so the test itself stays small.
     */
    /**
     * A non-seekable stream that never reaches end of file: read() throws $failure, or returns
     * an empty string when $failure is null.
     */
    private static function brokenStream(?RuntimeException $failure): StreamInterface
    {
        return new class($failure) implements StreamInterface
        {
            public function __construct(private readonly ?RuntimeException $failure) {}

            public function __toString(): string
            {
                return '';
            }

            public function close(): void {}

            public function detach()
            {
                return null;
            }

            public function getSize(): ?int
            {
                return null;
            }

            public function tell(): int
            {
                return 0;
            }

            public function eof(): bool
            {
                return false;
            }

            public function isSeekable(): bool
            {
                return false;
            }

            public function seek(int $offset, int $whence = SEEK_SET): void
            {
                throw new RuntimeException('Not seekable.');
            }

            public function rewind(): void
            {
                throw new RuntimeException('Not seekable.');
            }

            public function isWritable(): bool
            {
                return false;
            }

            public function write(string $string): int
            {
                throw new RuntimeException('Not writable.');
            }

            public function isReadable(): bool
            {
                return true;
            }

            public function read(int $length): string
            {
                if ($this->failure !== null) {
                    throw $this->failure;
                }

                return '';
            }

            public function getContents(): string
            {
                return $this->read(1);
            }

            public function getMetadata(?string $key = null): mixed
            {
                return $key === null ? [] : null;
            }
        };
    }

    private static function largeBody(int $bytes): StreamInterface
    {
        $resource = fopen('php://temp/maxmemory:1048576', 'w+b');
        self::assertIsResource($resource);
        $chunk = str_repeat('a', 1_000_000);
        for ($written = 0; $written < $bytes; $written += strlen($chunk)) {
            fwrite($resource, $chunk);
        }
        rewind($resource);

        return Stream::create($resource);
    }

    private static function networkException(RequestInterface $request, string $message): NetworkExceptionInterface
    {
        return new class($request, $message) extends RuntimeException implements NetworkExceptionInterface
        {
            public function __construct(private readonly RequestInterface $request, string $message)
            {
                parent::__construct($message);
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        };
    }

    private static function requestException(RequestInterface $request, string $message): RequestExceptionInterface
    {
        return new class($request, $message) extends RuntimeException implements RequestExceptionInterface
        {
            public function __construct(private readonly RequestInterface $request, string $message)
            {
                parent::__construct($message);
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        };
    }
}
