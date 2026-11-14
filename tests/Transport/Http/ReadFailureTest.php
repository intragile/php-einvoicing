<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Http;

use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\ReadFailure;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReadFailureTest extends TestCase
{
    private const KEY = 'test_FAKEKEY000000';

    /**
     * @return iterable<string, array{int, class-string, string}>
     */
    public static function statuses(): iterable
    {
        yield '401' => [401, AuthenticationFailed::class, 'Reading X failed: Acme refused the API key or its permission (HTTP 401).'];
        yield '403' => [403, AuthenticationFailed::class, 'Reading X failed: Acme refused the API key or its permission (HTTP 403).'];
        yield '404' => [404, UnexpectedResponse::class, 'Reading X failed: Acme does not know it (HTTP 404).'];
        yield '429' => [429, RateLimited::class, 'Reading X failed: Acme rate limit reached (HTTP 429).'];
        yield '500' => [500, TransportUnavailable::class, 'Reading X failed: Acme is unavailable (HTTP 500).'];
        yield '599' => [599, TransportUnavailable::class, 'Reading X failed: Acme is unavailable (HTTP 599).'];
        yield '418' => [418, UnexpectedResponse::class, 'Reading X failed with an unexpected status from Acme (HTTP 418).'];
        yield '600' => [600, UnexpectedResponse::class, 'Reading X failed with an unexpected status from Acme (HTTP 600).'];
    }

    /**
     * @param  class-string  $expected
     */
    #[DataProvider('statuses')]
    public function test_status_mapping(int $status, string $expected, string $message): void
    {
        $exception = ReadFailure::of(new Response($status, [], 'body '.self::KEY), 'Acme', 'Reading X', new Redactor([self::KEY]));

        self::assertInstanceOf($expected, $exception);
        self::assertSame($message, $exception->getMessage());
        if ($exception instanceof UnexpectedResponse) {
            self::assertSame('body [redacted]', $exception->bodyExcerpt);
            self::assertSame($status, $exception->status);
        }
    }

    /**
     * @return iterable<string, array{array<string, string>, ?int}>
     */
    public static function retryAfterHeaders(): iterable
    {
        yield 'seconds' => [['Retry-After' => '30'], 30];
        yield 'padded' => [['Retry-After' => ' 5 '], 5];
        yield 'zero' => [['Retry-After' => '0'], 0];
        yield 'date' => [['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'], null];
        yield 'negative' => [['Retry-After' => '-1'], null];
        yield 'missing' => [[], null];
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[DataProvider('retryAfterHeaders')]
    public function test_retry_after(array $headers, ?int $expected): void
    {
        $response = new Response(429, $headers);

        self::assertSame($expected, ReadFailure::retryAfter($response));
        $exception = ReadFailure::of($response, 'Acme', 'Reading X', new Redactor([]));
        self::assertInstanceOf(RateLimited::class, $exception);
        self::assertSame($expected, $exception->retryAfterSeconds);
    }
}
