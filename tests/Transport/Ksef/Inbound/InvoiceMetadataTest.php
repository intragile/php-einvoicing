<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Inbound;

use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Ksef\Inbound\InvoiceMetadata;
use IntrAgile\EInvoicing\Transport\Ksef\Session\InvoicingMode;
use PHPUnit\Framework\TestCase;

final class InvoiceMetadataTest extends TestCase
{
    use CapturesThrowable;

    private const OPERATION = 'The KSeF request POST /invoices/query/metadata';

    public function test_metadata_reads_invoicing_mode_and_corrected_hash(): void
    {
        $corrected = base64_encode(hash('sha256', 'the rejected invoice', true));

        $correction = InvoiceMetadata::fromAnswer(self::entry() + [
            'invoicingMode' => 'Offline',
            'hashOfCorrectedInvoice' => $corrected,
        ], self::OPERATION);
        $online = InvoiceMetadata::fromAnswer(self::entry() + ['invoicingMode' => 'Online'], self::OPERATION);
        $bare = InvoiceMetadata::fromAnswer(self::entry() + ['invoicingMode' => 'Emergency', 'hashOfCorrectedInvoice' => ''], self::OPERATION);

        self::assertSame(InvoicingMode::Offline, $correction->invoicingMode);
        self::assertSame($corrected, $correction->hashOfCorrectedInvoice);
        self::assertSame(InvoicingMode::Online, $online->invoicingMode);
        self::assertNull($online->hashOfCorrectedInvoice);
        self::assertNull($bare->invoicingMode);
        self::assertNull($bare->hashOfCorrectedInvoice);
    }

    public function test_metadata_reads_the_typed_fields(): void
    {
        $metadata = InvoiceMetadata::fromAnswer(self::entry(), self::OPERATION);

        self::assertSame('5555555555-20261122-010080615740-AA', $metadata->ksefNumber);
        self::assertSame('FA/1/2026', $metadata->invoiceNumber);
        self::assertSame('5555555555', $metadata->sellerNip);
        self::assertSame('2026-11-22T10:00:00+00:00', $metadata->permanentStorageDate->format(DATE_ATOM));
        self::assertSame('mkht+3m5trnfxlTYhq3QFn74LkEO69MFNlsMAkCDSPA=', $metadata->invoiceHash);
        self::assertSame(self::entry(), $metadata->raw);
    }

    public function test_missing_ksef_number_is_unexpected_response(): void
    {
        $entry = self::entry();
        unset($entry['ksefNumber']);

        self::assertInstanceOf(UnexpectedResponse::class, self::capture(static fn () => InvoiceMetadata::fromAnswer($entry, self::OPERATION)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function entry(): array
    {
        return [
            'ksefNumber' => '5555555555-20261122-010080615740-AA',
            'invoiceNumber' => 'FA/1/2026',
            'issueDate' => '2026-11-22',
            'permanentStorageDate' => '2026-11-22T10:00:00.0000000+00:00',
            'seller' => ['nip' => '5555555555', 'name' => 'Test Company 1'],
            'invoiceHash' => 'mkht+3m5trnfxlTYhq3QFn74LkEO69MFNlsMAkCDSPA=',
        ];
    }
}
