<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\France;

use Brick\Math\BigDecimal;
use Closure;
use DateTimeImmutable;
use IntrAgile\EInvoicing\France\Lifecycle\CharacteristicType;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleStatus;
use IntrAgile\EInvoicing\France\Lifecycle\StatusReason;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\B2BrouterFrance;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\BuyerResponse;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
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

final class B2BrouterFranceResponseTest extends TestCase
{
    use CapturesThrowable;

    private const KEY = 'stag_FAKEKEY000000';

    private const ACCOUNT = 4711;

    private const BASE = 'https://api-staging.b2brouter.net';

    /**
     * @return iterable<string, array{Closure(): BuyerResponse, LifecycleStatus, string}>
     */
    public static function responses(): iterable
    {
        yield '204 in process' => [static fn () => BuyerResponse::inProcess(), LifecycleStatus::InProcess, '{"state":"read"}'];
        yield '205 approved' => [static fn () => BuyerResponse::approved(), LifecycleStatus::Approved, '{"state":"accepted"}'];
        yield '206 partially approved' => [
            static fn () => BuyerResponse::partiallyApproved('250', CharacteristicType::MAP, 'One line does not match the purchase order'),
            LifecycleStatus::PartiallyApproved,
            '{"state":"accepted","amount":250.00,"amount_code":"MAP","reason":"One line does not match the purchase order"}',
        ];
        yield '207 disputed' => [
            static fn () => BuyerResponse::disputed('Prix unitaire différent de la commande'),
            LifecycleStatus::Disputed,
            '{"state":"annotated","dispute":true,"reason":"Prix unitaire différent de la commande"}',
        ];
        yield '210 refused with comment' => [
            static fn () => BuyerResponse::refused(StatusReason::MONTANTTOTAL_ERR, 'Incorrect total amount'),
            LifecycleStatus::Refused,
            '{"state":"refused","reason_code":"MONTANTTOTAL_ERR","reason":"Incorrect total amount"}',
        ];
        yield '210 refused without comment' => [
            static fn () => BuyerResponse::refused(StatusReason::DOUBLON),
            LifecycleStatus::Refused,
            '{"state":"refused","reason_code":"DOUBLON"}',
        ];
        yield '211 payment sent' => [static fn () => BuyerResponse::paymentSent(), LifecycleStatus::PaymentSent, '{"state":"paid"}'];
    }

    /**
     * @param  Closure(): BuyerResponse  $factory
     */
    #[DataProvider('responses')]
    public function test_each_response_writes_documented_body(Closure $factory, LifecycleStatus $status, string $json): void
    {
        $response = $factory();

        self::assertSame($status, $response->status());
        self::assertSame($json, $response->toJson());

        $recorder = new RecordingHttpClient(self::invoice(['id' => 900101, 'type' => 'ReceivedInvoice', 'state' => 'accepted']));
        self::france($recorder)->respond('900101', $response);
        $request = $recorder->requests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE.'/invoices/900101/mark_as', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame($json, (string) $request->getBody());
    }

    public function test_partial_approval_requires_amount_variant(): void
    {
        $exception = self::capture(fn () => BuyerResponse::partiallyApproved('10', CharacteristicType::MEN, 'Partial'));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('MAP, MAPTTC, MNA, MNATTC', $exception->getMessage());

        foreach ([CharacteristicType::MAPTTC, CharacteristicType::MNA, CharacteristicType::MNATTC] as $type) {
            self::assertStringContainsString('"amount_code":"'.$type->value.'"', BuyerResponse::partiallyApproved('10', $type, 'Partial')->toJson());
        }
    }

    public function test_amount_with_three_decimals_is_refused(): void
    {
        $exception = self::capture(fn () => BuyerResponse::partiallyApproved('0.305', CharacteristicType::MAP, 'Partial'));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('2 decimal', $exception->getMessage());

        $recorder = new RecordingHttpClient;
        $exception = self::capture(fn () => self::france($recorder)->recordPayment('590101', '10.001'));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertCount(0, $recorder->requests());
    }

    public function test_amount_is_written_without_float_rounding(): void
    {
        self::assertStringContainsString('"amount":0.30,', BuyerResponse::partiallyApproved('0.30', CharacteristicType::MAP, 'Partial')->toJson());
        self::assertStringContainsString('"amount":9999999999999.99,', BuyerResponse::partiallyApproved(BigDecimal::of('9999999999999.99'), CharacteristicType::MAP, 'Partial')->toJson());
        self::assertStringContainsString('"amount":12.00,', BuyerResponse::partiallyApproved(12, CharacteristicType::MAP, 'Partial')->toJson());
    }

    public function test_trailing_zeros_beyond_two_decimals_are_stripped(): void
    {
        self::assertStringContainsString('"amount":0.30,', BuyerResponse::partiallyApproved('0.300', CharacteristicType::MAP, 'Partial')->toJson());
        self::assertStringContainsString('"amount":5.00,', BuyerResponse::partiallyApproved('5.0000', CharacteristicType::MAP, 'Partial')->toJson());
    }

    public function test_zero_or_negative_amount_is_refused(): void
    {
        foreach (['0', '0.00', '-1', '10000000000000', 'abc'] as $amount) {
            $exception = self::capture(fn () => BuyerResponse::partiallyApproved($amount, CharacteristicType::MAP, 'Partial'));
            self::assertInstanceOf(InvalidArgumentException::class, $exception, $amount);
        }

        $recorder = new RecordingHttpClient;
        foreach (['0', '-5'] as $amount) {
            $exception = self::capture(fn () => self::france($recorder)->recordPayment('590101', $amount));
            self::assertInstanceOf(InvalidArgumentException::class, $exception, $amount);
        }
        self::assertCount(0, $recorder->requests());
    }

    public function test_refusal_reason_outside_b2brouter_list_is_refused(): void
    {
        foreach ([StatusReason::AUTRE, StatusReason::REJ_COH, StatusReason::PU_ERR] as $reason) {
            $exception = self::capture(fn () => BuyerResponse::refused($reason));
            self::assertInstanceOf(InvalidArgumentException::class, $exception, $reason->value);
            self::assertStringContainsString('ADR_ERR, REF_CT_ABSENT, CMD_ERR, TX_TVA_ERR, MONTANTTOTAL_ERR, CALCUL_ERR, DOUBLON, NON_CONFORME, DEST_ERR, TRANSAC_INC, EMMET_INC, CONTRAT_TERM, DOUBLE_FACT', $exception->getMessage());
        }

        $exception = self::capture(fn () => BuyerResponse::refused(StatusReason::DOUBLON, '  '));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
    }

    public function test_blank_dispute_reason_is_refused(): void
    {
        foreach (['', " \n\t"] as $reason) {
            self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => BuyerResponse::disputed($reason)));
            self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => BuyerResponse::partiallyApproved('1', CharacteristicType::MAP, $reason)));
        }
    }

    public function test_full_payment_posts_full_true(): void
    {
        $recorder = new RecordingHttpClient(self::payment(), self::invoice(['id' => 590101, 'type' => 'IssuedInvoice', 'state' => 'paid']));

        $status = self::france($recorder)->recordPayment('590101');

        self::assertSame(LifecycleStatus::Collected, $status->lifecycle);
        self::assertSame('paid', $status->platformState);
        $requests = $recorder->requests();
        self::assertCount(2, $requests);
        self::assertSame('POST', $requests[0]->getMethod());
        self::assertSame(self::BASE.'/accounts/4711/payments', (string) $requests[0]->getUri());
        self::assertSame('application/json', $requests[0]->getHeaderLine('Content-Type'));
        self::assertSame(self::KEY, $requests[0]->getHeaderLine('X-B2B-API-Key'));
        self::assertSame('{"payment":{"invoice_id":590101,"full":true}}', (string) $requests[0]->getBody());
        self::assertSame('GET', $requests[1]->getMethod());
        self::assertSame(self::BASE.'/invoices/590101', (string) $requests[1]->getUri());
    }

    public function test_partial_payment_posts_amount_then_reads_invoice(): void
    {
        $recorder = new RecordingHttpClient(self::payment(), self::invoice(['id' => 590101, 'type' => 'IssuedInvoice', 'state' => 'accepted']));

        $status = self::france($recorder)->recordPayment('590101', '900.50');

        self::assertSame(LifecycleStatus::Approved, $status->lifecycle);
        $requests = $recorder->requests();
        self::assertSame('{"payment":{"invoice_id":590101,"amount":900.50}}', (string) $requests[0]->getBody());
        self::assertSame('GET', $requests[1]->getMethod());
    }

    public function test_payment_date_is_sent_in_paris_date(): void
    {
        $recorder = new RecordingHttpClient(self::payment(), self::invoice(['id' => 590101, 'type' => 'IssuedInvoice', 'state' => 'paid']));

        self::france($recorder)->recordPayment('590101', null, new DateTimeImmutable('2026-11-17T23:30:00Z'));

        self::assertSame('{"payment":{"invoice_id":590101,"full":true,"date":"2026-11-18"}}', (string) $recorder->requests()[0]->getBody());
    }

    public function test_payment_sent_response_uses_mark_as_paid(): void
    {
        $recorder = new RecordingHttpClient(self::invoice(['id' => 900101, 'type' => 'ReceivedInvoice', 'state' => 'paid']));

        $status = self::france($recorder)->respond('900101', BuyerResponse::paymentSent());

        self::assertSame(LifecycleStatus::PaymentSent, $status->lifecycle);
        self::assertTrue($status->received);
        self::assertSame('900101', $status->documentId);
        $requests = $recorder->requests();
        self::assertCount(1, $requests);
        self::assertSame(self::BASE.'/invoices/900101/mark_as', (string) $requests[0]->getUri());
        self::assertSame('{"state":"paid"}', (string) $requests[0]->getBody());
    }

    public function test_payment_without_answer_is_outcome_unknown_with_advice(): void
    {
        $recorder = new RecordingHttpClient(self::networkFailure());

        $exception = self::capture(fn () => self::france($recorder)->recordPayment('590101', '10'));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('GET /accounts/{account}/payments?invoice_id=590101', $exception->getMessage());
        self::assertStringNotContainsString((string) self::ACCOUNT, $exception->getMessage());
        self::assertCount(1, $recorder->requests());

        $exception = self::capture(fn () => self::france(new RecordingHttpClient(new Response(502)))->recordPayment('590101'));
        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('GET /accounts/{account}/payments?invoice_id=590101', $exception->getMessage());
        self::assertStringNotContainsString((string) self::ACCOUNT, $exception->getMessage());
    }

    /**
     * @return iterable<string, array{Response}>
     */
    public static function unexpectedPaymentAnswers(): iterable
    {
        yield 'redirect' => [new Response(302, ['Location' => self::BASE.'/accounts/4711/payments/981'])];
        yield 'accepted' => [new Response(202)];
        yield 'no content' => [new Response(204)];
    }

    #[DataProvider('unexpectedPaymentAnswers')]
    public function test_unexpected_payment_answer_is_outcome_unknown_with_advice(Response $answer): void
    {
        $recorder = new RecordingHttpClient($answer);

        $exception = self::capture(fn () => self::france($recorder)->recordPayment('590101'));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertFalse($exception->isRetryable());
        self::assertStringContainsString('HTTP '.$answer->getStatusCode(), $exception->getMessage());
        self::assertStringContainsString('GET /accounts/{account}/payments?invoice_id=590101', $exception->getMessage());
        self::assertStringNotContainsString((string) self::ACCOUNT, $exception->getMessage());
        self::assertCount(1, $recorder->requests());
    }

    public function test_refused_payment_quotes_b2brouter(): void
    {
        $refused = new Response(422, ['Content-Type' => 'application/json'], '{"error":{"code":"parameter_invalid","param":"amount","message":"Amount must be greater than 0"}}');

        $exception = self::capture(fn () => self::france(new RecordingHttpClient($refused))->recordPayment('590101', '1'));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringContainsString('"Amount must be greater than 0"', $exception->getMessage());
    }

    public function test_refused_response_quotes_b2brouter(): void
    {
        $refused = new Response(422, ['Content-Type' => 'application/json'], '{"errors":["The invoice cannot be marked as accepted"]}');

        $exception = self::capture(fn () => self::france(new RecordingHttpClient($refused))->respond('900101', BuyerResponse::approved()));

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringContainsString('"The invoice cannot be marked as accepted"', $exception->getMessage());
        self::assertStringContainsString('900101', $exception->getMessage());
    }

    public function test_response_without_answer_is_outcome_unknown(): void
    {
        $exception = self::capture(fn () => self::france(new RecordingHttpClient(self::networkFailure()))->respond('900101', BuyerResponse::approved()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertStringContainsString('lifecycle()', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{Response}>
     */
    public static function unexpectedResponseAnswers(): iterable
    {
        yield 'server error' => [new Response(500)];
        yield 'bad gateway' => [new Response(502)];
        yield 'redirect' => [new Response(302, ['Location' => self::BASE.'/invoices/900101'])];
    }

    #[DataProvider('unexpectedResponseAnswers')]
    public function test_unexpected_response_answer_is_outcome_unknown(Response $answer): void
    {
        $exception = self::capture(fn () => self::france(new RecordingHttpClient($answer))->respond('900101', BuyerResponse::approved()));

        self::assertInstanceOf(OutcomeUnknown::class, $exception);
        self::assertFalse($exception->isRetryable());
        self::assertStringContainsString('HTTP '.$answer->getStatusCode(), $exception->getMessage());
        self::assertStringContainsString('lifecycle() for invoice "900101"', $exception->getMessage());
    }

    public function test_failed_read_after_recorded_payment_says_not_to_repeat(): void
    {
        $recorder = new RecordingHttpClient(self::payment(), self::networkFailure());

        $exception = self::capture(fn () => self::france($recorder)->recordPayment('590101'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertFalse($exception->isRetryable());
        self::assertStringContainsString('payment 981', $exception->getMessage());
        self::assertStringContainsString('do not record it again', $exception->getMessage());
    }

    public function test_invoice_ids_must_be_digits(): void
    {
        $recorder = new RecordingHttpClient;

        foreach (['', 'abc', '12/../3'] as $id) {
            self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => self::france($recorder)->respond($id, BuyerResponse::approved())), $id);
            self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => self::france($recorder)->recordPayment($id)), $id);
        }
        self::assertCount(0, $recorder->requests());
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
     * @param  array<string, mixed>  $invoice
     */
    private static function invoice(array $invoice): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['invoice' => $invoice], JSON_THROW_ON_ERROR));
    }

    private static function payment(): Response
    {
        return new Response(201, ['Content-Type' => 'application/json'], '{"payment":{"id":981,"invoice_id":590101,"amount":900.5,"currency":"EUR","date":"2026-11-18"}}');
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
