<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Directory;

use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Directory\DirectoryClient;
use IntrAgile\EInvoicing\Transport\Directory\DirectoryEnvironment;
use IntrAgile\EInvoicing\Transport\Directory\DirectoryQuery;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DirectoryClientTest extends TestCase
{
    private const EMPTY = '{"version":"1.0","total-result-count":0,"used-result-count":0,"matches":[]}';

    public function test_find_sends_the_exact_query_string(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::fixture()));

        self::client($recorder)->find(ParticipantId::parse('9910:hu11200589'));

        $request = $recorder->requests()[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https', $request->getUri()->getScheme());
        self::assertSame('directory.peppol.eu', $request->getUri()->getHost());
        self::assertSame('/search/1.0/json', $request->getUri()->getPath());
        self::assertSame('participant=iso6523-actorid-upis%3A%3A9910%3Ahu11200589&rpi=0&rpc=20', $request->getUri()->getQuery());
    }

    public function test_search_encodes_every_criterion_against_the_chosen_environment(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::EMPTY));
        $doctype = new DocumentTypeId('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1');

        self::client($recorder, DirectoryEnvironment::Test)->search(new DirectoryQuery(
            q: 'kft',
            name: 'Example',
            country: 'hu',
            documentType: $doctype,
            page: 2,
            pageSize: 50,
        ));

        $uri = $recorder->requests()[0]->getUri();
        self::assertSame('test-directory.peppol.eu', $uri->getHost());
        self::assertSame(
            'q=kft&name=Example&country=HU&doctype='.rawurlencode($doctype->toUri()).'&rpi=2&rpc=50',
            $uri->getQuery(),
        );
        self::assertStringContainsString('%23%23urn%3Acen.eu', $uri->getQuery());
    }

    public function test_maps_the_fixture_to_entries(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::fixture()));

        $result = self::client($recorder)->search(new DirectoryQuery(country: 'HU'));

        self::assertSame(83, $result->total);
        self::assertCount(2, $result->entries, 'The entry outside the iso6523-actorid-upis scheme is skipped.');

        $entry = $result->entries[0];
        self::assertTrue($entry->participant->equals(ParticipantId::parse('9910:hu11200589')));
        self::assertSame(['Example Kereskedelmi Kft.', 'Example Trading Ltd.'], $entry->names);
        self::assertSame('HU', $entry->country);
        self::assertSame([
            'busdox-docid-qns::urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1',
            'busdox-docid-qns::urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2::CreditNote##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1',
        ], $entry->documentTypes);
        self::assertSame([
            ['scheme' => 'HU:VAT', 'value' => 'HU11200589'],
            ['scheme' => '0088', 'value' => '5990000000001'],
        ], $entry->identifiers);
        self::assertInstanceOf(DateTimeImmutable::class, $entry->registeredOn);
        self::assertSame('2023-01-10', $entry->registeredOn->format('Y-m-d'), 'The earliest registration date of all entities.');

        $minimal = $result->entries[1];
        self::assertSame(['Minimal Zrt.'], $minimal->names);
        self::assertNull($minimal->country);
        self::assertSame([], $minimal->documentTypes);
        self::assertSame([], $minimal->identifiers);
        self::assertNull($minimal->registeredOn);
    }

    public function test_find_returns_the_matching_entry(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::fixture()));

        $entry = self::client($recorder)->find(ParticipantId::parse('9910:HU11200589'));

        self::assertNotNull($entry);
        self::assertSame('9910:hu11200589', $entry->participant->toString());
    }

    public function test_find_returns_null_when_nothing_matches(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::EMPTY));

        self::assertNull(self::client($recorder)->find(ParticipantId::parse('0208:0123456789')));
    }

    public function test_back_to_back_requests_are_throttled(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::EMPTY), new Response(200, [], self::EMPTY));
        $times = [100.0, 100.2];
        $sleeps = new SleepRecorder;
        $client = new DirectoryClient(
            self::http($recorder),
            clock: static function () use (&$times): float {
                return array_shift($times) ?? 100.5;
            },
            sleep: static function (int $microseconds) use ($sleeps): void {
                $sleeps->record($microseconds);
            },
        );

        $client->search(new DirectoryQuery(q: 'first'));
        self::assertCount(0, $sleeps->delays(), 'The first request is not delayed.');
        $client->search(new DirectoryQuery(q: 'second'));

        self::assertCount(1, $sleeps->delays());
        self::assertGreaterThanOrEqual(300_000, $sleeps->delays()[0]);
        self::assertLessThan(301_000, $sleeps->delays()[0]);
        self::assertCount(2, $recorder->requests());
    }

    public function test_a_clock_stepping_back_never_waits_longer_than_the_interval(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::EMPTY), new Response(200, [], self::EMPTY));
        $times = [5000.0, 1400.0];
        $sleeps = new SleepRecorder;
        $client = new DirectoryClient(
            self::http($recorder),
            clock: static function () use (&$times): float {
                return array_shift($times) ?? 1400.0;
            },
            sleep: static function (int $microseconds) use ($sleeps): void {
                $sleeps->record($microseconds);
            },
        );

        $client->search(new DirectoryQuery(q: 'first'));
        $client->search(new DirectoryQuery(q: 'second'));

        self::assertCount(1, $sleeps->delays());
        self::assertLessThanOrEqual(DirectoryClient::MIN_INTERVAL_MICROSECONDS, $sleeps->delays()[0]);
    }

    public function test_requests_further_apart_than_the_interval_are_not_delayed(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::EMPTY), new Response(200, [], self::EMPTY));
        $times = [100.0, 100.7];
        $sleeps = new SleepRecorder;
        $client = new DirectoryClient(
            self::http($recorder),
            clock: static function () use (&$times): float {
                return array_shift($times) ?? 101.0;
            },
            sleep: static function (int $microseconds) use ($sleeps): void {
                $sleeps->record($microseconds);
            },
        );

        $client->search(new DirectoryQuery(q: 'first'));
        $client->search(new DirectoryQuery(q: 'second'));

        self::assertSame([], $sleeps->delays());
    }

    public function test_429_is_rate_limited_with_retry_after(): void
    {
        $recorder = new RecordingHttpClient(new Response(429, ['Retry-After' => '3'], 'Too many requests'));

        try {
            self::client($recorder)->search(new DirectoryQuery(q: 'kft'));
            self::fail('RateLimited expected.');
        } catch (RateLimited $exception) {
            self::assertSame(3, $exception->retryAfterSeconds);
            self::assertTrue($exception->isRetryable());
        }
    }

    public function test_429_without_retry_after_has_no_delay(): void
    {
        $recorder = new RecordingHttpClient(new Response(429, [], ''));

        try {
            self::client($recorder)->search(new DirectoryQuery(q: 'kft'));
            self::fail('RateLimited expected.');
        } catch (RateLimited $exception) {
            self::assertNull($exception->retryAfterSeconds);
        }
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function unexpectedResponses(): iterable
    {
        yield 'bad request' => [400, 'Bad request'];
        yield 'server error' => [500, 'oops'];
        yield 'not json' => [200, '<html></html>'];
        yield 'matches not a list' => [200, '{"total-result-count":1,"matches":"x"}'];
        yield 'total missing' => [200, '{"matches":[]}'];
    }

    #[DataProvider('unexpectedResponses')]
    public function test_unexpected_responses_are_reported(int $status, string $body): void
    {
        $recorder = new RecordingHttpClient(new Response($status, [], $body));

        try {
            self::client($recorder)->search(new DirectoryQuery(q: 'kft'));
            self::fail('UnexpectedResponse expected.');
        } catch (UnexpectedResponse $exception) {
            self::assertSame($status, $exception->status);
        }
    }

    /**
     * @return iterable<string, array{Closure(): DirectoryQuery}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'no criterion' => [static fn (): DirectoryQuery => new DirectoryQuery];
        yield 'blank q only' => [static fn (): DirectoryQuery => new DirectoryQuery(q: '  ')];
        yield 'two-character name alone' => [static fn (): DirectoryQuery => new DirectoryQuery(name: 'ab')];
        yield 'padded two-character name alone' => [static fn (): DirectoryQuery => new DirectoryQuery(name: ' ab ')];
        yield 'country not alpha-2' => [static fn (): DirectoryQuery => new DirectoryQuery(country: 'HUN')];
        yield 'page size zero' => [static fn (): DirectoryQuery => new DirectoryQuery(q: 'kft', pageSize: 0)];
        yield 'page size above 100' => [static fn (): DirectoryQuery => new DirectoryQuery(q: 'kft', pageSize: 101)];
        yield 'negative page' => [static fn (): DirectoryQuery => new DirectoryQuery(q: 'kft', page: -1)];
        yield 'beyond 1000 results' => [static fn (): DirectoryQuery => new DirectoryQuery(q: 'kft', page: 10, pageSize: 100)];
    }

    /**
     * @param  Closure(): DirectoryQuery  $build
     */
    #[DataProvider('invalidQueries')]
    public function test_invalid_queries_are_refused(Closure $build): void
    {
        $this->expectException(InvalidArgumentException::class);

        $build();
    }

    public function test_short_name_is_accepted_with_another_criterion(): void
    {
        $query = new DirectoryQuery(name: 'ab', country: 'HU', page: 9, pageSize: 100);

        self::assertSame('ab', $query->name);
        self::assertSame('HU', $query->country);
    }

    private static function fixture(): string
    {
        $contents = file_get_contents(__DIR__.'/../../fixtures/directory/search-hu.json');
        self::assertIsString($contents);

        return $contents;
    }

    private static function http(RecordingHttpClient $recorder): HttpClient
    {
        $factory = new Psr17Factory;

        return new HttpClient($recorder, $factory, $factory);
    }

    private static function client(RecordingHttpClient $recorder, DirectoryEnvironment $env = DirectoryEnvironment::Production): DirectoryClient
    {
        return new DirectoryClient(self::http($recorder), $env, static fn (): float => 0.0, static function (int $microseconds): void {});
    }
}

/**
 * Records the delays a client asked to sleep.
 */
final class SleepRecorder
{
    /** @var list<int> */
    private array $delays = [];

    public function record(int $microseconds): void
    {
        $this->delays[] = $microseconds;
    }

    /**
     * @return list<int>
     */
    public function delays(): array
    {
        return $this->delays;
    }
}
