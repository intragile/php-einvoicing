<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Latarnia;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Poland\Offline\OutageCategory;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Ksef\Latarnia\KsefAvailability;
use IntrAgile\EInvoicing\Transport\Ksef\Latarnia\Latarnia;
use IntrAgile\EInvoicing\Transport\Ksef\Latarnia\LatarniaMessage;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class LatarniaTest extends TestCase
{
    use CapturesThrowable;

    private const TEST_BASE = 'https://api-latarnia-test.ksef.mf.gov.pl/';

    private const PRODUCTION_BASE = 'https://api-latarnia.ksef.mf.gov.pl/';

    /**
     * @return iterable<string, array{string, KsefAvailability}>
     */
    public static function statuses(): iterable
    {
        yield 'available' => ['AVAILABLE', KsefAvailability::Available];
        yield 'maintenance' => ['MAINTENANCE', KsefAvailability::Maintenance];
        yield 'failure' => ['FAILURE', KsefAvailability::Failure];
        yield 'total failure' => ['TOTAL_FAILURE', KsefAvailability::TotalFailure];
    }

    #[DataProvider('statuses')]
    public function test_status_parses_all_categories(string $wire, KsefAvailability $expected): void
    {
        $recorder = new RecordingHttpClient(self::json(200, json_encode(['status' => $wire, 'messages' => []], JSON_THROW_ON_ERROR)));

        $status = self::latarnia($recorder)->status();

        self::assertSame($expected, $status->availability);
        self::assertSame([], $status->messages);
        $request = $recorder->requests()[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api-latarnia-test.ksef.mf.gov.pl/status', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function test_status_without_messages_is_available_with_an_empty_list(): void
    {
        $status = self::latarnia(new RecordingHttpClient(self::fixture('status-available.json')))->status();

        self::assertSame(KsefAvailability::Available, $status->availability);
        self::assertSame([], $status->messages);
    }

    public function test_status_carries_the_messages_it_follows_from(): void
    {
        $status = self::latarnia(new RecordingHttpClient(self::fixture('status-failure.json')))->status();

        self::assertSame(KsefAvailability::Failure, $status->availability);
        self::assertCount(1, $status->messages);
        $message = $status->messages[0];
        self::assertSame('X/2030/F/11', $message->id);
        self::assertSame(4207, $message->eventId);
        self::assertSame(OutageCategory::Failure, $message->category);
        self::assertSame(LatarniaMessage::TYPE_FAILURE_START, $message->type);
        self::assertNull($message->end);
    }

    public function test_unknown_status_value_throws_unexpected(): void
    {
        $recorder = new RecordingHttpClient(self::json(200, '{"status":"PARTIAL_OUTAGE"}'));

        $exception = self::capture(fn () => self::latarnia($recorder)->status());

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertSame(200, $exception->status);
        self::assertStringContainsString('PARTIAL_OUTAGE', $exception->getMessage());
    }

    public function test_messages_parse_nullable_end(): void
    {
        $recorder = new RecordingHttpClient(self::fixture('messages.json'));

        $messages = self::latarnia($recorder)->messages();

        self::assertSame('https://api-latarnia-test.ksef.mf.gov.pl/messages', (string) $recorder->requests()[0]->getUri());
        self::assertCount(4, $messages);
        self::assertSame(['X/2030/F/14', 'X/2030/F/13', 'X/2030/F/11', 'X/2030/M/03'], array_map(static fn (LatarniaMessage $m): string => $m->id, $messages));

        [$ongoing, $end, $start, $maintenance] = $messages;
        self::assertNull($ongoing->end);
        self::assertEquals(new DateTimeImmutable('2030-04-12T11:00:00+00:00'), $ongoing->start);
        self::assertSame(LatarniaMessage::TYPE_FAILURE_END, $end->type);
        self::assertEquals(new DateTimeImmutable('2030-04-09T13:40:00+00:00'), $end->end);
        self::assertSame(2, $start->version);
        self::assertEquals(new DateTimeImmutable('2030-04-09T06:20:00+00:00'), $start->published);
        self::assertSame(OutageCategory::Maintenance, $maintenance->category);
        self::assertSame(LatarniaMessage::TYPE_MAINTENANCE_ANNOUNCEMENT, $maintenance->type);
        self::assertSame('Sample planned maintenance', $maintenance->title);
        self::assertSame('Sample text: planned works on a Saturday night.', $maintenance->text);
        self::assertEquals(new DateTimeImmutable('2030-04-07T03:30:00+00:00'), $maintenance->end);
    }

    public function test_message_with_end_field_left_out_is_ongoing(): void
    {
        $messages = self::latarnia(new RecordingHttpClient(self::json(200, json_encode([self::message(['end' => null])], JSON_THROW_ON_ERROR))))->messages();
        self::assertNull($messages[0]->end);

        $data = self::message();
        unset($data['end']);
        $messages = self::latarnia(new RecordingHttpClient(self::json(200, json_encode([$data], JSON_THROW_ON_ERROR))))->messages();
        self::assertNull($messages[0]->end);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedMessages(): iterable
    {
        yield 'unknown category' => [self::message(['category' => 'OUTAGE'])];
        yield 'unknown type' => [self::message(['type' => 'FAILURE_UPDATE'])];
        yield 'missing id' => [self::message(['id' => null])];
        yield 'string event id' => [self::message(['eventId' => '4207'])];
        yield 'date without zone' => [self::message(['start' => '2030-04-09T06:15:00'])];
        yield 'not a date' => [self::message(['published' => 'yesterday'])];
        yield 'missing version' => [self::message(['version' => null])];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    #[DataProvider('malformedMessages')]
    public function test_malformed_message_throws_unexpected(array $message): void
    {
        $recorder = new RecordingHttpClient(self::json(200, json_encode([$message], JSON_THROW_ON_ERROR)));

        $exception = self::capture(fn () => self::latarnia($recorder)->messages());

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableBodies(): iterable
    {
        yield 'not json' => ['<html>maintenance</html>'];
        yield 'empty' => [''];
        yield 'object instead of list' => ['{"messages":[]}'];
        yield 'scalar' => ['"AVAILABLE"'];
    }

    #[DataProvider('unreadableBodies')]
    public function test_unreadable_messages_body_throws_unexpected(string $body): void
    {
        $exception = self::capture(fn () => self::latarnia(new RecordingHttpClient(self::json(200, $body)))->messages());

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
    }

    public function test_unreadable_status_body_throws_unexpected(): void
    {
        $exception = self::capture(fn () => self::latarnia(new RecordingHttpClient(self::json(200, '[1, 2]')))->status());

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function unavailableStatuses(): iterable
    {
        yield 'too many requests' => [429];
        yield 'internal error' => [500];
        yield 'unavailable' => [503];
    }

    #[DataProvider('unavailableStatuses')]
    public function test_server_error_or_rate_limit_is_transport_unavailable(int $status): void
    {
        $exception = self::capture(fn () => self::latarnia(new RecordingHttpClient(new Response($status, [], 'busy')))->status());

        self::assertInstanceOf(TransportUnavailable::class, $exception);
        self::assertStringContainsString((string) $status, $exception->getMessage());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function unexpectedStatuses(): iterable
    {
        yield 'redirect' => [302];
        yield 'not found' => [404];
        yield 'forbidden' => [403];
    }

    #[DataProvider('unexpectedStatuses')]
    public function test_other_unsuccessful_answer_is_unexpected(int $status): void
    {
        $exception = self::capture(fn () => self::latarnia(new RecordingHttpClient(new Response($status, [], 'nope')))->messages());

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertSame($status, $exception->status);
    }

    public function test_network_failure_is_transport_unavailable(): void
    {
        $failure = new class('connection timed out') extends RuntimeException implements NetworkExceptionInterface
        {
            public function getRequest(): RequestInterface
            {
                return (new Psr17Factory)->createRequest('GET', 'https://api-latarnia-test.ksef.mf.gov.pl/status');
            }
        };

        $exception = self::capture(fn () => self::latarnia(new RecordingHttpClient($failure))->status());

        self::assertInstanceOf(TransportUnavailable::class, $exception);
    }

    public function test_demo_has_no_latarnia(): void
    {
        self::assertNull(KsefEnvironment::Demo->latarniaBaseUrl());

        $exception = self::capture(fn () => Latarnia::forEnvironment(KsefEnvironment::Demo, self::http(new RecordingHttpClient)));

        self::assertInstanceOf(LogicException::class, $exception);
        self::assertStringContainsString('DEMO', $exception->getMessage());
    }

    public function test_urls_per_environment(): void
    {
        self::assertSame(self::TEST_BASE, KsefEnvironment::Test->latarniaBaseUrl());
        self::assertSame(self::PRODUCTION_BASE, KsefEnvironment::Production->latarniaBaseUrl());

        // Canned answers only: the production host is never contacted.
        $production = new RecordingHttpClient(self::fixture('status-available.json'), self::fixture('messages.json'));
        $latarnia = Latarnia::forEnvironment(KsefEnvironment::Production, self::http($production));
        $latarnia->status();
        $latarnia->messages();
        self::assertSame(
            ['https://api-latarnia.ksef.mf.gov.pl/status', 'https://api-latarnia.ksef.mf.gov.pl/messages'],
            array_map(static fn (RequestInterface $r): string => (string) $r->getUri(), $production->requests()),
        );

        $test = new RecordingHttpClient(self::fixture('status-available.json'));
        Latarnia::forEnvironment(KsefEnvironment::Test, self::http($test))->status();
        self::assertSame('https://api-latarnia-test.ksef.mf.gov.pl/status', (string) $test->requests()[0]->getUri());
    }

    public function test_base_url_without_trailing_slash_is_accepted(): void
    {
        $recorder = new RecordingHttpClient(self::fixture('status-available.json'));

        (new Latarnia('https://latarnia.example.test', self::http($recorder)))->status();

        self::assertSame('https://latarnia.example.test/status', (string) $recorder->requests()[0]->getUri());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBaseUrls(): iterable
    {
        yield 'plain http' => ['http://api-latarnia-test.ksef.mf.gov.pl/'];
        yield 'no host' => ['https:///status'];
        yield 'not a url' => ['latarnia'];
        yield 'user info' => ['https://user:pass@latarnia.example.test/'];
        yield 'query' => ['https://latarnia.example.test/?x=1'];
    }

    #[DataProvider('invalidBaseUrls')]
    public function test_invalid_base_url_is_refused(string $baseUrl): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Latarnia($baseUrl, self::http(new RecordingHttpClient));
    }

    private static function latarnia(RecordingHttpClient $recorder): Latarnia
    {
        return new Latarnia(self::TEST_BASE, self::http($recorder));
    }

    private static function http(RecordingHttpClient $recorder): HttpClient
    {
        $factory = new Psr17Factory;

        return new HttpClient($recorder, $factory, $factory);
    }

    private static function json(int $status, string $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], $body);
    }

    private static function fixture(string $name): Response
    {
        $contents = file_get_contents(__DIR__.'/../../../fixtures/ksef/latarnia/'.$name);
        self::assertIsString($contents);

        return self::json(200, $contents);
    }

    /**
     * A well-formed message with the given fields replaced; a null replacement sets the field
     * to null.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function message(array $overrides = []): array
    {
        return array_replace([
            'id' => 'X/2030/F/21',
            'eventId' => 4300,
            'category' => 'FAILURE',
            'type' => 'FAILURE_START',
            'title' => 'Sample failure began',
            'text' => 'Sample text.',
            'start' => '2030-05-02T07:00:00Z',
            'end' => '2030-05-02T09:00:00Z',
            'version' => 1,
            'published' => '2030-05-02T07:05:00Z',
        ], $overrides);
    }
}
