<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\France;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Flux10\Flux10Writer;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\EReports;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\B2BrouterFrance;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\EReportDeposit;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\LedgerState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class B2BrouterFranceLedgerTest extends TestCase
{
    use CapturesThrowable;

    private const KEY = 'stag_FAKEKEY000000';

    private const ACCOUNT = 4711;

    private const BASE = 'https://api-staging.b2brouter.net';

    private const XML = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<Report><ReportDocument><Id>TR-2026-0001</Id></ReportDocument></Report>';

    public function test_deposit_posts_raw_xml_with_octet_stream(): void
    {
        $recorder = new RecordingHttpClient(self::ledger(201, ['id' => 68916, 'type' => 'Ledger::DgfipF10', 'state' => 'new']));

        $deposit = self::france($recorder)->depositEReport(self::XML);

        self::assertEquals(new EReportDeposit('68916', LedgerState::New, false), $deposit);
        $request = $recorder->requests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE.'/accounts/4711/ledgers/import', (string) $request->getUri());
        self::assertSame('application/octet-stream', $request->getHeaderLine('Content-Type'));
        self::assertSame(self::KEY, $request->getHeaderLine('X-B2B-API-Key'));
        self::assertSame(self::XML, (string) $request->getBody());
    }

    public function test_default_idempotency_key_is_payload_hash(): void
    {
        $recorder = new RecordingHttpClient(self::ledger(201, ['id' => 68916, 'state' => 'new']));

        self::france($recorder)->depositEReport(self::XML);

        self::assertSame(hash('sha256', self::XML), $recorder->requests()[0]->getHeaderLine('Idempotency-Key'));
    }

    public function test_given_idempotency_key_is_sent(): void
    {
        $recorder = new RecordingHttpClient(self::ledger(201, ['id' => 68916, 'state' => 'new']));

        self::france($recorder)->depositEReport(self::XML, 'F10-2026-11-0001');

        self::assertSame('F10-2026-11-0001', $recorder->requests()[0]->getHeaderLine('Idempotency-Key'));
    }

    public function test_invalid_idempotency_key_is_refused_without_request(): void
    {
        $recorder = new RecordingHttpClient;

        foreach (['', ' ', "key\r\nX-Other: 1", 'clé', str_repeat('k', 65)] as $key) {
            $exception = self::capture(fn () => self::france($recorder)->depositEReport(self::XML, $key));
            self::assertInstanceOf(InvalidArgumentException::class, $exception, $key);
        }
        self::assertCount(0, $recorder->requests());
    }

    public function test_repeated_deposit_returns_existing_ledger(): void
    {
        $recorder = new RecordingHttpClient(self::ledger(200, ['id' => 68916, 'state' => 'registered']));

        $deposit = self::france($recorder)->depositEReport(self::XML);

        self::assertSame('68916', $deposit->ledgerId);
        self::assertSame(LedgerState::Registered, $deposit->state);
        self::assertTrue($deposit->alreadyExisted);
    }

    public function test_key_reused_with_other_payload_is_rejected(): void
    {
        $conflict = new Response(409, ['Content-Type' => 'application/json'], '{"error":{"code":"idempotency_key_reused","message":"Idempotency-Key already used with a different payload"}}');

        $exception = self::capture(fn () => self::france(new RecordingHttpClient($conflict))->depositEReport(self::XML, 'F10-2026-11-0001'));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertFalse($exception->isRetryable());
        self::assertStringContainsString('HTTP 409', $exception->getMessage());
        self::assertStringContainsString('"F10-2026-11-0001"', $exception->getMessage());
        self::assertStringContainsString('different', $exception->getMessage());
    }

    public function test_missing_ledger_permission_is_explained(): void
    {
        $forbidden = new Response(403, ['Content-Type' => 'application/json'], '{"error":{"code":"forbidden","message":"Forbidden"}}');

        $exception = self::capture(fn () => self::france(new RecordingHttpClient($forbidden))->depositEReport(self::XML));

        self::assertInstanceOf(TransportException::class, $exception);
        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertStringContainsString('HTTP 403', $exception->getMessage());
        self::assertStringContainsString('ledger permission', $exception->getMessage());
        self::assertStringContainsString('B2Brouter plan', $exception->getMessage());
    }

    public function test_invalid_file_is_rejected_quoting_b2brouter(): void
    {
        $invalid = new Response(422, ['Content-Type' => 'application/json'], '{"error":{"code":"invalid_xml","message":"Issuer SIREN does not match the account"}}');

        $exception = self::capture(fn () => self::france(new RecordingHttpClient($invalid))->depositEReport(self::XML));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringContainsString('HTTP 422', $exception->getMessage());
        self::assertStringContainsString('"Issuer SIREN does not match the account"', $exception->getMessage());
    }

    public function test_namespaced_report_is_refused_without_request(): void
    {
        $recorder = new RecordingHttpClient;

        foreach (['<Report xmlns="urn:example"/>', '<Invoice/>', '<x:Report xmlns:x="urn:example"/>', 'not xml'] as $xml) {
            $exception = self::capture(fn () => self::france($recorder)->depositEReport($xml));
            self::assertInstanceOf(UnsupportedDocument::class, $exception, $xml);
        }
        self::assertCount(0, $recorder->requests());
    }

    public function test_deposit_without_answer_is_outcome_unknown(): void
    {
        $recorder = new RecordingHttpClient(self::networkFailure());

        $exception = self::capture(fn () => self::france($recorder)->depositEReport(self::XML));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertFalse($exception->isRetryable());
        self::assertStringContainsString('same Idempotency-Key', $exception->getMessage());
        self::assertStringContainsString(hash('sha256', self::XML), $exception->getMessage());
        self::assertStringContainsString('safe', $exception->getMessage());

        $exception = self::capture(fn () => self::france(new RecordingHttpClient(new Response(503)))->depositEReport(self::XML, 'F10-1'));
        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('HTTP 503', $exception->getMessage());
        self::assertStringContainsString('same Idempotency-Key "F10-1"', $exception->getMessage());
    }

    public function test_unreadable_created_answer_is_outcome_unknown(): void
    {
        $exception = self::capture(fn () => self::france(new RecordingHttpClient(new Response(201, [], 'not json')))->depositEReport(self::XML));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('same Idempotency-Key', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, LedgerState, bool, ?string, ?string}>
     */
    public static function ledgerStates(): iterable
    {
        yield 'new' => [['state' => 'new'], LedgerState::New, false, null, null];
        yield 'sent' => [['state' => 'sent'], LedgerState::Sent, false, null, null];
        yield 'acknowledged' => [['state' => 'acknowledged'], LedgerState::Acknowledged, false, null, null];
        yield 'deposited' => [['state' => 'deposited'], LedgerState::Deposited, false, null, null];
        yield 'registered' => [['state' => 'registered'], LedgerState::Registered, true, null, null];
        yield 'refused' => [
            ['state' => 'refused', 'error_code' => '303', 'error_description' => 'Période déjà déclarée'],
            LedgerState::Refused,
            true,
            '303',
            'Période déjà déclarée',
        ];
        yield 'error' => [['state' => 'error', 'error_code' => null, 'error_description' => 'Transport failure'], LedgerState::Error, true, null, 'Transport failure'];
        yield 'replaced' => [['state' => 'replaced'], LedgerState::Unknown, false, null, null];
        yield 'no state' => [[], LedgerState::Unknown, false, null, null];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    #[DataProvider('ledgerStates')]
    public function test_ledger_states_are_read(array $fields, LedgerState $state, bool $final, ?string $errorCode, ?string $errorDescription): void
    {
        $recorder = new RecordingHttpClient(self::ledger(200, ['id' => 68916, 'type' => 'Ledger::DgfipF10'] + $fields));

        $deposit = self::france($recorder)->eReportStatus('68916');

        self::assertEquals(new EReportDeposit('68916', $state, true, $errorCode, $errorDescription), $deposit);
        self::assertSame($final, $deposit->state->isFinal());
        $request = $recorder->requests()[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame(self::BASE.'/ledgers/68916', (string) $request->getUri());
    }

    public function test_unknown_ledger_is_unexpected(): void
    {
        $missing = new Response(404, ['Content-Type' => 'application/json'], '{"error":{"code":"resource_missing","message":"Not found"}}');

        self::assertInstanceOf(UnexpectedResponse::class, self::capture(fn () => self::france(new RecordingHttpClient($missing))->eReportStatus('68916')));
        self::assertInstanceOf(UnexpectedResponse::class, self::capture(fn () => self::france(new RecordingHttpClient(self::ledger(200, ['state' => 'new'])))->eReportStatus('68916')));
    }

    public function test_ledger_ids_must_be_digits(): void
    {
        $recorder = new RecordingHttpClient;

        foreach (['', 'abc', '12/../3'] as $id) {
            self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => self::france($recorder)->eReportStatus($id)), $id);
        }
        self::assertCount(0, $recorder->requests());
    }

    public function test_deposit_report_writes_flux10(): void
    {
        $report = EReports::fixtureTransactions();
        $recorder = new RecordingHttpClient(self::ledger(201, ['id' => 68917, 'state' => 'new']));

        $deposit = self::france($recorder)->depositReport($report);

        $xml = (new Flux10Writer)->write($report);
        self::assertSame('68917', $deposit->ledgerId);
        self::assertSame($xml, (string) $recorder->requests()[0]->getBody());
        self::assertSame(hash('sha256', $xml), $recorder->requests()[0]->getHeaderLine('Idempotency-Key'));

        $recorder = new RecordingHttpClient(self::ledger(201, ['id' => 68918, 'state' => 'new']));
        self::france($recorder)->depositReport($report, 'F10-OWN');
        self::assertSame('F10-OWN', $recorder->requests()[0]->getHeaderLine('Idempotency-Key'));
    }

    private static function france(RecordingHttpClient $recorder): B2BrouterFrance
    {
        $factory = new Psr17Factory;

        return new B2BrouterFrance(
            new B2BrouterConfig(self::KEY, self::ACCOUNT, self::BASE),
            new HttpClient($recorder, $factory, $factory),
        );
    }

    /**
     * @param  array<string, mixed>  $ledger
     */
    private static function ledger(int $status, array $ledger): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode(['ledger' => $ledger], JSON_THROW_ON_ERROR));
    }

    private static function networkFailure(): NetworkExceptionInterface
    {
        return new class(new Request('POST', self::BASE), 'Connection reset') extends RuntimeException implements NetworkExceptionInterface
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
