<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\Spain;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\TaxReport;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxReportTest extends TestCase
{
    use CapturesThrowable;

    private const DIR = __DIR__.'/../../../fixtures/spain/b2brouter/api';

    public function test_captured_tax_report_parses(): void
    {
        $body = self::json('tax-report.json');
        $object = $body['tax_report'];
        self::assertIsArray($object);

        $report = TaxReport::fromArray($body);

        self::assertSame('1064652', $report->id);
        self::assertSame('registered', $report->state);
        self::assertNotNull($report->fingerprint);
        self::assertMatchesRegularExpression('/\A[0-9A-F]{64}\z/', $report->fingerprint);
        self::assertSame($object['fingerprint'], $report->fingerprint);
        self::assertSame('292891', $report->previousId);
        self::assertNotNull($report->qrPng);
        self::assertStringStartsWith("\x89PNG\r\n\x1A\n", $report->qrPng);
        self::assertNotNull($report->recordXml);
        self::assertStringStartsWith('<?xml', $report->recordXml);
        self::assertSame($object, $report->raw);
        self::assertSame($report->fingerprint, $report->record()->fingerprint());
        self::assertTrue($report->isFinal());
        self::assertTrue($report->isRegistered());
    }

    public function test_previous_report_parses_with_its_own_previous_id(): void
    {
        $report = TaxReport::fromArray(self::json('tax-report-previous.json'));

        self::assertSame('1064651', $report->id);
        // previous_id is carried verbatim; it is not a tax report id.
        self::assertSame('292886', $report->previousId);
    }

    public function test_wrapped_and_flat_bodies_parse_alike(): void
    {
        $body = self::json('tax-report.json');
        $object = $body['tax_report'];
        self::assertIsArray($object);

        $wrapped = TaxReport::fromArray($body);
        $flat = TaxReport::fromArray($object);

        self::assertEquals($wrapped, $flat);
    }

    /**
     * @return iterable<string, array{string, DeliveryState, FailureCategory|null}>
     */
    public static function states(): iterable
    {
        yield 'registered' => ['registered', DeliveryState::Delivered, null];
        yield 'registered with errors' => ['registered_with_errors', DeliveryState::Delivered, null];
        yield 'refused' => ['refused', DeliveryState::Failed, FailureCategory::Validation];
        yield 'invalid' => ['invalid', DeliveryState::Failed, FailureCategory::Validation];
        yield 'error' => ['error', DeliveryState::Failed, FailureCategory::Other];
        yield 'annulled' => ['annulled', DeliveryState::Failed, FailureCategory::Other];
        yield 'new' => ['new', DeliveryState::Pending, null];
        yield 'processing' => ['processing', DeliveryState::Pending, null];
        yield 'signed' => ['signed', DeliveryState::Pending, null];
        yield 'sent' => ['sent', DeliveryState::Pending, null];
        yield 'acknowledged' => ['acknowledged', DeliveryState::Pending, null];
        yield 'unknown' => ['frobnicated', DeliveryState::Pending, null];
    }

    #[DataProvider('states')]
    public function test_state_mapping(string $state, DeliveryState $expected, ?FailureCategory $category): void
    {
        $report = TaxReport::fromArray(['tax_report' => ['id' => 7001, 'state' => $state, 'errors' => ['AEAT code 1100: invalid value']]]);
        $status = $report->deliveryStatus();

        self::assertSame('7001', $status->documentId);
        self::assertSame($expected, $status->state);
        self::assertSame($category, $status->failure?->category);
        self::assertSame($expected !== DeliveryState::Pending, $report->isFinal());
        self::assertSame($state === 'registered', $report->isRegistered());
        self::assertSame($state === 'registered_with_errors', $report->isRegisteredWithErrors());
        if ($category !== null) {
            self::assertSame('AEAT code 1100: invalid value', $status->failure?->message);
        }
    }

    public function test_report_without_binary_fields_has_nulls(): void
    {
        $report = TaxReport::fromArray(['id' => 7002, 'state' => 'signed', 'previous_id' => null, 'fingerprint' => null]);

        self::assertNull($report->fingerprint);
        self::assertNull($report->previousId);
        self::assertNull($report->qrPng);
        self::assertNull($report->recordXml);
        self::assertFalse($report->isFinal());

        $exception = self::capture(fn () => $report->record());
        self::assertInstanceOf(UnsupportedDocument::class, $exception);
        self::assertStringContainsString('xml_base64', $exception->getMessage());
    }

    public function test_invalid_base64_names_the_field(): void
    {
        foreach (['xml_base64', 'qr'] as $field) {
            $exception = self::capture(fn () => TaxReport::fromArray(['id' => 7003, 'state' => 'registered', $field => '***not base64***']));

            self::assertInstanceOf(UnsupportedDocument::class, $exception, $field);
            self::assertStringContainsString($field, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function malformed(): iterable
    {
        yield 'no id' => [['state' => 'registered']];
        yield 'zero id' => [['id' => 0, 'state' => 'registered']];
        yield 'string id' => [['id' => '7004', 'state' => 'registered']];
        yield 'no state' => [['id' => 7004]];
        yield 'blank state' => [['id' => 7004, 'state' => ' ']];
    }

    /**
     * @param  array<mixed>  $body
     */
    #[DataProvider('malformed')]
    public function test_malformed_report_is_unsupported(array $body): void
    {
        self::assertInstanceOf(UnsupportedDocument::class, self::capture(fn () => TaxReport::fromArray($body)));
    }

    /**
     * @return array<mixed>
     */
    private static function json(string $file): array
    {
        $data = json_decode((string) file_get_contents(self::DIR.'/'.$file), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
