<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\B2Brouter\ResponseMapper;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use InvalidArgumentException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class B2BrouterStatusTest extends TestCase
{
    use B2BrouterFixtures;

    public function test_status_request_and_mapping(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'invoice-sent.json'));

        $status = self::transport($recorder)->status('482913');

        self::assertCount(1, $recorder->requests());
        $request = $recorder->requests()[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.b2brouter.net/invoices/482913', (string) $request->getUri());
        self::assertSame(self::KEY, $request->getHeaderLine('X-B2B-API-Key'));
        self::assertSame('2026-06-26', $request->getHeaderLine('X-B2B-API-Version'));
        self::assertSame('482913', $status->documentId);
        self::assertSame(DeliveryState::Delivered, $status->state);
        self::assertNull($status->failure);
        self::assertEquals(new DateTimeImmutable('2026-11-12T08:00:09+00:00'), $status->changedAt);
    }

    /**
     * @return iterable<string, array{mixed, DeliveryState}>
     */
    public static function states(): iterable
    {
        foreach (['new', 'sending'] as $state) {
            yield $state => [$state, DeliveryState::Pending];
        }
        foreach (['sent', 'registered', 'read', 'accepted', 'refused', 'received', 'allegedly_paid', 'paid', 'closed'] as $state) {
            yield $state => [$state, DeliveryState::Delivered];
        }
        yield 'error' => ['error', DeliveryState::Failed];
        yield 'invalid' => ['invalid', DeliveryState::Failed];
        foreach (['annulled', 'expired', 'issued', 'downloaded', 'teleported'] as $state) {
            yield $state => [$state, DeliveryState::Unknown];
        }
        yield 'missing' => [null, DeliveryState::Unknown];
        yield 'not a string' => [3, DeliveryState::Unknown];
        yield 'other case' => ['SENT', DeliveryState::Unknown];
    }

    #[DataProvider('states')]
    public function test_state_mapping(mixed $state, DeliveryState $expected): void
    {
        self::assertSame($expected, ResponseMapper::stateOf($state));

        $data = self::decoded('invoice-sent.json');
        self::assertIsArray($data['invoice']);
        $data['invoice']['state'] = $state;
        $status = self::transport(new RecordingHttpClient(self::json(200, $data)))->status('482913');

        self::assertSame($expected, $status->state);
    }

    public function test_failed_carries_error_code_and_message(): void
    {
        $status = self::transport(new RecordingHttpClient(self::fixture(200, 'invoice-error.json')))->status('482914');

        self::assertSame(DeliveryState::Failed, $status->state);
        self::assertNotNull($status->failure);
        self::assertSame(FailureCategory::RecipientNotFound, $status->failure->category);
        self::assertSame('PEPPOL_NO_RECEIVER', $status->failure->providerCode);
        self::assertSame('The receiver is not registered in the Peppol network.', $status->failure->message);
        self::assertEquals(new DateTimeImmutable('2026-11-12T08:01:12.000Z'), $status->changedAt);
    }

    public function test_other_error_code_is_a_transport_failure(): void
    {
        $data = self::decoded('invoice-error.json');
        self::assertIsArray($data['invoice']);
        $data['invoice']['error_code'] = 'PEPPOL_AS4_TIMEOUT';
        $data['invoice']['error_message'] = 'Leaked '.self::KEY;

        $status = self::transport(new RecordingHttpClient(self::json(200, $data)))->status('482914');

        self::assertNotNull($status->failure);
        self::assertSame(FailureCategory::Transport, $status->failure->category);
        self::assertSame('PEPPOL_AS4_TIMEOUT', $status->failure->providerCode);
        self::assertSame('Leaked [redacted]', $status->failure->message);
    }

    public function test_failure_message_falls_back_to_errors_list(): void
    {
        $body = ['invoice' => ['id' => 1, 'state' => 'error', 'error_code' => null, 'error_message' => null, 'errors' => [
            ['error' => 'Validation failed: You cannot send invoices dated from a closed fiscal year'],
            'Second '.self::KEY,
            ['other' => 'ignored'],
            42,
        ]]];

        $status = self::transport(new RecordingHttpClient(self::json(200, $body)))->status('1');

        self::assertSame(DeliveryState::Failed, $status->state);
        self::assertNotNull($status->failure);
        self::assertSame(FailureCategory::Transport, $status->failure->category);
        self::assertSame('Validation failed: You cannot send invoices dated from a closed fiscal year; Second [redacted]', $status->failure->message);
    }

    public function test_unregistered_recipient_message_without_code_is_recipient_not_found(): void
    {
        $status = self::transport(new RecordingHttpClient(self::json(200, self::unregistered(null))))->status('1');

        self::assertSame(DeliveryState::Failed, $status->state);
        self::assertNotNull($status->failure);
        self::assertSame(FailureCategory::RecipientNotFound, $status->failure->category);
        self::assertNull($status->failure->providerCode);
        self::assertStringContainsString('not registered to receive', (string) $status->failure->message);
    }

    public function test_unregistered_recipient_phrase_is_matched_before_the_message_is_shortened(): void
    {
        $body = ['invoice' => ['id' => 1, 'state' => 'error', 'error_code' => null, 'error_message' => null, 'errors' => [
            ['error' => str_repeat('x', 400)],
            ['error' => 'Recipient is not registered to receive this document'],
        ]]];

        $status = self::transport(new RecordingHttpClient(self::json(200, $body)))->status('1');

        self::assertNotNull($status->failure);
        self::assertSame(FailureCategory::RecipientNotFound, $status->failure->category);
        self::assertLessThanOrEqual(303, strlen((string) $status->failure->message));
    }

    public function test_unregistered_recipient_message_with_another_code_stays_a_transport_failure(): void
    {
        $status = self::transport(new RecordingHttpClient(self::json(200, self::unregistered('X_OTHER'))))->status('1');

        self::assertNotNull($status->failure);
        self::assertSame(FailureCategory::Transport, $status->failure->category);
        self::assertSame('X_OTHER', $status->failure->providerCode);
    }

    /**
     * @return array<string, mixed>
     */
    private static function unregistered(?string $code): array
    {
        return ['invoice' => ['id' => 1, 'state' => 'error', 'error_code' => $code, 'error_message' => null, 'errors' => [
            ['error' => 'Recipient is not registered to receive this document (sandbox simulation)'],
        ]]];
    }

    public function test_failure_message_is_null_without_message_or_errors(): void
    {
        $body = ['invoice' => ['id' => 1, 'state' => 'error', 'error_code' => null, 'error_message' => null, 'errors' => []]];

        $status = self::transport(new RecordingHttpClient(self::json(200, $body)))->status('1');

        self::assertNotNull($status->failure);
        self::assertNull($status->failure->message);
    }

    public function test_invalid_is_a_validation_failure(): void
    {
        $data = self::decoded('invoice-error.json');
        self::assertIsArray($data['invoice']);
        $data['invoice']['state'] = 'invalid';
        $data['invoice']['error_code'] = null;
        $data['invoice']['error_message'] = null;

        $status = self::transport(new RecordingHttpClient(self::json(200, $data)))->status('482914');

        self::assertSame(DeliveryState::Failed, $status->state);
        self::assertNotNull($status->failure);
        self::assertSame(FailureCategory::Validation, $status->failure->category);
        self::assertNull($status->failure->providerCode);
        self::assertNull($status->failure->message);
    }

    public function test_received_invoice_status_is_refused(): void
    {
        $recorder = new RecordingHttpClient(new Response(302, ['Location' => self::BASE.'/received_invoices/900101']));

        $exception = self::capture(fn () => self::transport($recorder)->status('900101'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('a received invoice, not a sent one', $exception->getMessage());
        self::assertCount(1, $recorder->requests());
    }

    public function test_status_redirect_keeps_no_body_excerpt(): void
    {
        $recorder = new RecordingHttpClient(new Response(302, ['Location' => self::BASE.'/received_invoices/900101?token=abc'], '<a href="/received_invoices/900101?token=abc">moved</a>'));

        $exception = self::capture(fn () => self::transport($recorder)->status('900101'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertSame('', $exception->bodyExcerpt);
        self::assertStringNotContainsString('token=abc', $exception->getMessage());
        self::assertCount(1, $recorder->requests());
    }

    public function test_status_404_is_unexpected(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::fixture(404, 'error-404.json')))->status('1'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('"1"', $exception->getMessage());
        self::assertSame(404, $exception->status);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIds(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => [' '];
        yield 'letters' => ['abc'];
        yield 'path' => ['1/../2'];
        yield 'negative' => ['-1'];
        yield 'padded' => [' 12 '];
    }

    #[DataProvider('badIds')]
    public function test_non_numeric_id_is_refused_before_any_request(string $id): void
    {
        $recorder = new RecordingHttpClient;
        $transport = self::transport($recorder);

        foreach ([
            fn () => $transport->status($id),
            fn () => $transport->fetch($id),
            fn () => $transport->acknowledge($id),
        ] as $action) {
            self::assertInstanceOf(InvalidArgumentException::class, self::capture($action));
        }
        self::assertSame([], $recorder->requests());
    }

    /**
     * @return iterable<string, array{Response, class-string}>
     */
    public static function readErrors(): iterable
    {
        yield '401' => [self::fixture(401, 'error-401.json'), AuthenticationFailed::class];
        yield '403' => [self::fixture(403, 'error-403.json'), AuthenticationFailed::class];
        yield '404' => [self::fixture(404, 'error-404.json'), UnexpectedResponse::class];
        yield '429' => [new Response(429, ['Retry-After' => '7']), RateLimited::class];
        yield '500' => [new Response(500), TransportUnavailable::class];
        yield '503' => [new Response(503), TransportUnavailable::class];
        yield '418' => [new Response(418, [], 'teapot'), UnexpectedResponse::class];
        yield '200 not JSON' => [new Response(200, [], '<html/>'), UnexpectedResponse::class];
        yield '200 without invoice' => [self::json(200, ['data' => []]), UnexpectedResponse::class];
    }

    /**
     * @param  class-string  $expected
     */
    #[DataProvider('readErrors')]
    public function test_read_error_mapping(Response $response, string $expected): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient($response))->status('482913'));

        self::assertInstanceOf($expected, $exception);
        if ($exception instanceof RateLimited) {
            self::assertSame(7, $exception->retryAfterSeconds);
        }
        if (! $exception instanceof UnexpectedResponse || $response->getStatusCode() !== 200) {
            self::assertStringContainsString('HTTP '.$response->getStatusCode(), $exception->getMessage());
        }
    }

    public function test_status_network_failure_is_transport_unavailable(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::networkException('timeout with '.self::KEY)))->status('1'));

        self::assertInstanceOf(TransportUnavailable::class, $exception);
        self::assertStringNotContainsString(self::KEY, $exception->getMessage());
    }
}
