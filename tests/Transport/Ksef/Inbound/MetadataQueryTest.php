<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Inbound;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth\AuthFixtures;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InvoiceMetadata;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InvoiceQueryFilters;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\MetadataQuery;
use InvalidArgumentException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class MetadataQueryTest extends TestCase
{
    use AuthFixtures;

    private const FIRST = '5265877635-20250626-010080DD2B5E-26';

    private const SECOND = '5555555555-20250828-010080615740-E4';

    private const INVOICE_XML = '<?xml version="1.0" encoding="UTF-8"?><Faktura xmlns="http://crd.gov.pl/wzor/2025/06/25/13775/"/>';

    public function test_query_body_and_paging(): void
    {
        $recorder = new RecordingHttpClient(
            ...self::tokenAuthResponses(),
            ...[
                self::page([self::entry(self::FIRST, '2025-08-28T09:23:01.388123+00:00')], hasMore: true),
                self::page([self::entry(self::SECOND, '2025-08-28T10:24:01.388123+00:00')], hasMore: false),
            ],
        );
        $query = new MetadataQuery($this->authorizedApi($recorder));
        $filters = InvoiceQueryFilters::incoming(new DateTimeImmutable('2025-08-28T09:22:13.388+00:00'));

        $numbers = [];
        $offset = 0;
        do {
            $page = $query->page($filters, $offset, 100);
            foreach ($page['invoices'] as $invoice) {
                $numbers[] = $invoice->ksefNumber;
            }
            $offset++;
        } while ($page['hasMore']);

        self::assertSame([self::FIRST, self::SECOND], $numbers);
        $queries = self::queries($recorder);
        self::assertCount(2, $queries);
        foreach ($queries as $offset => $request) {
            self::assertSame('POST', $request->getMethod());
            self::assertSame('/v2/invoices/query/metadata', $request->getUri()->getPath());
            parse_str($request->getUri()->getQuery(), $parameters);
            self::assertSame(['pageOffset' => (string) $offset, 'pageSize' => '100', 'sortOrder' => 'Asc'], $parameters);
            self::assertSame('Bearer '.self::ACCESS_TOKEN, $request->getHeaderLine('Authorization'));
            self::assertSame([
                'subjectType' => 'Subject2',
                'dateRange' => [
                    'dateType' => 'PermanentStorage',
                    'from' => '2025-08-28T09:22:13.388000+00:00',
                    'restrictToPermanentStorageHwmDate' => true,
                ],
            ], self::body($request));
        }
    }

    public function test_hwm_is_returned_for_permanent_storage(): void
    {
        $recorder = new RecordingHttpClient(...self::tokenAuthResponses(), ...[
            self::page([self::entry(self::FIRST, '2025-08-28T09:23:01.388123+00:00')], hasMore: false, hwm: '2025-08-28T09:23:55.388+00:00'),
        ]);

        $page = (new MetadataQuery($this->authorizedApi($recorder)))->page(InvoiceQueryFilters::incoming(new DateTimeImmutable('2025-08-28T00:00:00+00:00')));

        self::assertEquals(new DateTimeImmutable('2025-08-28T09:23:55.388+00:00'), $page['hwm']);
        self::assertFalse($page['hasMore']);
        self::assertFalse($page['isTruncated']);
        self::assertCount(1, $page['invoices']);
        $invoice = $page['invoices'][0];
        self::assertInstanceOf(InvoiceMetadata::class, $invoice);
        self::assertSame(self::FIRST, $invoice->ksefNumber);
        self::assertSame('FA/1/2025', $invoice->invoiceNumber);
        self::assertSame('5555555555', $invoice->sellerNip);
        self::assertEquals(new DateTimeImmutable('2025-08-28T09:23:01.388123+00:00'), $invoice->permanentStorageDate);
        self::assertSame('mkht+3m5trnfxlTYhq3QFn74LkEO69MFNlsMAkCDSPA=', $invoice->invoiceHash);
        self::assertSame('PLN', $invoice->raw['currency']);
    }

    public function test_hwm_is_null_for_other_date_types(): void
    {
        $recorder = new RecordingHttpClient(...self::tokenAuthResponses(), ...[self::page([], hasMore: false, hwm: null)]);

        $page = (new MetadataQuery($this->authorizedApi($recorder)))->page(new InvoiceQueryFilters('Subject1', 'Issue', new DateTimeImmutable('2025-08-01T00:00:00+00:00')));

        self::assertNull($page['hwm']);
        self::assertSame([], $page['invoices']);
    }

    public function test_answer_without_paging_flags_is_unexpected(): void
    {
        $recorder = new RecordingHttpClient(...self::tokenAuthResponses(), ...[self::json(200, ['invoices' => []])]);

        $exception = self::capture(fn () => (new MetadataQuery($this->authorizedApi($recorder)))->page(InvoiceQueryFilters::incoming(new DateTimeImmutable('2025-08-28T00:00:00+00:00'))));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('hasMore', $exception->getMessage());
    }

    public function test_page_size_outside_ksef_limits_is_refused(): void
    {
        $query = new MetadataQuery($this->authorizedApi(new RecordingHttpClient));
        $filters = InvoiceQueryFilters::incoming(new DateTimeImmutable('2025-08-28T00:00:00+00:00'));

        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => $query->page($filters, 0, 9)));
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => $query->page($filters, 0, 251)));
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => $query->page($filters, -1)));
    }

    public function test_download_refuses_invalid_ksef_number_without_request(): void
    {
        $recorder = new RecordingHttpClient;
        $query = new MetadataQuery($this->authorizedApi($recorder));

        foreach (['', '5265877635-20250626-010080DD2B5E-27', '../../sessions', '5265877635-20250626-010080dd2b5e-26'] as $number) {
            $exception = self::capture(fn () => $query->download($number));
            self::assertInstanceOf(InvalidArgumentException::class, $exception, $number);
        }
        self::assertSame([], $recorder->requests());
    }

    public function test_download_returns_the_invoice_bytes(): void
    {
        $recorder = new RecordingHttpClient(...self::tokenAuthResponses(), ...[
            new Response(200, ['Content-Type' => 'application/xml', 'x-ms-meta-hash' => base64_encode(hash('sha256', self::INVOICE_XML, true))], self::INVOICE_XML),
        ]);

        $xml = (new MetadataQuery($this->authorizedApi($recorder)))->download(self::FIRST);

        self::assertSame(self::INVOICE_XML, $xml);
        $requests = $recorder->requests();
        $download = $requests[count($requests) - 1];
        self::assertSame('GET', $download->getMethod());
        self::assertSame('/v2/invoices/ksef/'.self::FIRST, $download->getUri()->getPath());
        self::assertSame('application/xml', $download->getHeaderLine('Accept'));
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $download->getHeaderLine('Authorization'));
    }

    public function test_download_with_mismatching_hash_header_is_unexpected(): void
    {
        $recorder = new RecordingHttpClient(...self::tokenAuthResponses(), ...[
            new Response(200, ['Content-Type' => 'application/xml', 'x-ms-meta-hash' => base64_encode(hash('sha256', 'other', true))], self::INVOICE_XML),
        ]);

        $exception = self::capture(fn () => (new MetadataQuery($this->authorizedApi($recorder)))->download(self::FIRST));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString(self::FIRST, $exception->getMessage());
    }

    public function test_filters_refuse_inconsistent_values(): void
    {
        $from = new DateTimeImmutable('2025-08-28T00:00:00+00:00');

        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => new InvoiceQueryFilters('Buyer', 'PermanentStorage', $from)));
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => new InvoiceQueryFilters('Subject2', 'Storage', $from)));
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => new InvoiceQueryFilters('Subject2', 'Issue', $from, restrictToHwm: true)));
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => new InvoiceQueryFilters('Subject2', 'Issue', $from, $from->modify('-1 second'))));
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => new InvoiceQueryFilters('Subject2', 'Issue', $from, ksefNumber: 'nope')));
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => new InvoiceQueryFilters('Subject2', 'Issue', $from, sellerNip: '123')));

        $filters = new InvoiceQueryFilters('Subject1', 'Invoicing', $from, $from->modify('+1 day'), ksefNumber: self::FIRST, sellerNip: '5265877635');
        self::assertSame([
            'subjectType' => 'Subject1',
            'dateRange' => ['dateType' => 'Invoicing', 'from' => '2025-08-28T00:00:00.000000+00:00', 'to' => '2025-08-29T00:00:00.000000+00:00'],
            'ksefNumber' => self::FIRST,
            'sellerNip' => '5265877635',
        ], $filters->json());
    }

    /**
     * One InvoiceMetadata entry, shaped like the OpenAPI example.
     *
     * @return array<string, mixed>
     */
    private static function entry(string $ksefNumber, string $permanentStorageDate): array
    {
        return [
            'ksefNumber' => $ksefNumber,
            'invoiceNumber' => 'FA/1/2025',
            'issueDate' => '2025-08-27',
            'invoicingDate' => '2025-08-28T09:22:13.3881234+00:00',
            'acquisitionDate' => '2025-08-28T09:22:56.3881234+00:00',
            'permanentStorageDate' => $permanentStorageDate,
            'seller' => ['nip' => '5555555555', 'name' => 'Test Company 1'],
            'buyer' => ['identifier' => ['type' => 'Nip', 'value' => '5265877635'], 'name' => 'Test Company 4'],
            'netAmount' => 100.0,
            'grossAmount' => 123.0,
            'vatAmount' => 23.0,
            'currency' => 'PLN',
            'invoicingMode' => 'Online',
            'invoiceType' => 'Vat',
            'formCode' => ['systemCode' => 'FA (3)', 'schemaVersion' => '1-0E', 'value' => 'FA'],
            'isSelfInvoicing' => false,
            'hasAttachment' => false,
            'invoiceHash' => 'mkht+3m5trnfxlTYhq3QFn74LkEO69MFNlsMAkCDSPA=',
            'thirdSubjects' => [],
        ];
    }

    /**
     * A QueryInvoicesMetadataResponse.
     *
     * @param  list<array<string, mixed>>  $invoices
     */
    private static function page(array $invoices, bool $hasMore, bool $isTruncated = false, ?string $hwm = '2025-08-28T12:00:00.000+00:00'): Response
    {
        $answer = ['hasMore' => $hasMore, 'isTruncated' => $isTruncated, 'invoices' => $invoices];
        if ($hwm !== null) {
            $answer['permanentStorageHwmDate'] = $hwm;
        }

        return self::json(200, $answer);
    }

    /**
     * @return list<RequestInterface>
     */
    private static function queries(RecordingHttpClient $recorder): array
    {
        return array_values(array_filter(
            $recorder->requests(),
            static fn (RequestInterface $request): bool => $request->getUri()->getPath() === '/v2/invoices/query/metadata',
        ));
    }
}
