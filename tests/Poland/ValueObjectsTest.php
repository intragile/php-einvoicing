<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Poland;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Poland\AdvanceInvoiceReference;
use IntrAgile\EInvoicing\Poland\BankAccount;
use IntrAgile\EInvoicing\Poland\ContactDetails;
use IntrAgile\EInvoicing\Poland\CorrectedInvoice;
use IntrAgile\EInvoicing\Poland\Correction;
use IntrAgile\EInvoicing\Poland\Footer;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Poland\Order;
use IntrAgile\EInvoicing\Poland\OrderLine;
use IntrAgile\EInvoicing\Poland\PolishParty;
use IntrAgile\EInvoicing\Poland\Procedure;
use IntrAgile\EInvoicing\Poland\RawFragment;
use IntrAgile\EInvoicing\Poland\Settlement;
use IntrAgile\EInvoicing\Poland\SettlementEntry;
use IntrAgile\EInvoicing\Poland\ThirdParty;
use IntrAgile\EInvoicing\Poland\ThirdPartyRole;
use IntrAgile\EInvoicing\Poland\TransactionTerms;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function test_third_party_needs_exactly_one_role_form(): void
    {
        $party = new Party('Factor sp. z o.o.');

        self::assertSame(ThirdPartyRole::Factor, (new ThirdParty($party, ThirdPartyRole::Factor))->role);
        self::assertSame('Agent', (new ThirdParty($party, otherRole: 'Agent'))->otherRole);
        $this->assertRefused(static fn () => new ThirdParty($party));
        $this->assertRefused(static fn () => new ThirdParty($party, ThirdPartyRole::Factor, 'Agent'));
    }

    public function test_third_party_share_is_a_percentage(): void
    {
        $party = new Party('Buyer 2');

        self::assertSame('0', (string) (new ThirdParty($party, ThirdPartyRole::AdditionalBuyer, share: '0'))->share);
        self::assertSame('100', (string) (new ThirdParty($party, ThirdPartyRole::AdditionalBuyer, share: 100))->share);
        $this->assertRefused(static fn () => new ThirdParty($party, ThirdPartyRole::AdditionalBuyer, share: '100.01'));
        $this->assertRefused(static fn () => new ThirdParty($party, ThirdPartyRole::AdditionalBuyer, share: '-1'));
    }

    public function test_advance_reference_needs_exactly_one_identifier(): void
    {
        $ksef = KsefNumber::parse($this->ksefNumber());

        self::assertSame($ksef, (new AdvanceInvoiceReference($ksef, null))->ksefNumber);
        self::assertSame('ZAL/1', (new AdvanceInvoiceReference(null, 'ZAL/1'))->number);
        $this->assertRefused(static fn () => new AdvanceInvoiceReference(null, null));
        $this->assertRefused(static fn () => new AdvanceInvoiceReference($ksef, 'ZAL/1'));
    }

    public function test_order_line_refuses_import_procedures(): void
    {
        self::assertSame(Procedure::TT_D, (new OrderLine(procedure: Procedure::TT_D))->procedure);
        $this->assertRefused(static fn () => new OrderLine(procedure: Procedure::I_42));
        $this->assertRefused(static fn () => new OrderLine(procedure: Procedure::I_63));
    }

    public function test_order_needs_one_to_ten_thousand_lines(): void
    {
        self::assertCount(1, (new Order('100', [new OrderLine]))->lines);
        $this->assertRefused(static fn () => new Order('100', []));
    }

    public function test_raw_fragment_must_be_one_fa3_element(): void
    {
        $fragment = new RawFragment('<Zalacznik xmlns="'.Fa3Format::NS.'"><BlokDanych/></Zalacznik>');
        self::assertSame('Zalacznik', $fragment->name);

        $this->assertRefused(static fn () => new RawFragment('<Zalacznik xmlns="urn:other"/>'));
        $this->assertRefused(static fn () => new RawFragment('<Zalacznik/>'));
        $this->assertRefused(static fn () => new RawFragment('<a xmlns="'.Fa3Format::NS.'"/><b xmlns="'.Fa3Format::NS.'"/>'));
        $this->assertRefused(static fn () => new RawFragment('<!DOCTYPE a><a xmlns="'.Fa3Format::NS.'"/>'));
        $this->assertRefused(static fn () => new RawFragment(''));
    }

    public function test_settlement_balance(): void
    {
        // Brochure example 24: P_15 246, one charge of 17 and a deduction of 300 leave -37 to pay.
        $settlement = new Settlement(
            [new SettlementEntry('17', 'Koszty transportu')],
            [new SettlementEntry('300', 'Zaliczka')],
        );

        self::assertSame('-37', (string) $settlement->balance(BigDecimal::of('246')));
        $this->assertRefused(static fn () => new Settlement(array_fill(0, 101, new SettlementEntry('1', 'x')), []));
    }

    public function test_buyer_id_is_at_most_32_characters(): void
    {
        self::assertSame(str_repeat('A', 32), (new PolishParty(buyerId: str_repeat('A', 32)))->buyerId);
        $this->assertRefused(static fn () => new PolishParty(buyerId: str_repeat('A', 33)));
    }

    public function test_party_contacts(): void
    {
        $contact = new ContactDetails('a@example.com', null);

        self::assertCount(3, (new PolishParty(contacts: [$contact, $contact, $contact]))->contacts);
        $this->assertRefused(static fn () => new PolishParty(contacts: [$contact, $contact, $contact, $contact]));
        $this->assertRefused(static fn () => new ContactDetails(null, null));
    }

    public function test_correction_needs_at_least_one_corrected_invoice(): void
    {
        $corrected = new CorrectedInvoice('FV/1', new DateTimeImmutable('2026-01-02'), null);

        self::assertNull((new Correction([$corrected]))->invoices[0]->ksefNumber);
        $this->assertRefused(static fn () => new Correction([]));
        $this->assertRefused(static fn () => new Correction([$corrected], buyersBefore: array_fill(0, 102, new Party('B'))));
    }

    public function test_transaction_terms_contract_rate_and_currency_go_together(): void
    {
        $terms = new TransactionTerms(contractRate: '4.2500', contractCurrency: CurrencyCode::EUR);
        self::assertSame(CurrencyCode::EUR, $terms->contractCurrency);

        $this->assertRefused(static fn () => new TransactionTerms(contractRate: '4.25'));
        $this->assertRefused(static fn () => new TransactionTerms(contractCurrency: CurrencyCode::EUR));
    }

    public function test_footer_and_bank_account_limits(): void
    {
        $this->assertRefused(static fn () => new Footer(['a', 'b', 'c', 'd']));
        self::assertSame(3, (new BankAccount('PL61109010140000071219812874', ownBankAccount: 3))->ownBankAccount);
        $this->assertRefused(static fn () => new BankAccount('PL61109010140000071219812874', ownBankAccount: 4));
    }

    public function test_decimals_refuse_floats(): void
    {
        $this->assertRefused(static fn () => new SettlementEntry(1.5, 'x'));
    }

    private function ksefNumber(): string
    {
        $first32 = '5265877635-20250826-0100001AF629';

        return $first32.'-'.KsefNumber::checksum($first32);
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
