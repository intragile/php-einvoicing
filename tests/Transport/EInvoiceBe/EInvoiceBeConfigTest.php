<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\EInvoiceBe;

use IntrAgile\EInvoicing\Transport\EInvoiceBe\EInvoiceBeConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class EInvoiceBeConfigTest extends TestCase
{
    private const KEY = 'test_FAKEKEY000000';

    public function test_defaults(): void
    {
        $config = new EInvoiceBeConfig('  '.self::KEY."\n");

        self::assertSame('https://api.e-invoice.be', EInvoiceBeConfig::DEFAULT_BASE_URL);
        self::assertSame('https://api.e-invoice.be', $config->baseUrl);
        self::assertSame(7, $config->inboundLookbackDays);
        self::assertSame(10, $config->inboundMaxPages);
        self::assertSame([
            'Authorization' => 'Bearer '.self::KEY,
            'Accept' => 'application/json',
        ], $config->headers());
        self::assertSame([self::KEY], $config->secrets());
        self::assertSame('Key [redacted]', $config->redactor()->redact('Key '.self::KEY));
    }

    public function test_custom_values_and_trailing_slash(): void
    {
        $config = new EInvoiceBeConfig(self::KEY, 'https://api-dev.e-invoice.be/', 30, 3);

        self::assertSame('https://api-dev.e-invoice.be', $config->baseUrl);
        self::assertSame(30, $config->inboundLookbackDays);
        self::assertSame(3, $config->inboundMaxPages);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function limits(): iterable
    {
        yield 'smallest' => [1, 1];
        yield 'largest' => [365, 100];
    }

    #[DataProvider('limits')]
    public function test_limits_are_accepted(int $lookbackDays, int $maxPages): void
    {
        $config = new EInvoiceBeConfig(self::KEY, inboundLookbackDays: $lookbackDays, inboundMaxPages: $maxPages);

        self::assertSame($lookbackDays, $config->inboundLookbackDays);
        self::assertSame($maxPages, $config->inboundMaxPages);
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function badLimits(): iterable
    {
        yield 'look-back zero' => [0, 10, 'look-back'];
        yield 'look-back negative' => [-1, 10, 'look-back'];
        yield 'look-back too long' => [366, 10, 'look-back'];
        yield 'pages zero' => [7, 0, 'pages'];
        yield 'pages too many' => [7, 101, 'pages'];
    }

    #[DataProvider('badLimits')]
    public function test_bad_limits_are_refused(int $lookbackDays, int $maxPages, string $fragment): void
    {
        $exception = self::capture(fn () => new EInvoiceBeConfig(self::KEY, inboundLookbackDays: $lookbackDays, inboundMaxPages: $maxPages));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString($fragment, $exception->getMessage());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badBaseUrls(): iterable
    {
        yield 'http' => ['http://api.e-invoice.be'];
        yield 'user info' => ['https://user:'.self::KEY.'@api.e-invoice.be'];
        yield 'query' => ['https://api.e-invoice.be?api_key=x'];
        yield 'fragment' => ['https://api.e-invoice.be#x'];
        yield 'no host' => ['https://'];
    }

    #[DataProvider('badBaseUrls')]
    public function test_bad_base_url_is_refused(string $url): void
    {
        $exception = self::capture(fn () => new EInvoiceBeConfig(self::KEY, $url));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('e-invoice.be base URL', $exception->getMessage());
        self::assertStringNotContainsString(self::KEY, $exception->getMessage());
    }

    public function test_short_key_is_refused_without_quoting_it(): void
    {
        $exception = self::capture(fn () => new EInvoiceBeConfig(' short12 '));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('e-invoice.be API key', $exception->getMessage());
        self::assertStringNotContainsString('short12', $exception->getMessage());
    }

    public function test_config_dump_hides_secrets(): void
    {
        $config = new EInvoiceBeConfig(self::KEY, inboundLookbackDays: 21);

        foreach ([
            print_r($config, true),
            var_export($config, true),
            json_encode($config, JSON_THROW_ON_ERROR),
            serialize($config->__debugInfo()),
            print_r($config->redactor(), true),
        ] as $output) {
            self::assertStringNotContainsString(self::KEY, (string) $output);
            self::assertStringNotContainsString(base64_encode(self::KEY), (string) $output);
        }
        self::assertStringContainsString('21', print_r($config, true));
        self::assertStringContainsString('api.e-invoice.be', print_r($config, true));
    }

    private static function capture(callable $action): Throwable
    {
        try {
            $action();
        } catch (Throwable $exception) {
            return $exception;
        }
        self::fail('No exception was thrown.');
    }
}
