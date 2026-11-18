<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Fa3;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineAllowance;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use IntrAgile\EInvoicing\Poland\Annotations;
use IntrAgile\EInvoicing\Poland\Exemption;
use IntrAgile\EInvoicing\Poland\ExemptionBasis;
use IntrAgile\EInvoicing\Poland\Gtu;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\PolishParty;
use IntrAgile\EInvoicing\Poland\ThirdParty;
use IntrAgile\EInvoicing\Poland\ThirdPartyRole;
use IntrAgile\EInvoicing\Poland\VatRateCode;
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

    public function test_vat_is_not_lost_through_a_rate_code_override(): void
    {
        $line = PolishInvoices::line('1', 'X', '1', UnitCode::H87, '100.00', VatCategory::StandardRate, '23')
            ->withExtension(new PolishLine(rateCode: VatRateCode::Exempt));
        $invoice = TotalsCalculator::complete(PolishInvoices::consumer()->withLines([$line])->withVatBreakdown([]))
            ->withExtension(new PolishInvoice(annotations: new Annotations(exemption: new Exemption(ExemptionBasis::Act, 'art. 43'))));

        $message = self::refusal($invoice);
        self::assertStringContainsString('P_12 (line "1"): ', $message);
        self::assertStringContainsString('BT-117', $message);
        self::assertStringContainsString('P_13_7', $message);
    }

    /**
     * @return array<string, array{VatCategory, numeric-string|null, VatRateCode, bool}>
     */
    public static function overrides(): array
    {
        return [
            '23 as 22' => [VatCategory::StandardRate, '23', VatRateCode::Rate22, true],
            '22 as 23' => [VatCategory::StandardRate, '22', VatRateCode::Rate23, true],
            '8 as 7' => [VatCategory::StandardRate, '8', VatRateCode::Rate7, true],
            'AE as np II' => [VatCategory::ReverseCharge, '0', VatRateCode::EuServicesArticle100, true],
            'Z as 0 KR' => [VatCategory::ZeroRated, '0', VatRateCode::ZeroDomestic, true],
            'K as 0 WDT' => [VatCategory::IntraCommunitySupply, '0', VatRateCode::ZeroIntraCommunity, true],
            'G as 0 EX' => [VatCategory::ExportOutsideEu, '0', VatRateCode::ZeroExport, true],
            'S 23 as zw' => [VatCategory::StandardRate, '23', VatRateCode::Exempt, false],
            'S 23 as 8' => [VatCategory::StandardRate, '23', VatRateCode::Rate8, false],
            'E as 0 KR' => [VatCategory::Exempt, '0', VatRateCode::ZeroDomestic, false],
            'Z as 0 WDT' => [VatCategory::ZeroRated, '0', VatRateCode::ZeroIntraCommunity, false],
            'O as np II' => [VatCategory::NotSubjectToVat, null, VatRateCode::EuServicesArticle100, false],
        ];
    }

    /**
     * @param  numeric-string|null  $rate
     */
    #[DataProvider('overrides')]
    public function test_rate_code_override_must_stand_for_the_line_category(VatCategory $category, ?string $rate, VatRateCode $code, bool $accepted): void
    {
        $line = PolishInvoices::line('1', 'X', '1', UnitCode::H87, '100.00', $category, $rate)
            ->withExtension(new PolishLine(rateCode: $code));
        $invoice = TotalsCalculator::complete(PolishInvoices::domestic()->withLines([$line])->withVatBreakdown([]));

        $problems = self::problems($invoice);
        $override = array_values(array_filter($problems, static fn (string $problem): bool => str_contains($problem, 'override')));
        self::assertSame($accepted ? [] : 1, $accepted ? $override : count($override), implode("\n", $problems));
    }

    public function test_third_party_internal_id_and_legal_registration_are_refused_together(): void
    {
        $thirdParty = new ThirdParty(
            party: new Party(name: 'Odbiorca', legalRegistration: new Identifier('KRS0000123'), address: new PostalAddress(CountryCode::PL, line1: 'ul. A 1', city: 'Łódź', postCode: '90-001')),
            role: ThirdPartyRole::from(2),
            internalId: '2222222222-00001',
        );
        $message = self::refusal(PolishInvoices::domestic()->withExtension(new PolishInvoice(thirdParties: [$thirdParty])));

        self::assertStringContainsString('Podmiot3[1]: ', $message);
        self::assertStringContainsString('IDWew', $message);
        self::assertStringContainsString('legal registration', $message);
    }

    public function test_bucket_line_data_beyond_the_amounts_is_refused(): void
    {
        $invoice = PolishInvoices::advanceInEuro();
        $line = $invoice->lines[0];
        $data = new InvoiceLine(
            id: $line->id,
            quantity: '2',
            unit: UnitCode::H87,
            price: $line->price,
            item: new Item('Regał metalowy', description: 'Opis X', sellersIdentifier: 'IDX-1', buyersIdentifier: 'B-1', originCountry: CountryCode::DE),
            vat: $line->vat,
            netAmount: $line->netAmount,
            note: 'Uwaga Y',
            orderLineReference: '7',
            buyerAccountingReference: 'K-1',
            allowances: [new LineAllowance(amount: '1.00', reason: 'Rabat')],
        );
        $data = $data->withExtension(new PolishLine(bucketLine: true, gtu: Gtu::from('GTU_06'), unitLabel: 'szt.'));

        $message = self::refusal($invoice->withLines([$data]));
        foreach (['BT-129', 'BT-130', 'BT-153', 'BT-154', 'BT-155', 'BT-156', 'BT-159', 'BT-127', 'BT-132', 'BT-133', 'BG-27', 'GTU', 'P_8A'] as $term) {
            self::assertStringContainsString($term.' (bucket line "1")', $message);
        }
    }

    public function test_plain_bucket_line_passes(): void
    {
        Fa3Guard::check(PolishInvoices::advanceInEuro());
        $read = (new Fa3Reader)->read(self::write(PolishInvoices::advanceInEuro()))->invoice;
        Fa3Guard::check($read);
        $this->addToAssertionCount(2);
    }

    public function test_gln_without_an_address_is_refused(): void
    {
        $invoice = PolishInvoices::domestic()
            ->withBuyer(new Party(name: 'Nabywca', vatIdentifier: PolishInvoices::BUYER_VAT))
            ->withExtension(new PolishInvoice(buyer: new PolishParty(gln: '5901234123457')));

        $message = self::refusal($invoice);
        self::assertStringContainsString('Podmiot2 GLN: ', $message);
    }

    public function test_one_stop_shop_line_feeds_p_13_5_without_a_rate_code(): void
    {
        $line = PolishInvoices::line('1', 'Ebook', '1', UnitCode::C62, '100.00', VatCategory::StandardRate, '19')
            ->withExtension(new PolishLine(ossRate: '19'));
        $invoice = TotalsCalculator::complete(PolishInvoices::consumer()->withLines([$line])->withVatBreakdown([]));

        $xml = self::write($invoice);
        $fields = Fa3Fields::of($xml);
        self::assertSame(['100.00'], $fields['Fa/P_13_5']);
        self::assertSame(['19.00'], $fields['Fa/P_14_5']);
        self::assertSame(['19'], $fields['Fa/FaWiersz[1]/P_12_XII']);
        self::assertArrayNotHasKey('Fa/FaWiersz[1]/P_12', $fields);
        self::assertTrue(Fa3Validator::create()->validate($xml)->isValid());

        $read = (new Fa3Reader)->read($xml);
        self::assertSame([], $read->ignored);
        $readLine = $read->invoice->lines[0];
        self::assertSame(VatCategory::StandardRate, $readLine->vat->category);
        self::assertSame('19', (string) $readLine->vat->rate);
        self::assertSame('19', (string) $readLine->extensions()->get(PolishLine::class)?->ossRate);
        self::assertSame($xml, self::write($read->invoice));
    }

    public function test_one_stop_shop_file_survives_read_and_write(): void
    {
        $source = preg_replace(
            ['#<P_13_1>([^<]*)</P_13_1>\s*<P_14_1>([^<]*)</P_14_1>#', '#<P_12>23</P_12>#'],
            ['<P_13_5>$1</P_13_5><P_14_5>$2</P_14_5>', '<P_12_XII>19</P_12_XII>'],
            self::write(PolishInvoices::consumer()),
        );
        self::assertIsString($source);
        self::assertTrue(Fa3Validator::create()->validate($source)->isValid());

        $read = (new Fa3Reader)->read($source)->invoice;
        self::assertSame(VatCategory::StandardRate, $read->vatBreakdown[0]->category);
        self::assertSame('19', (string) $read->vatBreakdown[0]->rate);
        self::assertSame(Fa3Fields::of($source), Fa3Fields::of(self::write($read)));
    }

    /**
     * @return array<string, array{VatCategory, numeric-string|null, PolishLine, string}>
     */
    public static function badOssLines(): array
    {
        return [
            'with a rate code override' => [VatCategory::NotSubjectToVat, null, new PolishLine(ossRate: '19', rateCode: VatRateCode::OutsideTerritory), 'P_12_XII (line "1"): '],
            'not standard rated' => [VatCategory::NotSubjectToVat, null, new PolishLine(ossRate: '19'), 'P_12_XII (line "1"): '],
            'rate differs' => [VatCategory::StandardRate, '20', new PolishLine(ossRate: '19'), 'P_12_XII (line "1"): '],
        ];
    }

    /**
     * @param  numeric-string|null  $rate
     */
    #[DataProvider('badOssLines')]
    public function test_one_stop_shop_line_must_be_standard_rated_at_its_oss_rate(VatCategory $category, ?string $rate, PolishLine $polish, string $term): void
    {
        $line = PolishInvoices::line('1', 'Ebook', '1', UnitCode::C62, '100.00', $category, $rate)->withExtension($polish);
        $invoice = TotalsCalculator::complete(PolishInvoices::consumer()->withLines([$line])->withVatBreakdown([]));

        self::assertStringContainsString($term, self::refusal($invoice));
    }

    public function test_one_stop_shop_bucket_line_is_refused(): void
    {
        $invoice = PolishInvoices::advanceInEuro();
        $line = PolishInvoices::line('1', 'P_13_5', '1', UnitCode::C62, '3252.03', VatCategory::StandardRate, '19')
            ->withExtension(new PolishLine(bucketLine: true, ossRate: '19'));

        self::assertStringContainsString('P_12_XII (bucket line "1"): ', self::refusal($invoice->withLines([$line])));
    }

    public function test_taxi_lump_sum_file_is_read_and_refused_clearly_on_write(): void
    {
        $source = preg_replace(
            ['#<P_13_1>([^<]*)</P_13_1>\s*<P_14_1>([^<]*)</P_14_1>#', '#<P_12>23</P_12>#'],
            ['<P_13_4>$1</P_13_4><P_14_4>$2</P_14_4>', '<P_12>4</P_12>'],
            self::write(PolishInvoices::consumer()),
        );
        self::assertIsString($source);
        $result = (new Fa3Reader)->read($source);
        self::assertSame([], $result->ignored);
        self::assertSame('99.00', (string) $result->invoice->vatBreakdown[0]->taxableAmount);

        $exception = self::capture(fn () => self::write($result->invoice));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('taxi lump-sum', $exception->getMessage());
        self::assertStringContainsString('P_13_4', $exception->getMessage());
    }

    public function test_unknown_standard_rate_points_to_the_oss_rate_not_to_an_override(): void
    {
        $line = PolishInvoices::line('1', 'Ebook', '1', UnitCode::C62, '100.00', VatCategory::StandardRate, '19');
        $message = self::refusal(TotalsCalculator::complete(PolishInvoices::consumer()->withLines([$line])->withVatBreakdown([])));

        self::assertStringContainsString('PolishLine::$ossRate', $message);
        self::assertStringNotContainsString('rate code explicitly', $message);
    }

    private static function refusal(Invoice $invoice): string
    {
        $exception = self::capture(static fn () => Fa3Guard::check($invoice));
        self::assertInstanceOf(InvalidArgumentException::class, $exception);

        return $exception->getMessage();
    }

    /**
     * @return list<string>
     */
    private static function problems(Invoice $invoice): array
    {
        try {
            Fa3Guard::check($invoice);
        } catch (InvalidArgumentException $e) {
            return explode('; ', substr($e->getMessage(), strlen('The invoice cannot be written as FA(3): ')));
        }

        return [];
    }

    private static function write(Invoice $invoice): string
    {
        return (new Fa3Writer(static fn (): DateTimeImmutable => new DateTimeImmutable(self::CLOCK)))->write($invoice);
    }
}
