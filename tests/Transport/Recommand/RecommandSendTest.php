<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Recommand;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\RecipientUnreachable;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandConfig;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandTransport;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Throwable;

final class RecommandSendTest extends TestCase
{
    use CapturesThrowable;

    private const KEY_ID = 'key_01testkey';

    private const SECRET = 'secret_01topsecretvalue';

    private const XML = '<?xml version="1.0" encoding="UTF-8"?><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>';

    public function test_send_request_has_exact_method_url_headers_and_body(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'send-delivered.json'));

        self::transport($recorder, companyId: 'c_01/ex ample')->send(self::document());

        self::assertCount(1, $recorder->requests());
        $request = $recorder->requests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://app.recommand.eu/api/v1/c_01%2Fex%20ample/send', (string) $request->getUri());
        self::assertSame('Basic '.base64_encode(self::KEY_ID.':'.self::SECRET), $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'documentType' => 'xml',
            'document' => self::XML,
            'recipient' => '0208:0123456789AbC',
            'doctypeId' => PeppolBis3::INVOICE,
            'processId' => PeppolBis3::PROCESS,
        ], json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_custom_base_url_is_used_without_trailing_slash(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'send-delivered.json'));

        self::transport($recorder, baseUrl: 'https://ap.example/')->send(self::document());

        self::assertSame('https://ap.example/api/v1/c_01example/send', (string) $recorder->requests()[0]->getUri());
    }

    public function test_delivered_send_maps_to_send_result(): void
    {
        $result = self::transport(new RecordingHttpClient(self::fixture(200, 'send-delivered.json')))->send(self::document());

        self::assertSame('doc_01delivered', $result->documentId);
        self::assertSame(DeliveryState::Delivered, $result->state);
        self::assertSame('a1b2c3d4-0000-4000-8000-000000000001@recommand.eu', $result->peppolMessageId);
        self::assertSame('5f0c8f3e-2d0a-4c55-9a43-6a2f1d0b7e11', $result->envelopeId);
        self::assertNull($result->failure);
    }

    public function test_null_delivery_status_is_pending_and_unknown_fields_are_ignored(): void
    {
        $result = self::transport(new RecordingHttpClient(self::fixture(200, 'send-pending.json')))->send(self::document());

        self::assertSame('doc_01pending', $result->documentId);
        self::assertSame(DeliveryState::Pending, $result->state);
        self::assertNull($result->peppolMessageId);
        self::assertNull($result->envelopeId);
        self::assertNull($result->failure);
    }

    public function test_failed_peppol_delivery_carries_its_failure(): void
    {
        $result = self::transport(new RecordingHttpClient(self::fixture(200, 'send-failed-delivery.json')))->send(self::document());

        self::assertSame(DeliveryState::Failed, $result->state);
        self::assertNotNull($result->failure);
        self::assertSame(FailureCategory::Transport, $result->failure->category);
        self::assertSame('The receiving access point did not answer.', $result->failure->message);
        self::assertSame('AS4_TIMEOUT', $result->failure->providerCode);
    }

    public function test_unknown_failure_category_is_other(): void
    {
        $body = str_replace('"category": "transport"', '"category": "something_new"', self::read('send-failed-delivery.json'));
        self::assertStringContainsString('something_new', $body);

        $result = self::transport(new RecordingHttpClient(new Response(200, [], $body)))->send(self::document());

        self::assertSame(FailureCategory::Other, $result->failure?->category);
    }

    public function test_unknown_delivery_status_is_unknown_state(): void
    {
        $data = self::json('send-pending.json');
        $data['deliveryStatus'] = 'teleported';

        $result = self::transport(new RecordingHttpClient(new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR))))->send(self::document());

        self::assertSame(DeliveryState::Unknown, $result->state);
    }

    public function test_400_is_document_rejected_with_errors(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::fixture(400, 'error-400.json')))->send(self::document()));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertFalse($exception->isRetryable());
        self::assertSame(self::json('error-400.json')['errors'], $exception->errors['errors']);
        self::assertSame(self::json('error-400.json')['invalidInputDetails'], $exception->errors['invalidInputDetails']);
        self::assertStringContainsString('The receiver EndpointID does not match the recipient.', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function authenticationStatuses(): iterable
    {
        yield '401' => [401];
        yield '403' => [403];
    }

    #[DataProvider('authenticationStatuses')]
    public function test_401_and_403_are_authentication_failed(int $status): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::fixture($status, 'error-401.json')))->send(self::document()));

        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertStringContainsString((string) $status, $exception->getMessage());
    }

    public function test_422_is_recipient_unreachable_with_category(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::fixture(422, 'error-422.json')))->send(self::document()));

        self::assertInstanceOf(RecipientUnreachable::class, $exception);
        self::assertSame(FailureCategory::RecipientNotFound, $exception->category);
        self::assertStringContainsString('0208:0123456789AbC', $exception->getMessage());
    }

    public function test_429_is_rate_limited_with_retry_after(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(429, ['Retry-After' => '17'], '{"success":false,"errors":{}}')))->send(self::document()));

        self::assertInstanceOf(RateLimited::class, $exception);
        self::assertSame(17, $exception->retryAfterSeconds);
        self::assertTrue($exception->isRetryable());
    }

    public function test_503_is_transport_unavailable(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(503, [], '{"success":false,"errors":{}}')))->send(self::document()));

        self::assertInstanceOf(TransportUnavailable::class, $exception);
        self::assertTrue($exception->isRetryable());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function possiblyAcceptedStatuses(): iterable
    {
        yield '500' => [500, '{"success":false,"errors":{}}'];
        yield '502' => [502, 'Bad Gateway'];
        yield '501' => [501, 'Not Implemented'];
        yield '504' => [504, 'Gateway Timeout'];
        yield '507' => [507, 'Insufficient Storage'];
        yield '524' => [524, 'A timeout occurred'];
        yield '599' => [599, ''];
    }

    #[DataProvider('possiblyAcceptedStatuses')]
    public function test_server_error_on_send_is_outcome_unknown(int $status, string $body): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response($status, [], $body)))->send(self::document()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertFalse($exception->isRetryable());
        self::assertStringContainsString('0208:0123456789AbC', $exception->getMessage());
        self::assertStringContainsString('HTTP '.$status, $exception->getMessage());
        self::assertStringContainsString('check the document list before resending', strtolower($exception->getMessage()));
    }

    public function test_other_status_is_unexpected(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(418, [], '{"success":false}')))->send(self::document()));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertSame(418, $exception->status);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableSuccesses(): iterable
    {
        yield 'not json' => ['<html>maintenance</html>'];
        yield 'without success' => ['{"id":"doc_1"}'];
        yield 'with success false' => ['{"success":false,"id":"doc_1"}'];
        yield 'without id' => ['{"success":true}'];
        yield 'with empty id' => ['{"success":true,"id":""}'];
    }

    #[DataProvider('unreadableSuccesses')]
    public function test_unreadable_200_on_send_is_outcome_unknown(string $body): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(200, [], $body)))->send(self::document()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertFalse($exception->isRetryable());
        self::assertStringContainsString('0208:0123456789AbC', $exception->getMessage());
        self::assertStringContainsString('HTTP 200', $exception->getMessage());
        self::assertStringContainsString('check the document list before resending', strtolower($exception->getMessage()));
        $previous = $exception->getPrevious();
        self::assertInstanceOf(UnexpectedResponse::class, $previous);
        self::assertSame(200, $previous->status);
        self::assertSame($body, $previous->bodyExcerpt);
    }

    public function test_secret_cut_at_the_excerpt_boundary_is_redacted(): void
    {
        // The secret starts ten bytes before the excerpt limit, so a body cut first and
        // redacted afterwards would keep its first ten bytes.
        $body = str_repeat('x', UnexpectedResponse::EXCERPT_BYTES - 10).self::SECRET.' trailing text';

        $send = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(200, [], $body)))->send(self::document()));
        $status = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(200, [], $body)))->status('doc_1'));

        self::assertInstanceOf(UnexpectedResponse::class, $send->getPrevious());
        self::assertInstanceOf(UnexpectedResponse::class, $status);
        foreach ([$send->getPrevious(), $status] as $exception) {
            self::assertStringNotContainsString('secret_', $exception->bodyExcerpt);
            self::assertStringStartsWith(str_repeat('x', UnexpectedResponse::EXCERPT_BYTES - 10).'[redacted]', $exception->bodyExcerpt);
        }
    }

    public function test_timeout_after_send_is_outcome_unknown(): void
    {
        $failure = self::requestException('Operation timed out after 30001 milliseconds');

        $exception = self::capture(fn () => self::transport(new RecordingHttpClient($failure))->send(self::document()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertFalse($exception->isRetryable());
        self::assertStringContainsString('check', strtolower($exception->getMessage()));
        self::assertStringContainsString('0208:0123456789AbC', $exception->getMessage());
        self::assertStringNotContainsString(self::SECRET, $exception->getMessage());
        self::assertInstanceOf(OutcomeUnknown::class, $exception->getPrevious());
        self::assertSame($failure, $exception->getPrevious()->getPrevious());
    }

    public function test_status_network_failure_is_retryable(): void
    {
        $status = self::capture(fn () => self::transport(new RecordingHttpClient(self::networkException('Could not resolve host')))->status('doc_1'));
        $send = self::capture(fn () => self::transport(new RecordingHttpClient(self::networkException('Could not resolve host')))->send(self::document()));

        self::assertInstanceOf(TransportUnavailable::class, $status);
        self::assertTrue($status->isRetryable());
        self::assertInstanceOf(OutcomeUnknown::class, $send);
        self::assertFalse($send->isRetryable());
    }

    public function test_status_request_and_mapping(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'document.json'));

        $status = self::transport($recorder)->status('doc_01/failed');

        $request = $recorder->requests()[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://app.recommand.eu/api/v1/documents/doc_01%2Ffailed', (string) $request->getUri());
        self::assertSame('Basic '.base64_encode(self::KEY_ID.':'.self::SECRET), $request->getHeaderLine('Authorization'));
        self::assertSame('doc_01/failed', $status->documentId);
        self::assertSame(DeliveryState::Failed, $status->state);
        self::assertSame(FailureCategory::RecipientRejected, $status->failure?->category);
        self::assertSame('Rejected by the receiver.', $status->failure->message);
        self::assertNull($status->failure->providerCode);
        self::assertEquals(new DateTimeImmutable('2026-11-12T09:16:00.000Z'), $status->changedAt);
    }

    public function test_status_404_names_the_document_id(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(404, [], '{"success":false,"errors":{"document":["Not found"]}}')))->status('doc_missing'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertSame(404, $exception->status);
        self::assertStringContainsString('doc_missing', $exception->getMessage());
    }

    public function test_status_of_empty_id_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::transport(new RecordingHttpClient)->status('');
    }

    public function test_secret_never_leaks(): void
    {
        $config = self::config();
        self::assertStringNotContainsString('secret_', var_export($config, true));
        self::assertStringNotContainsString('secret_', print_r($config, true));
        self::assertStringNotContainsString('key_01', var_export($config, true));
        self::assertStringNotContainsString('key_01', print_r($config, true));
        self::assertStringNotContainsString('secret_', var_export(self::transport(new RecordingHttpClient), true));

        $echo = json_encode([
            'authorization' => 'Basic '.base64_encode(self::KEY_ID.':'.self::SECRET),
            'decoded' => self::KEY_ID.':'.self::SECRET,
        ], JSON_THROW_ON_ERROR);
        $scenarios = [
            fn () => self::transport(new RecordingHttpClient(new Response(418, [], $echo)))->send(self::document()),
            fn () => self::transport(new RecordingHttpClient(new Response(200, [], $echo)))->send(self::document()),
            fn () => self::transport(new RecordingHttpClient(new Response(200, [], 'not json '.$echo)))->send(self::document()),
            fn () => self::transport(new RecordingHttpClient(new Response(400, [], '{"success":false,"errors":{"echo":'.$echo.'}}')))->send(self::document()),
            fn () => self::transport(new RecordingHttpClient(new Response(404, [], $echo)))->status('doc_1'),
            fn () => self::transport(new RecordingHttpClient(self::fixture(401, 'error-401.json')))->send(self::document()),
            fn () => self::transport(new RecordingHttpClient(self::fixture(422, 'error-422.json')))->send(self::document()),
            fn () => self::transport(new RecordingHttpClient(new Response(429)))->send(self::document()),
            fn () => self::transport(new RecordingHttpClient(new Response(500, [], $echo)))->send(self::document()),
            fn () => self::transport(new RecordingHttpClient(new Response(502, [], $echo)))->send(self::document()),
            fn () => self::transport(new RecordingHttpClient(new Response(503, [], $echo)))->send(self::document()),
            fn () => self::transport(new RecordingHttpClient(new Response(504, [], $echo)))->send(self::document()),
            fn () => self::transport(new RecordingHttpClient(self::requestException('failed: '.self::SECRET)))->send(self::document()),
            fn () => self::transport(new RecordingHttpClient(self::networkException('failed: '.self::SECRET)))->status('doc_1'),
        ];

        foreach ($scenarios as $index => $scenario) {
            // Only exceptions raised by the driver are checked: a chained client exception
            // carries whatever message the PSR-18 client produced.
            $exception = self::capture($scenario);
            self::assertStringNotContainsString('secret_', $exception->getMessage(), "scenario $index: ".$exception::class);
            $excerpted = $exception instanceof UnexpectedResponse ? $exception : $exception->getPrevious();
            if ($excerpted instanceof UnexpectedResponse) {
                self::assertStringNotContainsString('secret_', $excerpted->getMessage(), "scenario $index previous");
                self::assertStringNotContainsString('secret_', $excerpted->bodyExcerpt, "scenario $index excerpt");
                self::assertStringNotContainsString(base64_encode(self::KEY_ID.':'.self::SECRET), $excerpted->bodyExcerpt, "scenario $index excerpt");
            }
            if ($exception instanceof DocumentRejected) {
                self::assertStringNotContainsString('secret_', var_export($exception->errors, true), "scenario $index errors");
            }
        }
    }

    public function test_http_base_url_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RecommandConfig(self::KEY_ID, self::SECRET, 'c_01example', 'http://app.recommand.eu');
    }

    public function test_empty_credentials_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RecommandConfig('', self::SECRET, 'c_01example');
    }

    public function test_short_credentials_are_refused_without_quoting_them(): void
    {
        foreach ([['k_01234', self::SECRET], [self::KEY_ID, 's_01234'], ['  k_01234   ', self::SECRET]] as [$keyId, $secret]) {
            $exception = self::capture(fn () => new RecommandConfig($keyId, $secret, 'c_01example'));

            self::assertInstanceOf(InvalidArgumentException::class, $exception);
            self::assertStringContainsString('8 characters', $exception->getMessage());
            self::assertStringNotContainsString('_01234', $exception->getMessage());
            self::assertStringNotContainsString(self::SECRET, $exception->getMessage());
        }
    }

    public function test_company_id_is_trimmed(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'send-pending.json'));

        self::transport($recorder, companyId: "  c_01example\n")->send(self::document());

        self::assertSame('c_01example', self::config("  c_01example\n")->companyId);
        self::assertSame('https://app.recommand.eu/api/v1/c_01example/send', (string) $recorder->requests()[0]->getUri());
    }

    public function test_encoded_credentials_in_client_errors_are_redacted(): void
    {
        $leak = 'failed: '.base64_encode(self::SECRET).' / '.rawurlencode(self::KEY_ID.'/x').' / '.base64_encode(self::KEY_ID);

        $status = self::capture(fn () => self::transport(new RecordingHttpClient(self::networkException($leak)))->status('doc_1'));

        self::assertInstanceOf(TransportUnavailable::class, $status);
        self::assertStringNotContainsString(base64_encode(self::SECRET), $status->getMessage());
        self::assertStringNotContainsString(base64_encode(self::KEY_ID), $status->getMessage());
        self::assertStringNotContainsString(self::KEY_ID, $status->getMessage());
    }

    public function test_success_without_id_is_outcome_unknown(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(200, [], '{"success":true,"deliveryStatus":"pending"}')))->send(self::document()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('check the document list', strtolower($exception->getMessage()));
        self::assertStringContainsString('no document id', $exception->getMessage());
    }

    public function test_whitespace_status_id_is_refused(): void
    {
        $recorder = new RecordingHttpClient;

        $exception = self::capture(fn () => self::transport($recorder)->status("  \t"));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame([], $recorder->requests());
    }

    /**
     * @return iterable<string, array{Response, class-string<Throwable>, bool}>
     */
    public static function statusFailures(): iterable
    {
        yield '401' => [new Response(401, [], '{"success":false}'), AuthenticationFailed::class, false];
        yield '403' => [new Response(403, [], '{"success":false}'), AuthenticationFailed::class, false];
        yield '429' => [new Response(429, ['Retry-After' => '12'], '{"success":false}'), RateLimited::class, true];
        yield '500' => [new Response(500, [], 'oops'), TransportUnavailable::class, true];
        yield '503' => [new Response(503, [], ''), TransportUnavailable::class, true];
        yield '200 without a document' => [new Response(200, [], '{"success":true}'), UnexpectedResponse::class, false];
    }

    /**
     * @param  class-string<Throwable>  $expected
     */
    #[DataProvider('statusFailures')]
    public function test_status_failures_use_the_common_mapping(Response $response, string $expected, bool $retryable): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient($response))->status('doc_1'));

        self::assertInstanceOf($expected, $exception);
        self::assertInstanceOf(TransportException::class, $exception);
        self::assertSame($retryable, $exception->isRetryable());
        if ($exception instanceof RateLimited) {
            self::assertSame(12, $exception->retryAfterSeconds);
        }
        if ($exception instanceof AuthenticationFailed || $exception instanceof TransportUnavailable) {
            self::assertStringContainsString('doc_1', $exception->getMessage());
            self::assertStringContainsString('HTTP '.$response->getStatusCode(), $exception->getMessage());
        }
    }

    public function test_status_with_null_delivery_status_is_pending(): void
    {
        $data = self::json('document.json');
        self::assertIsArray($data['document']);
        $data['document']['deliveryStatus'] = null;
        $data['document']['deliveries'] = [];

        $status = self::transport(new RecordingHttpClient(new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR))))->status('doc_01failed');

        self::assertSame(DeliveryState::Pending, $status->state);
        self::assertNull($status->failure);
        self::assertNull($status->changedAt);
    }

    public function test_invalid_input_details_are_quoted_as_path_and_message(): void
    {
        $body = '{"success":false,"invalidInputDetails":[{"path":["recipient"],"message":"Invalid Peppol address"},{"path":["document","lines",0],"message":"Required"}]}';

        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(400, [], $body)))->send(self::document()));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertSame('Recommand rejected the document (HTTP 400): recipient: Invalid Peppol address; document.lines.0: Required.', $exception->getMessage());
    }

    private static function config(string $companyId = 'c_01example', string $baseUrl = 'https://app.recommand.eu'): RecommandConfig
    {
        return new RecommandConfig(self::KEY_ID, self::SECRET, $companyId, $baseUrl);
    }

    private static function transport(RecordingHttpClient $recorder, string $companyId = 'c_01example', string $baseUrl = 'https://app.recommand.eu'): RecommandTransport
    {
        $factory = new Psr17Factory;

        return new RecommandTransport(self::config($companyId, $baseUrl), new HttpClient($recorder, $factory, $factory));
    }

    private static function document(): OutboundDocument
    {
        return new OutboundDocument(
            self::XML,
            new ParticipantId('0208', '0987654321'),
            new ParticipantId('0208', '0123456789AbC'),
            new DocumentTypeId(PeppolBis3::INVOICE),
            new ProcessId(PeppolBis3::PROCESS),
        );
    }

    private static function fixture(int $status, string $name): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], self::read($name));
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(string $name): array
    {
        /** @var array<string, mixed> */
        return json_decode(self::read($name), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function read(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../../fixtures/recommand/'.$name);
        self::assertIsString($contents);

        return $contents;
    }

    private static function networkException(string $message): NetworkExceptionInterface
    {
        return new class(new Request('GET', 'https://app.recommand.eu'), $message) extends RuntimeException implements NetworkExceptionInterface
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

    private static function requestException(string $message): RequestExceptionInterface
    {
        return new class(new Request('POST', 'https://app.recommand.eu'), $message) extends RuntimeException implements RequestExceptionInterface
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
