<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Poland;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Poland\CorrectedInvoice;
use IntrAgile\EInvoicing\Poland\Correction;
use IntrAgile\EInvoicing\Poland\InvoiceKind;
use IntrAgile\EInvoicing\Poland\PartialAdvance;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\ThirdParty;
use IntrAgile\EInvoicing\Poland\ThirdPartyRole;
use IntrAgile\EInvoicing\Poland\VatBucket;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PolishInvoiceTest extends TestCase
{
    public function test_defaults_describe_a_plain_vat_invoice(): void
    {
        $invoice = new PolishInvoice;

        self::assertNull($invoice->kind);
        self::assertFalse($invoice->annotations->cashMethod);
        self::assertFalse($invoice->annotations->selfBilling);
        self::assertFalse($invoice->annotations->reverseCharge);
        self::assertFalse($invoice->annotations->splitPayment);
        self::assertFalse($invoice->annotations->triangular);
        self::assertNull($invoice->annotations->exemption);
        self::assertNull($invoice->annotations->newTransport);
        self::assertNull($invoice->annotations->margin);
        self::assertSame([], $invoice->thirdParties);
        self::assertSame([], $invoice->vatInPln);
        self::assertNull($invoice->correction);
        self::assertFalse($invoice->buyerIsJstUnit);
        self::assertFalse($invoice->buyerIsGvMember);
        self::assertFalse($invoice->article109Invoice);
        self::assertFalse($invoice->relatedParties);
        self::assertFalse($invoice->exciseRefund);
    }

    public function test_vat_in_pln_accepts_only_buckets_one_to_four(): void
    {
        $invoice = new PolishInvoice(vatInPln: ['1' => BigDecimal::of('23.00'), VatBucket::TaxiLumpSum->value => '4.00']);

        self::assertTrue(BigDecimal::of('23.00')->isEqualTo($invoice->vatInPlnFor(VatBucket::Basic) ?? BigDecimal::zero()));
        self::assertTrue(BigDecimal::of('4.00')->isEqualTo($invoice->vatInPlnFor(VatBucket::TaxiLumpSum) ?? BigDecimal::zero()));
        self::assertNull($invoice->vatInPlnFor(VatBucket::Reduced1));

        foreach (['5', '6_1', '0', 'x'] as $key) {
            try {
                new PolishInvoice(vatInPln: [$key => BigDecimal::one()]);
                self::fail('Bucket '.$key.' accepted.');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('P_14_', $e->getMessage());
            }
        }
    }

    public function test_list_limits_follow_the_xsd(): void
    {
        $advance = new PartialAdvance(new DateTimeImmutable('2026-01-10'), BigDecimal::of('100'));
        $invoice = new PolishInvoice(kind: InvoiceKind::Zal, partialAdvances: array_fill(0, 31, $advance));
        self::assertCount(31, $invoice->partialAdvances);

        $this->assertRefused(static fn () => new PolishInvoice(kind: InvoiceKind::Zal, partialAdvances: array_fill(0, 32, $advance)));

        $thirdParty = new ThirdParty(new Party('Factor'), ThirdPartyRole::Factor);
        self::assertCount(100, (new PolishInvoice(thirdParties: array_fill(0, 100, $thirdParty)))->thirdParties);
        $this->assertRefused(static fn () => new PolishInvoice(thirdParties: array_fill(0, 101, $thirdParty)));
        $this->assertRefused(static fn () => new PolishInvoice(warehouseDocuments: array_fill(0, 1001, 'WZ')));
    }

    public function test_partial_advances_need_an_advance_or_settlement_kind(): void
    {
        $advance = new PartialAdvance(new DateTimeImmutable('2026-01-10'), BigDecimal::of('100'));

        foreach ([InvoiceKind::Zal, InvoiceKind::KorZal, InvoiceKind::Roz, null] as $kind) {
            self::assertCount(1, (new PolishInvoice(kind: $kind, partialAdvances: [$advance]))->partialAdvances);
        }
        $this->assertRefused(static fn () => new PolishInvoice(kind: InvoiceKind::Vat, partialAdvances: [$advance]));
    }

    public function test_correction_needs_a_correction_kind(): void
    {
        $correction = new Correction([new CorrectedInvoice('FV/1', new DateTimeImmutable('2026-01-02'), null)]);

        self::assertSame($correction, (new PolishInvoice(kind: InvoiceKind::KorZal, correction: $correction))->correction);
        self::assertSame($correction, (new PolishInvoice(correction: $correction))->correction);
        $this->assertRefused(static fn () => new PolishInvoice(kind: InvoiceKind::Vat, correction: $correction));
    }

    public function test_margin_line_has_no_rate_code(): void
    {
        self::assertTrue((new PolishLine(margin: true))->margin);
        $this->assertRefused(static fn () => new PolishLine(rateCode: VatRateCode::Rate23, margin: true));
    }

    public function test_bucket_line_is_not_a_state_before_row(): void
    {
        self::assertTrue((new PolishLine(bucketLine: true))->bucketLine);
        $this->assertRefused(static fn () => new PolishLine(stateBefore: true, bucketLine: true));
    }

    /**
     * @param  callable(): mixed  $build
     */
    private function assertRefused(callable $build): void
    {
        try {
            $build();
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);

            return;
        }
        self::fail('Expected an InvalidArgumentException.');
    }
}
