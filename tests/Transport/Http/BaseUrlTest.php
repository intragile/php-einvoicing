<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Http;

use IntrAgile\EInvoicing\Transport\Http\BaseUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BaseUrlTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validUrls(): iterable
    {
        yield 'host only' => ['https://api.example', 'https://api.example'];
        yield 'trailing slashes trimmed' => ['https://api.example//', 'https://api.example'];
        yield 'path kept' => ['https://api.example/v1/', 'https://api.example/v1'];
        yield 'port kept' => ['https://api.example:8443', 'https://api.example:8443'];
        yield 'uppercase scheme' => ['HTTPS://api.example', 'HTTPS://api.example'];
    }

    #[DataProvider('validUrls')]
    public function test_valid_url_is_normalised(string $url, string $expected): void
    {
        self::assertSame($expected, BaseUrl::normalize($url, 'Example base URL'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'http' => ['http://api.example', 'http://api.example'];
        yield 'no scheme' => ['api.example', 'api.example'];
        yield 'no host' => ['https:///path', 'https:///path'];
        yield 'empty' => ['', ''];
        yield 'query' => ['https://api.example?x=1', 'https://api.example?x=1'];
        yield 'fragment' => ['https://api.example#top', 'https://api.example#top'];
        yield 'user info masked' => ['https://user:pa55word@api.example', 'https://[redacted]@api.example'];
        yield 'user only masked' => ['https://user@api.example', 'https://[redacted]@api.example'];
    }

    #[DataProvider('invalidUrls')]
    public function test_invalid_url_is_refused_with_the_label(string $url, string $shown): void
    {
        try {
            BaseUrl::normalize($url, 'Example base URL');
            self::fail('No exception was thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                sprintf('The Example base URL "%s" must be an https:// URL without user info, query or fragment.', $shown),
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('pa55word', $exception->getMessage());
        }
    }
}
