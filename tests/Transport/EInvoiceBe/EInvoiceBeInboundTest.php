<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\EInvoiceBe;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\InboundSource;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EInvoiceBeInboundTest extends TestCase
{
    use EInvoiceBeFixtures;

    private const DOC = 'doc-In0000000001';

    private const SIGNED_URL = 'https://0123456789abcdef0123456789abcdef.eu.r2.cloudflarestorage.com/tenant-ubl-documents/tenant-42/doc-In0000000001.xml?X-Amz-Expires=3600&X-Amz-Signature=FAKESIGNATURE0123456789abcdef';

    public function test_transport_is_an_inbound_source(): void
    {
        self::assertInstanceOf(InboundSource::class, self::transport(new RecordingHttpClient));
    }

    public function test_pending_request_and_mapping(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'inbox-page-1.json'), self::fixture(200, 'inbox-page-2.json'));

        $references = self::transport($recorder)->pending();

        self::assertCount(2, $recorder->requests());
        [$first, $second] = $recorder->requests();
        self::assertSame('GET', $first->getMethod());
        self::assertSame('https://api.e-invoice.be/api/inbox/?page=1&page_size=100&sort_by=created_at&sort_order=desc', (string) $first->getUri());
        self::assertSame('Bearer '.self::KEY, $first->getHeaderLine('Authorization'));
        self::assertSame('https://api.e-invoice.be/api/inbox/?page=2&page_size=100&sort_by=created_at&sort_order=desc', (string) $second->getUri());

        // The fourth document is older than the seven-day look-back.
        self::assertSame(['doc-In0000000001', 'doc-In0000000002', 'doc-In0000000003'], array_map(
            static fn ($reference): string => $reference->documentId,
            $references,
        ));
        self::assertNull($references[0]->sender);
        self::assertNull($references[0]->receiver);
        self::assertNull($references[0]->documentType);
        self::assertNull($references[1]->documentType);
        self::assertEquals(new DateTimeImmutable('2026-11-14T09:30:00.000001Z'), $references[0]->receivedAt);
        self::assertEquals(new DateTimeImmutable('2026-11-13T16:05:12+01:00'), $references[1]->receivedAt);
    }

    public function test_pending_stops_at_lookback_and_page_cap(): void
    {
        // Page cap: one page allowed, although the inbox says there is another.
        $capped = new RecordingHttpClient(self::fixture(200, 'inbox-page-1.json'));
        self::assertCount(2, self::transport($capped, maxPages: 1)->pending());
        self::assertCount(1, $capped->requests());

        // Look-back passed on the first page: no further page is read.
        $lookback = new RecordingHttpClient(self::fixture(200, 'inbox-page-1.json'));
        $references = self::transport($lookback, lookbackDays: 1, now: '2026-11-14T20:00:00Z')->pending();
        self::assertSame(['doc-In0000000001'], array_map(static fn ($reference): string => $reference->documentId, $references));
        self::assertCount(1, $lookback->requests());

        // Look-back boundary: a document exactly one day old is still listed.
        $boundary = new RecordingHttpClient(self::fixture(200, 'inbox-page-1.json'), self::fixture(200, 'inbox-page-2.json'));
        $references = self::transport($boundary, lookbackDays: 1, now: '2026-11-14T15:05:12Z')->pending();
        self::assertSame(['doc-In0000000001', 'doc-In0000000002'], array_map(static fn ($reference): string => $reference->documentId, $references));
        self::assertCount(2, $boundary->requests());

        // Ten pages at most by default.
        $pages = [];
        for ($page = 1; $page <= 10; $page++) {
            $pages[] = self::page($page, true);
        }
        $full = new RecordingHttpClient(...$pages);
        self::assertCount(1000, self::transport($full)->pending());
        self::assertCount(10, $full->requests());
        self::assertStringContainsString('?page=10&page_size=100&', (string) $full->requests()[9]->getUri());
    }

    public function test_pending_skips_an_id_seen_on_an_earlier_page(): void
    {
        $page1 = self::decoded('inbox-page-1.json');
        $page2 = self::decoded('inbox-page-2.json');
        self::assertIsArray($page1['items']);
        self::assertIsArray($page2['items']);
        // A document received meanwhile shifts the pages, so the second page repeats one.
        $page2['items'][0] = $page1['items'][1];

        $references = self::transport(new RecordingHttpClient(self::json(200, $page1), self::json(200, $page2)))->pending();

        self::assertSame(['doc-In0000000001', 'doc-In0000000002'], array_map(static fn ($reference): string => $reference->documentId, $references));
    }

    public function test_empty_inbox_is_an_empty_list(): void
    {
        $recorder = new RecordingHttpClient(self::json(200, ['items' => [], 'total' => 0, 'page' => 1, 'page_size' => 100, 'pages' => 0, 'has_next_page' => false]));

        self::assertSame([], self::transport($recorder)->pending());
        self::assertCount(1, $recorder->requests());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedPages(): iterable
    {
        yield 'not JSON' => ['<html/>'];
        yield 'no items' => ['{"has_next_page":false}'];
        yield 'items is an object' => ['{"items":{"a":1},"has_next_page":false}'];
        yield 'item not an object' => ['{"items":[1],"has_next_page":false}'];
        yield 'item without id' => ['{"items":[{"created_at":"2026-11-14T09:30:00Z"}],"has_next_page":false}'];
        yield 'item with a foreign id' => ['{"items":[{"id":"../x","created_at":"2026-11-14T09:30:00Z"}],"has_next_page":false}'];
        yield 'item without created_at' => ['{"items":[{"id":"doc-1"}],"has_next_page":false}'];
        yield 'item with created_at without time zone' => ['{"items":[{"id":"doc-1","created_at":"2026-11-14T09:30:00"}],"has_next_page":false}'];
    }

    #[DataProvider('malformedPages')]
    public function test_malformed_page_is_unexpected(string $body): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(200, [], $body)))->pending());

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
    }

    public function test_fetch_reads_the_ubl_record_then_the_signed_url(): void
    {
        $xml = self::read('inbound-ubl.xml');
        $recorder = new RecordingHttpClient(self::fixture(200, 'ubl-200.json'), new Response(200, ['Content-Type' => 'application/xml'], $xml));

        $document = self::transport($recorder)->fetch(self::DOC);

        self::assertCount(2, $recorder->requests());
        [$record, $download] = $recorder->requests();
        self::assertSame('GET', $record->getMethod());
        self::assertSame('https://api.e-invoice.be/api/documents/doc-In0000000001/ubl', (string) $record->getUri());
        self::assertSame('Bearer '.self::KEY, $record->getHeaderLine('Authorization'));
        self::assertSame('GET', $download->getMethod());
        self::assertSame(self::SIGNED_URL, (string) $download->getUri());

        self::assertSame($xml, $document->xml);
        self::assertNull($document->envelope);
        self::assertSame(self::DOC, $document->reference->documentId);
        self::assertNotNull($document->reference->sender);
        self::assertSame('0208:0987654321', $document->reference->sender->toString());
        self::assertNotNull($document->reference->receiver);
        self::assertSame('0208:0123456789', $document->reference->receiver->toString());
        self::assertNull($document->reference->documentType);
        self::assertEquals(new DateTimeImmutable(self::NOW), $document->reference->receivedAt);
    }

    public function test_signed_url_fetch_sends_no_authorization(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'ubl-200.json'), new Response(200, [], self::read('inbound-ubl.xml')));

        self::transport($recorder)->fetch(self::DOC);

        $download = $recorder->requests()[1];
        self::assertFalse($download->hasHeader('Authorization'));
        foreach ($download->getHeaders() as $values) {
            foreach ($values as $value) {
                self::assertStringNotContainsString(self::KEY, $value);
            }
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badSignedUrls(): iterable
    {
        yield 'plain http' => ['http://0123456789abcdef0123456789abcdef.eu.r2.cloudflarestorage.com/doc.xml?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'relative' => ['/tenant-ubl-documents/doc.xml?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'no host' => ['https:///doc.xml?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'user info' => ['https://user:FAKESIGNATURE0123456789abcdef@0123456789abcdef0123456789abcdef.eu.r2.cloudflarestorage.com/doc.xml'];
        yield 'unparsable' => ['https://[0123456789abcdef0123456789abcdef.eu.r2.cloudflarestorage.com/doc.xml?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'whitespace' => ['https://0123456789abcdef0123456789abcdef.eu.r2.cloudflarestorage.com/doc .xml?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'IPv6 literal' => ['https://[::1]/doc.xml?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'missing' => [null];
        yield 'not a string' => [42];
    }

    #[DataProvider('badSignedUrls')]
    public function test_signed_url_must_be_https(mixed $url): void
    {
        $data = self::decoded('ubl-200.json');
        $data['signed_url'] = $url;
        $recorder = new RecordingHttpClient(self::json(200, $data));

        $exception = self::capture(fn () => self::transport($recorder)->fetch(self::DOC));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('signed', $exception->getMessage());
        self::assertCount(1, $recorder->requests());
        self::assertStringNotContainsString('FAKESIGNATURE0123456789abcdef', $exception->getMessage().$exception->bodyExcerpt);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeSignedUrlHosts(): iterable
    {
        yield 'IPv4 literal' => ['https://127.0.0.1/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'localhost' => ['https://localhost/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'localhost subdomain' => ['https://a.localhost/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'localhost in capitals' => ['https://A.LocalHost/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'shorthand IPv4' => ['https://127.1/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'decimal IPv4' => ['https://2130706433/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'hexadecimal IPv4' => ['https://0x7f.1/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'foreign host' => ['https://storage.example/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'allowed suffix as a label of a foreign host' => ['https://evil.r2.cloudflarestorage.com.attacker.example/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'bare allowed suffix' => ['https://r2.cloudflarestorage.com/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'parent of the allowed suffix' => ['https://cloudflarestorage.com/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'hexadecimal last label' => ['https://1.0x7f/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
    }

    #[DataProvider('unsafeSignedUrlHosts')]
    public function test_signed_url_with_unsafe_host_is_refused(string $url): void
    {
        $data = self::decoded('ubl-200.json');
        $data['signed_url'] = $url;
        $recorder = new RecordingHttpClient(self::json(200, $data));

        $exception = self::capture(fn () => self::transport($recorder)->fetch(self::DOC));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        // UnexpectedResponse appends the status to the reason.
        self::assertSame('The signed download URL host is not allowed (HTTP 200).', $exception->getMessage());
        self::assertCount(1, $recorder->requests());
        self::assertSame('', $exception->bodyExcerpt);
        self::assertStringNotContainsString((string) parse_url($url, PHP_URL_HOST), $exception->getMessage());
        self::assertStringNotContainsString('FAKESIGNATURE0123456789abcdef', $exception->getMessage());
    }

    #[DataProvider('allowedSignedUrlHosts')]
    public function test_signed_url_on_the_storage_host_is_downloaded(string $url): void
    {
        $data = self::decoded('ubl-200.json');
        $data['signed_url'] = $url;
        $recorder = new RecordingHttpClient(self::json(200, $data), new Response(200, [], self::read('inbound-ubl.xml')));

        try {
            self::transport($recorder)->fetch(self::DOC);
        } catch (\Throwable) {
            // Only the request that reaches the download host matters here.
        }

        self::assertCount(2, $recorder->requests());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedSignedUrlHosts(): iterable
    {
        yield 'storage host' => ['https://0123456789abcdef0123456789abcdef.eu.r2.cloudflarestorage.com/tenant-ubl-documents/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
        yield 'storage host in capitals' => ['https://0123456789ABCDEF0123456789ABCDEF.EU.R2.CLOUDFLARESTORAGE.COM/tenant-ubl-documents/x?X-Amz-Signature=FAKESIGNATURE0123456789abcdef'];
    }

    public function test_signed_url_is_redacted(): void
    {
        $echo = '<Error><Code>AccessDenied</Code><Message>Request has expired</Message><Url>'.self::SIGNED_URL.'</Url>'
            .'<Signature>FAKESIGNATURE0123456789abcdef</Signature></Error>';
        $recorder = new RecordingHttpClient(self::fixture(200, 'ubl-200.json'), new Response(403, ['Content-Type' => 'application/xml'], $echo));

        $exception = self::capture(fn () => self::transport($recorder)->fetch(self::DOC));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('HTTP 403', $exception->getMessage());
        self::assertSame('', $exception->bodyExcerpt);
        self::assertStringNotContainsString('FAKESIGNATURE', $exception->getMessage());
        self::assertStringNotContainsString('0123456789abcdef0123456789abcdef.eu.r2.cloudflarestorage.com', $exception->getMessage());

        foreach ([
            new Response(503, [], $echo),
            new Response(404, [], $echo),
            self::networkException('Could not resolve host for '.self::SIGNED_URL),
        ] as $failure) {
            $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::fixture(200, 'ubl-200.json'), $failure))->fetch(self::DOC));
            $text = $exception->getMessage().($exception instanceof UnexpectedResponse ? $exception->bodyExcerpt : '')
                .($exception->getPrevious()?->getMessage() ?? '');

            self::assertStringNotContainsString('FAKESIGNATURE', $text);
            self::assertStringNotContainsString('0123456789abcdef0123456789abcdef.eu.r2.cloudflarestorage.com/tenant-ubl-documents', $text);
        }
    }

    public function test_signed_url_is_redacted_from_a_ubl_record_error(): void
    {
        $data = self::decoded('ubl-200.json');
        $data['file_hash'] = str_repeat('0', 64);
        $recorder = new RecordingHttpClient(self::json(200, $data), new Response(200, [], self::read('inbound-ubl.xml')));

        $exception = self::capture(fn () => self::transport($recorder)->fetch(self::DOC));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringNotContainsString('FAKESIGNATURE', $exception->getMessage().$exception->bodyExcerpt);
    }

    public function test_signed_download_is_timed_out_as_unavailable(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::fixture(200, 'ubl-200.json'), new Response(502)))->fetch(self::DOC));

        self::assertInstanceOf(TransportUnavailable::class, $exception);
        self::assertStringContainsString('HTTP 502', $exception->getMessage());
    }

    public function test_file_hash_mismatch_is_refused(): void
    {
        $xml = self::read('inbound-ubl.xml');
        $data = self::decoded('ubl-200.json');
        $data['file_hash'] = hash('sha256', $xml.' ');

        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::json(200, $data), new Response(200, [], $xml)))->fetch(self::DOC));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('hash', $exception->getMessage());
        self::assertStringContainsString(self::DOC, $exception->getMessage());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function ignoredOrMatchingHashes(): iterable
    {
        $hash = hash('sha256', self::read('inbound-ubl.xml'));
        yield 'upper case SHA-256' => [strtoupper($hash)];
        yield 'MD5 shape' => [str_repeat('a', 32)];
        yield 'prefixed' => ['sha256:'.str_repeat('a', 64)];
        yield 'null' => [null];
        yield 'not a string' => [5];
    }

    #[DataProvider('ignoredOrMatchingHashes')]
    public function test_matching_or_unrecognised_hash_is_accepted(mixed $hash): void
    {
        $xml = self::read('inbound-ubl.xml');
        $data = self::decoded('ubl-200.json');
        $data['file_hash'] = $hash;

        $document = self::transport(new RecordingHttpClient(self::json(200, $data), new Response(200, [], $xml)))->fetch(self::DOC);

        self::assertSame($xml, $document->xml);
    }

    public function test_fetch_unwraps_sbdh(): void
    {
        $data = self::decoded('ubl-200.json');
        unset($data['file_hash']);
        $recorder = new RecordingHttpClient(self::json(200, $data), new Response(200, [], self::read('inbound-sbdh.xml')));

        $document = self::transport($recorder)->fetch(self::DOC);

        self::assertNotNull($document->envelope);
        self::assertSame('b7e3c1a0-5d2f-4e8a-9c61-2f4d8e0a1b33', $document->envelope->instanceIdentifier);
        self::assertStringNotContainsString('StandardBusinessDocument', $document->xml);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function participantFields(): iterable
    {
        yield 'missing' => [['sender_peppol_scheme' => null, 'sender_peppol_id' => null, 'receiver_peppol_scheme' => null, 'receiver_peppol_id' => null]];
        yield 'scheme name instead of ICD' => [['sender_peppol_scheme' => 'iso6523-actorid-upis', 'receiver_peppol_scheme' => 'iso6523-actorid-upis']];
        yield 'empty values' => [['sender_peppol_id' => '', 'receiver_peppol_id' => ' ']];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    #[DataProvider('participantFields')]
    public function test_unusable_participant_fields_give_null(array $fields): void
    {
        $data = array_replace(self::decoded('ubl-200.json'), $fields);

        $document = self::transport(new RecordingHttpClient(self::json(200, $data), new Response(200, [], self::read('inbound-ubl.xml'))))->fetch(self::DOC);

        self::assertNull($document->reference->sender);
        self::assertNull($document->reference->receiver);
    }

    public function test_fetch_refuses_doctype_xml(): void
    {
        $xxe = '<?xml version="1.0"?><!DOCTYPE Invoice [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            .'<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">&xxe;</Invoice>';
        $data = self::decoded('ubl-200.json');
        unset($data['file_hash']);

        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::json(200, $data), new Response(200, [], $xxe)))->fetch(self::DOC));

        self::assertInstanceOf(UnsafeXml::class, $exception);
    }

    public function test_fetch_of_malformed_xml_is_unsupported(): void
    {
        $data = self::decoded('ubl-200.json');
        unset($data['file_hash']);

        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::json(200, $data), new Response(200, [], '<Invoice>')))->fetch(self::DOC));

        self::assertInstanceOf(UnsupportedDocument::class, $exception);
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
        yield '429' => [new Response(429, ['Retry-After' => '3']), RateLimited::class];
        yield '500' => [new Response(500), TransportUnavailable::class];
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
        $fetch = self::capture(fn () => self::transport(new RecordingHttpClient($response))->fetch(self::DOC));

        self::assertInstanceOf($expected, $pending);
        self::assertInstanceOf($expected, $fetch);
        self::assertStringContainsString('HTTP '.$response->getStatusCode(), $pending->getMessage());
        self::assertStringContainsString('HTTP '.$response->getStatusCode(), $fetch->getMessage());
        self::assertStringContainsString(self::DOC, $fetch->getMessage());
    }

    public function test_acknowledge_makes_no_request(): void
    {
        $recorder = new RecordingHttpClient;

        self::transport($recorder)->acknowledge(self::DOC);

        self::assertSame([], $recorder->requests());
    }

    public function test_key_never_appears_in_inbound_messages(): void
    {
        $echo = '{"detail":"Unknown key '.self::KEY.' / '.base64_encode(self::KEY).'"}';
        foreach ([
            fn () => self::transport(new RecordingHttpClient(new Response(418, [], $echo)))->pending(),
            fn () => self::transport(new RecordingHttpClient(new Response(404, [], $echo)))->fetch(self::DOC),
            fn () => self::transport(new RecordingHttpClient(new Response(200, [], '{"items":[{"id":"'.self::KEY.'"}]}')))->pending(),
            fn () => self::transport(new RecordingHttpClient(new Response(200, [], '{"id":"x","signed_url":"http://h/'.self::KEY.'"}')))->fetch(self::DOC),
        ] as $action) {
            $exception = self::capture($action);
            $text = $exception->getMessage().($exception instanceof UnexpectedResponse ? $exception->bodyExcerpt : '');

            self::assertStringNotContainsString(self::KEY, $text);
            self::assertStringNotContainsString(base64_encode(self::KEY), $text);
        }
    }

    /**
     * A full inbox page of documents received within the last hour.
     */
    private static function page(int $page, bool $hasNext): Response
    {
        $items = [];
        for ($index = 1; $index <= 100; $index++) {
            $items[] = [
                'id' => sprintf('doc-P%02dI%03d', $page, $index),
                'created_at' => '2026-11-14T11:30:00Z',
                'state' => 'RECEIVED',
                'direction' => 'INBOUND',
            ];
        }

        return self::json(200, ['items' => $items, 'total' => 5000, 'page' => $page, 'page_size' => 100, 'pages' => 50, 'has_next_page' => $hasNext]);
    }
}
