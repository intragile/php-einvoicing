<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Fa3;

use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Guard;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Reader;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\PolishInvoices;
use IntrAgile\EInvoicing\Validation\Poland\Fa3Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * FA(3) data that must survive a read → write round trip, or be refused, never silently changed
 * or dropped.
 */
final class Fa3LosslessTest extends TestCase
{
    use CapturesThrowable;

    private const CLOCK = '2026-11-18T10:00:00Z';

    /**
     * @return array<string, array{string}>
     */
    public static function kindsWithoutRateCode(): array
    {
        return ['VAT' => ['VAT'], 'UPR' => ['UPR']];
    }

    #[DataProvider('kindsWithoutRateCode')]
    public function test_row_without_rate_code_is_read_from_its_only_bucket_and_written_without_one(string $kind): void
    {
        $source = str_replace(
            ['<P_12>23</P_12>', '<RodzajFaktury>VAT</RodzajFaktury>'],
            ['', "<RodzajFaktury>{$kind}</RodzajFaktury>"],
            self::write(PolishInvoices::consumer()),
        );
        self::assertStringNotContainsString('<P_12>', $source);

        $result = (new Fa3Reader)->read($source);
        $line = $result->invoice->lines[0];
        self::assertSame([], $result->ignored);
        self::assertSame(VatCategory::StandardRate, $line->vat->category);
        self::assertSame('23', (string) $line->vat->rate);
        self::assertTrue($line->extensions()->get(PolishLine::class)?->rateCodeOmitted);

        $written = self::write($result->invoice);
        self::assertStringNotContainsString('<P_12>', $written);
        self::assertSame(Fa3Fields::of($source), Fa3Fields::of($written));
        self::assertTrue(Fa3Validator::create()->validate($written)->isValid());
    }

    public function test_row_without_rate_code_among_several_buckets_is_not_given_a_code(): void
    {
        $source = self::write(PolishInvoices::domestic());
        $source = preg_replace('#<P_12>8</P_12>#', '', $source, 1);
        self::assertIsString($source);

        $read = (new Fa3Reader)->read($source)->invoice;
        $line = $read->lines[2];
        self::assertSame(VatCategory::NotSubjectToVat, $line->vat->category);
        self::assertTrue($line->extensions()->get(PolishLine::class)?->rateCodeOmitted);

        $exception = self::capture(fn () => self::write($read));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('line "3"', $exception->getMessage());
        self::assertStringContainsString('P_13_8', $exception->getMessage());
        self::assertStringNotContainsString('np I</P_12>', $source);
    }

    public function test_rate_code_omission_conflicts_with_a_margin_line(): void
    {
        $exception = self::capture(static fn () => new PolishLine(margin: true, rateCodeOmitted: true));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
    }

    public function test_line_whose_bucket_is_not_written_is_refused(): void
    {
        $invoice = PolishInvoices::domestic();
        $rows = array_values(array_filter(
            $invoice->vatBreakdown,
            static fn (VatBreakdown $row): bool => ! $row->rate?->isEqualTo(8),
        ));
        $invoice = $invoice->withVatBreakdown($rows);

        $exception = self::capture(static fn () => Fa3Guard::check($invoice));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('BT-151 (line "3"): ', $exception->getMessage());
        self::assertStringContainsString('P_13_2', $exception->getMessage());
    }

    private static function write(Invoice $invoice): string
    {
        return (new Fa3Writer(static fn (): DateTimeImmutable => new DateTimeImmutable(self::CLOCK)))->write($invoice);
    }
}
