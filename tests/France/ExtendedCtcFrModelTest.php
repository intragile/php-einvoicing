<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France;

use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\FrenchGuard;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\France\FrenchLine;
use IntrAgile\EInvoicing\France\FrenchProfile;
use IntrAgile\EInvoicing\France\InformationalLine;
use IntrAgile\EInvoicing\France\LineSubType;
use IntrAgile\EInvoicing\Model\Extensions;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineAllowance;
use IntrAgile\EInvoicing\Model\LineCharge;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ExtendedCtcFrModelTest extends TestCase
{
    public function test_invoice_line_withers_keep_extensions(): void
    {
        $french = new FrenchLine(parentLineId: '1', subType: LineSubType::Detail);
        $line = $this->line('2')->withExtension($french);

        $copies = [
            $line->withNetAmount('10'),
            $line->withQuantity('3'),
            $line->withPrice(new Price(netPrice: '2')),
            $line->withVat(new LineVat(VatCategory::StandardRate, '10')),
            $line->withNote('note'),
            $line->withPeriod(new Period(start: new \DateTimeImmutable('2026-01-01'))),
            $line->withAllowance(new LineAllowance(amount: '1', reason: 'Discount')),
            $line->withCharge(new LineCharge(amount: '1', reason: 'Fee')),
        ];

        foreach ($copies as $copy) {
            self::assertSame($french, $copy->extensions()->get(FrenchLine::class));
        }
        self::assertSame([], $this->line('3')->extensions()->all());
        self::assertSame([], $line->withExtensions(Extensions::none())->extensions()->all());
    }

    public function test_informational_line_refuses_detail_subtype(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EXT-FR-FE-163');

        new InformationalLine(id: '1', subType: LineSubType::Detail, item: new Item(name: 'Group'));
    }

    public function test_informational_line_refuses_a_subtype_in_its_french_data(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InformationalLine(
            id: '1',
            subType: LineSubType::Group,
            item: new Item(name: 'Group'),
            french: new FrenchLine(subType: LineSubType::Detail),
        );
    }

    public function test_french_line_subtype_is_detail_or_none(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('InformationalLine');

        new FrenchLine(subType: LineSubType::Group);
    }

    public function test_informational_lines_need_extended_profile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(FrenchProfile::ExtendedCtcFr->value);

        new FrenchInvoice(FrenchProfile::En16931, [self::group('1')], ['1']);
    }

    public function test_line_order_must_cover_every_line_once(): void
    {
        $invoice = FrenchInvoices::extendedCtcFr();
        $french = $invoice->extensions()->get(FrenchInvoice::class);
        self::assertNotNull($french);
        FrenchGuard::check($invoice);

        foreach ([
            'missing line' => array_slice($french->lineOrder, 1),
            'repeated line' => [...$french->lineOrder, $french->lineOrder[0]],
            'unknown line' => [...$french->lineOrder, '99'],
            'empty with informational lines' => [],
        ] as $case => $order) {
            $broken = $invoice->withExtension(new FrenchInvoice($french->profile, $french->informationalLines, $order));
            try {
                FrenchGuard::check($broken);
                self::fail("Line order accepted: {$case}");
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('line order', $e->getMessage(), $case);
            }
        }
    }

    public function test_line_identifiers_must_be_unique(): void
    {
        $invoice = FrenchInvoices::extendedCtcFr();
        $french = $invoice->extensions()->get(FrenchInvoice::class);
        self::assertNotNull($french);
        [$group, $information] = $french->informationalLines;
        $sharedWithRegular = new InformationalLine(id: '2', subType: LineSubType::Information, item: $information->item);
        $first = $invoice->lines[0];

        foreach ([
            'informational line shares a regular line id' => $invoice->withExtension(
                new FrenchInvoice($french->profile, [$group, $sharedWithRegular], ['1', '2', '3', '2']),
            ),
            'two informational lines share an id' => $invoice->withExtension(
                new FrenchInvoice($french->profile, [$group, self::group('1'), $information], ['1', '1', '2', '3', '4']),
            ),
            'two regular lines share an id' => $invoice
                ->withLines([$first, $this->line('2')])
                ->withExtension(new FrenchInvoice($french->profile, $french->informationalLines, ['1', '2', '2', '4'])),
        ] as $case => $broken) {
            try {
                FrenchGuard::check($broken);
                self::fail("Duplicate line identifier accepted: {$case}");
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('Line identifier (BT-126) "', $e->getMessage(), $case);
                self::assertStringContainsString('more than one line', $e->getMessage(), $case);
            }
        }
    }

    public function test_line_order_needs_extended_profile(): void
    {
        $invoice = FrenchInvoices::domesticB2b()->withExtension(new FrenchInvoice(FrenchProfile::En16931, [], ['2', '1']));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('line order');
        $this->expectExceptionMessage(FrenchProfile::ExtendedCtcFr->value);

        FrenchGuard::check($invoice);
    }

    public function test_line_order_may_be_empty_without_informational_lines(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        [$first, $second] = $invoice->lines;
        $second = $second->withExtension(new FrenchLine(parentLineId: '1', subType: LineSubType::Detail));
        $plain = $invoice->withLines([$first, $second])->withExtension(new FrenchInvoice(FrenchProfile::ExtendedCtcFr));

        FrenchGuard::check($plain);

        $this->addToAssertionCount(1);
    }

    public function test_parent_line_id_must_name_a_line(): void
    {
        $invoice = FrenchInvoices::extendedCtcFr();
        $lines = $invoice->lines;
        $lines[0] = $lines[0]->withExtension(new FrenchLine(parentLineId: '99', subType: LineSubType::Detail));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EXT-FR-FE-162');

        FrenchGuard::check($invoice->withLines($lines));
    }

    public function test_french_line_data_needs_extended_profile(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $lines = $invoice->lines;
        $lines[0] = $lines[0]->withExtension(new FrenchLine(vatDueDateCode: '3'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(FrenchProfile::ExtendedCtcFr->value);

        FrenchGuard::check($invoice->withLines($lines));
    }

    public function test_line_amounts_take_at_most_two_decimals(): void
    {
        $invoice = FrenchInvoices::extendedCtcFr();
        $lines = $invoice->lines;
        $lines[0] = $lines[0]->withExtension(new FrenchLine(subType: LineSubType::Detail, vatAmount: '1.005'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EXT-FR-FE-181');

        FrenchGuard::check($invoice->withLines($lines));
    }

    public function test_informational_lines_stay_out_of_the_totals(): void
    {
        $invoice = FrenchInvoices::extendedCtcFr();

        self::assertSame('500.00', (string) $invoice->totals?->sumOfLineNetAmounts);
    }

    private function line(string $id): InvoiceLine
    {
        return new InvoiceLine(
            id: $id,
            quantity: '1',
            unit: UnitCode::C62,
            price: new Price(netPrice: '1'),
            item: new Item(name: 'Item'),
            vat: new LineVat(VatCategory::StandardRate, '20'),
        );
    }

    private static function group(string $id): InformationalLine
    {
        return new InformationalLine(id: $id, subType: LineSubType::Group, item: new Item(name: 'Group'));
    }
}
