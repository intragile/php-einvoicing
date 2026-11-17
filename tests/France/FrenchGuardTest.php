<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France;

use IntrAgile\EInvoicing\France\FrenchGuard;
use IntrAgile\EInvoicing\France\FrenchInvoice;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FrenchGuardTest extends TestCase
{
    public function test_french_guard_names_every_offending_decimal(): void
    {
        $invoice = $this->offending();

        try {
            FrenchGuard::check($invoice);
            self::fail('No exception thrown.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('BT-146 10.1234567: at most 6 decimals', $e->getMessage());
            self::assertStringContainsString('BT-131 12.345: at most 2 decimals', $e->getMessage());
            self::assertStringContainsString('BT-129 1.23456: at most 4 decimals', $e->getMessage());
        }
    }

    public function test_writers_run_the_guard_on_french_invoices(): void
    {
        foreach ([new UblWriter, new CiiWriter] as $writer) {
            try {
                $writer->write($this->offending());
                self::fail($writer::class.' wrote the invoice.');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('BT-146 10.1234567: at most 6 decimals', $e->getMessage(), $writer::class);
            }
        }
    }

    public function test_guard_ignores_invoices_without_french_extension(): void
    {
        $invoice = $this->offending()->withExtensions($this->offending()->extensions()->without(FrenchInvoice::class));

        FrenchGuard::check($invoice);
        self::assertNotSame('', (new UblWriter)->write($invoice));
        self::assertNotSame('', (new CiiWriter)->write($invoice));
    }

    public function test_calculator_output_passes_the_guard(): void
    {
        foreach (FrenchInvoices::all() as $invoice) {
            FrenchGuard::check($invoice);
        }
        $this->addToAssertionCount(1);
    }

    private function offending(): Invoice
    {
        $invoice = FrenchInvoices::domesticB2b();
        $line = $invoice->lines[0]
            ->withPrice(new Price(netPrice: '10.1234567'))
            ->withQuantity('1.23456')
            ->withNetAmount('12.345');

        return $invoice->withLines([$line, ...array_slice($invoice->lines, 1)]);
    }
}
