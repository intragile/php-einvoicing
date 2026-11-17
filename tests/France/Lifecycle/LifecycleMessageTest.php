<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France\Lifecycle;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\France\CollectedAmount;
use IntrAgile\EInvoicing\France\Lifecycle\AcknowledgementType;
use IntrAgile\EInvoicing\France\Lifecycle\CdvProfile;
use IntrAgile\EInvoicing\France\Lifecycle\CharacteristicType;
use IntrAgile\EInvoicing\France\Lifecycle\InvoiceReference;
use IntrAgile\EInvoicing\France\Lifecycle\Lifecycle;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleMessage;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleParty;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleStatus;
use IntrAgile\EInvoicing\France\Lifecycle\PartyRole;
use IntrAgile\EInvoicing\France\Lifecycle\RegulatoryScope;
use IntrAgile\EInvoicing\France\Lifecycle\StatusDetail;
use IntrAgile\EInvoicing\France\Lifecycle\StatusReason;
use IntrAgile\EInvoicing\France\Siret;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use IntrAgile\EInvoicing\Tests\Support\LifecycleMessages;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LifecycleMessageTest extends TestCase
{
    public function test_invoice_reference_takes_siren_from_siret(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $seller = $invoice->seller;
        $invoice = $invoice->withSeller(new Party(
            name: $seller->name,
            identifiers: [Siret::identifier(FrenchInvoices::SELLER_SIRET)],
        ));
        $receivedAt = new DateTimeImmutable('2026-11-02 09:30:00', new DateTimeZone('Europe/Paris'));

        $reference = InvoiceReference::of($invoice, $receivedAt);

        self::assertSame('F202600003', $reference->number);
        self::assertSame(InvoiceTypeCode::C_380, $reference->type);
        self::assertSame($receivedAt, $reference->receivedAt);
        self::assertSame('2026-11-02', $reference->issueDate?->format('Y-m-d'));
        self::assertSame(FrenchInvoices::SELLER_SIREN, $reference->issuer?->value);
        self::assertSame('0002', $reference->issuer->scheme);
    }

    public function test_invoice_reference_prefers_legal_registration(): void
    {
        $reference = InvoiceReference::of(FrenchInvoices::domesticB2b(), new DateTimeImmutable);

        self::assertSame(FrenchInvoices::SELLER_SIREN, $reference->issuer?->value);
        self::assertSame('0002', $reference->issuer->scheme);
    }

    public function test_invoice_reference_without_seller_siren_has_no_issuer(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $invoice = $invoice->withSeller(new Party(name: 'FOREIGN'));

        self::assertNull(InvoiceReference::of($invoice, new DateTimeImmutable)->issuer);
    }

    public function test_collected_has_one_men_block_per_rate(): void
    {
        $message = Lifecycle::collected(
            LifecycleMessages::invoice(),
            LifecycleMessages::seller(),
            LifecycleMessages::buyer(),
            [new CollectedAmount('891.83', '20'), new CollectedAmount('108.18', '5.5')],
            CurrencyCode::EUR,
            LifecycleMessages::at('2025-08-02 10:00:00'),
        );

        self::assertSame(LifecycleStatus::Collected, $message->status);
        self::assertSame(AcknowledgementType::Processing, $message->acknowledgementType());
        self::assertSame('47', $message->statusCode());
        self::assertSame([PartyRole::Buyer, PartyRole::PublicPortal], array_map(static fn (LifecycleParty $p) => $p->role, $message->recipients));
        self::assertCount(1, $message->details);
        $detail = $message->details[0];
        self::assertSame(1, $detail->sequence);
        self::assertCount(2, $detail->characteristics);
        foreach ($detail->characteristics as $characteristic) {
            self::assertSame(CharacteristicType::MEN, $characteristic->type);
            self::assertSame(CurrencyCode::EUR, $characteristic->currency);
            self::assertFalse($characteristic->changed);
        }
        self::assertSame('891.83', (string) $detail->characteristics[0]->amount);
        self::assertSame('20', (string) $detail->characteristics[0]->percent);
        self::assertSame('108.18', (string) $detail->characteristics[1]->amount);
        self::assertSame('5.5', (string) $detail->characteristics[1]->percent);
    }

    public function test_collected_needs_an_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Lifecycle::collected(
            LifecycleMessages::invoice(),
            LifecycleMessages::seller(),
            LifecycleMessages::buyer(),
            [],
            CurrencyCode::EUR,
            LifecycleMessages::at('2025-08-02 10:00:00'),
        );
    }

    public function test_refused_requires_comment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('comment');

        Lifecycle::refused(
            LifecycleMessages::invoice(),
            LifecycleMessages::buyer(),
            LifecycleMessages::seller(),
            StatusReason::TX_TVA_ERR,
            '  ',
            LifecycleMessages::at('2025-07-01 18:00:00'),
        );
    }

    public function test_refused_carries_reason_and_comment(): void
    {
        $message = LifecycleMessages::refused();

        self::assertSame(LifecycleStatus::Refused, $message->status);
        self::assertSame(PartyRole::Buyer, $message->issuer->role);
        self::assertSame([PartyRole::Seller, PartyRole::PublicPortal], array_map(static fn (LifecycleParty $p) => $p->role, $message->recipients));
        self::assertSame(StatusReason::TX_TVA_ERR, $message->details[0]->reason);
        self::assertSame('Taux de TVA erroné', $message->details[0]->reasonText);
        self::assertNotSame('', $message->details[0]->notes[0]->content);
    }

    public function test_detail_sequences_must_ascend(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LifecycleMessages::collected()->withDetails([new StatusDetail(2), new StatusDetail(1)]);
    }

    public function test_detail_sequences_must_be_unique(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LifecycleMessages::collected()->withDetails([new StatusDetail(1), new StatusDetail(1)]);
    }

    public function test_detail_sequence_starts_at_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StatusDetail(0);
    }

    public function test_message_id_longer_than_50_is_refused(): void
    {
        $message = LifecycleMessages::collected();
        self::assertSame(str_repeat('a', 50), $message->withId(str_repeat('a', 50))->id);

        $this->expectException(InvalidArgumentException::class);

        $message->withId(str_repeat('a', 51));
    }

    public function test_empty_message_id_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LifecycleMessages::collected()->withId(' ');
    }

    public function test_message_needs_a_recipient(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LifecycleMessages::collected()->withRecipients([]);
    }

    public function test_default_id_follows_fnfe_pattern(): void
    {
        self::assertSame('F202500003_212_20250701151000#380_20250701', LifecycleMessages::collected()->id);
        self::assertSame('F202500003_212_20250701151000#380_20250701', LifecycleMessages::uc1Collected()->id);
    }

    public function test_default_id_formats_the_receipt_time_in_paris(): void
    {
        $utc = new DateTimeImmutable('2025-07-01 13:10:00', new DateTimeZone('UTC'));
        $invoice = new InvoiceReference('F1', InvoiceTypeCode::C_380, $utc, new DateTimeImmutable('2025-07-01'), null);

        $message = Lifecycle::status(
            LifecycleStatus::Approved,
            $invoice,
            LifecycleMessages::platform(),
            LifecycleMessages::buyerIssuer(),
            [LifecycleMessages::seller()],
            $utc,
        );

        self::assertSame('F1_205_20250701151000#380_20250701', $message->id);
    }

    public function test_default_id_without_issue_date(): void
    {
        $invoice = new InvoiceReference('F1', InvoiceTypeCode::C_380, LifecycleMessages::at('2025-07-01 15:10:00'), null, null);

        $message = Lifecycle::status(LifecycleStatus::Approved, $invoice, LifecycleMessages::platform(), LifecycleMessages::buyerIssuer(), [LifecycleMessages::seller()], LifecycleMessages::at('2025-07-02 10:00:00'));

        self::assertSame('F1_205_20250701151000#380', $message->id);
    }

    public function test_nineteen_character_number_falls_back_to_a_hashed_id(): void
    {
        $number = 'F'.str_repeat('0', 18);
        $full = $number.'_205_20250701151000#380_20250701';

        self::assertSame(51, strlen($full));
        self::assertSame('205_20250701151000_'.substr(hash('sha256', $full), 0, 30), $this->approvedFor($number)->id);
    }

    public function test_long_invoice_number_falls_back_to_a_hashed_id(): void
    {
        $number = str_repeat('F', 25).'0123456789';
        self::assertSame(35, strlen($number));

        $message = $this->approvedFor($number);
        $full = $number.'_205_20250701151000#380_20250701';

        self::assertSame('205_20250701151000_'.substr(hash('sha256', $full), 0, 30), $message->id);
        self::assertSame(49, strlen($message->id));
    }

    public function test_fallback_id_is_deterministic(): void
    {
        $number = str_repeat('F', 25).'0123456789';

        self::assertSame($this->approvedFor($number)->id, $this->approvedFor($number)->id);
        self::assertNotSame($this->approvedFor($number)->id, $this->approvedFor(str_repeat('G', 25).'0123456789')->id);
    }

    public function test_longest_fitting_number_keeps_the_fnfe_pattern(): void
    {
        // The pattern adds 32 characters to the invoice number, so 18 is the longest that fits.
        $number = 'F'.str_repeat('0', 17);

        $message = $this->approvedFor($number);

        self::assertSame($number.'_205_20250701151000#380_20250701', $message->id);
        self::assertSame(50, strlen($message->id));
    }

    public function test_explicit_id_overrides_the_default(): void
    {
        $message = Lifecycle::status(
            LifecycleStatus::Approved,
            LifecycleMessages::invoice(),
            LifecycleMessages::platform(),
            LifecycleMessages::buyerIssuer(),
            [LifecycleMessages::seller()],
            LifecycleMessages::at('2025-07-02 10:00:00'),
            id: 'MSG-1',
        );

        self::assertSame('MSG-1', $message->id);
    }

    public function test_acknowledgement_type_and_status_code_default_to_the_status(): void
    {
        $message = LifecycleMessages::deposited();

        self::assertSame(AcknowledgementType::Platform, $message->acknowledgementType());
        self::assertSame('10', $message->statusCode());

        $overridden = $message->withAcknowledgementType(AcknowledgementType::Processing)->withStatusCode('51');
        self::assertSame(AcknowledgementType::Processing, $overridden->acknowledgementType());
        self::assertSame('51', $overridden->statusCode());
        self::assertSame('10', $overridden->withStatusCode(null)->statusCode());
    }

    public function test_withers_keep_other_fields(): void
    {
        $message = LifecycleMessages::uc1Collected();
        $issuedAt = LifecycleMessages::at('2025-08-03 08:00:00');

        $changed = $message->withIssuedAt($issuedAt);

        self::assertSame($issuedAt, $changed->issuedAt);
        self::assertSame($message->id, $changed->id);
        self::assertSame($message->sender, $changed->sender);
        self::assertSame($message->issuer, $changed->issuer);
        self::assertSame($message->recipients, $changed->recipients);
        self::assertSame($message->status, $changed->status);
        self::assertSame($message->invoice, $changed->invoice);
        self::assertSame($message->statusAt, $changed->statusAt);
        self::assertSame($message->profile, $changed->profile);
        self::assertSame($message->scope, $changed->scope);
        self::assertSame($message->name, $changed->name);
        self::assertSame($message->statusLabel, $changed->statusLabel);
        self::assertSame($message->details, $changed->details);
        self::assertSame($message->invoiceRecipients, $changed->invoiceRecipients);
        self::assertSame($message->test, $changed->test);

        $other = $message
            ->withProfile(CdvProfile::EinvoicingF2)
            ->withScope(null)
            ->withName(null)
            ->withStatusLabel('Custom')
            ->withTest(true)
            ->withInvoiceRecipients([LifecycleMessages::buyer()])
            ->withStatus(LifecycleStatus::PaymentSent)
            ->withStatusAt($issuedAt)
            ->withSender(LifecycleParty::platform('0003', 'PA-E Vendeur'))
            ->withIssuer(LifecycleMessages::buyerIssuer())
            ->withInvoice(new InvoiceReference('X1', InvoiceTypeCode::C_381, $issuedAt, null, new Identifier('1', '0002')));

        self::assertSame(CdvProfile::EinvoicingF2, $other->profile);
        self::assertNull($other->scope);
        self::assertNull($other->name);
        self::assertSame('Custom', $other->statusLabel);
        self::assertTrue($other->test);
        self::assertCount(1, $other->invoiceRecipients);
        self::assertSame(LifecycleStatus::PaymentSent, $other->status);
        self::assertSame('X1', $other->invoice->number);
        self::assertSame('0003', $other->sender->globalId?->value);
        self::assertSame($message->id, $other->id);
        self::assertSame($message->details, $other->details);
        self::assertSame(RegulatoryScope::Regulated, $message->scope);
    }

    public function test_uc1_collected_reproduces_the_fnfe_example_values(): void
    {
        $message = LifecycleMessages::uc1Collected();

        self::assertSame('UC1_F202500003_07-CDV-212_Encaissee', $message->name);
        self::assertSame('20250802100500', $message->issuedAt->format('YmdHis'));
        self::assertSame('20250802100000', $message->statusAt->format('YmdHis'));
        self::assertNull($message->sender->globalId);
        self::assertSame(PartyRole::Platform, $message->sender->role);
        self::assertSame('VENDEUR', $message->issuer->name);
        self::assertSame('0225', $message->recipients[0]->uri?->scheme);
        self::assertSame('200000008', $message->recipients[0]->uri->value);
        self::assertSame('PPF', $message->recipients[1]->name);
        self::assertSame('0000', $message->recipients[1]->globalId?->value);
        self::assertSame('0238', $message->recipients[1]->globalId->scheme);
        self::assertSame('12000', (string) $message->details[0]->characteristics[0]->amount);
        self::assertSame('20.00', (string) $message->details[0]->characteristics[0]->percent);
    }

    public function test_every_status_has_a_factory(): void
    {
        $statuses = array_map(static fn (LifecycleMessage $m) => $m->status, LifecycleMessages::all());

        foreach (LifecycleStatus::cases() as $status) {
            self::assertContains($status, $statuses, $status->value);
            self::assertSame($status, LifecycleMessages::forStatus($status)->status);
        }
    }

    public function test_factories_satisfy_the_structural_cdar_conditions(): void
    {
        foreach (LifecycleMessages::all() as $key => $message) {
            if ($message->acknowledgementType() === AcknowledgementType::Processing) {
                self::assertSame('0002', $message->issuer->globalId?->scheme, $key);
            } else {
                self::assertSame(PartyRole::Platform, $message->issuer->role, $key);
            }
            foreach ($message->recipients as $recipient) {
                if (! in_array($recipient->role, [PartyRole::Platform, PartyRole::PublicPortal], true)) {
                    self::assertNotNull($recipient->uri, $key);
                }
            }
            self::assertSame('0002', $message->invoice->issuer?->scheme, $key);
            if ($message->status->requiresReason()) {
                self::assertNotNull($message->details[0]->reason ?? null, $key);
            }
        }
    }

    private function approvedFor(string $number): LifecycleMessage
    {
        $invoice = new InvoiceReference($number, InvoiceTypeCode::C_380, LifecycleMessages::at('2025-07-01 15:10:00'), new DateTimeImmutable('2025-07-01'), null);

        return Lifecycle::status(LifecycleStatus::Approved, $invoice, LifecycleMessages::platform(), LifecycleMessages::buyerIssuer(), [LifecycleMessages::seller()], LifecycleMessages::at('2025-07-02 10:00:00'));
    }
}
