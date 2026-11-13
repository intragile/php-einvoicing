<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Validation\Peppol;

use IntrAgile\EInvoicing\Validation\Peppol\Downloader;
use IntrAgile\EInvoicing\Validation\Peppol\PeppolSources;
use IntrAgile\EInvoicing\Validation\Peppol\StreamOpener;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DownloaderTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function nonHttpsUrls(): array
    {
        return [
            'http' => ['http://raw.githubusercontent.com/OpenPEPPOL/peppol-bis-invoice-3/3.0.22/rules/sch/CEN-EN16931-UBL.sch'],
            'file' => ['file:///etc/hosts'],
            'plain path' => ['/etc/hosts'],
            'upper-case scheme' => ['HTTP://example.com/'],
        ];
    }

    #[DataProvider('nonHttpsUrls')]
    public function test_refuses_anything_but_https(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('https');

        (new Downloader)->get($url);
    }

    #[Group('network')]
    public function test_downloads_the_pinned_rule_files(): void
    {
        $downloader = new Downloader;

        foreach (PeppolSources::all() as $source) {
            self::assertSame($source['sha256'], hash('sha256', $downloader->get(PeppolSources::BASE_URL.$source['file'])), $source['file']);
        }
    }

    public function test_refuses_redirect_to_http(): void
    {
        $opener = new ScriptedStreamOpener([
            [['HTTP/1.1 302 Found', 'Location: http://example.com/rules.sch'], ''],
        ]);

        try {
            (new Downloader(opener: $opener))->get('https://example.com/start');
            self::fail('A redirect to http:// must be refused.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Only https:// URLs are downloaded; "http://example.com/rules.sch" is not one.', $exception->getMessage());
        }
        // The refused target is never opened.
        self::assertSame(['https://example.com/start'], $opener->urls);
    }

    public function test_stops_after_three_redirects(): void
    {
        $opener = new ScriptedStreamOpener(array_fill(0, 5, [['HTTP/1.1 302 Found', 'Location: https://example.com/next'], '']));

        try {
            (new Downloader(opener: $opener))->get('https://example.com/start');
            self::fail('A fourth redirect must be refused.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('more than 3 redirects', $exception->getMessage());
        }
        self::assertCount(4, $opener->urls);
    }

    public function test_relative_location_stays_on_same_host(): void
    {
        $opener = new ScriptedStreamOpener([
            [['HTTP/1.1 301 Moved Permanently', 'Location: /moved/rules.sch'], ''],
            [['HTTP/1.1 200 OK', 'Content-Type: text/plain'], 'payload'],
        ]);

        $body = (new Downloader(opener: $opener))->get('https://example.com:8443/start');

        self::assertSame('payload', $body);
        self::assertSame(['https://example.com:8443/start', 'https://example.com:8443/moved/rules.sch'], $opener->urls);
    }

    public function test_aborts_above_size_limit(): void
    {
        $opener = new ScriptedStreamOpener([
            [['HTTP/1.1 200 OK'], str_repeat('x', 1001)],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('larger than 1000 bytes');

        (new Downloader(maxBytes: 1000, opener: $opener))->get('https://example.com/big');
    }

    public function test_times_out_with_slow_body(): void
    {
        $opener = new ScriptedStreamOpener([
            [['HTTP/1.1 200 OK'], 'slow body'],
        ]);
        $now = 0.0;
        $clock = static function () use (&$now): float {
            $now += 31.0;

            return $now;
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('timed out');

        (new Downloader(opener: $opener, clock: $clock))->get('https://example.com/slow');
    }

    public function test_non_200_names_status(): void
    {
        $opener = new ScriptedStreamOpener([
            [['HTTP/1.1 404 Not Found'], 'missing'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP status 404');

        (new Downloader(opener: $opener))->get('https://example.com/missing');
    }
}

/**
 * Replays canned responses from in-memory streams and records the requested URLs.
 */
final class ScriptedStreamOpener implements StreamOpener
{
    /** @var list<string> */
    public array $urls = [];

    /**
     * @param  list<array{list<string>, string}>  $responses  headers and body of each response in order
     */
    public function __construct(private array $responses) {}

    public function open(string $url, int $timeoutSeconds): array|false
    {
        $this->urls[] = $url;
        $response = array_shift($this->responses);
        if ($response === null) {
            return false;
        }
        [$headers, $body] = $response;
        $stream = fopen('php://memory', 'w+b');
        if ($stream === false) {
            return false;
        }
        fwrite($stream, $body);
        rewind($stream);

        return [$headers, $stream];
    }
}
