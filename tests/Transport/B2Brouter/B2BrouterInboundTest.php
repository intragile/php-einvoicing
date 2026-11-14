<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\InboundSource;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class B2BrouterInboundTest extends TestCase
{
    use B2BrouterFixtures;

    public function test_transport_is_an_inbound_source(): void
    {
        self::assertInstanceOf(InboundSource::class, self::transport(new RecordingHttpClient));
    }

    public function test_pending_request_and_mapping(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'received-list.json'));

        $references = self::transport($recorder)->pending();

        self::assertCount(1, $recorder->requests());
        $request = $recorder->requests()[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.b2brouter.net/accounts/4711/invoices?type=ReceivedInvoice&offset=0&limit=100', (string) $request->getUri());
        self::assertSame(self::KEY, $request->getHeaderLine('X-B2B-API-Key'));

        self::assertCount(2, $references);
        self::assertSame('900101', $references[0]->documentId);
        self::assertNull($references[0]->sender);
        self::assertNull($references[0]->receiver);
        self::assertSame('xml.ubl.invoice.bis3', $references[0]->documentType);
        self::assertEquals(new DateTimeImmutable('2026-11-12T07:45:10.000Z'), $references[0]->receivedAt);
        self::assertSame('900102', $references[1]->documentId);
        self::assertNull($references[1]->documentType);
        self::assertEquals(new DateTimeImmutable('2026-11-12T07:50:00+01:00'), $references[1]->receivedAt);
    }

    public function test_pending_pages_until_short_page_and_caps_at_ten(): void
    {
        $short = new RecordingHttpClient(self::page(0, 100), self::page(100, 100), self::page(200, 3));

        $references = self::transport($short)->pending();

        self::assertCount(203, $references);
        self::assertCount(3, $short->requests());
        self::assertStringContainsString('&offset=100&limit=100', (string) $short->requests()[1]->getUri());
        self::assertStringContainsString('&offset=200&limit=100', (string) $short->requests()[2]->getUri());

        $pages = [];
        for ($page = 0; $page < 10; $page++) {
            $pages[] = self::page($page * 100, 100);
        }
        $full = new RecordingHttpClient(...$pages);

        $references = self::transport($full)->pending();

        self::assertCount(1000, $references);
        self::assertCount(10, $full->requests());
        self::assertStringContainsString('&offset=900&limit=100', (string) $full->requests()[9]->getUri());
    }

    public function test_pending_skips_an_id_seen_on_an_earlier_page(): void
    {
        $recorder = new RecordingHttpClient(self::page(0, 100), self::page(99, 2));

        $references = self::transport($recorder)->pending();

        self::assertCount(101, $references);
        self::assertSame('101', $references[100]->documentId);
    }

    public function test_empty_inbox_is_an_empty_list(): void
    {
        $recorder = new RecordingHttpClient(self::json(200, ['invoices' => [], 'total_count' => 0]));

        self::assertSame([], self::transport($recorder)->pending());
        self::assertCount(1, $recorder->requests());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedLists(): iterable
    {
        yield 'no list' => ['{"total_count":0}'];
        yield 'list is an object' => ['{"invoices":{"a":1}}'];
        yield 'item not an object' => ['{"invoices":[1]}'];
        yield 'item without id' => ['{"invoices":[{"created_at":"2026-11-12T07:45:10Z"}]}'];
        yield 'item with string id' => ['{"invoices":[{"id":"x1","created_at":"2026-11-12T07:45:10Z"}]}'];
        yield 'item without created_at' => ['{"invoices":[{"id":5}]}'];
        yield 'item with lenient created_at' => ['{"invoices":[{"id":5,"created_at":"2026-02-30T07:45:10Z"}]}'];
    }

    #[DataProvider('malformedLists')]
    public function test_malformed_list_is_unexpected(string $body): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(200, [], $body)))->pending());

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
    }

    public function test_fetch_bare_ubl(): void
    {
        $xml = self::read('original-ubl.xml');
        $recorder = new RecordingHttpClient(new Response(200, ['Content-Type' => 'application/xml'], $xml));

        $document = self::transport($recorder)->fetch('900101');

        self::assertCount(1, $recorder->requests());
        $request = $recorder->requests()[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.b2brouter.net/invoices/900101/as/original', (string) $request->getUri());
        self::assertSame(self::KEY, $request->getHeaderLine('X-B2B-API-Key'));
        self::assertStringStartsWith('application/xml', $request->getHeaderLine('Accept'));
        self::assertSame($xml, $document->xml);
        self::assertNull($document->envelope);
        self::assertSame('900101', $document->reference->documentId);
    }

    public function test_fetch_unwraps_sbdh(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, ['Content-Type' => 'application/xml'], self::read('original-sbdh.xml')));

        $document = self::transport($recorder)->fetch('900101');

        self::assertNotNull($document->envelope);
        self::assertSame('b7e3c1a0-5d2f-4e8a-9c61-2f4d8e0a1b33', $document->envelope->instanceIdentifier);
        self::assertStringStartsWith('<?xml', $document->xml);
        self::assertStringNotContainsString('StandardBusinessDocument', $document->xml);
    }

    public function test_fetch_refuses_doctype_xml(): void
    {
        $xxe = '<?xml version="1.0"?><!DOCTYPE Invoice [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            .'<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">&xxe;</Invoice>';

        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(200, [], $xxe)))->fetch('900101'));

        self::assertInstanceOf(UnsafeXml::class, $exception);
    }

    public function test_fetch_of_malformed_xml_is_unsupported(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(200, [], '<Invoice>')))->fetch('900101'));

        self::assertInstanceOf(UnsupportedDocument::class, $exception);
    }

    public function test_same_host_redirect_is_followed_once(): void
    {
        $xml = self::read('original-ubl.xml');
        $recorder = new RecordingHttpClient(
            new Response(302, ['Location' => 'https://API.b2brouter.net/storage/originals/900101.xml']),
            new Response(200, [], $xml),
        );

        $document = self::transport($recorder)->fetch('900101');

        self::assertSame($xml, $document->xml);
        self::assertCount(2, $recorder->requests());
        $second = $recorder->requests()[1];
        self::assertSame('GET', $second->getMethod());
        self::assertSame('https://api.b2brouter.net/storage/originals/900101.xml', (string) $second->getUri());
        self::assertSame(self::KEY, $second->getHeaderLine('X-B2B-API-Key'));
    }

    public function test_relative_redirect_resolves_against_the_base_host(): void
    {
        $recorder = new RecordingHttpClient(
            new Response(302, ['Location' => '/storage/originals/900101.xml']),
            new Response(200, [], self::read('original-ubl.xml')),
        );

        self::transport($recorder)->fetch('900101');

        self::assertSame('https://api.b2brouter.net/storage/originals/900101.xml', (string) $recorder->requests()[1]->getUri());
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function refusedRedirects(): iterable
    {
        yield 'foreign host' => [['Location' => 'https://evil.example/steal?token=abc']];
        yield 'scheme-relative foreign host' => [['Location' => '//evil.example/steal']];
        yield 'plain http on the same host' => [['Location' => 'http://api.b2brouter.net/storage/x.xml']];
        yield 'other port on the same host' => [['Location' => 'https://api.b2brouter.net:8443/storage/x.xml']];
        yield 'user info' => [['Location' => 'https://user:pass@api.b2brouter.net/storage/x.xml']];
        yield 'host suffix' => [['Location' => 'https://api.b2brouter.net.evil.example/x.xml']];
        yield 'missing Location' => [[]];
    }

    /**
     * @param  array<string, string>  $headers
     */
    #[DataProvider('refusedRedirects')]
    public function test_foreign_redirect_is_refused(array $headers): void
    {
        $recorder = new RecordingHttpClient(new Response(302, $headers, '<a href="https://evil.example/steal?token=abc">moved</a>'));

        $exception = self::capture(fn () => self::transport($recorder)->fetch('900101'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertCount(1, $recorder->requests());
        self::assertStringNotContainsString('token=abc', $exception->getMessage());
        self::assertStringNotContainsString('pass', $exception->getMessage());
        self::assertSame('', $exception->bodyExcerpt);
    }

    public function test_second_redirect_is_refused(): void
    {
        $recorder = new RecordingHttpClient(
            new Response(302, ['Location' => self::BASE.'/storage/a.xml']),
            new Response(302, ['Location' => self::BASE.'/storage/b.xml']),
        );

        $exception = self::capture(fn () => self::transport($recorder)->fetch('900101'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertCount(2, $recorder->requests());
    }

    /**
     * @return iterable<string, array{Response, class-string}>
     */
    public static function readErrors(): iterable
    {
        yield '401' => [self::fixture(401, 'error-401.json'), AuthenticationFailed::class];
        yield '403' => [self::fixture(403, 'error-403.json'), AuthenticationFailed::class];
        yield '404' => [self::fixture(404, 'error-404.json'), UnexpectedResponse::class];
        yield '429' => [new Response(429), RateLimited::class];
        yield '502' => [new Response(502), TransportUnavailable::class];
        yield '418' => [new Response(418), UnexpectedResponse::class];
    }

    /**
     * @param  class-string  $expected
     */
    #[DataProvider('readErrors')]
    public function test_read_error_mapping(Response $response, string $expected): void
    {
        $pending = self::capture(fn () => self::transport(new RecordingHttpClient($response))->pending());
        $fetch = self::capture(fn () => self::transport(new RecordingHttpClient($response))->fetch('900101'));

        self::assertInstanceOf($expected, $pending);
        self::assertInstanceOf($expected, $fetch);
        self::assertStringContainsString('HTTP '.$response->getStatusCode(), $pending->getMessage());
        self::assertStringContainsString('HTTP '.$response->getStatusCode(), $fetch->getMessage());
    }

    public function test_acknowledge_posts_ack(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'ack-200.json'));

        self::transport($recorder)->acknowledge('900101');

        self::assertCount(1, $recorder->requests());
        $request = $recorder->requests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.b2brouter.net/invoices/900101/ack', (string) $request->getUri());
        self::assertSame(self::KEY, $request->getHeaderLine('X-B2B-API-Key'));
        self::assertSame('2026-06-26', $request->getHeaderLine('X-B2B-API-Version'));
    }

    /**
     * @return iterable<string, array{Response, class-string}>
     */
    public static function acknowledgeErrors(): iterable
    {
        yield '401' => [self::fixture(401, 'error-401.json'), AuthenticationFailed::class];
        yield '403' => [self::fixture(403, 'error-403.json'), AuthenticationFailed::class];
        yield '404' => [self::fixture(404, 'error-404.json'), UnexpectedResponse::class];
        yield '429' => [new Response(429, ['Retry-After' => '5']), RateLimited::class];
        yield '500' => [new Response(500), OutcomeUnknown::class];
        yield '503' => [new Response(503), OutcomeUnknown::class];
        yield '418' => [new Response(418), UnexpectedResponse::class];
    }

    /**
     * @param  class-string  $expected
     */
    #[DataProvider('acknowledgeErrors')]
    public function test_acknowledge_error_mapping(Response $response, string $expected): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient($response))->acknowledge('900101'));

        self::assertInstanceOf($expected, $exception);
        self::assertStringContainsString('HTTP '.$response->getStatusCode(), $exception->getMessage());
    }

    public function test_acknowledge_lost_answer_is_outcome_unknown_and_says_retry_is_safe(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::networkException('timeout')))->acknowledge('900101'));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('acknowledging is idempotent', $exception->getMessage());
        self::assertStringContainsString('retry', $exception->getMessage());
    }

    public function test_key_never_appears_in_inbound_messages(): void
    {
        $echo = 'Unknown key '.self::KEY.' / '.base64_encode(self::KEY);
        foreach ([
            fn () => self::transport(new RecordingHttpClient(new Response(418, [], $echo)))->pending(),
            fn () => self::transport(new RecordingHttpClient(new Response(404, [], $echo)))->fetch('1'),
            fn () => self::transport(new RecordingHttpClient(new Response(418, [], $echo)))->acknowledge('1'),
            fn () => self::transport(new RecordingHttpClient(new Response(200, [], '{"invoices":[{"id":"'.self::KEY.'"}]}')))->pending(),
        ] as $action) {
            $exception = self::capture($action);
            $text = $exception->getMessage().($exception instanceof UnexpectedResponse ? $exception->bodyExcerpt : '');

            self::assertStringNotContainsString(self::KEY, $text);
            self::assertStringNotContainsString(base64_encode(self::KEY), $text);
        }
    }

    /**
     * A list page of received invoices with consecutive ids starting at $first + 1.
     */
    private static function page(int $first, int $count): Response
    {
        $invoices = [];
        for ($id = $first + 1; $id <= $first + $count; $id++) {
            $invoices[] = [
                'id' => $id,
                'type' => 'ReceivedInvoice',
                'document_type_code' => 'xml.ubl.invoice.bis3',
                'created_at' => '2026-11-12T07:45:10.000Z',
            ];
        }

        return self::json(200, ['invoices' => $invoices, 'offset' => $first, 'limit' => 100]);
    }
}
