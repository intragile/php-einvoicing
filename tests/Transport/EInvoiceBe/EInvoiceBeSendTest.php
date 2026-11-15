<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\EInvoiceBe;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Peppol\PeppolDocumentTypes;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;
use IntrAgile\EInvoicing\Transport\Peppol\XRechnungPeppol;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EInvoiceBeSendTest extends TestCase
{
    use EInvoiceBeFixtures;

    private const DRAFT = 'doc-7Hq2Lm9Xa4';

    private const UBL_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const UBL_CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';

    public function test_send_is_create_then_send_with_four_ids(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(201, 'create-201.json'), self::fixture(200, 'send-200.json'));

        $result = self::transport($recorder)->send(self::document());

        self::assertCount(2, $recorder->requests());
        [$create, $send] = $recorder->requests();
        self::assertSame('POST', $create->getMethod());
        self::assertSame('https://api.e-invoice.be/api/documents/ubl', (string) $create->getUri());
        self::assertSame('Bearer '.self::KEY, $create->getHeaderLine('Authorization'));
        self::assertSame('application/json', $create->getHeaderLine('Accept'));
        self::assertMatchesRegularExpression('/\Amultipart\/form-data; boundary=[0-9a-f]{32}\z/', $create->getHeaderLine('Content-Type'));

        self::assertSame('POST', $send->getMethod());
        self::assertSame(
            'https://api.e-invoice.be/api/documents/doc-7Hq2Lm9Xa4/send?sender_peppol_scheme=0208&sender_peppol_id=0987654321&receiver_peppol_scheme=0088&receiver_peppol_id=9508397101047',
            (string) $send->getUri(),
        );
        self::assertSame('Bearer '.self::KEY, $send->getHeaderLine('Authorization'));
        self::assertSame('', (string) $send->getBody());

        self::assertSame(self::DRAFT, $result->documentId);
        self::assertSame(DeliveryState::Pending, $result->state);
        self::assertNull($result->failure);
    }

    public function test_participant_values_are_percent_encoded(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(201, 'create-201.json'), self::fixture(200, 'send-200.json'));
        $document = new OutboundDocument(
            self::xml(),
            new ParticipantId('9925', 'BE0123&x=1'),
            new ParticipantId('0088', '9508397101047'),
            new DocumentTypeId(PeppolBis3::INVOICE),
            new ProcessId(PeppolBis3::PROCESS),
        );

        self::transport($recorder)->send($document);

        self::assertStringContainsString('?sender_peppol_scheme=9925&sender_peppol_id=BE0123%26x%3D1&receiver_peppol_scheme=0088', (string) $recorder->requests()[1]->getUri());
    }

    public function test_multipart_body_carries_the_exact_xml(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(201, 'create-201.json'), self::fixture(200, 'send-200.json'));
        $xml = self::xml();

        self::transport($recorder)->send(self::document());

        $create = $recorder->requests()[0];
        $boundary = substr($create->getHeaderLine('Content-Type'), strlen('multipart/form-data; boundary='));
        self::assertSame(
            '--'.$boundary."\r\n"
            .'Content-Disposition: form-data; name="file"; filename="invoice.xml"'."\r\n"
            ."Content-Type: application/xml\r\n"
            ."\r\n"
            .$xml."\r\n"
            .'--'.$boundary."--\r\n",
            (string) $create->getBody(),
        );
    }

    public function test_credit_note_is_sent(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(201, 'create-201.json'), self::fixture(200, 'send-200.json'));

        $result = self::transport($recorder)->send(self::document(PeppolBis3::CREDIT_NOTE, self::xml(root: 'CreditNote', namespace: self::UBL_CREDIT_NOTE)));

        self::assertSame(self::DRAFT, $result->documentId);
        self::assertCount(2, $recorder->requests());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsendable(): iterable
    {
        yield 'XRechnung UBL' => [XRechnungPeppol::UBL_INVOICE, self::xml(XRechnung::CIUS)];
        yield 'XRechnung UBL declared as BIS' => [PeppolBis3::INVOICE, self::xml(XRechnung::CIUS)];
        yield 'CII' => [
            XRechnungPeppol::CII_INVOICE,
            '<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"'
            .' xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">'
            .'<rsm:ExchangedDocumentContext><ram:GuidelineSpecifiedDocumentContextParameter><ram:ID>'.XRechnung::CIUS
            .'</ram:ID></ram:GuidelineSpecifiedDocumentContextParameter></rsm:ExchangedDocumentContext></rsm:CrossIndustryInvoice>',
        ];
        yield 'BIS with an extension suffix' => [PeppolBis3::INVOICE, self::xml(PeppolBis3::CUSTOMIZATION.'#conformant#urn:example.com:extension:1.0')];
        yield 'BIS with a trailing space' => [PeppolBis3::INVOICE, self::xml(PeppolBis3::CUSTOMIZATION.' ')];
        yield 'self-billing' => [PeppolBis3::INVOICE, self::xml('urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:selfbilling:3.0')];
        yield 'no CustomizationID' => [PeppolBis3::INVOICE, '<Invoice xmlns="'.self::UBL_INVOICE.'"/>'];
        yield 'credit note declared, invoice given' => [PeppolBis3::CREDIT_NOTE, self::xml()];
        yield 'invoice declared, credit note given' => [PeppolBis3::INVOICE, self::xml(root: 'CreditNote', namespace: self::UBL_CREDIT_NOTE)];
        yield 'other document type' => ['urn:example:doctype', self::xml()];
    }

    #[DataProvider('unsendable')]
    public function test_xrechnung_is_refused_before_any_request(string $documentType, string $xml): void
    {
        $recorder = new RecordingHttpClient;

        $exception = self::capture(fn () => self::transport($recorder)->send(self::document($documentType, $xml)));

        self::assertInstanceOf(UnsupportedDocument::class, $exception);
        self::assertStringContainsString(PeppolBis3::INVOICE, $exception->getMessage());
        self::assertStringContainsString(PeppolBis3::CREDIT_NOTE, $exception->getMessage());
        self::assertStringContainsString('e-invoice.be does not accept XRechnung or CII', $exception->getMessage());
        self::assertSame([], $recorder->requests());
    }

    public function test_malformed_xml_is_refused_before_any_request(): void
    {
        $recorder = new RecordingHttpClient;

        $exception = self::capture(fn () => self::transport($recorder)->send(self::document(PeppolBis3::INVOICE, '<Invoice>')));

        self::assertInstanceOf(UnsupportedDocument::class, $exception);
        self::assertSame([], $recorder->requests());
    }

    /**
     * @return iterable<string, array{Response, class-string, list<string>, ?int}>
     */
    public static function createErrors(): iterable
    {
        $number = 'invoice number "INV-2026-001"';
        yield '401' => [self::fixture(401, 'error-401.json'), AuthenticationFailed::class, ['HTTP 401'], null];
        yield '403' => [self::fixture(403, 'error-401.json'), AuthenticationFailed::class, ['HTTP 403'], null];
        yield '415' => [self::fixture(415, 'error-415.json'), DocumentRejected::class, ['HTTP 415', '"The UBL file matches no supported document profile."'], null];
        yield '422' => [self::fixture(422, 'error-422-validation.json'), DocumentRejected::class, ['HTTP 422', '"body.file: Field required; query.page_size: Input should be less than or equal to 100"'], null];
        yield '422 without detail' => [new Response(422, [], 'nope'), DocumentRejected::class, ['HTTP 422', 'no details given'], null];
        yield '429 with Retry-After' => [new Response(429, ['Retry-After' => '30']), RateLimited::class, ['HTTP 429'], 30];
        yield '429 without Retry-After' => [new Response(429), RateLimited::class, ['HTTP 429'], null];
        yield '500' => [new Response(500, [], 'Internal Server Error'), OutcomeUnknown::class, ['HTTP 500', $number, 'search the e-invoice.be drafts for '.$number.' before resending'], null];
        yield '502' => [new Response(502), OutcomeUnknown::class, ['HTTP 502', $number], null];
        yield '503' => [new Response(503), OutcomeUnknown::class, ['HTTP 503', $number], null];
        yield '201 not JSON' => [new Response(201, [], '<html>created</html>'), OutcomeUnknown::class, ['HTTP 201', $number], null];
        yield '201 without id' => [self::json(201, ['state' => 'DRAFT']), OutcomeUnknown::class, ['HTTP 201', $number], null];
        yield '201 with a foreign id' => [self::json(201, ['id' => '../admin', 'state' => 'DRAFT']), OutcomeUnknown::class, ['HTTP 201', $number], null];
        yield '404' => [self::fixture(404, 'error-404.json'), UnexpectedResponse::class, ['HTTP 404'], null];
        yield '418' => [new Response(418, [], 'teapot'), UnexpectedResponse::class, ['HTTP 418'], null];
    }

    /**
     * @param  class-string  $expected
     * @param  list<string>  $fragments
     */
    #[DataProvider('createErrors')]
    public function test_create_error_mapping(Response $response, string $expected, array $fragments, ?int $retryAfter): void
    {
        $recorder = new RecordingHttpClient($response);

        $exception = self::capture(fn () => self::transport($recorder)->send(self::document()));

        self::assertInstanceOf($expected, $exception);
        foreach ($fragments as $fragment) {
            self::assertStringContainsString($fragment, $exception->getMessage());
        }
        if ($exception instanceof RateLimited) {
            self::assertSame($retryAfter, $exception->retryAfterSeconds);
        }
        if ($exception instanceof DocumentRejected && $response->getStatusCode() === 415) {
            self::assertSame(['detail' => 'The UBL file matches no supported document profile.'], $exception->errors);
        }
        self::assertCount(1, $recorder->requests());
    }

    public function test_404_on_upload_has_dedicated_message(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(404, 'error-404.json'));

        $exception = self::capture(fn () => self::transport($recorder)->send(self::document()));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('HTTP 404', $exception->getMessage());
        self::assertStringContainsString('upload endpoint', $exception->getMessage());
        self::assertStringContainsString('check the configured base URL and API version', $exception->getMessage());
        self::assertStringNotContainsString('does not know it', $exception->getMessage());
        self::assertCount(1, $recorder->requests());
    }

    /**
     * @return iterable<string, array{Response, class-string, list<string>}>
     */
    public static function sendErrors(): iterable
    {
        $retry = 'the draft exists; read its state, and send it again only when it is DRAFT or FAILED';
        yield '400' => [self::fixture(400, 'error-400.json'), DocumentRejected::class, ['HTTP 400', '"The document is missing Peppol routing information."']];
        yield '405' => [self::fixture(405, 'error-405.json'), UnexpectedResponse::class, ['HTTP 405', 'not in DRAFT or FAILED; read its state']];
        yield '406' => [self::fixture(406, 'error-406.json'), DocumentRejected::class, ['HTTP 406', '"The document is not a valid UBL document."']];
        yield '409' => [self::fixture(409, 'error-409.json'), DocumentRejected::class, ['HTTP 409', "the sender is not one of the company's Peppol identifiers"]];
        yield '422' => [self::fixture(422, 'error-422-validation.json'), DocumentRejected::class, ['HTTP 422', 'Field required']];
        yield '401' => [self::fixture(401, 'error-401.json'), AuthenticationFailed::class, ['HTTP 401']];
        yield '404' => [self::fixture(404, 'error-404.json'), UnexpectedResponse::class, ['HTTP 404']];
        yield '429' => [new Response(429, ['Retry-After' => '9']), RateLimited::class, ['HTTP 429']];
        yield '500' => [new Response(500), OutcomeUnknown::class, ['HTTP 500', $retry]];
        yield '503' => [new Response(503), OutcomeUnknown::class, ['HTTP 503', $retry]];
        yield '418' => [new Response(418), UnexpectedResponse::class, ['HTTP 418']];
    }

    /**
     * @param  class-string  $expected
     * @param  list<string>  $fragments
     */
    #[DataProvider('sendErrors')]
    public function test_send_error_mapping(Response $response, string $expected, array $fragments): void
    {
        $recorder = new RecordingHttpClient(self::fixture(201, 'create-201.json'), $response);

        $exception = self::capture(fn () => self::transport($recorder)->send(self::document()));

        self::assertInstanceOf($expected, $exception);
        self::assertStringContainsString('"'.self::DRAFT.'"', $exception->getMessage());
        foreach ($fragments as $fragment) {
            self::assertStringContainsString($fragment, $exception->getMessage());
        }
        self::assertCount(2, $recorder->requests());
    }

    public function test_unreadable_send_answer_keeps_the_draft_id(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(201, 'create-201.json'), new Response(200, [], '<html/>'));

        $result = self::transport($recorder)->send(self::document());

        self::assertSame(self::DRAFT, $result->documentId);
        self::assertSame(DeliveryState::Unknown, $result->state);
    }

    public function test_lost_answer_is_outcome_unknown_after_create(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(201, 'create-201.json'), self::networkException('Operation timed out after 30001 milliseconds'));

        $exception = self::capture(fn () => self::transport($recorder)->send(self::document()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('draft "'.self::DRAFT.'"', $exception->getMessage());
        self::assertStringContainsString('the draft exists; read its state, and send it again only when it is DRAFT or FAILED', $exception->getMessage());
        self::assertCount(2, $recorder->requests());
    }

    public function test_lost_answer_is_outcome_unknown_on_create(): void
    {
        $recorder = new RecordingHttpClient(self::networkException('Connection reset by peer'));

        $exception = self::capture(fn () => self::transport($recorder)->send(self::document()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('search the e-invoice.be drafts for invoice number "INV-2026-001" before resending', $exception->getMessage());
        self::assertCount(1, $recorder->requests());
    }

    public function test_lost_answer_without_a_readable_number_says_so(): void
    {
        $recorder = new RecordingHttpClient(self::networkException('Connection reset by peer'));
        $xml = '<Invoice xmlns="'.self::UBL_INVOICE.'" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
            .'<cbc:CustomizationID>'.PeppolBis3::CUSTOMIZATION.'</cbc:CustomizationID></Invoice>';

        $exception = self::capture(fn () => self::transport($recorder)->send(self::document(PeppolBis3::INVOICE, $xml)));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('invoice number cannot be read', $exception->getMessage());
    }

    public function test_key_never_appears_in_messages(): void
    {
        $echo = sprintf('{"detail":"Key %s, %s, %s is not valid"}', self::KEY, rawurlencode(self::KEY), base64_encode(self::KEY));
        $validation = sprintf('{"detail":[{"loc":["header","%s"],"msg":"bad %s","type":"x"}]}', self::KEY, self::KEY);
        $cases = [];
        foreach ([401, 415, 500, 418] as $status) {
            $cases[] = [new Response($status, [], $echo)];
        }
        $cases[] = [new Response(422, [], $validation)];
        $cases[] = [new Response(201, [], $echo)];
        foreach ([400, 405, 409, 500, 418] as $status) {
            $cases[] = [self::fixture(201, 'create-201.json'), new Response($status, [], $echo)];
        }
        $cases[] = [self::fixture(201, 'create-201.json'), new Response(422, [], $validation)];

        foreach ($cases as $responses) {
            $exception = self::capture(fn () => self::transport(new RecordingHttpClient(...$responses))->send(self::document()));
            $text = $exception->getMessage().($exception instanceof UnexpectedResponse ? $exception->bodyExcerpt : '')
                .($exception instanceof DocumentRejected ? json_encode($exception->errors, JSON_THROW_ON_ERROR) : '')
                .($exception->getPrevious()?->getMessage() ?? '');

            self::assertStringNotContainsString(self::KEY, $text);
            self::assertStringNotContainsString(base64_encode(self::KEY), $text);
        }

        foreach ([
            [self::networkException('cURL error: header Authorization: Bearer '.self::KEY)],
            [self::fixture(201, 'create-201.json'), self::networkException('cURL error: header Authorization: Bearer '.self::KEY)],
        ] as $responses) {
            $exception = self::capture(fn () => self::transport(new RecordingHttpClient(...$responses))->send(self::document()));
            self::assertInstanceOf(OutcomeUnknown::class, $exception);
            self::assertStringNotContainsString(self::KEY, $exception->getMessage());
            $previous = $exception->getPrevious();
            self::assertNotNull($previous);
            self::assertStringNotContainsString(self::KEY, $previous->getMessage());
        }
    }

    private static function xml(
        string $customization = PeppolDocumentTypes::BIS_CUSTOMIZATION,
        string $root = 'Invoice',
        string $namespace = self::UBL_INVOICE,
    ): string {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<'.$root.' xmlns="'.$namespace.'" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
            .'<cbc:CustomizationID>'.$customization.'</cbc:CustomizationID><cbc:ID>INV-2026-001</cbc:ID></'.$root.'>';
    }

    private static function document(string $documentType = PeppolBis3::INVOICE, ?string $xml = null): OutboundDocument
    {
        return new OutboundDocument(
            $xml ?? self::xml(),
            new ParticipantId('0208', '0987654321'),
            new ParticipantId('0088', '9508397101047'),
            new DocumentTypeId($documentType),
            new ProcessId(PeppolBis3::PROCESS),
        );
    }
}
