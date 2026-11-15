<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Recommand;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\InboundSource;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandConfig;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandTransport;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class RecommandInboundTest extends TestCase
{
    use CapturesThrowable;

    private const KEY_ID = 'key_01testkey';

    private const SECRET = 'secret_01topsecretvalue';

    public function test_transport_is_an_inbound_source(): void
    {
        self::assertInstanceOf(InboundSource::class, self::transport(new RecordingHttpClient));
    }

    public function test_pending_request_has_exact_method_url_and_headers(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'inbox.json'));

        self::transport($recorder, companyId: 'c_01/ex ample')->pending();

        self::assertCount(1, $recorder->requests());
        $request = $recorder->requests()[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://app.recommand.eu/api/v1/inbox?companyId=c_01%2Fex%20ample', (string) $request->getUri());
        self::assertSame('Basic '.base64_encode(self::KEY_ID.':'.self::SECRET), $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function test_inbox_maps_to_references_with_unparsable_sender_as_null(): void
    {
        $references = self::transport(new RecordingHttpClient(self::fixture(200, 'inbox.json')))->pending();

        self::assertCount(2, $references);
        [$first, $second] = $references;

        self::assertSame('doc_01inboundfirst', $first->documentId);
        self::assertNotNull($first->sender);
        self::assertTrue($first->sender->equals(new ParticipantId('0208', '0987654321')));
        self::assertNotNull($first->receiver);
        self::assertTrue($first->receiver->equals(new ParticipantId('0208', '0123456789')));
        self::assertSame(PeppolBis3::INVOICE, $first->documentType);
        self::assertEquals(new DateTimeImmutable('2026-11-12T07:45:10.000Z'), $first->receivedAt);

        self::assertSame('doc_01inboundsecond', $second->documentId);
        self::assertNull($second->sender);
        self::assertNotNull($second->receiver);
        self::assertEquals(new DateTimeImmutable('2026-11-12T08:02:00+00:00'), $second->receivedAt);
    }

    public function test_empty_inbox_is_an_empty_list(): void
    {
        $references = self::transport(new RecordingHttpClient(new Response(200, [], '{"success":true,"documents":[]}')))->pending();

        self::assertSame([], $references);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedInboxes(): iterable
    {
        yield 'no documents' => ['{"success":true}'];
        yield 'documents not a list' => ['{"success":true,"documents":"none"}'];
        yield 'entry without id' => ['{"success":true,"documents":[{"createdAt":"2026-11-12T07:45:10Z"}]}'];
        yield 'entry without createdAt' => ['{"success":true,"documents":[{"id":"doc_1"}]}'];
        yield 'entry with bad createdAt' => ['{"success":true,"documents":[{"id":"doc_1","createdAt":"yesterday-ish"}]}'];
        yield 'not success' => ['{"success":false,"documents":[]}'];
    }

    #[DataProvider('malformedInboxes')]
    public function test_malformed_inbox_is_unexpected(string $body): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(200, [], $body)))->pending());

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
    }

    public function test_inbox_failures_use_the_common_mapping(): void
    {
        $recorder = new RecordingHttpClient(
            new Response(401, [], '{"success":false}'),
            new Response(429, ['Retry-After' => '7'], '{"success":false}'),
            new Response(503, [], ''),
            self::networkException('timed out'),
        );
        $transport = self::transport($recorder);

        self::assertInstanceOf(AuthenticationFailed::class, self::capture(fn () => $transport->pending()));
        $limited = self::capture(fn () => $transport->pending());
        self::assertInstanceOf(RateLimited::class, $limited);
        self::assertSame(7, $limited->retryAfterSeconds);
        self::assertInstanceOf(TransportUnavailable::class, self::capture(fn () => $transport->pending()));
        $network = self::capture(fn () => $transport->pending());
        self::assertInstanceOf(TransportUnavailable::class, $network);
        self::assertTrue($network->isRetryable());
    }

    public function test_fetch_request_and_bare_document(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'inbound-document.json'));

        $document = self::transport($recorder)->fetch('doc_01/inbound first');

        self::assertSame('GET', $recorder->requests()[0]->getMethod());
        self::assertSame('https://app.recommand.eu/api/v1/documents/doc_01%2Finbound%20first', (string) $recorder->requests()[0]->getUri());
        self::assertSame(self::document('inbound-document.json')['xml'], $document->xml);
        self::assertNull($document->envelope);
        self::assertSame('doc_01inboundfirst', $document->reference->documentId);
        self::assertNotNull($document->reference->sender);
        self::assertSame('0987654321', $document->reference->sender->value);
        self::assertSame(PeppolBis3::INVOICE, $document->reference->documentType);
    }

    public function test_inbound_sbdh_is_unwrapped(): void
    {
        $document = self::transport(new RecordingHttpClient(self::fixture(200, 'inbound-document-sbdh.json')))->fetch('doc_01inboundsbdh');

        self::assertStringNotContainsString('StandardBusinessDocument', $document->xml);
        $root = simplexml_load_string($document->xml);
        self::assertNotFalse($root);
        self::assertSame('Invoice', $root->getName());
        self::assertSame('INV-SBDH-1', (string) $root->children('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2')->ID);

        self::assertNotNull($document->envelope);
        self::assertSame('BE', $document->envelope->countryC1);
        self::assertSame('b7e3c1a0-5d2f-4e8a-9c61-2f4d8e0a1b33', $document->envelope->instanceIdentifier);
        self::assertTrue($document->envelope->sender->equals(new ParticipantId('0208', '0987654321')));
        self::assertSame(PeppolBis3::INVOICE, $document->envelope->documentType->value);
    }

    public function test_inbound_xxe_is_refused(): void
    {
        $xxe = '<?xml version="1.0"?><!DOCTYPE Invoice [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            .'<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">&xxe;</Invoice>';

        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::withXml($xxe)))->fetch('doc_01'));

        self::assertInstanceOf(UnsafeXml::class, $exception);
    }

    public function test_inbound_sbdh_with_xxe_is_refused(): void
    {
        $xxe = '<?xml version="1.0"?><!DOCTYPE StandardBusinessDocument [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            .'<StandardBusinessDocument xmlns="http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader">&xxe;</StandardBusinessDocument>';

        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::withXml($xxe)))->fetch('doc_01'));

        self::assertInstanceOf(UnsafeXml::class, $exception);
    }

    public function test_malformed_inbound_xml_is_unsupported(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::withXml('<Invoice><unclosed></Invoice>')))->fetch('doc_01'));

        self::assertInstanceOf(UnsupportedDocument::class, $exception);
    }

    public function test_document_without_xml_is_unexpected(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::withXml(null)))->fetch('doc_01'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('document has no XML', $exception->getMessage());
    }

    public function test_fetch_404_names_the_document_id(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(404, [], '{"success":false}')))->fetch('doc_missing'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertSame(404, $exception->status);
        self::assertStringContainsString('doc_missing', $exception->getMessage());
    }

    public function test_inbound_body_over_20_mb_is_refused(): void
    {
        // A disk-backed stream without Content-Length, so the cap is enforced while reading.
        $resource = fopen('php://temp/maxmemory:65536', 'w+b');
        self::assertIsResource($resource);
        $chunk = str_repeat('a', 1_048_576);
        fwrite($resource, '{"success":true,"document":{"xml":"');
        for ($i = 0; $i < 20; $i++) {
            fwrite($resource, $chunk);
        }
        fwrite($resource, '"}}');
        rewind($resource);

        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(200, [], Stream::create($resource))))->fetch('doc_big'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('20000000 bytes', $exception->getMessage());
    }

    public function test_fetch_of_empty_id_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::transport(new RecordingHttpClient)->fetch('');
    }

    public function test_acknowledge_request_shape(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, ['Content-Type' => 'application/json'], '{"success":true}'));

        self::transport($recorder)->acknowledge('doc_01/inbound first');

        self::assertCount(1, $recorder->requests());
        $request = $recorder->requests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://app.recommand.eu/api/v1/documents/doc_01%2Finbound%20first/mark-as-read', (string) $request->getUri());
        self::assertSame('Basic '.base64_encode(self::KEY_ID.':'.self::SECRET), $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(['read' => true], json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_acknowledge_404_is_unexpected(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(404, [], '{"success":false}')))->acknowledge('doc_missing'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('doc_missing', $exception->getMessage());
    }

    public function test_acknowledge_failures_use_the_common_mapping(): void
    {
        $recorder = new RecordingHttpClient(
            new Response(403, [], ''),
            new Response(500, [], ''),
            new Response(200, [], '{"success":false}'),
        );
        $transport = self::transport($recorder);

        self::assertInstanceOf(AuthenticationFailed::class, self::capture(fn () => $transport->acknowledge('doc_01')));
        self::assertInstanceOf(TransportUnavailable::class, self::capture(fn () => $transport->acknowledge('doc_01')));
        self::assertInstanceOf(UnexpectedResponse::class, self::capture(fn () => $transport->acknowledge('doc_01')));
    }

    public function test_acknowledge_network_failure_is_outcome_unknown_and_says_retry_is_safe(): void
    {
        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(self::networkException('timed out')))->acknowledge('doc_01'));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('doc_01', $exception->getMessage());
        self::assertStringContainsString('safe to retry', $exception->getMessage());
    }

    public function test_acknowledge_of_empty_id_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::transport(new RecordingHttpClient)->acknowledge('');
    }

    public function test_secret_never_leaks_from_inbound_errors(): void
    {
        $leak = sprintf('{"success":false,"echo":"%s %s"}', self::SECRET, base64_encode(self::KEY_ID.':'.self::SECRET));
        $recorder = new RecordingHttpClient(
            new Response(418, [], $leak),
            new Response(418, [], $leak),
            new Response(418, [], $leak),
        );
        $transport = self::transport($recorder);

        foreach ([fn () => $transport->pending(), fn () => $transport->fetch('doc_01'), fn () => $transport->acknowledge('doc_01')] as $action) {
            $exception = self::capture($action);
            self::assertInstanceOf(UnexpectedResponse::class, $exception);
            self::assertStringNotContainsString(self::SECRET, $exception->getMessage().$exception->bodyExcerpt);
            self::assertStringNotContainsString(self::KEY_ID, $exception->getMessage().$exception->bodyExcerpt);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function lenientCreationTimes(): iterable
    {
        yield 'no time zone' => ['2026-11-12T07:45:10'];
        yield 'space separator' => ['2026-11-12 07:45:10+00:00'];
        yield 'date only' => ['2026-11-12'];
        yield 'relative' => ['now'];
        yield 'rolled-over date' => ['2026-02-30T00:00:00Z'];
        yield 'microseconds' => ['2026-11-12T07:45:10.123456Z'];
    }

    #[DataProvider('lenientCreationTimes')]
    public function test_lenient_created_at_is_refused(string $createdAt): void
    {
        $body = json_encode(['success' => true, 'documents' => [['id' => 'doc_1', 'createdAt' => $createdAt]]], JSON_THROW_ON_ERROR);

        $exception = self::capture(fn () => self::transport(new RecordingHttpClient(new Response(200, [], $body)))->pending());

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('createdAt', $exception->getMessage());
    }

    public function test_fetch_of_an_envelope_with_an_incomplete_header_is_unsupported(): void
    {
        $data = json_decode(self::read('inbound-document-sbdh.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsArray($data['document']);
        self::assertIsString($data['document']['xml']);
        $data['document']['xml'] = str_replace('<HeaderVersion>1.0</HeaderVersion>', '', $data['document']['xml']);
        $response = new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR));

        $exception = self::capture(fn () => self::transport(new RecordingHttpClient($response))->fetch('doc_01inboundsbdh'));

        self::assertInstanceOf(UnsupportedDocument::class, $exception);
        self::assertStringContainsString('HeaderVersion', $exception->getMessage());
    }

    public function test_whitespace_document_ids_are_refused(): void
    {
        $recorder = new RecordingHttpClient;
        $transport = self::transport($recorder);

        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => $transport->fetch(' ')));
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => $transport->acknowledge("\n")));
        self::assertSame([], $recorder->requests());
    }

    private static function transport(RecordingHttpClient $recorder, string $companyId = 'c_01example'): RecommandTransport
    {
        $factory = new Psr17Factory;

        return new RecommandTransport(
            new RecommandConfig(self::KEY_ID, self::SECRET, $companyId),
            new HttpClient($recorder, $factory, $factory),
        );
    }

    private static function fixture(int $status, string $name): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], self::read($name));
    }

    /**
     * The inbound document fixture with its xml replaced.
     */
    private static function withXml(?string $xml): Response
    {
        $data = json_decode(self::read('inbound-document.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsArray($data['document']);
        $data['document']['xml'] = $xml;

        return new Response(200, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private static function document(string $name): array
    {
        $data = json_decode(self::read($name), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsArray($data['document']);

        /** @var array<string, mixed> */
        return $data['document'];
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
}
