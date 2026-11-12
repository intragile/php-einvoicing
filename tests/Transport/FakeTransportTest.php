<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\DeliveryFailure;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\FakeTransport;
use IntrAgile\EInvoicing\Transport\InboundDocument;
use IntrAgile\EInvoicing\Transport\InboundReference;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;
use IntrAgile\EInvoicing\Transport\SendResult;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class FakeTransportTest extends TestCase
{
    public function test_default_results_are_delivered_with_sequential_ids(): void
    {
        $fake = new FakeTransport;

        $first = $fake->send(self::document('0208:0000000001'));
        $second = $fake->send(self::document('0208:0000000002'));

        self::assertSame('fake-1', $first->documentId);
        self::assertSame('fake-2', $second->documentId);
        self::assertSame(DeliveryState::Delivered, $first->state);
        self::assertNull($first->failure);
        self::assertCount(2, $fake->sent());
        self::assertSame('0208:0000000002', $fake->sent()[1]->receiver->toString());
    }

    public function test_queued_outcomes_are_returned_in_order_then_defaults(): void
    {
        $failed = new SendResult('doc-9', DeliveryState::Failed, null, null, new DeliveryFailure(FailureCategory::RecipientNotFound, 'Not in SMP', 'E404'));
        $fake = new FakeTransport;
        $fake->willReturn($failed, new RateLimited('Slow down', 30));
        $fake->willReturn(new OutcomeUnknown('Timed out'));

        self::assertSame($failed, $fake->send(self::document()));
        try {
            $fake->send(self::document());
            self::fail('RateLimited was not thrown.');
        } catch (RateLimited $exception) {
            self::assertSame(30, $exception->retryAfterSeconds);
            self::assertTrue($exception->isRetryable());
        }
        try {
            $fake->send(self::document());
            self::fail('OutcomeUnknown was not thrown.');
        } catch (OutcomeUnknown $exception) {
            self::assertFalse($exception->isRetryable());
        }
        self::assertSame('fake-1', $fake->send(self::document())->documentId);
        self::assertCount(4, $fake->sent());
    }

    public function test_status_reports_the_state_of_a_sent_document(): void
    {
        $failure = new DeliveryFailure(FailureCategory::Validation, 'Rejected', null);
        $fake = new FakeTransport;
        $fake->willReturn(new SendResult('doc-1', DeliveryState::Failed, null, null, $failure));
        $fake->send(self::document());
        $fake->send(self::document());

        $failed = $fake->status('doc-1');
        self::assertSame(DeliveryState::Failed, $failed->state);
        self::assertSame($failure, $failed->failure);
        self::assertSame(DeliveryState::Delivered, $fake->status('fake-1')->state);
        self::assertSame(DeliveryState::Unknown, $fake->status('never-sent')->state);
    }

    public function test_inbound_round_trip(): void
    {
        $fake = new FakeTransport;
        $first = self::inbound('in-1');
        $second = self::inbound('in-2');
        $fake->receive($first);
        $fake->receive($second);

        self::assertSame([$first->reference, $second->reference], $fake->pending());
        self::assertSame($first, $fake->fetch('in-1'));

        $fake->acknowledge('in-1');

        self::assertSame([$second->reference], $fake->pending());
        self::assertSame(['in-1'], $fake->acknowledged());
    }

    public function test_fetching_an_unknown_inbound_document_fails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('in-404');

        (new FakeTransport)->fetch('in-404');
    }

    public function test_assert_sent_passes_when_a_document_matches(): void
    {
        $fake = new FakeTransport;
        $fake->send(self::document('0208:0000000001'));

        $fake->assertSent(static fn (OutboundDocument $d): bool => $d->receiver->toString() === '0208:0000000001');

        $this->addToAssertionCount(1);
    }

    public function test_assert_sent_failure_lists_what_was_sent(): void
    {
        $fake = new FakeTransport;
        $fake->send(self::document('0208:0000000001'));
        $fake->send(self::document('9930:DE123456789'));

        try {
            $fake->assertSent(static fn (OutboundDocument $d): bool => false);
            self::fail('LogicException was not thrown.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('No sent document matched', $exception->getMessage());
            self::assertStringContainsString('2 documents were sent', $exception->getMessage());
            self::assertStringContainsString('0208:0000000001', $exception->getMessage());
            self::assertStringContainsString('9930:DE123456789', $exception->getMessage());
        }
    }

    public function test_assert_sent_failure_when_nothing_was_sent(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('no document was sent');

        (new FakeTransport)->assertSent(static fn (OutboundDocument $d): bool => true);
    }

    public function test_unknown_failure_category_is_other(): void
    {
        self::assertSame(FailureCategory::RecipientNotFound, FailureCategory::fromValue('recipient_not_found'));
        self::assertSame(FailureCategory::Other, FailureCategory::fromValue('something_new'));
    }

    private static function document(string $receiver = '0208:0123456789'): OutboundDocument
    {
        return new OutboundDocument(
            '<Invoice/>',
            ParticipantId::parse('0208:9999999999'),
            ParticipantId::parse($receiver),
            new DocumentTypeId(PeppolBis3::INVOICE),
            new ProcessId(PeppolBis3::PROCESS),
        );
    }

    private static function inbound(string $id): InboundDocument
    {
        return new InboundDocument(
            new InboundReference($id, ParticipantId::parse('0208:0000000001'), null, PeppolBis3::INVOICE, new DateTimeImmutable('2026-11-12T10:00:00Z')),
            '<Invoice/>',
            null,
        );
    }
}
