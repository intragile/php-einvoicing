<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\Spain;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Tests\Support\SpanishInvoices;
use IntrAgile\EInvoicing\Tests\Transport\B2Brouter\B2BrouterFixtures;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\B2BrouterVerifactu;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\IssuedInvoice;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\VerifactuPolling;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class B2BrouterVerifactuTest extends TestCase
{
    use B2BrouterFixtures;

    private const SPAIN = __DIR__.'/../../../fixtures/spain/b2brouter/api/';

    private const IMPORT_QUERY = 'send_after_import=true&issued=true&transport_type_code_for_contact=email&document_type_code_for_contact=pdf.invoice';

    private const CII = '<?xml version="1.0" encoding="UTF-8"?><rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"/>';

    /** @var list<int> */
    private array $waits = [];

    public function test_issue_posts_ubl_to_import_with_email_transport(): void
    {
        $recorder = new RecordingHttpClient(self::spain(201, 'import-201.json'));
        $invoice = SpanishInvoices::domestic();

        $this->verifactu($recorder)->issue($invoice);

        self::assertCount(1, $recorder->requests());
        $request = $recorder->requests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.b2brouter.net/accounts/4711/invoices/import?'.self::IMPORT_QUERY, (string) $request->getUri());
        self::assertSame('application/octet-stream', $request->getHeaderLine('Content-Type'));
        self::assertSame('2026-06-26', $request->getHeaderLine('X-B2B-API-Version'));
        self::assertSame(self::KEY, $request->getHeaderLine('X-B2B-API-Key'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame((new UblWriter)->write($invoice), (string) $request->getBody());
        self::assertSame('', $request->getHeaderLine('Idempotency-Key'));
    }

    public function test_issue_accepts_ubl_text_as_given(): void
    {
        $recorder = new RecordingHttpClient(self::spain(201, 'import-201.json'));
        $ubl = (new UblWriter)->write(SpanishInvoices::creditNote());

        $this->verifactu($recorder)->issue($ubl);

        self::assertSame($ubl, (string) $recorder->requests()[0]->getBody());
    }

    public function test_issue_result_is_pending(): void
    {
        $issued = $this->verifactu(new RecordingHttpClient(self::spain(201, 'import-201.json')))->issue(SpanishInvoices::domestic());

        self::assertSame('24433033', $issued->invoiceId);
        self::assertSame('LIVE-75058f2f4e70', $issued->number);
        self::assertSame('sending', $issued->state);
        self::assertSame(['1064652'], $issued->taxReportIds);
        self::assertSame('1064652', $issued->latestTaxReportId());
        self::assertSame('24433033', $issued->status->documentId);
        self::assertSame(DeliveryState::Pending, $issued->status->state);
        self::assertNull($issued->status->failure);
    }

    public function test_sent_invoice_is_still_pending_until_its_report_registers(): void
    {
        $response = self::json(201, ['invoice' => ['id' => 501, 'number' => 'F-1', 'state' => 'sent', 'tax_report_ids' => []]]);

        $issued = $this->verifactu(new RecordingHttpClient($response))->issue(SpanishInvoices::domestic());

        self::assertSame(DeliveryState::Pending, $issued->status->state);
        self::assertNull($issued->latestTaxReportId());
    }

    public function test_errored_invoice_is_failed(): void
    {
        $response = self::json(201, ['invoice' => ['id' => 502, 'state' => 'error', 'error_message' => 'The NIF is not valid.']]);

        $issued = $this->verifactu(new RecordingHttpClient($response))->issue(SpanishInvoices::domestic());

        self::assertSame(DeliveryState::Failed, $issued->status->state);
        self::assertSame('The NIF is not valid.', $issued->status->failure?->message);
        self::assertSame([], $issued->taxReportIds);
    }

    public function test_raw_cii_is_refused_without_request(): void
    {
        $recorder = new RecordingHttpClient;

        $exception = self::capture(fn () => $this->verifactu($recorder)->issue(self::CII));

        self::assertInstanceOf(UnsupportedDocument::class, $exception);
        self::assertStringContainsString('CrossIndustryInvoice', $exception->getMessage());
        self::assertSame([], $recorder->requests());
    }

    public function test_text_that_is_not_xml_is_refused_without_request(): void
    {
        $recorder = new RecordingHttpClient;

        self::assertInstanceOf(UnsupportedDocument::class, self::capture(fn () => $this->verifactu($recorder)->issue('not xml')));
        self::assertSame([], $recorder->requests());
    }

    public function test_number_taken_is_document_rejected(): void
    {
        $exception = self::capture(fn () => $this->verifactu(new RecordingHttpClient(self::fixture(422, 'error-422-taken.json')))->issue(SpanishInvoices::domestic()));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringContainsString(FailureCategory::Duplicate->value, $exception->getMessage());
        self::assertStringContainsString('parameter_taken on number', $exception->getMessage());
        self::assertStringContainsString('invoice number "', $exception->getMessage());
    }

    public function test_lost_import_is_outcome_unknown_naming_the_number(): void
    {
        $ubl = (new UblWriter)->write(SpanishInvoices::domestic());
        if (preg_match('#<cbc:ID>([^<]+)</cbc:ID>#', $ubl, $match) !== 1) {
            self::fail('The written UBL has no cbc:ID.');
        }

        $exception = self::capture(fn () => $this->verifactu(new RecordingHttpClient(self::networkException('Connection reset')))->issue($ubl));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString(sprintf('invoice number "%s"', $match[1]), $exception->getMessage());
        self::assertStringContainsString('may or may not have imported it', $exception->getMessage());
        self::assertStringContainsString('before resending', $exception->getMessage());
    }

    public function test_server_error_and_unreadable_201_are_outcome_unknown(): void
    {
        foreach ([new Response(503, [], 'busy'), new Response(201, [], '<html/>'), self::json(201, ['invoice' => ['state' => 'new']])] as $response) {
            $exception = self::capture(fn () => $this->verifactu(new RecordingHttpClient($response))->issue(SpanishInvoices::domestic()));

            self::assertInstanceOf(OutcomeUnknown::class, $exception);
            self::assertStringContainsString('before resending', $exception->getMessage());
        }
    }

    public function test_invoice_reads_tax_report_ids(): void
    {
        $recorder = new RecordingHttpClient(self::spain(200, 'invoice.json'));

        $issued = $this->verifactu($recorder)->invoice('24433033');

        self::assertSame('GET', $recorder->requests()[0]->getMethod());
        self::assertSame('https://api.b2brouter.net/invoices/24433033', (string) $recorder->requests()[0]->getUri());
        self::assertSame(self::KEY, $recorder->requests()[0]->getHeaderLine('X-B2B-API-Key'));
        self::assertSame(['1064652'], $issued->taxReportIds);
        self::assertSame('1064652', $issued->latestTaxReportId());
    }

    public function test_invoice_of_another_id_is_unexpected(): void
    {
        self::assertInstanceOf(UnexpectedResponse::class, self::capture(fn () => $this->verifactu(new RecordingHttpClient(self::spain(200, 'invoice.json')))->invoice('24433034')));
    }

    public function test_malformed_tax_report_ids_are_unexpected(): void
    {
        foreach (['1', [0], ['x'], [1.5]] as $ids) {
            $response = self::json(200, ['invoice' => ['id' => 503, 'state' => 'sent', 'tax_report_ids' => $ids]]);

            self::assertInstanceOf(UnexpectedResponse::class, self::capture(fn () => $this->verifactu(new RecordingHttpClient($response))->invoice('503')));
        }
    }

    public function test_latest_tax_report_id_is_the_highest(): void
    {
        $issued = new IssuedInvoice('1', null, 'sent', ['7001', '7010', '7002'], new DeliveryStatus('1', DeliveryState::Pending));

        self::assertSame('7010', $issued->latestTaxReportId());
        self::assertSame('100000000000000000001', (new IssuedInvoice('1', null, 'sent', ['99999999999999999999', '100000000000000000001'], new DeliveryStatus('1', DeliveryState::Pending)))->latestTaxReportId());
        self::assertNull((new IssuedInvoice('1', null, 'new', [], new DeliveryStatus('1', DeliveryState::Pending)))->latestTaxReportId());
    }

    public function test_tax_report_is_read_by_id(): void
    {
        $recorder = new RecordingHttpClient(self::spain(200, 'tax-report.json'));

        $report = $this->verifactu($recorder)->taxReport('1064652');

        self::assertSame('https://api.b2brouter.net/tax_reports/1064652', (string) $recorder->requests()[0]->getUri());
        self::assertSame('registered', $report->state);
        self::assertTrue($report->isRegistered());
    }

    public function test_tax_report_of_another_id_or_unknown_is_unexpected(): void
    {
        self::assertInstanceOf(UnexpectedResponse::class, self::capture(fn () => $this->verifactu(new RecordingHttpClient(self::spain(200, 'tax-report.json')))->taxReport('1064651')));
        self::assertInstanceOf(UnexpectedResponse::class, self::capture(fn () => $this->verifactu(new RecordingHttpClient(self::fixture(404, 'error-404.json')))->taxReport('292891')));
    }

    public function test_lost_read_is_transport_unavailable(): void
    {
        self::assertInstanceOf(TransportUnavailable::class, self::capture(fn () => $this->verifactu(new RecordingHttpClient(self::networkException('timeout')))->taxReport('1')));
    }

    public function test_await_registered_returns_the_registered_report(): void
    {
        $recorder = new RecordingHttpClient(
            self::invoiceWith([]),
            self::invoiceWith([7001]),
            self::report(7001, 'sent'),
            self::report(7001, 'registered'),
        );

        $report = $this->verifactu($recorder)->awaitRegistered('501');

        self::assertNotNull($report);
        self::assertSame('registered', $report->state);
        self::assertSame([3000, 3000, 3000], $this->waits);
        self::assertSame(
            ['/invoices/501', '/invoices/501', '/tax_reports/7001', '/tax_reports/7001'],
            array_map(static fn ($request): string => $request->getUri()->getPath(), $recorder->requests()),
        );
    }

    public function test_await_registered_stops_at_a_refused_report(): void
    {
        $report = $this->verifactu(new RecordingHttpClient(self::invoiceWith([7001]), self::report(7001, 'refused')))->awaitRegistered('501');

        self::assertNotNull($report);
        self::assertSame('refused', $report->state);
        self::assertSame(DeliveryState::Failed, $report->deliveryStatus()->state);
        self::assertSame([3000], $this->waits);
    }

    public function test_await_registered_stops_at_a_report_registered_with_errors(): void
    {
        $recorder = new RecordingHttpClient(self::invoiceWith([7001]), self::report(7001, 'signed'), self::report(7001, 'registered_with_errors'));

        $report = $this->verifactu($recorder)->awaitRegistered('501');

        self::assertNotNull($report);
        self::assertSame('registered_with_errors', $report->state);
        self::assertTrue($report->isFinal());
        self::assertFalse($report->isRegistered());
        self::assertTrue($report->isRegisteredWithErrors());
        self::assertSame(DeliveryState::Delivered, $report->deliveryStatus()->state);
        self::assertSame([3000, 3000], $this->waits);
        self::assertCount(3, $recorder->requests());
    }

    public function test_await_registered_is_bounded(): void
    {
        $recorder = new RecordingHttpClient(...array_fill(0, 5, self::invoiceWith([])));

        $report = $this->verifactu($recorder, new VerifactuPolling(5, 3000, $this->recordWait(...)))->awaitRegistered('501');

        self::assertNull($report);
        self::assertSame([3000, 3000, 3000, 3000], $this->waits);
        self::assertCount(5, $recorder->requests());
    }

    public function test_await_returns_last_non_final_report_at_the_bound(): void
    {
        $recorder = new RecordingHttpClient(self::invoiceWith([7001]), self::report(7001, 'processing'), self::report(7001, 'signed'));

        $report = $this->verifactu($recorder, new VerifactuPolling(3, 50, $this->recordWait(...)))->awaitRegistered('501');

        self::assertNotNull($report);
        self::assertSame('signed', $report->state);
        self::assertFalse($report->isFinal());
        self::assertSame([50, 50], $this->waits);
        self::assertCount(3, $recorder->requests());
    }

    public function test_await_reads_a_report_that_first_appears_on_the_last_attempt(): void
    {
        $recorder = new RecordingHttpClient(self::invoiceWith([]), self::invoiceWith([]), self::invoiceWith([7001]), self::report(7001, 'signed'));

        $report = $this->verifactu($recorder, new VerifactuPolling(3, 50, $this->recordWait(...)))->awaitRegistered('501');

        self::assertNotNull($report);
        self::assertSame('signed', $report->state);
        self::assertSame([50, 50], $this->waits);
        self::assertSame(
            ['/invoices/501', '/invoices/501', '/invoices/501', '/tax_reports/7001'],
            array_map(static fn ($request): string => $request->getUri()->getPath(), $recorder->requests()),
        );
    }

    public function test_await_with_one_attempt_reads_the_report_at_once(): void
    {
        $recorder = new RecordingHttpClient(self::invoiceWith([7001]), self::report(7001, 'registered'));

        $report = $this->verifactu($recorder, new VerifactuPolling(1, 50, $this->recordWait(...)))->awaitRegistered('501');

        self::assertNotNull($report);
        self::assertSame('registered', $report->state);
        self::assertSame([], $this->waits);
        self::assertCount(2, $recorder->requests());
    }

    public function test_polling_defaults_and_bounds(): void
    {
        $polling = new VerifactuPolling;

        self::assertSame(60, $polling->attempts);
        self::assertSame(3000, $polling->waitMs);
        foreach ([[0, 3000], [-1, 3000], [20, 0], [20, -5]] as [$attempts, $waitMs]) {
            self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => new VerifactuPolling($attempts, $waitMs)));
        }

        (new VerifactuPolling(1, 7, $this->recordWait(...)))->sleep(7);
        self::assertSame([7], $this->waits);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIds(): iterable
    {
        yield 'traversal' => ['../1'];
        yield 'space' => ['1 2'];
        yield 'empty' => [''];
        yield 'sign' => ['-1'];
        yield 'query' => ['1?x=1'];
    }

    #[DataProvider('badIds')]
    public function test_ids_are_digits_only(string $id): void
    {
        $recorder = new RecordingHttpClient;
        $verifactu = $this->verifactu($recorder);

        foreach ([
            fn () => $verifactu->invoice($id),
            fn () => $verifactu->taxReport($id),
            fn () => $verifactu->awaitRegistered($id),
        ] as $call) {
            self::assertInstanceOf(InvalidArgumentException::class, self::capture($call));
        }
        self::assertSame([], $recorder->requests());
    }

    public function test_find_by_number_returns_the_single_exact_match(): void
    {
        $recorder = new RecordingHttpClient(
            self::invoiceList([['id' => 24433033, 'number' => 'LIVE-75058f2f4e70'], ['id' => 24433040, 'number' => 'LIVE-75058f2f4e70-B']]),
            self::spain(200, 'invoice.json'),
        );

        $found = $this->verifactu($recorder)->findByNumber('LIVE-75058f2f4e70');

        self::assertNotNull($found);
        self::assertSame('24433033', $found->invoiceId);
        self::assertSame(['1064652'], $found->taxReportIds);
        self::assertSame(
            'https://api.b2brouter.net/accounts/4711/invoices?type=IssuedInvoice&number=LIVE-75058f2f4e70&offset=0&limit=500',
            (string) $recorder->requests()[0]->getUri(),
        );
        self::assertSame('https://api.b2brouter.net/invoices/24433033', (string) $recorder->requests()[1]->getUri());
    }

    public function test_find_by_number_encodes_the_number(): void
    {
        $recorder = new RecordingHttpClient(self::invoiceList([]));

        $this->verifactu($recorder)->findByNumber('A/1 &2');

        self::assertStringContainsString('&number=A%2F1%20%262&', (string) $recorder->requests()[0]->getUri());
    }

    public function test_find_by_number_without_match_is_null(): void
    {
        $recorder = new RecordingHttpClient(self::invoiceList([['id' => 24433040, 'number' => 'LIVE-1-B']]));

        self::assertNull($this->verifactu($recorder)->findByNumber('LIVE-1'));
        self::assertCount(1, $recorder->requests());
    }

    public function test_find_by_number_with_two_matches_throws(): void
    {
        $recorder = new RecordingHttpClient(self::invoiceList([['id' => 11, 'number' => 'LIVE-1'], ['id' => 12, 'number' => 'LIVE-1']]));

        $exception = self::capture(fn () => $this->verifactu($recorder)->findByNumber('LIVE-1'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('"LIVE-1"', $exception->getMessage());
        self::assertCount(1, $recorder->requests());
    }

    public function test_find_by_number_beyond_one_page_is_not_decided(): void
    {
        $invoices = [];
        for ($i = 1; $i <= 3; $i++) {
            $invoices[] = ['id' => $i, 'number' => 'LIVE-1'.$i];
        }
        $response = self::json(200, ['invoices' => $invoices, 'meta' => ['total_count' => 900, 'offset' => 0, 'limit' => 500]]);

        self::assertInstanceOf(UnexpectedResponse::class, self::capture(fn () => $this->verifactu(new RecordingHttpClient($response))->findByNumber('LIVE-1')));
    }

    public function test_find_by_number_takes_an_exact_match_on_a_page_of_many(): void
    {
        $invoices = [['id' => 24433033, 'number' => 'LIVE-75058f2f4e70']];
        for ($i = 1; $i < 500; $i++) {
            $invoices[] = ['id' => 30000000 + $i, 'number' => 'LIVE-75058f2f4e70-'.$i];
        }
        $recorder = new RecordingHttpClient(
            self::json(200, ['invoices' => $invoices, 'meta' => ['total_count' => 900, 'offset' => 0, 'limit' => 500]]),
            self::spain(200, 'invoice.json'),
        );

        $found = $this->verifactu($recorder)->findByNumber('LIVE-75058f2f4e70');

        self::assertNotNull($found);
        self::assertSame('24433033', $found->invoiceId);
    }

    public function test_find_by_number_on_a_complete_full_page_without_match_is_null(): void
    {
        $invoices = [];
        for ($i = 1; $i <= 500; $i++) {
            $invoices[] = ['id' => $i, 'number' => 'LIVE-1-'.$i];
        }

        self::assertNull($this->verifactu(new RecordingHttpClient(self::invoiceList($invoices)))->findByNumber('LIVE-1'));
    }

    public function test_find_by_number_on_a_full_page_without_total_is_not_decided(): void
    {
        $invoices = [];
        for ($i = 1; $i <= 500; $i++) {
            $invoices[] = ['id' => $i, 'number' => 'LIVE-1-'.$i];
        }
        $response = self::json(200, ['invoices' => $invoices]);

        $exception = self::capture(fn () => $this->verifactu(new RecordingHttpClient($response))->findByNumber('LIVE-1'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('cannot decide', $exception->getMessage());
    }

    public function test_find_by_blank_number_is_refused_without_request(): void
    {
        $recorder = new RecordingHttpClient;

        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => $this->verifactu($recorder)->findByNumber(' ')));
        self::assertSame([], $recorder->requests());
    }

    public function test_key_never_appears_in_exceptions(): void
    {
        $echo = sprintf('{"error":{"code":"invalid","message":"Key %s, %s, %s is not valid"}}', self::KEY, rawurlencode(self::KEY), base64_encode(self::KEY));
        $calls = [
            fn (B2BrouterVerifactu $verifactu) => $verifactu->issue(SpanishInvoices::domestic()),
            fn (B2BrouterVerifactu $verifactu) => $verifactu->invoice('1'),
            fn (B2BrouterVerifactu $verifactu) => $verifactu->taxReport('1'),
            fn (B2BrouterVerifactu $verifactu) => $verifactu->findByNumber('F-1'),
        ];
        foreach ([400, 401, 403, 404, 422, 500] as $status) {
            foreach ($calls as $index => $call) {
                $exception = self::capture(fn () => $call($this->verifactu(new RecordingHttpClient(new Response($status, [], $echo)))));
                $text = self::exceptionText($exception);

                self::assertStringNotContainsString(self::KEY, $text, $status.'/'.$index);
                self::assertStringNotContainsString(base64_encode(self::KEY), $text, $status.'/'.$index);
            }
        }
    }

    public function test_debug_info_has_no_key(): void
    {
        $verifactu = $this->verifactu(new RecordingHttpClient);

        ob_start();
        var_dump($verifactu);
        $dump = (string) ob_get_clean();

        foreach ([print_r($verifactu, true), var_export($verifactu, true), $dump] as $text) {
            self::assertStringNotContainsString(self::KEY, $text);
        }
    }

    private function verifactu(RecordingHttpClient $recorder, ?VerifactuPolling $polling = null): B2BrouterVerifactu
    {
        $factory = new Psr17Factory;

        return new B2BrouterVerifactu(
            new B2BrouterConfig(self::KEY, self::ACCOUNT, self::BASE),
            new HttpClient($recorder, $factory, $factory),
            $polling ?? new VerifactuPolling(sleep: $this->recordWait(...)),
        );
    }

    private function recordWait(int $ms): void
    {
        $this->waits[] = $ms;
    }

    private static function spain(int $status, string $name): Response
    {
        $contents = file_get_contents(self::SPAIN.$name);
        self::assertIsString($contents);

        return new Response($status, ['Content-Type' => 'application/json'], $contents);
    }

    /**
     * @param  list<int>  $ids
     */
    private static function invoiceWith(array $ids): Response
    {
        return self::json(200, ['invoice' => ['id' => 501, 'number' => 'F-1', 'state' => 'sending', 'tax_report_ids' => $ids]]);
    }

    private static function report(int $id, string $state): Response
    {
        return self::json(200, ['tax_report' => ['id' => $id, 'state' => $state]]);
    }

    /**
     * @param  list<array<string, mixed>>  $invoices
     */
    private static function invoiceList(array $invoices): Response
    {
        $listed = array_map(static fn (array $invoice): array => $invoice + ['type' => 'IssuedInvoice', 'state' => 'sent'], $invoices);

        return self::json(200, ['invoices' => $listed, 'meta' => ['total_count' => count($listed), 'offset' => 0, 'limit' => 500]]);
    }

    private static function exceptionText(Throwable $exception): string
    {
        $text = '';
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $text .= $current->getMessage()
                .($current instanceof UnexpectedResponse ? $current->bodyExcerpt : '')
                .($current instanceof DocumentRejected ? json_encode($current->errors, JSON_THROW_ON_ERROR) : '');
        }

        return $text;
    }
}
