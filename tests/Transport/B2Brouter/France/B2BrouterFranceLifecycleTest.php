<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\France;

use IntrAgile\EInvoicing\France\Lifecycle\LifecycleStatus;
use IntrAgile\EInvoicing\France\Lifecycle\StatusReason;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\B2BrouterFrance;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class B2BrouterFranceLifecycleTest extends TestCase
{
    use CapturesThrowable;

    private const KEY = 'stag_FAKEKEY000000';

    private const ACCOUNT = 4711;

    private const BASE = 'https://api-staging.b2brouter.net';

    /**
     * @return iterable<string, array{string, ?LifecycleStatus}>
     */
    public static function issuedStates(): iterable
    {
        yield 'sent' => ['sent', LifecycleStatus::Deposited];
        yield 'registered' => ['registered', LifecycleStatus::Received];
        yield 'read' => ['read', LifecycleStatus::InProcess];
        yield 'accepted' => ['accepted', LifecycleStatus::Approved];
        yield 'annotated' => ['annotated', LifecycleStatus::Disputed];
        yield 'refused' => ['refused', LifecycleStatus::Refused];
        yield 'paid' => ['paid', LifecycleStatus::Collected];
        yield 'new' => ['new', null];
        yield 'sending' => ['sending', null];
        yield 'error' => ['error', null];
    }

    #[DataProvider('issuedStates')]
    public function test_issued_states_map_to_lifecycle(string $state, ?LifecycleStatus $expected): void
    {
        $recorder = new RecordingHttpClient(self::invoice(['id' => 590101, 'type' => 'IssuedInvoice', 'state' => $state]));

        $status = self::france($recorder)->lifecycle('590101');

        self::assertSame($expected, $status->lifecycle);
        self::assertSame($state, $status->platformState);
        self::assertFalse($status->received);
        self::assertSame('590101', $status->documentId);
        self::assertSame('590101', $status->delivery->documentId);
        $request = $recorder->requests()[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame(self::BASE.'/invoices/590101', (string) $request->getUri());
        self::assertSame(self::KEY, $request->getHeaderLine('X-B2B-API-Key'));
    }

    /**
     * @return iterable<string, array{string, ?LifecycleStatus}>
     */
    public static function receivedStates(): iterable
    {
        yield 'read' => ['read', LifecycleStatus::InProcess];
        yield 'accepted' => ['accepted', LifecycleStatus::Approved];
        yield 'refused' => ['refused', LifecycleStatus::Refused];
        yield 'paid' => ['paid', LifecycleStatus::PaymentSent];
        yield 'annotated' => ['annotated', null];
        yield 'new' => ['new', null];
        yield 'registered' => ['registered', null];
    }

    #[DataProvider('receivedStates')]
    public function test_received_states_map_to_lifecycle(string $state, ?LifecycleStatus $expected): void
    {
        $recorder = new RecordingHttpClient(self::invoice(['id' => 900101, 'type' => 'ReceivedInvoice', 'state' => $state]));

        $status = self::france($recorder)->lifecycle('900101');

        self::assertSame($expected, $status->lifecycle);
        self::assertTrue($status->received);
    }

    public function test_refusal_reason_code_is_read(): void
    {
        $response = self::invoice(['id' => 590101, 'type' => 'IssuedInvoice', 'state' => 'refused', 'refuse_reason_code' => 'MONTANTTOTAL_ERR']);

        $status = self::france(new RecordingHttpClient($response))->lifecycle('590101');

        self::assertSame(LifecycleStatus::Refused, $status->lifecycle);
        self::assertSame(StatusReason::MONTANTTOTAL_ERR, $status->refusalReason);
        self::assertSame(DeliveryState::Delivered, $status->delivery->state);
    }

    public function test_unknown_or_missing_refusal_reason_is_null(): void
    {
        foreach ([['refuse_reason_code' => 'NOT_A_CODE'], ['refuse_reason_code' => 42], []] as $extra) {
            $response = self::invoice(['id' => 590101, 'state' => 'refused'] + $extra);

            self::assertNull(self::france(new RecordingHttpClient($response))->lifecycle('590101')->refusalReason);
        }
    }

    public function test_tax_report_ids_are_read(): void
    {
        $response = self::invoice(['id' => 590101, 'state' => 'registered', 'tax_report_ids' => [7001, 7002, 'x', -1, null]]);

        $status = self::france(new RecordingHttpClient($response))->lifecycle('590101');

        self::assertSame(['7001', '7002'], $status->taxReportIds);
    }

    public function test_tax_report_ids_default_to_empty(): void
    {
        foreach ([[], ['tax_report_ids' => null], ['tax_report_ids' => 'oops']] as $extra) {
            $response = self::invoice(['id' => 590101, 'state' => 'sent'] + $extra);

            self::assertSame([], self::france(new RecordingHttpClient($response))->lifecycle('590101')->taxReportIds);
        }
    }

    public function test_unknown_state_keeps_raw_state_and_null_lifecycle(): void
    {
        $response = self::invoice(['id' => 590101, 'type' => 'IssuedInvoice', 'state' => 'something_new']);

        $status = self::france(new RecordingHttpClient($response))->lifecycle('590101');

        self::assertSame('something_new', $status->platformState);
        self::assertNull($status->lifecycle);
        self::assertSame(DeliveryState::Unknown, $status->delivery->state);
    }

    public function test_errored_invoice_carries_failure_and_change_time(): void
    {
        $response = self::invoice([
            'id' => 590101,
            'state' => 'error',
            'state_updated_at' => '2026-11-18T10:00:00Z',
            'errors' => ['BR-FR-01: SIREN missing'],
        ]);

        $status = self::france(new RecordingHttpClient($response))->lifecycle('590101');

        self::assertSame(DeliveryState::Failed, $status->delivery->state);
        self::assertSame(FailureCategory::Transport, $status->delivery->failure?->category);
        self::assertSame('BR-FR-01: SIREN missing', $status->delivery->failure->message);
        self::assertSame('2026-11-18T10:00:00+00:00', $status->delivery->changedAt?->format(DATE_ATOM));
    }

    public function test_received_invoice_redirect_is_followed_once(): void
    {
        $recorder = new RecordingHttpClient(
            new Response(302, ['Location' => '/received_invoices/900101']),
            self::invoice(['id' => 900101, 'type' => 'ReceivedInvoice', 'state' => 'accepted']),
        );

        $status = self::france($recorder)->lifecycle('900101');

        self::assertTrue($status->received);
        self::assertSame(LifecycleStatus::Approved, $status->lifecycle);
        self::assertCount(2, $recorder->requests());
        $second = $recorder->requests()[1];
        self::assertSame('GET', $second->getMethod());
        self::assertSame(self::BASE.'/received_invoices/900101', (string) $second->getUri());
        self::assertSame(self::KEY, $second->getHeaderLine('X-B2B-API-Key'));
    }

    public function test_redirect_target_without_type_is_read_as_received(): void
    {
        $recorder = new RecordingHttpClient(
            new Response(302, ['Location' => self::BASE.'/received_invoices/900101']),
            self::invoice(['id' => 900101, 'state' => 'paid']),
        );

        $status = self::france($recorder)->lifecycle('900101');

        self::assertTrue($status->received);
        self::assertSame(LifecycleStatus::PaymentSent, $status->lifecycle);
    }

    public function test_second_redirect_is_refused(): void
    {
        $recorder = new RecordingHttpClient(
            new Response(302, ['Location' => self::BASE.'/a']),
            new Response(302, ['Location' => self::BASE.'/b?token=abc'], '<a href="/b?token=abc">moved</a>'),
        );

        $exception = self::capture(fn () => self::france($recorder)->lifecycle('900101'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('more than once', $exception->getMessage());
        self::assertStringNotContainsString('token=abc', $exception->getMessage().$exception->bodyExcerpt);
    }

    public function test_foreign_redirect_is_refused(): void
    {
        $recorder = new RecordingHttpClient(new Response(302, ['Location' => 'https://evil.example/steal?token=abc']));

        $exception = self::capture(fn () => self::france($recorder)->lifecycle('900101'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertCount(1, $recorder->requests());
        self::assertStringNotContainsString('token=abc', $exception->getMessage().$exception->bodyExcerpt);
    }

    public function test_redirect_other_than_302_is_refused(): void
    {
        $recorder = new RecordingHttpClient(new Response(301, ['Location' => self::BASE.'/elsewhere?token=abc'], '<a href="/elsewhere?token=abc">moved</a>'));

        $exception = self::capture(fn () => self::france($recorder)->lifecycle('900101'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertCount(1, $recorder->requests());
        self::assertStringNotContainsString('token=abc', $exception->getMessage().$exception->bodyExcerpt);
    }

    public function test_unreadable_redirect_target_is_unexpected(): void
    {
        $recorder = new RecordingHttpClient(
            new Response(302, ['Location' => '/received_invoices/900101']),
            new Response(200, ['Content-Type' => 'text/html'], '<html>not json</html>'),
        );

        $exception = self::capture(fn () => self::france($recorder)->lifecycle('900101'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
    }

    public function test_body_without_invoice_or_state_is_unexpected(): void
    {
        foreach ([['invoices' => []], ['invoice' => ['id' => 590101]], ['invoice' => ['id' => 590101, 'state' => '  ']]] as $body) {
            $response = new Response(200, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));

            $exception = self::capture(fn () => self::france(new RecordingHttpClient($response))->lifecycle('590101'));

            self::assertInstanceOf(UnexpectedResponse::class, $exception);
        }
    }

    public function test_read_errors_are_mapped(): void
    {
        $unknown = new Response(404, ['Content-Type' => 'application/json'], '{"error":{"code":"resource_missing","message":"Not found"}}');
        $exception = self::capture(fn () => self::france(new RecordingHttpClient($unknown))->lifecycle('590101'));
        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('590101', $exception->getMessage());

        $refused = new Response(401, ['Content-Type' => 'application/json'], '{"error":{"message":"Invalid key '.self::KEY.'"}}');
        $exception = self::capture(fn () => self::france(new RecordingHttpClient($refused))->lifecycle('590101'));
        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertStringNotContainsString(self::KEY, $exception->getMessage());
    }

    public function test_invoice_id_must_be_digits(): void
    {
        $recorder = new RecordingHttpClient;

        foreach (['', 'abc', '12/../3', '1 2'] as $id) {
            $exception = self::capture(fn () => self::france($recorder)->lifecycle($id));
            self::assertInstanceOf(InvalidArgumentException::class, $exception, $id);
        }
        self::assertCount(0, $recorder->requests());
    }

    private static function france(RecordingHttpClient $recorder): B2BrouterFrance
    {
        $factory = new Psr17Factory;

        return new B2BrouterFrance(
            new B2BrouterConfig(self::KEY, self::ACCOUNT, self::BASE),
            new HttpClient($recorder, $factory, $factory),
        );
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private static function invoice(array $invoice): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['invoice' => $invoice], JSON_THROW_ON_ERROR));
    }
}
