<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;
use IntrAgile\EInvoicing\Tests\Support\PolishInvoices;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Tests\Support\RequestCounter;
use IntrAgile\EInvoicing\Tests\Transport\Ksef\Session\SessionFixtures;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\CursorWindow;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InMemoryCursorStore;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use IntrAgile\EInvoicing\Transport\Ksef\KsefErrors;
use IntrAgile\EInvoicing\Transport\Ksef\KsefTransport;
use IntrAgile\EInvoicing\Transport\Ksef\Session\InvoicingMode;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use ReflectionProperty;
use RuntimeException;

final class KsefTransportTest extends TestCase
{
    use SessionFixtures;

    private const DOCUMENT_ID = self::SESSION_REF.'/'.self::INVOICE_REF;

    private const ORIGINAL_KSEF_NUMBER = '5265877635-20250626-010080DD2B5E-26';

    private const INBOX_FIRST = '5555555555-20261120-010080615740-BB';

    private const INBOX_SECOND = '7777777777-20261121-0100806157AB-87';

    private const INBOX_KEY = 'test:5265877635:Subject2';

    private const INBOX_HWM = '2026-11-21T09:59:00.000000+00:00';

    private const RECEIVED_XML = '<?xml version="1.0" encoding="UTF-8"?><Faktura xmlns="http://crd.gov.pl/wzor/2025/06/25/13775/"/>';

    public function test_send_invoice_writes_fa3_and_returns_pending(): void
    {
        $recorder = new RecordingHttpClient(...[...self::openingResponses(), self::sendResponse()]);

        $result = $this->transport($recorder)->send(PolishInvoices::domestic());

        self::assertSame(self::DOCUMENT_ID, $result->documentId);
        self::assertSame(DeliveryState::Pending, $result->state);
        self::assertNull($result->failure);
        $expected = (new Fa3Writer($this->now()))->write(PolishInvoices::domestic());
        $sends = self::requestsTo($recorder, 'POST', '/sessions/online/'.self::SESSION_REF.'/invoices');
        self::assertCount(1, $sends);
        $body = self::body($sends[0]);
        self::assertSame(base64_encode(hash('sha256', $expected, true)), $body['invoiceHash']);
        self::assertSame(strlen($expected), $body['invoiceSize']);
        // One key list for the authentication and the session: the registry is shared.
        self::assertCount(1, self::requestsTo($recorder, 'GET', '/security/public-key-certificates'));
    }

    public function test_send_valid_raw_xml_is_sent_unchanged(): void
    {
        $xml = (new Fa3Writer($this->now()))->write(PolishInvoices::domestic());
        $recorder = new RecordingHttpClient(...[...self::openingResponses(), self::sendResponse()]);

        $result = $this->transport($recorder)->send($xml);

        self::assertSame(self::DOCUMENT_ID, $result->documentId);
        $body = self::body(self::requestsTo($recorder, 'POST', '/sessions/online/'.self::SESSION_REF.'/invoices')[0]);
        self::assertSame(base64_encode(hash('sha256', $xml, true)), $body['invoiceHash']);
    }

    public function test_send_invalid_raw_xml_is_refused_without_request(): void
    {
        $counter = new RequestCounter(new RecordingHttpClient);
        $transport = $this->transport($counter);

        $exception = self::capture(static fn () => $transport->send(self::INVOICE_XML));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringContainsString('FA(3)', $exception->getMessage());
        self::assertNotSame([], $exception->errors);
        self::assertIsList($exception->errors['errors'] ?? null);
        self::assertSame(0, $counter->count());
    }

    public function test_send_batch_refuses_invalid_raw_xml_without_request(): void
    {
        $counter = new RequestCounter(new RecordingHttpClient);
        $transport = $this->transport($counter);
        $valid = (new Fa3Writer($this->now()))->write(PolishInvoices::domestic());

        $exception = self::capture(static fn () => $transport->sendBatch(['a.xml' => $valid, 'b.xml' => self::INVOICE_XML]));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringContainsString('b.xml', $exception->getMessage());
        self::assertSame(0, $counter->count());
    }

    public function test_status_parses_document_id_and_maps_state(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::invoiceStatusResponse(['code' => 150, 'description' => 'Trwa przetwarzanie']),
            self::invoiceStatusResponse(['code' => 200, 'description' => 'Sukces'], [
                'ksefNumber' => self::KSEF_NUMBER,
                'acquisitionDate' => '2026-11-21T10:00:03.0154302+00:00',
            ]),
        ]);
        $transport = $this->transport($recorder);

        $pending = $transport->status(self::DOCUMENT_ID);
        $delivered = $transport->status(self::DOCUMENT_ID);

        self::assertSame(self::DOCUMENT_ID, $pending->documentId);
        self::assertSame(DeliveryState::Pending, $pending->state);
        self::assertNull($pending->failure);
        self::assertSame(DeliveryState::Delivered, $delivered->state);
        self::assertEquals(new DateTimeImmutable('2026-11-21T10:00:03.0154302+00:00'), $delivered->changedAt);
        $get = self::requestsTo($recorder, 'GET', '/invoices/'.self::INVOICE_REF);
        self::assertCount(2, $get);
        self::assertSame('/v2/sessions/'.self::SESSION_REF.'/invoices/'.self::INVOICE_REF, $get[0]->getUri()->getPath());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedDocumentIds(): iterable
    {
        yield 'empty' => [''];
        yield 'no separator' => [self::SESSION_REF];
        yield 'empty invoice part' => [self::SESSION_REF.'/'];
        yield 'empty session part' => ['/'.self::INVOICE_REF];
        yield 'three parts' => [self::DOCUMENT_ID.'/X'];
        yield 'path traversal' => ['../'.self::INVOICE_REF];
        yield 'query' => [self::SESSION_REF.'/'.self::INVOICE_REF.'?x=1'];
    }

    #[DataProvider('malformedDocumentIds')]
    public function test_malformed_document_id_is_invalid_argument(string $documentId): void
    {
        $counter = new RequestCounter(new RecordingHttpClient);
        $transport = $this->transport($counter);

        foreach ([
            static fn () => $transport->status($documentId),
            static fn () => $transport->invoiceStatus($documentId),
            static fn () => $transport->awaitFinal($documentId),
            static fn () => $transport->upo($documentId),
        ] as $call) {
            self::assertInstanceOf(InvalidArgumentException::class, self::capture($call));
        }
        self::assertSame(0, $counter->count());
    }

    public function test_duplicate_maps_to_duplicate_with_original_number(): void
    {
        // The first send is lost on the way back; the resend is a duplicate of the invoice KSeF
        // accepted from the first one.
        $recorder = new RecordingHttpClient(...[
            ...self::openingResponses(),
            self::networkException('Connection reset by peer'),
            self::sendResponse(self::SECOND_INVOICE_REF),
            self::invoiceStatusResponse([
                'code' => 440,
                'description' => 'Duplikat faktury',
                'details' => ['Duplikat faktury. Faktura o numerze KSeF: '.self::ORIGINAL_KSEF_NUMBER.' została już prawidłowo przesłana do systemu w sesji: 20250626-SO-2F14610000-242991F8C9-B4'],
                'extensions' => [
                    'originalSessionReferenceNumber' => '20250626-SO-2F14610000-242991F8C9-B4',
                    'originalKsefNumber' => self::ORIGINAL_KSEF_NUMBER,
                ],
            ]),
        ]);
        $transport = $this->transport($recorder);

        $lost = self::capture(static fn () => $transport->send(PolishInvoices::domestic()));
        self::assertInstanceOf(OutcomeUnknown::class, $lost);

        $resent = $transport->send(PolishInvoices::domestic());
        self::assertSame(self::SESSION_REF.'/'.self::SECOND_INVOICE_REF, $resent->documentId);

        $status = $transport->status($resent->documentId);
        self::assertSame(DeliveryState::Failed, $status->state);
        self::assertNotNull($status->failure);
        self::assertSame(FailureCategory::Duplicate, $status->failure->category);
        self::assertSame('440', $status->failure->providerCode);
        self::assertStringContainsString(self::ORIGINAL_KSEF_NUMBER, (string) $status->failure->message);
        // The resend reused the open session: one session was opened.
        self::assertCount(1, self::requestsTo($recorder, 'POST', '/sessions/online'));
    }

    public function test_pending_fetch_acknowledge_cycle_moves_cursor(): void
    {
        $store = new InMemoryCursorStore;
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::metadataPage([
                self::metadataEntry(self::INBOX_FIRST, '2026-11-21T09:00:00.000+00:00'),
                self::metadataEntry(self::INBOX_SECOND, '2026-11-21T09:30:00.000+00:00'),
            ]),
            new Response(200, ['Content-Type' => 'application/xml'], self::RECEIVED_XML),
            self::metadataPage([], '2026-11-21T09:59:30.000+00:00'),
        ]);
        $transport = $this->transport($recorder, $store);
        $key = 'test:5265877635:Subject2';

        $pending = $transport->pending();

        self::assertSame([self::INBOX_FIRST, self::INBOX_SECOND], array_map(static fn ($reference): string => $reference->documentId, $pending));
        self::assertSame('FA (3)', $pending[0]->documentType);
        self::assertNull($pending[0]->sender);
        self::assertEquals(new DateTimeImmutable('2026-11-21T09:00:00+00:00'), $pending[0]->receivedAt);
        $firstQuery = self::body(self::requestsTo($recorder, 'POST', '/invoices/query/metadata')[0]);
        self::assertSame([
            'subjectType' => 'Subject2',
            'dateRange' => [
                'dateType' => 'PermanentStorage',
                // The first run starts the default lookback before now.
                'from' => '2026-10-22T10:00:00.000000+00:00',
                'restrictToPermanentStorageHwmDate' => true,
            ],
        ], $firstQuery);
        $window = $store->window($key);
        self::assertInstanceOf(CursorWindow::class, $window);
        self::assertSame([self::INBOX_FIRST, self::INBOX_SECOND], $window->pending());

        $document = $transport->fetch(self::INBOX_FIRST);
        self::assertSame(self::RECEIVED_XML, $document->xml);
        self::assertSame(self::INBOX_FIRST, $document->reference->documentId);
        self::assertSame('FA (3)', $document->reference->documentType);
        self::assertEquals(new DateTimeImmutable('2026-11-21T09:00:00+00:00'), $document->reference->receivedAt);
        self::assertSame('/v2/invoices/ksef/'.self::INBOX_FIRST, self::requestsTo($recorder, 'GET', '/invoices/ksef/'.self::INBOX_FIRST)[0]->getUri()->getPath());

        $transport->acknowledge(self::INBOX_FIRST);
        $window = $store->window($key);
        self::assertNotNull($window);
        self::assertEquals(new DateTimeImmutable('2026-10-22T10:00:00+00:00'), $window->from, 'The cursor stays while an invoice of the window is open.');
        self::assertSame([self::INBOX_SECOND], $window->pending());

        $transport->acknowledge(self::INBOX_SECOND);
        $window = $store->window($key);
        self::assertNotNull($window);
        self::assertEquals(new DateTimeImmutable(self::INBOX_HWM), $window->from);
        self::assertSame([], $window->ids);

        self::assertSame([], $transport->pending());
        $secondQuery = self::body(self::requestsTo($recorder, 'POST', '/invoices/query/metadata')[1]);
        self::assertSame(self::INBOX_HWM, self::dateRange($secondQuery)['from']);
        // An empty window with a known end moves on by itself.
        self::assertEquals(new DateTimeImmutable('2026-11-21T09:59:30+00:00'), $store->window($key)?->from);
    }

    public function test_pending_offers_unacknowledged_invoices_again_after_restart(): void
    {
        $store = new InMemoryCursorStore;
        $store->save('test:5265877635:Subject2', CursorWindow::start(new DateTimeImmutable('2026-11-20T00:00:00+00:00')));
        $entry = self::metadataEntry(self::INBOX_FIRST, '2026-11-21T09:00:00.000+00:00');
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::metadataPage([$entry]),
            ...self::tokenAuthResponses(),
            self::metadataPage([$entry]),
        ]);

        $first = $this->transport($recorder, $store);
        self::assertCount(1, $first->pending());
        // A new instance over the same store: the invoice was not acknowledged.
        $second = $this->transport($recorder, $store);
        self::assertSame(self::INBOX_FIRST, $second->pending()[0]->documentId ?? null);

        foreach (self::requestsTo($recorder, 'POST', '/invoices/query/metadata') as $query) {
            self::assertSame('2026-11-20T00:00:00.000000+00:00', self::dateRange(self::body($query))['from']);
        }
    }

    public function test_old_window_is_read_in_bounded_chunks(): void
    {
        $store = new InMemoryCursorStore;
        $store->save(self::INBOX_KEY, CursorWindow::start(new DateTimeImmutable('2026-05-01T00:00:00+00:00')));
        $june = '5555555555-20260601-010080615740-33';
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::metadataPage([self::metadataEntry($june, '2026-06-01T08:00:00.000+00:00')]),
            self::metadataPage([]),
            self::metadataPage([]),
        ]);
        $transport = $this->transport($recorder, $store);

        self::assertSame([$june], array_map(static fn ($reference): string => $reference->documentId, $transport->pending()));
        // KSeF's high water mark (today) lies beyond the chunk: the window ends at the chunk end.
        self::assertEquals(new DateTimeImmutable('2026-07-30T00:00:00+00:00'), $store->window(self::INBOX_KEY)?->hwm);
        $transport->acknowledge($june);
        self::assertEquals(new DateTimeImmutable('2026-07-30T00:00:00+00:00'), $store->window(self::INBOX_KEY)?->from);

        self::assertSame([], $transport->pending());
        self::assertEquals(new DateTimeImmutable('2026-10-28T00:00:00+00:00'), $store->window(self::INBOX_KEY)?->from);
        self::assertSame([], $transport->pending());

        $ranges = array_map(static fn (RequestInterface $query): array => self::dateRange(self::body($query)), self::requestsTo($recorder, 'POST', '/invoices/query/metadata'));
        self::assertSame('2026-05-01T00:00:00.000000+00:00', $ranges[0]['from']);
        self::assertSame('2026-07-30T00:00:00.000000+00:00', $ranges[0]['to']);
        self::assertTrue($ranges[0]['restrictToPermanentStorageHwmDate']);
        self::assertSame('2026-07-30T00:00:00.000000+00:00', $ranges[1]['from']);
        self::assertSame('2026-10-28T00:00:00.000000+00:00', $ranges[1]['to']);
        // A chunk end after now leaves the last query open-ended, as KSeF bounds it by its mark.
        self::assertSame('2026-10-28T00:00:00.000000+00:00', $ranges[2]['from']);
        self::assertArrayNotHasKey('to', $ranges[2]);
    }

    public function test_page_limit_with_more_pages_narrows_the_window_like_truncation(): void
    {
        $store = new InMemoryCursorStore;
        [$pages, $numbers] = self::inboxPages(10);
        $recorder = new RecordingHttpClient(...[...self::tokenAuthResponses(), ...$pages]);
        $transport = $this->transport($recorder, $store);

        $pending = $transport->pending();

        self::assertCount(10, $pending);
        self::assertCount(10, self::requestsTo($recorder, 'POST', '/invoices/query/metadata'));
        self::assertEquals(new DateTimeImmutable('2026-11-01T00:09:00+00:00'), $store->window(self::INBOX_KEY)?->hwm);
        foreach ($numbers as $number) {
            $transport->acknowledge($number);
        }
        // The cursor stops at the last listed invoice, not at the high water mark beyond it.
        self::assertEquals(new DateTimeImmutable('2026-11-01T00:09:00+00:00'), $store->window(self::INBOX_KEY)?->from);
    }

    public function test_one_inbox_read_stays_within_the_hourly_metadata_quota(): void
    {
        $store = new InMemoryCursorStore;
        [$pages] = self::inboxPages(12);
        $recorder = new RecordingHttpClient(...[...self::tokenAuthResponses(), ...$pages]);

        self::assertCount(10, $this->transport($recorder, $store)->pending());
        // KSeF allows 20 metadata queries per hour for each context and IP; one read spends at most ten.
        self::assertCount(10, self::requestsTo($recorder, 'POST', '/invoices/query/metadata'));
        self::assertEquals(new DateTimeImmutable('2026-11-01T00:09:00+00:00'), $store->window(self::INBOX_KEY)?->hwm);
    }

    public function test_rate_limit_after_a_page_narrows_the_stored_window_so_the_cursor_progresses(): void
    {
        $store = new InMemoryCursorStore;
        [$pages, $numbers] = self::inboxPages(2);
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            ...$pages,
            self::fixture(429, 'too-many-requests-problem-details.json', ['Retry-After' => '3600']),
            self::metadataPage([], '2026-11-21T09:59:30.000+00:00'),
        ]);
        $transport = $this->transport($recorder, $store);

        $exception = self::capture(static fn () => $transport->pending());

        self::assertInstanceOf(RateLimited::class, $exception);
        $window = $store->window(self::INBOX_KEY);
        self::assertNotNull($window);
        self::assertSame($numbers, $window->pending(), 'The invoices listed before the refusal are kept.');
        self::assertEquals(new DateTimeImmutable('2026-11-01T00:01:00+00:00'), $window->hwm, 'The window ends at the last listed invoice.');

        foreach ($numbers as $number) {
            $transport->acknowledge($number);
        }
        self::assertSame([], $transport->pending());
        $queries = self::requestsTo($recorder, 'POST', '/invoices/query/metadata');
        self::assertCount(4, $queries);
        self::assertSame('2026-11-01T00:01:00.000000+00:00', self::dateRange(self::body($queries[3]))['from']);
    }

    public function test_rate_limit_on_the_first_page_leaves_the_stored_window_unchanged(): void
    {
        $store = new InMemoryCursorStore;
        $start = CursorWindow::start(new DateTimeImmutable('2026-11-20T00:00:00+00:00'));
        $store->save(self::INBOX_KEY, $start);
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::fixture(429, 'too-many-requests-problem-details.json', ['Retry-After' => '3600']),
        ]);

        self::assertInstanceOf(RateLimited::class, self::capture(fn () => $this->transport($recorder, $store)->pending()));
        self::assertEquals($start, $store->window(self::INBOX_KEY));
    }

    public function test_reset_inbox_restarts_the_cursor_and_prunes_listed_metadata(): void
    {
        $store = new InMemoryCursorStore;
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::metadataPage([
                self::metadataEntry(self::INBOX_FIRST, '2026-11-21T09:00:00.000+00:00'),
                self::metadataEntry(self::INBOX_SECOND, '2026-11-21T09:30:00.000+00:00'),
            ]),
            self::metadataPage([], '2026-11-21T09:59:50.000+00:00'),
        ]);
        $transport = $this->transport($recorder, $store);
        $cache = new ReflectionProperty(KsefTransport::class, 'inbox');

        self::assertCount(2, $transport->pending());
        self::assertSame([self::INBOX_FIRST, self::INBOX_SECOND], array_keys((array) $cache->getValue($transport)));

        $transport->resetInbox(new DateTimeImmutable('2026-11-21T09:45:00+00:00'));
        $window = $store->window(self::INBOX_KEY);
        self::assertNotNull($window);
        self::assertEquals(new DateTimeImmutable('2026-11-21T09:45:00+00:00'), $window->from);
        self::assertSame([], $window->ids, 'The unacknowledged invoices of the old window are dropped.');

        self::assertSame([], $transport->pending());
        self::assertSame('2026-11-21T09:45:00.000000+00:00', self::dateRange(self::body(self::requestsTo($recorder, 'POST', '/invoices/query/metadata')[1]))['from']);
        self::assertSame([], $cache->getValue($transport), 'Metadata of invoices no longer pending is not kept.');
    }

    public function test_fetch_of_unlisted_invoice_queries_its_metadata(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::metadataPage([self::metadataEntry(self::INBOX_FIRST, '2026-11-20T08:00:00.000+00:00')]),
            new Response(200, ['Content-Type' => 'application/xml'], self::RECEIVED_XML),
        ]);

        $document = $this->transport($recorder)->fetch(self::INBOX_FIRST);

        self::assertSame(self::RECEIVED_XML, $document->xml);
        self::assertEquals(new DateTimeImmutable('2026-11-20T08:00:00+00:00'), $document->reference->receivedAt);
        $query = self::body(self::requestsTo($recorder, 'POST', '/invoices/query/metadata')[0]);
        self::assertSame(self::INBOX_FIRST, $query['ksefNumber'] ?? null);
        self::assertSame('Subject2', $query['subjectType'] ?? null);
        // From the day before the date in the KSeF number (Polish time); open-ended up to now.
        self::assertSame(['dateType' => 'PermanentStorage', 'from' => '2026-11-19T00:00:00.000000+01:00'], self::dateRange($query));
    }

    public function test_fetch_of_invoice_outside_the_inbox_is_unexpected(): void
    {
        $recorder = new RecordingHttpClient(...[...self::tokenAuthResponses(), self::metadataPage([])]);
        $transport = $this->transport($recorder);

        $exception = self::capture(static fn () => $transport->fetch(self::INBOX_FIRST));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString(self::INBOX_FIRST, $exception->getMessage());
        self::assertSame([], self::requestsTo($recorder, 'GET', '/invoices/ksef/'.self::INBOX_FIRST));
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(static fn () => $transport->fetch('not-a-ksef-number')));
    }

    public function test_upo_fetched_by_ksef_number_of_document(): void
    {
        $session = '20261120-SO-4F70173000-DBF8CA6741-F1';
        $ksefNumber = '4425138491-20261121-00F89C000000-00';
        $xml = (string) file_get_contents(__DIR__.'/../../fixtures/ksef/upo-te.xml');
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::invoiceStatusResponse(['code' => 200, 'description' => 'Sukces'], ['ksefNumber' => $ksefNumber]),
            new Response(200, ['Content-Type' => 'application/xml'], $xml),
        ]);

        $upo = $this->transport($recorder)->upo($session.'/'.self::INVOICE_REF);

        self::assertSame($xml, $upo->xml);
        self::assertSame([$ksefNumber], $upo->ksefNumbers);
        self::assertSame(
            ['GET /sessions/'.$session.'/invoices/'.self::INVOICE_REF, 'GET /sessions/'.$session.'/invoices/ksef/'.$ksefNumber.'/upo'],
            array_values(array_filter(self::paths($recorder), static fn (string $path): bool => str_starts_with($path, 'GET /sessions/'))),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function statusesWithoutKsefNumber(): iterable
    {
        yield 'still processing' => [['code' => 150, 'description' => 'Trwa przetwarzanie'], 'status 150'];
        yield 'rejected' => [['code' => 450, 'description' => 'Błąd weryfikacji semantyki dokumentu faktury'], 'status 450'];
    }

    /**
     * @param  array<string, mixed>  $status
     */
    #[DataProvider('statusesWithoutKsefNumber')]
    public function test_upo_before_final_status_is_refused_with_message(array $status, string $expected): void
    {
        $recorder = new RecordingHttpClient(...[...self::tokenAuthResponses(), self::invoiceStatusResponse($status)]);
        $transport = $this->transport($recorder);

        $exception = self::capture(static fn () => $transport->upo(self::DOCUMENT_ID));

        self::assertInstanceOf(LogicException::class, $exception);
        self::assertStringContainsString(self::DOCUMENT_ID, $exception->getMessage());
        self::assertStringContainsString($expected, $exception->getMessage());
        self::assertStringContainsString('no KSeF number', $exception->getMessage());
        self::assertSame([], self::requestsTo($recorder, 'GET', '/upo'));
    }

    public function test_close_drops_the_session_even_when_refused_so_the_next_send_opens_a_new_one(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::openingResponses(),
            self::sendResponse(),
            self::fixture(400, 'bad-request-problem-details.json'),
            self::openResponse(self::SECOND_SESSION_REF),
            self::sendResponse(self::SECOND_INVOICE_REF),
        ]);
        $transport = $this->transport($recorder);
        $transport->send(PolishInvoices::domestic());

        self::assertInstanceOf(DocumentRejected::class, self::capture(static fn () => $transport->close()));
        $result = $transport->send(PolishInvoices::domestic());

        self::assertSame(self::SECOND_SESSION_REF.'/'.self::SECOND_INVOICE_REF, $result->documentId);
        self::assertCount(1, self::requestsTo($recorder, 'POST', '/sessions/online/'.self::SESSION_REF.'/close'));
    }

    public function test_debug_info_has_no_secrets(): void
    {
        $recorder = new RecordingHttpClient(...[...self::openingResponses(), self::sendResponse()]);
        $transport = $this->transport($recorder);
        $transport->send(PolishInvoices::domestic());

        ob_start();
        var_dump($transport);
        $dump = (string) ob_get_clean();
        $dump .= print_r($transport, true);

        self::assertStringContainsString(self::SESSION_REF, $dump);
        foreach ([self::KSEF_TOKEN, self::ACCESS_TOKEN, self::REFRESH_TOKEN, self::AUTH_TOKEN, 'PRIVATE KEY'] as $secret) {
            self::assertStringNotContainsString($secret, $dump);
        }
        $exception = self::capture(static fn () => serialize($transport));
        self::assertInstanceOf(LogicException::class, $exception);
    }

    public function test_offline_flag_is_sent(): void
    {
        $recorder = new RecordingHttpClient(...[...self::openingResponses(), self::sendResponse(), self::sendResponse(self::SECOND_INVOICE_REF)]);
        $transport = $this->transport($recorder);

        $transport->send(PolishInvoices::domestic());
        $transport->send(PolishInvoices::domestic(), true);

        $sends = self::requestsTo($recorder, 'POST', '/sessions/online/'.self::SESSION_REF.'/invoices');
        self::assertCount(2, $sends);
        self::assertFalse(self::body($sends[0])['offlineMode']);
        self::assertTrue(self::body($sends[1])['offlineMode']);
        self::assertArrayNotHasKey('hashOfCorrectedInvoice', self::body($sends[1]));
    }

    public function test_batch_offline_flag_is_sent(): void
    {
        $recorder = new RecordingHttpClient(...[...self::batchResponses(), ...self::batchResponses(withKeys: false)]);
        $transport = $this->transport($recorder);

        $transport->sendBatch(['a.xml' => PolishInvoices::domestic()]);
        $transport->sendBatch(['a.xml' => PolishInvoices::domestic()], true);

        $opens = self::requestsTo($recorder, 'POST', '/sessions/batch');
        self::assertCount(2, $opens);
        self::assertFalse(self::body($opens[0])['offlineMode']);
        self::assertTrue(self::body($opens[1])['offlineMode']);
    }

    public function test_status_reads_invoicing_mode(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::invoiceStatusResponse(['code' => 200, 'description' => 'Sukces'], [
                'ksefNumber' => self::KSEF_NUMBER,
                'acquisitionDate' => '2026-11-21T10:00:03.0154302+00:00',
                'invoicingMode' => 'Offline',
            ]),
        ]);

        $status = $this->transport($recorder)->invoiceStatus(self::DOCUMENT_ID);

        self::assertSame(InvoicingMode::Offline, $status->invoicingMode);
    }

    public function test_correction_hash_is_computed_from_rejected_file(): void
    {
        $corrected = (new Fa3Writer($this->now()))->write(PolishInvoices::domestic());
        $recorder = new RecordingHttpClient(...[...self::openingResponses(), self::sendResponse()]);

        $result = $this->transport($recorder)->sendTechnicalCorrection($corrected, self::INVOICE_XML);

        self::assertSame(self::DOCUMENT_ID, $result->documentId);
        self::assertSame(DeliveryState::Pending, $result->state);
        $body = self::body(self::requestsTo($recorder, 'POST', '/sessions/online/'.self::SESSION_REF.'/invoices')[0]);
        self::assertSame(base64_encode(hash('sha256', self::INVOICE_XML, true)), $body['hashOfCorrectedInvoice']);
        self::assertSame(base64_encode(hash('sha256', $corrected, true)), $body['invoiceHash']);
    }

    public function test_correction_of_an_invoice_object_is_written_as_fa3(): void
    {
        $recorder = new RecordingHttpClient(...[...self::openingResponses(), self::sendResponse()]);

        $this->transport($recorder)->sendTechnicalCorrection(PolishInvoices::domestic(), self::INVOICE_XML);

        $expected = (new Fa3Writer($this->now()))->write(PolishInvoices::domestic());
        $body = self::body(self::requestsTo($recorder, 'POST', '/sessions/online/'.self::SESSION_REF.'/invoices')[0]);
        self::assertSame(base64_encode(hash('sha256', $expected, true)), $body['invoiceHash']);
        self::assertSame(base64_encode(hash('sha256', self::INVOICE_XML, true)), $body['hashOfCorrectedInvoice']);
    }

    public function test_correction_with_identical_bytes_refused(): void
    {
        $xml = (new Fa3Writer($this->now()))->write(PolishInvoices::domestic());
        $counter = new RequestCounter(new RecordingHttpClient);
        $transport = $this->transport($counter);

        $raw = self::capture(static fn () => $transport->sendTechnicalCorrection($xml, $xml));
        $written = self::capture(static fn () => $transport->sendTechnicalCorrection(PolishInvoices::domestic(), $xml));
        // A rejected file that fails validation is refused as identical, not as invalid.
        $broken = self::capture(static fn () => $transport->sendTechnicalCorrection(self::INVOICE_XML, self::INVOICE_XML));
        $empty = self::capture(static fn () => $transport->sendTechnicalCorrection($xml, ''));

        foreach ([$raw, $written, $broken] as $exception) {
            self::assertInstanceOf(InvalidArgumentException::class, $exception);
            self::assertStringContainsString('identical', $exception->getMessage());
        }
        self::assertInstanceOf(InvalidArgumentException::class, $empty);
        self::assertSame(0, $counter->count());
    }

    public function test_correction_with_a_rejected_file_that_is_not_xml_refused(): void
    {
        $xml = (new Fa3Writer($this->now()))->write(PolishInvoices::domestic());
        $counter = new RequestCounter(new RecordingHttpClient);
        $transport = $this->transport($counter);
        $notXml = [
            'a path' => '/var/invoices/rejected/FA-1-2026.xml',
            'Base64' => base64_encode(self::INVOICE_XML),
            'a digest' => base64_encode(hash('sha256', self::INVOICE_XML, true)),
            'JSON' => '{"invoiceHash":"abc"}',
            'truncated XML' => substr(self::INVOICE_XML, 0, 80),
        ];

        foreach ($notXml as $what => $rejected) {
            $exception = self::capture(static fn () => $transport->sendTechnicalCorrection($xml, $rejected));
            self::assertInstanceOf(InvalidArgumentException::class, $exception, $what);
            self::assertStringContainsString('not well-formed XML', $exception->getMessage(), $what);
        }
        self::assertSame(0, $counter->count());
    }

    public function test_correction_sends_offline_true(): void
    {
        $corrected = (new Fa3Writer($this->now()))->write(PolishInvoices::domestic());
        $recorder = new RecordingHttpClient(...[...self::openingResponses(), self::sendResponse()]);

        $this->transport($recorder)->sendTechnicalCorrection($corrected, self::INVOICE_XML);

        $body = self::body(self::requestsTo($recorder, 'POST', '/sessions/online/'.self::SESSION_REF.'/invoices')[0]);
        self::assertTrue($body['offlineMode']);
    }

    public function test_21166_maps_to_document_rejected(): void
    {
        $corrected = (new Fa3Writer($this->now()))->write(PolishInvoices::domestic());
        $recorder = new RecordingHttpClient(...[
            ...self::openingResponses(),
            self::json(400, [
                'title' => 'Bad Request',
                'status' => 400,
                'detail' => 'Żądanie jest nieprawidłowe.',
                'errors' => [[
                    'code' => 21166,
                    'description' => 'Korekta techniczna faktury jest niedostępna.',
                    'details' => ['Faktura posiada już przetworzoną prawidłowo korektę techniczną'],
                ]],
            ]),
        ]);
        $transport = $this->transport($recorder);

        $exception = self::capture(static fn () => $transport->sendTechnicalCorrection($corrected, self::INVOICE_XML));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringContainsString('a technical correction was already accepted for the rejected invoice it names', $exception->getMessage());
        self::assertStringContainsString('21166', $exception->getMessage());
        self::assertTrue(KsefErrors::hasCode($exception, 21166));
    }

    public function test_correction_goes_through_online_session_only(): void
    {
        $corrected = (new Fa3Writer($this->now()))->write(PolishInvoices::domestic());
        $recorder = new RecordingHttpClient(...[...self::openingResponses(), self::sendResponse()]);

        $this->transport($recorder)->sendTechnicalCorrection($corrected, self::INVOICE_XML);

        self::assertSame([], self::requestsTo($recorder, 'POST', '/sessions/batch'));
        self::assertSame(
            ['POST /sessions/online', 'POST /sessions/online/'.self::SESSION_REF.'/invoices'],
            array_values(array_filter(self::paths($recorder), static fn (string $path): bool => str_starts_with($path, 'POST /sessions'))),
        );
    }

    private function transport(ClientInterface $client, ?InMemoryCursorStore $store = null, ?KsefConfig $config = null): KsefTransport
    {
        $factory = new Psr17Factory;

        return KsefTransport::create($config ?? $this->sessionConfig(), new HttpClient($client, $factory, $factory), $store, now: $this->now());
    }

    /**
     * The answers of one single-part batch send: the authentication (with the key list on the
     * first one), the opened batch session with its upload request, the upload and the close.
     *
     * @return list<Response>
     */
    private static function batchResponses(bool $withKeys = true): array
    {
        $open = self::json(201, [
            'referenceNumber' => '20250626-SB-213D593000-4DE10D80A5-E9',
            'partUploadRequests' => [[
                'ordinalNumber' => 1,
                'method' => 'PUT',
                'url' => 'https://ksef-api-storage.example/storage/00/batch-parts/1?sv=2025-01-05&sr=b&sp=w&sig=c2lnbmF0dXJl',
                'headers' => ['x-ms-blob-type' => 'BlockBlob'],
            ]],
        ]);

        return [
            ...($withKeys ? [self::keysResponse(), ...self::tokenAuthResponses(withKeys: false)] : []),
            $open,
            new Response(201),
            new Response(204),
        ];
    }

    /**
     * One InvoiceMetadata entry, shaped like the OpenAPI example.
     *
     * @return array<string, mixed>
     */
    private static function metadataEntry(string $ksefNumber, string $permanentStorageDate): array
    {
        return [
            'ksefNumber' => $ksefNumber,
            'invoiceNumber' => 'FA/1/2026',
            'issueDate' => '2026-11-20',
            'invoicingDate' => $permanentStorageDate,
            'acquisitionDate' => $permanentStorageDate,
            'permanentStorageDate' => $permanentStorageDate,
            'seller' => ['nip' => substr($ksefNumber, 0, 10), 'name' => 'Test Seller'],
            'buyer' => ['identifier' => ['type' => 'Nip', 'value' => '5265877635'], 'name' => 'Test Buyer'],
            'netAmount' => 100.0,
            'grossAmount' => 123.0,
            'vatAmount' => 23.0,
            'currency' => 'PLN',
            'invoicingMode' => 'Online',
            'invoiceType' => 'Vat',
            'formCode' => ['systemCode' => 'FA (3)', 'schemaVersion' => '1-0E', 'value' => 'FA'],
            'isSelfInvoicing' => false,
            'hasAttachment' => false,
            'invoiceHash' => base64_encode(hash('sha256', self::RECEIVED_XML, true)),
            'thirdSubjects' => [],
        ];
    }

    /**
     * Metadata pages of one invoice each, every page announcing more, a minute apart from
     * 2026-11-01T00:00.
     *
     * @return array{list<Response>, list<string>}
     */
    private static function inboxPages(int $count): array
    {
        $pages = [];
        $numbers = [];
        for ($i = 0; $i < $count; $i++) {
            $first32 = sprintf('5555555555-20261101-0100806157%02X', $i);
            $numbers[] = $number = $first32.'-'.KsefNumber::checksum($first32);
            $pages[] = self::json(200, [
                'hasMore' => true,
                'isTruncated' => false,
                'permanentStorageHwmDate' => self::INBOX_HWM,
                'invoices' => [self::metadataEntry($number, sprintf('2026-11-01T00:%02d:00.000+00:00', $i))],
            ]);
        }

        return [$pages, $numbers];
    }

    /**
     * @param  list<array<string, mixed>>  $invoices
     */
    private static function metadataPage(array $invoices, string $hwm = self::INBOX_HWM): Response
    {
        return self::json(200, ['hasMore' => false, 'isTruncated' => false, 'permanentStorageHwmDate' => $hwm, 'invoices' => $invoices]);
    }

    /**
     * @param  array<mixed>  $query
     * @return array<mixed>
     */
    private static function dateRange(array $query): array
    {
        $range = $query['dateRange'] ?? null;
        self::assertIsArray($range);

        return $range;
    }

    private static function networkException(string $message): NetworkExceptionInterface
    {
        return new class(new Request('POST', 'https://api-test.ksef.mf.gov.pl/v2'), $message) extends RuntimeException implements NetworkExceptionInterface
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
