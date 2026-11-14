<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\EInvoiceBe;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\EInvoiceBe\ResponseMapper;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use InvalidArgumentException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

final class EInvoiceBeStatusTest extends TestCase
{
    use EInvoiceBeFixtures;

    public function test_status_request_and_mapping(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'document-sent.json'));

        $status = self::transport($recorder)->status('doc-7Hq2Lm9Xa4');

        self::assertCount(1, $recorder->requests());
        $request = $recorder->requests()[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.e-invoice.be/api/documents/doc-7Hq2Lm9Xa4', (string) $request->getUri());
        self::assertSame('Bearer '.self::KEY, $request->getHeaderLine('Authorization'));
        self::assertSame('doc-7Hq2Lm9Xa4', $status->documentId);
        self::assertSame(DeliveryState::Delivered, $status->state);
        self::assertNull($status->failure);
        self::assertNull($status->changedAt);
    }

    /**
     * @return iterable<string, array{mixed, DeliveryState}>
     */
    public static function states(): iterable
    {
        yield 'DRAFT' => ['DRAFT', DeliveryState::Pending];
        yield 'TRANSIT' => ['TRANSIT', DeliveryState::Pending];
        yield 'SENT' => ['SENT', DeliveryState::Delivered];
        yield 'RECEIVED' => ['RECEIVED', DeliveryState::Unknown];
        yield 'unknown state' => ['ARCHIVED', DeliveryState::Unknown];
        yield 'other case' => ['sent', DeliveryState::Unknown];
        yield 'missing' => [null, DeliveryState::Unknown];
        yield 'not a string' => [3, DeliveryState::Unknown];
    }

    #[DataProvider('states')]
    public function test_state_mapping(mixed $state, DeliveryState $expected): void
    {
        self::assertSame($expected, ResponseMapper::stateOf($state));

        $data = self::decoded('document-sent.json');
        $data['state'] = $state;
        $recorder = new RecordingHttpClient(self::json(200, $data));

        $status = self::transport($recorder)->status('doc-7Hq2Lm9Xa4');

        self::assertSame($expected, $status->state);
        self::assertNull($status->failure);
        self::assertCount(1, $recorder->requests());
        self::assertSame(DeliveryState::Failed, ResponseMapper::stateOf('FAILED'));
    }

    public function test_failed_reads_timeline_once(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'document-failed.json'), self::fixture(200, 'timeline-send-failed.json'));

        $status = self::transport($recorder)->status('doc-Fa1LeD0001');

        self::assertCount(2, $recorder->requests());
        $timeline = $recorder->requests()[1];
        self::assertSame('GET', $timeline->getMethod());
        self::assertSame('https://api.e-invoice.be/api/documents/doc-Fa1LeD0001/timeline', (string) $timeline->getUri());
        self::assertSame('Bearer '.self::KEY, $timeline->getHeaderLine('Authorization'));

        self::assertSame(DeliveryState::Failed, $status->state);
        self::assertNotNull($status->failure);
        self::assertSame(FailureCategory::Transport, $status->failure->category);
        self::assertSame('transmission failed; e-invoice.be reports no technical reason', $status->failure->message);
        self::assertSame('send_failed', $status->failure->providerCode);
        self::assertEquals(new DateTimeImmutable('2026-11-12T10:02:31.25Z'), $status->changedAt);
    }

    /**
     * @return iterable<string, array{Response|ClientExceptionInterface}>
     */
    public static function timelineFailures(): iterable
    {
        yield '500' => [new Response(500)];
        yield '401' => [self::fixture(401, 'error-401.json')];
        yield '404' => [self::fixture(404, 'error-404.json')];
        yield '429' => [new Response(429)];
        yield 'not JSON' => [new Response(200, [], '<html/>')];
        yield 'no events' => [self::json(200, ['document_id' => 'doc-Fa1LeD0001'])];
        yield 'events not a list' => [self::json(200, ['events' => ['a' => 1]])];
        yield 'no send_failed event' => [self::json(200, ['events' => [['event_type' => 'send_attempted', 'timestamp' => '2026-11-12T09:10:01Z']]])];
        yield 'network failure' => [self::networkException('timeout')];
    }

    #[DataProvider('timelineFailures')]
    public function test_timeline_failure_keeps_failed_state(Response|ClientExceptionInterface $timeline): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'document-failed.json'), $timeline);

        $status = self::transport($recorder)->status('doc-Fa1LeD0001');

        self::assertCount(2, $recorder->requests());
        self::assertSame(DeliveryState::Failed, $status->state);
        self::assertNotNull($status->failure);
        self::assertSame(FailureCategory::Transport, $status->failure->category);
        self::assertNull($status->failure->message);
        self::assertNull($status->failure->providerCode);
        self::assertNull($status->changedAt);
    }

    public function test_send_failed_event_without_a_valid_time_is_still_reported(): void
    {
        $recorder = new RecordingHttpClient(
            self::fixture(200, 'document-failed.json'),
            self::json(200, ['events' => [['event_type' => 'send_failed', 'timestamp' => 'yesterday'], 'junk']]),
        );

        $status = self::transport($recorder)->status('doc-Fa1LeD0001');

        self::assertNotNull($status->failure);
        self::assertSame('send_failed', $status->failure->providerCode);
        self::assertNull($status->changedAt);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIds(): iterable
    {
        yield 'empty' => [''];
        yield 'no prefix' => ['7Hq2Lm9Xa4'];
        yield 'prefix only' => ['doc-'];
        yield 'path' => ['doc-1/../2'];
        yield 'traversal' => ['../doc-1'];
        yield 'whitespace' => ['doc-1 2'];
        yield 'trailing newline' => ["doc-12\n"];
        yield 'query' => ['doc-1?x=1'];
        yield 'too long' => ['doc-'.str_repeat('a', 65)];
    }

    #[DataProvider('badIds')]
    public function test_malformed_id_is_refused_before_any_request(string $id): void
    {
        $recorder = new RecordingHttpClient;
        $transport = self::transport($recorder);

        foreach ([
            fn () => $transport->status($id),
            fn () => $transport->fetch($id),
            fn () => $transport->acknowledge($id),
        ] as $action) {
            $exception = self::capture($action);
            self::assertInstanceOf(InvalidArgumentException::class, $exception);
            self::assertStringContainsString('e-invoice.be document id', $exception->getMessage());
        }
        self::assertSame([], $recorder->requests());
    }

    public function test_longest_id_is_accepted(): void
    {
        $id = 'doc-'.str_repeat('a', 64);
        $data = self::decoded('document-sent.json');
        $data['id'] = $id;

        self::assertSame($id, self::transport(new RecordingHttpClient(self::json(200, $data)))->status($id)->documentId);
    }

    /**
     * @return iterable<string, array{Response, class-string}>
     */
    public static function readErrors(): iterable
    {
        yield '401' => [self::fixture(401, 'error-401.json'), AuthenticationFailed::class];
        yield '403' => [self::fixture(403, 'error-401.json'), AuthenticationFailed::class];
        yield '404' => [self::fixture(404, 'error-404.json'), UnexpectedResponse::class];
        yield '422' => [self::fixture(422, 'error-422-validation.json'), UnexpectedResponse::class];
        yield '429' => [new Response(429, ['Retry-After' => '7']), RateLimited::class];
        yield '500' => [new Response(500), TransportUnavailable::class];
        yield '503' => [new Response(503), TransportUnavailable::class];
        yield '418' => [new Response(418, [], 'teapot'), UnexpectedResponse::class];
    }

    /**
     * @param  class-string  $expected
     */
    #[DataProvider('readErrors')]
    public function test_read_error_mapping(Response $response, string $expected): void
    {
        $recorder = new RecordingHttpClient($response);

        $exception = self::capture(fn () => self::transport($recorder)->status('doc-7Hq2Lm9Xa4'));

        self::assertInstanceOf($expected, $exception);
        self::assertStringContainsString('HTTP '.$response->getStatusCode(), $exception->getMessage());
        self::assertStringContainsString('"doc-7Hq2Lm9Xa4"', $exception->getMessage());
        if ($exception instanceof RateLimited) {
            self::assertSame(7, $exception->retryAfterSeconds);
        }
        self::assertCount(1, $recorder->requests());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadable(): iterable
    {
        yield 'not JSON' => ['<html/>'];
        yield 'a list' => ['[1,2]'];
    }

    #[DataProvider('unreadable')]
    public function test_unreadable_status_is_unexpected(string $body): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(200, [], $body)))->status('doc-7Hq2Lm9Xa4'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
    }

    public function test_status_network_failure_is_transport_unavailable(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::networkException('timeout with '.self::KEY)))->status('doc-1'));

        self::assertInstanceOf(TransportUnavailable::class, $exception);
        self::assertStringNotContainsString(self::KEY, $exception->getMessage());
    }

    public function test_key_never_appears_in_status_messages(): void
    {
        $echo = '{"detail":"Unknown key '.self::KEY.' / '.base64_encode(self::KEY).'"}';
        foreach ([401, 404, 418, 500] as $status) {
            $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response($status, [], $echo)))->status('doc-1'));
            $text = $exception->getMessage().($exception instanceof UnexpectedResponse ? $exception->bodyExcerpt : '');

            self::assertStringNotContainsString(self::KEY, $text);
            self::assertStringNotContainsString(base64_encode(self::KEY), $text);
        }
    }
}
