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
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{string}>
     */
    public static function malformedKeys(): array
    {
        return ['trailing zeros' => ['S|21.00'], 'lower case' => ['s|21'], 'one zero' => ['S|21.0'], 'no rate' => ['S'], 'empty rate' => ['S|']];
    }

    #[DataProvider('malformedKeys')]
    public function test_malformed_breakdown_keys_are_refused(string $key): void
    {
        $this->expectException(InvalidRecord::class);
        new SpanishInvoice(breakdown: [$key => new BreakdownOverride]);
    }

    public function test_euro_amounts_refuse_malformed_keys(): void
    {
        $this->expectException(InvalidRecord::class);
        new EuroAmounts(Amount::of('1'), Amount::of('2'), ['S|21.0' => ['base' => Amount::of('1'), 'quota' => null]]);
    }

    public function test_breakdown_value_must_be_an_override(): void
    {
        $this->expectException(InvalidRecord::class);
        /** @phpstan-ignore argument.type (proves the runtime guard for untyped callers) */
        new SpanishInvoice(breakdown: ['S|21' => 'exempt']);
    }

    public function test_generated_keys_are_accepted(): void
    {
        $keys = [
            SpanishInvoice::breakdownKey(VatCategory::StandardRate, BigDecimal::of('21.00')),
            SpanishInvoice::breakdownKey(VatCategory::StandardRate, BigDecimal::of('10.5')),
            SpanishInvoice::breakdownKey(VatCategory::ZeroRated, BigDecimal::of('0')),
            SpanishInvoice::breakdownKey(VatCategory::StandardRate, BigDecimal::of('100')),
            SpanishInvoice::breakdownKey(VatCategory::Exempt, null),
        ];
        $spanish = new SpanishInvoice(breakdown: array_fill_keys($keys, new BreakdownOverride));

        self::assertSame($keys, array_keys($spanish->breakdown));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function malformedEuroEntries(): array
    {
        return [
            'missing base' => [['quota' => null]],
            'missing quota key' => [['base' => Amount::of('1')]],
            'base not an amount' => [['base' => '1', 'quota' => null]],
            'quota not an amount' => [['base' => Amount::of('1'), 'quota' => 2]],
            'not an array' => [[]],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    #[DataProvider('malformedEuroEntries')]
    public function test_euro_amounts_refuse_a_malformed_entry(array $entry): void
    {
        $this->expectException(InvalidRecord::class);
        /** @phpstan-ignore argument.type (proves the runtime guard for untyped callers) */
        new EuroAmounts(Amount::of('1'), Amount::of('2'), ['S|21' => $entry]);
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
