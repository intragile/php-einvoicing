<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\France;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\B2BrouterFrance;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\FrenchDocument;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\FrenchDocumentType;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class B2BrouterFranceSendTest extends TestCase
{
    use CapturesThrowable;

    private const KEY = 'stag_FAKEKEY000000';

    private const ACCOUNT = 4711;

    private const BASE = 'https://api-staging.b2brouter.net';

    private const XML = '<?xml version="1.0" encoding="UTF-8"?><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
        .' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><cbc:ID>FA-2026-0001</cbc:ID></Invoice>';

    private const IMPORT = '/accounts/4711/invoices/import?send_after_import=true&issued=true&transport_type_code_for_contact=peppol&document_type_code_for_contact=';

    public function test_ubl_invoice_is_imported_with_frcius_code(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(201, 'import-201-new.json'));

        self::france($recorder)->send(self::document());

        self::assertCount(1, $recorder->requests());
        $request = $recorder->requests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE.self::IMPORT.'xml.ubl.invoice.frcius.v1', (string) $request->getUri());
        self::assertSame('application/octet-stream', $request->getHeaderLine('Content-Type'));
        self::assertSame(self::KEY, $request->getHeaderLine('X-B2B-API-Key'));
        self::assertSame(B2BrouterConfig::API_VERSION, $request->getHeaderLine('X-B2B-API-Version'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame(self::XML, (string) $request->getBody());
    }

    public function test_factur_x_is_imported_as_octet_stream_with_facturx_code(): void
    {
        $pdf = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n1 0 obj << >> endobj\n%%EOF\n";
        $recorder = new RecordingHttpClient(self::fixture(201, 'import-201-new.json'));

        self::france($recorder)->send(new FrenchDocument($pdf, FrenchDocumentType::FacturX, 'FA-2026-0001'));

        $request = $recorder->requests()[0];
        self::assertSame(self::BASE.self::IMPORT.'xml.cii.cross_industry_invoice.facturx.fr.all_profiles.v1', (string) $request->getUri());
        self::assertSame('application/octet-stream', $request->getHeaderLine('Content-Type'));
        self::assertSame($pdf, (string) $request->getBody());
    }

    public function test_every_french_type_is_sent_with_its_code(): void
    {
        foreach (FrenchDocumentType::cases() as $type) {
            $recorder = new RecordingHttpClient(self::fixture(201, 'import-201-new.json'));

            self::france($recorder)->send(new FrenchDocument(self::XML, $type, 'FA-2026-0001'));

            self::assertSame(self::BASE.self::IMPORT.rawurlencode($type->value), (string) $recorder->requests()[0]->getUri());
        }
    }

    public function test_created_invoice_is_pending(): void
    {
        $result = self::france(new RecordingHttpClient(self::fixture(201, 'import-201-new.json')))->send(self::document());

        self::assertSame('590101', $result->documentId);
        self::assertSame(DeliveryState::Pending, $result->state);
        self::assertNull($result->failure);
    }

    /**
     * @return iterable<string, array{string, DeliveryState}>
     */
    public static function states(): iterable
    {
        yield 'new' => ['new', DeliveryState::Pending];
        yield 'sending' => ['sending', DeliveryState::Pending];
        yield 'sent' => ['sent', DeliveryState::Delivered];
        yield 'accepted' => ['accepted', DeliveryState::Delivered];
        yield 'paid' => ['paid', DeliveryState::Delivered];
        yield 'error' => ['error', DeliveryState::Failed];
        yield 'unknown' => ['something_new', DeliveryState::Unknown];
    }

    #[DataProvider('states')]
    public function test_import_state_is_mapped(string $state, DeliveryState $expected): void
    {
        $response = self::json(201, ['invoice' => ['id' => 590110, 'state' => $state]]);

        self::assertSame($expected, self::france(new RecordingHttpClient($response))->send(self::document())->state);
    }

    public function test_errored_invoice_is_failed_with_errors(): void
    {
        $result = self::france(new RecordingHttpClient(self::fixture(201, 'import-201-error.json')))->send(self::document());

        self::assertSame('590102', $result->documentId);
        self::assertSame(DeliveryState::Failed, $result->state);
        self::assertNotNull($result->failure);
        self::assertSame(FailureCategory::Transport, $result->failure->category);
        self::assertSame(
            'The buyer SIREN is not listed in the French directory.; No routing address could be found for the buyer.',
            $result->failure->message,
        );
        self::assertNull($result->failure->providerCode);
    }

    public function test_unregistered_receiver_without_error_code_is_recipient_not_found(): void
    {
        $response = self::json(201, ['invoice' => ['id' => 590112, 'state' => 'error', 'error_code' => null, 'errors' => ['The buyer is not registered to receive invoices.']]]);

        $result = self::france(new RecordingHttpClient($response))->send(self::document());

        self::assertSame(DeliveryState::Failed, $result->state);
        self::assertNotNull($result->failure);
        self::assertSame(FailureCategory::RecipientNotFound, $result->failure->category);
        self::assertSame('The buyer is not registered to receive invoices.', $result->failure->message);
        self::assertNull($result->failure->providerCode);
    }

    public function test_errored_invoice_without_errors_list_uses_error_message(): void
    {
        $response = self::json(201, ['invoice' => ['id' => 590111, 'state' => 'error', 'error_code' => 'X1', 'error_message' => 'Delivery failed.', 'errors' => []]]);

        $result = self::france(new RecordingHttpClient($response))->send(self::document());

        self::assertNotNull($result->failure);
        self::assertSame('Delivery failed.', $result->failure->message);
        self::assertSame('X1', $result->failure->providerCode);
    }

    public function test_422_schematron_error_is_document_rejected(): void
    {
        $exception = self::capture(fn () => self::france(new RecordingHttpClient(self::fixture(422, 'import-422-schematron.json')))->send(self::document()));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        $message = $exception->getMessage();
        self::assertStringContainsString('G1.24', $message);
        self::assertStringContainsString('HTTP 422', $message);
        self::assertStringContainsString('invoice number "FA-2026-0001"', $message);
        self::assertStringContainsString('B2Brouter invoice 590103', $message);
        self::assertStringContainsString('PATCH', $message);
        self::assertStringContainsString('delete', $message);
        self::assertStringContainsString('parameter_taken', $message);
        self::assertArrayHasKey('error', $exception->errors);
        self::assertSame('590103', $exception->errors['invoice_id'] ?? null);
    }

    public function test_422_with_invoice_object_names_its_id(): void
    {
        $response = self::json(422, [
            'error' => ['code' => 'invalid_document', 'message' => 'Rule BR-FR-01 failed.'],
            'invoice' => ['id' => 590104, 'state' => 'error'],
        ]);

        $exception = self::capture(fn () => self::france(new RecordingHttpClient($response))->send(self::document()));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringContainsString('B2Brouter invoice 590104', $exception->getMessage());
        self::assertStringContainsString('BR-FR-01', $exception->getMessage());
    }

    public function test_422_without_invoice_id_still_explains_the_correction(): void
    {
        $response = self::json(422, ['error' => ['code' => 'parameter_blank', 'message' => 'The due date must not be blank.', 'param' => 'due_date']]);

        $exception = self::capture(fn () => self::france(new RecordingHttpClient($response))->send(self::document()));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringContainsString('parameter_blank on due_date', $exception->getMessage());
        self::assertStringContainsString('"The due date must not be blank."', $exception->getMessage());
        self::assertStringContainsString('PATCH', $exception->getMessage());
        self::assertArrayNotHasKey('invoice_id', $exception->errors);
    }

    public function test_422_number_taken_is_duplicate(): void
    {
        $response = self::json(422, ['error' => ['code' => 'parameter_taken', 'message' => 'The number has already been taken.', 'param' => 'number']]);

        $exception = self::capture(fn () => self::france(new RecordingHttpClient($response))->send(self::document()));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringContainsString(FailureCategory::Duplicate->value, $exception->getMessage());
        self::assertStringContainsString('"FA-2026-0001"', $exception->getMessage());
    }

    public function test_406_is_unsupported_document(): void
    {
        $exception = self::capture(fn () => self::france(new RecordingHttpClient(self::fixture(406, 'import-406.json')))->send(self::document()));

        self::assertInstanceOf(UnsupportedDocument::class, $exception);
        self::assertStringContainsString('HTTP 406', $exception->getMessage());
        self::assertStringContainsString('application/octet-stream', $exception->getMessage());
        self::assertStringContainsString('xml.ubl.invoice.frcius.v1', $exception->getMessage());
        self::assertStringContainsString('"The file type cannot be imported."', $exception->getMessage());
    }

    public function test_403_is_transport_exception(): void
    {
        $exception = self::capture(fn () => self::france(new RecordingHttpClient(self::fixture(403, 'import-403.json')))->send(self::document()));

        self::assertInstanceOf(TransportException::class, $exception);
        self::assertFalse($exception->isRetryable());
        self::assertStringContainsString('HTTP 403', $exception->getMessage());
        self::assertStringContainsString('permission', $exception->getMessage());
        self::assertStringContainsString('"The account is not enabled for this action."', $exception->getMessage());
    }

    public function test_401_is_authentication_failed(): void
    {
        $response = self::json(401, ['error' => ['code' => 'unauthorized', 'message' => 'Invalid API Key provided.']]);

        $exception = self::capture(fn () => self::france(new RecordingHttpClient($response))->send(self::document()));

        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertStringContainsString('HTTP 401', $exception->getMessage());
    }

    public function test_429_is_rate_limited(): void
    {
        $exception = self::capture(fn () => self::france(new RecordingHttpClient(new Response(429, ['Retry-After' => '20'])))->send(self::document()));

        self::assertInstanceOf(RateLimited::class, $exception);
        self::assertSame(20, $exception->retryAfterSeconds);
    }

    public function test_redirect_is_unexpected_without_body(): void
    {
        $response = new Response(302, ['Location' => 'https://elsewhere.example/?token=secret'], 'Moved to https://elsewhere.example/?token=secret');

        $exception = self::capture(fn () => self::france(new RecordingHttpClient($response))->send(self::document()));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertSame('', $exception->bodyExcerpt);
        self::assertStringNotContainsString('token=secret', $exception->getMessage());
    }

    public function test_no_answer_is_outcome_unknown(): void
    {
        $recorder = new RecordingHttpClient(self::networkException('Operation timed out after 30001 milliseconds'));

        $exception = self::capture(fn () => self::france($recorder)->send(self::document()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('invoice number "FA-2026-0001"', $exception->getMessage());
        self::assertStringContainsString('before resending', $exception->getMessage());
        self::assertStringContainsString('duplicate', $exception->getMessage());
    }

    public function test_no_answer_without_number_says_so(): void
    {
        $recorder = new RecordingHttpClient(self::networkException('Connection reset'));

        $exception = self::capture(fn () => self::france($recorder)->send(new FrenchDocument(self::XML, FrenchDocumentType::UblInvoice, null)));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('invoice number cannot be read', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function serverErrors(): iterable
    {
        yield '500' => [500];
        yield '502' => [502];
        yield '503' => [503];
        yield '504' => [504];
    }

    #[DataProvider('serverErrors')]
    public function test_5xx_is_outcome_unknown(int $status): void
    {
        $exception = self::capture(fn () => self::france(new RecordingHttpClient(new Response($status, [], 'Server Error')))->send(self::document()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString(sprintf('HTTP %d', $status), $exception->getMessage());
        self::assertStringContainsString('"FA-2026-0001"', $exception->getMessage());
        self::assertStringContainsString('before resending', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableCreated(): iterable
    {
        yield 'not JSON' => ['<html>created</html>'];
        yield 'no invoice' => ['{"ok":true}'];
        yield 'no id' => ['{"invoice":{"state":"new"}}'];
        yield 'zero id' => ['{"invoice":{"id":0,"state":"new"}}'];
    }

    #[DataProvider('unreadableCreated')]
    public function test_unreadable_201_is_outcome_unknown(string $body): void
    {
        $exception = self::capture(fn () => self::france(new RecordingHttpClient(new Response(201, [], $body)))->send(self::document()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('"FA-2026-0001"', $exception->getMessage());
        self::assertStringContainsString('before resending', $exception->getMessage());
    }

    public function test_api_key_never_appears_in_exception(): void
    {
        $echo = sprintf('{"error":{"code":"invalid","message":"Key %s, %s, %s is not valid","invoice_id":590105}}', self::KEY, rawurlencode(self::KEY), base64_encode(self::KEY));
        foreach ([400, 401, 403, 406, 409, 418, 422, 500] as $status) {
            $exception = self::capture(fn () => self::france(new RecordingHttpClient(new Response($status, [], $echo)))->send(self::document()));
            $text = $exception->getMessage().($exception instanceof UnexpectedResponse ? $exception->bodyExcerpt : '')
                .($exception instanceof DocumentRejected ? json_encode($exception->errors, JSON_THROW_ON_ERROR) : '');

            self::assertStringNotContainsString(self::KEY, $text, (string) $status);
            self::assertStringNotContainsString(base64_encode(self::KEY), $text, (string) $status);
        }

        $errored = self::json(201, ['invoice' => ['id' => 590106, 'state' => 'error', 'errors' => ['Key '.self::KEY.' refused']]]);
        $failure = self::france(new RecordingHttpClient($errored))->send(self::document())->failure;
        self::assertNotNull($failure);
        self::assertStringNotContainsString(self::KEY, (string) $failure->message);

        $exception = self::capture(fn () => self::france(new RecordingHttpClient(self::networkException('cURL error: header X-B2B-API-Key: '.self::KEY)))->send(self::document()));
        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringNotContainsString(self::KEY, $exception->getMessage());
        $previous = $exception->getPrevious();
        self::assertNotNull($previous);
        self::assertStringNotContainsString(self::KEY, $previous->getMessage());
    }

    /**
     * The client over a plain HTTP client built without secrets: the client must add the API key
     * to the redacted values itself.
     */
    private static function france(RecordingHttpClient $recorder): B2BrouterFrance
    {
        $factory = new Psr17Factory;

        return new B2BrouterFrance(
            new B2BrouterConfig(self::KEY, self::ACCOUNT, self::BASE),
            new HttpClient($recorder, $factory, $factory),
        );
    }

    private static function document(): FrenchDocument
    {
        return new FrenchDocument(self::XML, FrenchDocumentType::UblInvoice, 'FA-2026-0001');
    }

    private static function fixture(int $status, string $name): Response
    {
        $contents = file_get_contents(__DIR__.'/../../../fixtures/b2brouter-france/'.$name);
        self::assertIsString($contents);

        return new Response($status, ['Content-Type' => 'application/json'], $contents);
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function json(int $status, array $data): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }

    private static function networkException(string $message): NetworkExceptionInterface
    {
        return new class(new Request('POST', self::BASE), $message) extends RuntimeException implements NetworkExceptionInterface
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
