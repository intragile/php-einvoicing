<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Session;

use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Ksef\Session\InvoicingMode;
use IntrAgile\EInvoicing\Transport\Ksef\Session\KsefInvoiceStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KsefInvoiceStatusTest extends TestCase
{
    use CapturesThrowable;

    /**
     * @return iterable<string, array{int, DeliveryState, ?FailureCategory}>
     */
    public static function statusCodes(): iterable
    {
        yield '100 accepted for processing' => [100, DeliveryState::Pending, null];
        yield '150 processing' => [150, DeliveryState::Pending, null];
        yield '200 success' => [200, DeliveryState::Delivered, null];
        yield '405 cancelled by a session error' => [405, DeliveryState::Failed, FailureCategory::Transport];
        yield '410 wrong permission scope' => [410, DeliveryState::Failed, FailureCategory::DocumentNotSupported];
        yield '415 attachments not allowed' => [415, DeliveryState::Failed, FailureCategory::DocumentNotSupported];
        yield '430 invoice file verification error' => [430, DeliveryState::Failed, FailureCategory::Validation];
        yield '435 decryption error' => [435, DeliveryState::Failed, FailureCategory::Transport];
        yield '440 duplicate' => [440, DeliveryState::Failed, FailureCategory::Duplicate];
        yield '450 semantic verification error' => [450, DeliveryState::Failed, FailureCategory::Validation];
        yield '500 unknown error' => [500, DeliveryState::Failed, FailureCategory::Other];
        yield '550 cancelled by the system' => [550, DeliveryState::Failed, FailureCategory::Transport];
        yield '999 a code this version does not know' => [999, DeliveryState::Failed, FailureCategory::Other];
    }

    #[DataProvider('statusCodes')]
    public function test_status_mapping_table(int $code, DeliveryState $state, ?FailureCategory $category): void
    {
        $status = new KsefInvoiceStatus($code, 'Opis statusu', ['Pierwszy szczegół', 'Drugi szczegół']);

        self::assertSame($state, $status->deliveryState());
        self::assertSame($state !== DeliveryState::Pending, $status->isFinal());
        $failure = $status->failure();
        if ($category === null) {
            self::assertNull($failure);

            return;
        }
        self::assertNotNull($failure);
        self::assertSame($category, $failure->category);
        self::assertSame((string) $code, $failure->providerCode);
        self::assertSame('Opis statusu: Pierwszy szczegół; Drugi szczegół', $failure->message);
    }

    public function test_message_without_details_is_the_description(): void
    {
        self::assertSame('Błąd weryfikacji pliku faktury', (new KsefInvoiceStatus(430, 'Błąd weryfikacji pliku faktury'))->failure()?->message);
    }

    public function test_duplicate_message_names_the_original_number_from_the_extensions(): void
    {
        $status = new KsefInvoiceStatus(440, 'Duplikat faktury', [], originalKsefNumber: '5265877635-20250626-010080DD2B5E-26');

        self::assertSame(
            'Duplikat faktury (original KSeF number: 5265877635-20250626-010080DD2B5E-26)',
            $status->failure()?->message,
        );
    }

    public function test_from_answer_reads_the_session_invoice_status(): void
    {
        $status = KsefInvoiceStatus::fromAnswer([
            'ordinalNumber' => 1,
            'referenceNumber' => '20250625-EE-319D7EE000-B67F415CDC-2C',
            'ksefNumber' => '5265877635-20250626-010080DD2B5E-26',
            'acquisitionDate' => '2026-11-21T10:00:03.0154302+00:00',
            'status' => ['code' => 200, 'description' => 'Sukces', 'details' => null, 'extensions' => null],
        ], 'The KSeF request');

        self::assertSame(200, $status->code);
        self::assertSame('Sukces', $status->description);
        self::assertSame([], $status->details);
        self::assertSame('5265877635-20250626-010080DD2B5E-26', $status->ksefNumber);
        self::assertSame('2026-11-21T10:00:03+00:00', $status->acquisitionDate?->format(DATE_ATOM));
        self::assertNull($status->originalKsefNumber);
    }

    public function test_status_reads_invoicing_mode(): void
    {
        $answer = [
            'referenceNumber' => '20250625-EE-319D7EE000-B67F415CDC-2C',
            'status' => ['code' => 200, 'description' => 'Sukces'],
        ];

        $offline = KsefInvoiceStatus::fromAnswer($answer + ['invoicingMode' => 'Offline'], 'The KSeF request');
        $online = KsefInvoiceStatus::fromAnswer($answer + ['invoicingMode' => 'Online'], 'The KSeF request');
        // A rejected invoice carries no mode, and a mode this version does not know is not guessed.
        $absent = KsefInvoiceStatus::fromAnswer($answer, 'The KSeF request');
        $unknown = KsefInvoiceStatus::fromAnswer($answer + ['invoicingMode' => 'Emergency'], 'The KSeF request');

        self::assertSame(InvoicingMode::Offline, $offline->invoicingMode);
        self::assertSame(InvoicingMode::Online, $online->invoicingMode);
        self::assertNull($absent->invoicingMode);
        self::assertNull($unknown->invoicingMode);
    }

    public function test_from_answer_without_status_code_is_unexpected_response(): void
    {
        $exception = self::capture(static fn () => KsefInvoiceStatus::fromAnswer(['status' => ['description' => 'x']], 'The KSeF request GET /x'));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('status.code', $exception->getMessage());
    }
}
