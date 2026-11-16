<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;
use IntrAgile\EInvoicing\Transport\Peppol\XRechnungPeppol;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class B2BrouterSendTest extends TestCase
{
    /** A Peppol document type the driver has no B2Brouter code for. */
    private const ORDER = 'urn:oasis:names:specification:ubl:schema:xsd:Order-2::Order##urn:fdc:peppol.eu:poacc:trns:order:3::2.1';

    use B2BrouterFixtures;

    private const XML = '<?xml version="1.0" encoding="UTF-8"?><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
        .' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><cbc:ID>INV-2026-001</cbc:ID></Invoice>';

    public function test_send_posts_raw_xml_with_query_and_headers(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(201, 'import-201.json'));

        $result = self::transport($recorder)->send(self::document());

        self::assertCount(1, $recorder->requests());
        $request = $recorder->requests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://api.b2brouter.net/accounts/4711/invoices/import?send_after_import=true&issued=true&transport_type_code_for_contact=peppol&document_type_code_for_contact=xml.ubl.invoice.bis3',
            (string) $request->getUri(),
        );
        self::assertSame(self::KEY, $request->getHeaderLine('X-B2B-API-Key'));
        self::assertSame('2026-06-26', $request->getHeaderLine('X-B2B-API-Version'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('application/octet-stream', $request->getHeaderLine('Content-Type'));
        self::assertFalse($request->hasHeader('Authorization'));
        self::assertSame(self::XML, (string) $request->getBody());

        self::assertSame('482913', $result->documentId);
        self::assertSame(DeliveryState::Pending, $result->state);
        self::assertNull($result->failure);
    }

    public function test_credit_note_uses_the_credit_note_code(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(201, 'import-201.json'));

        self::transport($recorder)->send(self::document(PeppolBis3::CREDIT_NOTE));

        self::assertStringEndsWith('&document_type_code_for_contact=xml.ubl.credit_note.bis3', (string) $recorder->requests()[0]->getUri());
    }

    public function test_unmapped_doctype_is_refused_before_any_request(): void
    {
        $recorder = new RecordingHttpClient;

        $exception = self::capture(fn () => self::transport($recorder)->send(self::document(self::ORDER)));

        self::assertInstanceOf(UnsupportedDocument::class, $exception);
        self::assertStringContainsString(self::ORDER, $exception->getMessage());
        self::assertStringContainsString('document type code', $exception->getMessage());
        self::assertSame([], $recorder->requests());
    }

    public function test_xrechnung_is_sent_with_its_default_code(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(201, 'import-201.json'));

        self::transport($recorder)->send(self::document(XRechnungPeppol::CII_INVOICE));

        self::assertStringEndsWith('&document_type_code_for_contact=xml.cii.cross_industry_invoice.xrechnung.v3.0', (string) $recorder->requests()[0]->getUri());
    }

    public function test_configured_xrechnung_code_is_used(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(201, 'import-201.json'));

        self::transport($recorder, [XRechnungPeppol::UBL_INVOICE => 'xml.ubl.invoice.xrechnung.v30'])
            ->send(self::document(XRechnungPeppol::UBL_INVOICE));

        self::assertStringEndsWith('&document_type_code_for_contact=xml.ubl.invoice.xrechnung.v30', (string) $recorder->requests()[0]->getUri());
    }

    public function test_send_result_of_an_error_state_carries_the_failure(): void
    {
        $result = self::transport(new RecordingHttpClient(self::fixture(201, 'invoice-error.json')))->send(self::document());

        self::assertSame('482914', $result->documentId);
        self::assertSame(DeliveryState::Failed, $result->state);
        self::assertNotNull($result->failure);
        self::assertSame(FailureCategory::RecipientNotFound, $result->failure->category);
    }

    /**
     * @return iterable<string, array{Response, class-string, list<string>, ?int}>
     */
    public static function errorStatuses(): iterable
    {
        yield '401' => [self::fixture(401, 'error-401.json'), AuthenticationFailed::class, ['HTTP 401'], null];
        yield '403' => [self::fixture(403, 'error-403.json'), AuthenticationFailed::class, ['HTTP 403', 'no_account_group', 'permission'], null];
        yield '406' => [self::fixture(406, 'error-406.json'), DocumentRejected::class, ['HTTP 406', '"The document could not be imported: the buyer has no Peppol identifier."'], null];
        yield '409' => [self::fixture(409, 'error-409.json'), DocumentRejected::class, ['HTTP 409', 'another request'], null];
        yield '422 taken' => [self::fixture(422, 'error-422-taken.json'), DocumentRejected::class, [FailureCategory::Duplicate->value, 'an invoice with this number exists; check it before resending', 'INV-2026-001'], null];
        yield '422 other' => [self::fixture(422, 'error-422-blank.json'), DocumentRejected::class, ['HTTP 422', 'parameter_blank', 'due_date', 'The due date must not be blank.'], null];
        yield '400' => [self::fixture(400, 'error-400.json'), DocumentRejected::class, ['HTTP 400', 'invalid_request_params'], null];
        yield '429 with Retry-After' => [new Response(429, ['Retry-After' => '30']), RateLimited::class, ['HTTP 429'], 30];
        yield '429 without Retry-After' => [new Response(429), RateLimited::class, ['HTTP 429'], null];
        yield '500' => [new Response(500, [], 'Internal Server Error'), OutcomeUnknown::class, ['HTTP 500', '"INV-2026-001"'], null];
        yield '502' => [new Response(502), OutcomeUnknown::class, ['HTTP 502', '"INV-2026-001"'], null];
        yield '503' => [new Response(503), OutcomeUnknown::class, ['HTTP 503', '"INV-2026-001"'], null];
        yield '504' => [new Response(504), OutcomeUnknown::class, ['HTTP 504', '"INV-2026-001"'], null];
        yield '404' => [self::fixture(404, 'error-404.json'), UnexpectedResponse::class, ['HTTP 404'], null];
    }

    /**
     * @param  class-string  $expected
     * @param  list<string>  $fragments
     */
    #[DataProvider('errorStatuses')]
    public function test_error_status_mapping(Response $response, string $expected, array $fragments, ?int $retryAfter): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient($response))->send(self::document()));

        self::assertInstanceOf($expected, $exception);
        foreach ($fragments as $fragment) {
            self::assertStringContainsString($fragment, $exception->getMessage());
        }
        if ($exception instanceof RateLimited) {
            self::assertSame($retryAfter, $exception->retryAfterSeconds);
        }
        if ($exception instanceof DocumentRejected) {
            self::assertArrayHasKey('error', $exception->errors);
        }
    }

    public function test_lost_answer_is_outcome_unknown_naming_the_number(): void
    {
        $recorder = new RecordingHttpClient(self::networkException('Operation timed out after 30001 milliseconds'));

        $exception = self::capture(fn () => self::transport($recorder)->send(self::document()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('invoice number "INV-2026-001"', $exception->getMessage());
        self::assertStringContainsString('before resending', $exception->getMessage());
    }

    public function test_lost_answer_without_a_readable_number_says_so(): void
    {
        $recorder = new RecordingHttpClient(self::networkException('Connection reset'));
        $document = new OutboundDocument(
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>',
            new ParticipantId('0208', '0987654321'),
            new ParticipantId('0088', '9508397101047'),
            new DocumentTypeId(PeppolBis3::INVOICE),
            new ProcessId(PeppolBis3::PROCESS),
        );

        $exception = self::capture(fn () => self::transport($recorder)->send($document));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('invoice number cannot be read', $exception->getMessage());
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
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(201, [], $body)))->send(self::document()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('"INV-2026-001"', $exception->getMessage());
    }

    public function test_key_never_appears_in_messages(): void
    {
        $echo = sprintf('{"error":{"code":"invalid_api_key","message":"Key %s, %s, %s is not valid"}}', self::KEY, rawurlencode(self::KEY), base64_encode(self::KEY));
        $responses = [
            new Response(401, [], $echo),
            new Response(500, [], $echo),
            new Response(406, [], $echo),
            new Response(422, [], $echo),
            new Response(418, [], $echo),
        ];
        foreach ($responses as $response) {
            $exception = self::capture(fn () => self::transport(new RecordingHttpClient($response))->send(self::document()));
            $text = $exception->getMessage().($exception instanceof UnexpectedResponse ? $exception->bodyExcerpt : '')
                .($exception instanceof DocumentRejected ? json_encode($exception->errors, JSON_THROW_ON_ERROR) : '');

            self::assertStringNotContainsString(self::KEY, $text);
            self::assertStringNotContainsString(base64_encode(self::KEY), $text);
        }

        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::networkException('cURL error: header X-B2B-API-Key: '.self::KEY)))->send(self::document()));
        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringNotContainsString(self::KEY, $exception->getMessage());
        $previous = $exception->getPrevious();
        self::assertNotNull($previous);
        self::assertStringNotContainsString(self::KEY, $previous->getMessage());
    }

    private static function document(string $documentType = PeppolBis3::INVOICE): OutboundDocument
    {
        return new OutboundDocument(
            self::XML,
            new ParticipantId('0208', '0987654321'),
            new ParticipantId('0088', '9508397101047'),
            new DocumentTypeId($documentType),
            new ProcessId(PeppolBis3::PROCESS),
        );
    }
}
