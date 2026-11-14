<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\EInvoiceBe;

use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The production guards of the opt-in e-invoice.be live test, checked offline so that they run
 * with the default suite.
 */
final class EInvoiceBeLiveGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string|false}>
     */
    public static function unconfirmed(): iterable
    {
        yield 'not set' => [false];
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'true' => ['true'];
        yield 'yes' => ['yes'];
        yield 'leading space' => [' 1'];
        yield 'trailing newline' => ["1\n"];
        yield 'leading zero' => ['01'];
    }

    #[DataProvider('unconfirmed')]
    public function test_live_guard_requires_confirmation(string|false $confirmation): void
    {
        $refusal = EInvoiceBeLiveTest::sandboxConfirmationRefusal($confirmation);

        self::assertNotNull($refusal);
        self::assertStringContainsString('EINVOICEBE_SANDBOX_CONFIRMED=1', $refusal);
    }

    public function test_live_guard_accepts_confirmation(): void
    {
        self::assertNull(EInvoiceBeLiveTest::sandboxConfirmationRefusal('1'));
    }

    public function test_live_guard_takes_the_first_company_peppol_id(): void
    {
        $company = EInvoiceBeLiveTest::companyParticipant(['plan' => 'starter', 'peppol_ids' => ['0208:0123456789', '9925:BE0123456789']]);

        self::assertNotNull($company);
        self::assertSame('0208', $company->icd);
        self::assertSame('0123456789', $company->value);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function companiesWithoutPeppolId(): iterable
    {
        yield 'field missing' => [['plan' => 'starter']];
        yield 'null' => [['peppol_ids' => null]];
        yield 'empty list' => [['peppol_ids' => []]];
        yield 'not a list' => [['peppol_ids' => ['main' => '0208:0123456789']]];
        yield 'number' => [['peppol_ids' => [208]]];
        yield 'malformed' => [['peppol_ids' => ['0123456789']]];
    }

    /**
     * @param  array<mixed>  $me
     */
    #[DataProvider('companiesWithoutPeppolId')]
    public function test_live_guard_needs_a_company_peppol_id(array $me): void
    {
        self::assertNull(EInvoiceBeLiveTest::companyParticipant($me));
    }

    public function test_live_guard_sends_only_to_the_company(): void
    {
        $company = ParticipantId::parse('0208:0123456789');
        $other = ParticipantId::parse('0208:9876543210');

        self::assertNull(EInvoiceBeLiveTest::selfAddressRefusal($company, ParticipantId::parse('0208:0123456789'), $company));

        foreach ([[$company, $other], [$other, $company], [$other, $other]] as [$sender, $receiver]) {
            $refusal = EInvoiceBeLiveTest::selfAddressRefusal($sender, $receiver, $company);
            self::assertNotNull($refusal);
            self::assertStringNotContainsString('0123456789', $refusal);
            self::assertStringNotContainsString('9876543210', $refusal);
        }
    }
}
