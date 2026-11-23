<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Spain\BreakdownOverride;
use IntrAgile\EInvoicing\Spain\CorrectionType;
use IntrAgile\EInvoicing\Spain\EuroAmounts;
use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\IssuedBy;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\OperationQualification;
use IntrAgile\EInvoicing\Spain\RegimeKey;
use IntrAgile\EInvoicing\Spain\SpanishInvoice;
use IntrAgile\EInvoicing\Spain\TaxType;
use IntrAgile\EInvoicing\Spain\Verifactu\AltaOperation;
use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxParty;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use PHPUnit\Framework\TestCase;

final class SpanishInvoiceTest extends TestCase
{
    public function test_defaults_describe_a_domestic_f1(): void
    {
        $spanish = new SpanishInvoice;

        self::assertNull($spanish->type);
        self::assertNull($spanish->correctionType);
        self::assertNull($spanish->correctionAmounts);
        self::assertSame([], $spanish->substituted);
        self::assertNull($spanish->description);
        self::assertFalse($spanish->simplifiedArt7273);
        self::assertFalse($spanish->withoutRecipientArt61d);
        self::assertNull($spanish->issuedBy);
        self::assertNull($spanish->thirdParty);
        self::assertSame([], $spanish->recipients);
        self::assertFalse($spanish->coupon);
        self::assertSame([], $spanish->breakdown);
        self::assertNull($spanish->euroAmounts);
        self::assertSame(AltaOperation::Initial, $spanish->operation);
    }

    public function test_breakdown_key(): void
    {
        self::assertSame('S|21', SpanishInvoice::breakdownKey(VatCategory::StandardRate, BigDecimal::of('21.00')));
        self::assertSame('S|10.5', SpanishInvoice::breakdownKey(VatCategory::StandardRate, BigDecimal::of('10.5')));
        self::assertSame('S|10', SpanishInvoice::breakdownKey(VatCategory::StandardRate, BigDecimal::of('10.00')));
        self::assertSame('Z|0', SpanishInvoice::breakdownKey(VatCategory::ZeroRated, BigDecimal::of('0.00')));
        self::assertSame('S|100', SpanishInvoice::breakdownKey(VatCategory::StandardRate, BigDecimal::of('100')));
        self::assertSame('E|-', SpanishInvoice::breakdownKey(VatCategory::Exempt, null));
    }

    public function test_correction_type_needs_a_corrective_type(): void
    {
        $this->expectException(InvalidRecord::class);
        new SpanishInvoice(type: InvoiceType::F1, correctionType: CorrectionType::Difference);
    }

    public function test_correction_type_is_accepted_on_a_corrective_type(): void
    {
        $spanish = new SpanishInvoice(type: InvoiceType::R1, correctionType: CorrectionType::Difference);

        self::assertSame(CorrectionType::Difference, $spanish->correctionType);
    }

    public function test_third_party_needs_issued_by_t(): void
    {
        $party = new TaxParty('Third Party S.L.', Nif::parse('B64829179'), null);

        try {
            new SpanishInvoice(thirdParty: $party);
            self::fail('A third party without issuedBy T must be refused.');
        } catch (InvalidRecord) {
            $this->addToAssertionCount(1);
        }

        try {
            new SpanishInvoice(issuedBy: IssuedBy::Recipient, thirdParty: $party);
            self::fail('A third party with issuedBy D must be refused.');
        } catch (InvalidRecord) {
            $this->addToAssertionCount(1);
        }

        $spanish = new SpanishInvoice(issuedBy: IssuedBy::ThirdParty, thirdParty: $party);
        self::assertSame($party, $spanish->thirdParty);
    }

    public function test_override_carries_one_classification(): void
    {
        $qualified = new BreakdownOverride(
            tax: TaxType::Iva,
            regime: RegimeKey::from('01'),
            qualification: OperationQualification::from('S1'),
        );
        self::assertNull($qualified->exemption);

        $exempt = new BreakdownOverride(exemption: ExemptOperation::E1);
        self::assertNull($exempt->qualification);

        $this->expectException(InvalidRecord::class);
        new BreakdownOverride(qualification: OperationQualification::from('S1'), exemption: ExemptOperation::E1);
    }

    public function test_euro_amounts_hold_totals_and_breakdown(): void
    {
        $euro = new EuroAmounts(
            Amount::of('21.00'),
            Amount::of('121.00'),
            ['S|21' => ['base' => Amount::of('100.00'), 'quota' => Amount::of('21.00')]],
        );

        self::assertSame('121.00', $euro->total->text);
        self::assertSame('21.00', $euro->breakdown['S|21']['quota']?->text);
    }

    public function test_extension_round_trips_through_invoice(): void
    {
        $spanish = new SpanishInvoice(
            type: InvoiceType::F1,
            description: 'Consulting',
            recipients: [new TaxParty('Cliente', Nif::parse('89890002E'), null)],
        );
        $invoice = InvoiceFactory::mixedVat()->withExtension($spanish);

        self::assertSame($spanish, $invoice->extensions()->get(SpanishInvoice::class));
    }
}
