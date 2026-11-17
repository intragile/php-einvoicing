<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France\EReporting;

use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\France\CollectedAmount;
use IntrAgile\EInvoicing\France\EReporting\DailyPayments;
use IntrAgile\EInvoicing\France\EReporting\DailyTransactions;
use IntrAgile\EInvoicing\France\EReporting\Declarant;
use IntrAgile\EInvoicing\France\EReporting\DeclarantRole;
use IntrAgile\EInvoicing\France\EReporting\EReport;
use IntrAgile\EInvoicing\France\EReporting\ReportPeriod;
use IntrAgile\EInvoicing\France\EReporting\ReportType;
use IntrAgile\EInvoicing\France\EReporting\TransactionCategory;
use IntrAgile\EInvoicing\France\EReporting\TransactionsReport;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleParty;
use IntrAgile\EInvoicing\France\Lifecycle\PartyRole;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Tests\Support\EReports;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EReportTest extends TestCase
{
    public function test_decade_periods(): void
    {
        self::assertPeriod('2026-11-01', '2026-11-10', ReportPeriod::decade(2026, 11, 1));
        self::assertPeriod('2026-11-11', '2026-11-20', ReportPeriod::decade(2026, 11, 2));
        self::assertPeriod('2026-11-21', '2026-11-30', ReportPeriod::decade(2026, 11, 3));
        self::assertPeriod('2027-02-21', '2027-02-28', ReportPeriod::decade(2027, 2, 3));
    }

    public function test_month_and_bimester_periods(): void
    {
        self::assertPeriod('2026-11-01', '2026-11-30', ReportPeriod::month(2026, 11));
        self::assertPeriod('2028-01-01', '2028-02-29', ReportPeriod::bimester(2028, 1));
        self::assertPeriod('2026-11-01', '2026-12-31', ReportPeriod::bimester(2026, 11));
    }

    public function test_decade_must_be_one_to_three(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('decade');

        ReportPeriod::decade(2026, 11, 4);
    }

    public function test_bimester_must_start_on_odd_month(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('odd month');

        ReportPeriod::bimester(2026, 2);
    }

    public function test_period_end_must_follow_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('after');

        new ReportPeriod(new DateTimeImmutable('2026-11-10'), new DateTimeImmutable('2026-11-10'));
    }

    /**
     * @return iterable<string, array{LifecycleParty, string}>
     */
    public static function invalidPlatforms(): iterable
    {
        yield 'SIREN scheme' => [new LifecycleParty(new Identifier('PA01', '0002'), PartyRole::Platform, 'PLATEFORME'), '0238'];
        yield 'five characters' => [LifecycleParty::platform('PA001', 'PLATEFORME'), '4 characters'];
        yield 'seller role' => [new LifecycleParty(new Identifier('PA01', '0238'), PartyRole::Seller, 'PLATEFORME'), 'WK'];
        yield 'no identifier' => [new LifecycleParty(null, PartyRole::Platform, 'PLATEFORME'), '0238'];
        yield 'no name' => [LifecycleParty::platform('PA01'), 'name'];
    }

    #[DataProvider('invalidPlatforms')]
    public function test_platform_must_be_a_pa_matricule(LifecycleParty $platform, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        EReport::payments('PA-1', EReports::issuedAt(), ReportType::Initial, $platform, EReports::seller(), EReports::fixturePayments()->payments ?? self::fail());
    }

    public function test_id_is_at_most_50_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('50');

        EReport::transactions(str_repeat('A', 51), EReports::issuedAt(), ReportType::Initial, EReports::platform(), EReports::seller(), new TransactionsReport(EReports::november()));
    }

    public function test_report_holds_exactly_one_kind(): void
    {
        $transactions = EReports::fixtureTransactions();
        $payments = EReports::fixturePayments();

        self::assertNotNull($transactions->transactions);
        self::assertNull($transactions->payments);
        self::assertNull($payments->transactions);
        self::assertNotNull($payments->payments);
        self::assertSame('E-REPORTING NOVEMBRE', $transactions->name);
    }

    public function test_declarant_needs_a_siren(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SIREN');

        new Declarant('12345', 'LE VENDEUR', DeclarantRole::Seller);
    }

    public function test_daily_blocks_need_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DailyPayments(new DateTimeImmutable('2026-11-21'), []);
    }

    public function test_daily_transactions_need_subtotals(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DailyTransactions(new DateTimeImmutable('2026-11-21'), CurrencyCode::EUR, TransactionCategory::Goods, '0.00', '0.00', []);
    }

    public function test_amounts_refuse_floats(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DailyPayments(new DateTimeImmutable('2026-11-21'), [new CollectedAmount(1.5, '20')]);
    }

    private static function assertPeriod(string $start, string $end, ReportPeriod $period): void
    {
        self::assertSame($start, $period->start->format('Y-m-d'));
        self::assertSame($end, $period->end->format('Y-m-d'));
    }
}
