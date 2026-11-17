<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\France\CollectedAmount;
use IntrAgile\EInvoicing\France\Lifecycle\CharacteristicType;
use IntrAgile\EInvoicing\France\Lifecycle\InvoiceReference;
use IntrAgile\EInvoicing\France\Lifecycle\Lifecycle;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleMessage;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleParty;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleStatus;
use IntrAgile\EInvoicing\France\Lifecycle\PartyRole;
use IntrAgile\EInvoicing\France\Lifecycle\RequestedAction;
use IntrAgile\EInvoicing\France\Lifecycle\StatusCharacteristic;
use IntrAgile\EInvoicing\France\Lifecycle\StatusDetail;
use IntrAgile\EInvoicing\France\Lifecycle\StatusNote;
use IntrAgile\EInvoicing\France\Lifecycle\StatusReason;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\Model\Identifier;

/**
 * French lifecycle messages for tests, one or more per status, modelled on the FNFE use case 1
 * examples (invoice F202500003 of 1 July 2025 from seller 100000009 to buyer 200000008).
 *
 * Every factory builds a message meant to pass the French CDAR rules.
 */
final class LifecycleMessages
{
    public const SELLER_SIREN = '100000009';

    public const BUYER_SIREN = '200000008';

    public static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('Europe/Paris'));
    }

    /**
     * Invoice F202500003, deposited on 1 July 2025 at 15:10 French time.
     */
    public static function invoice(): InvoiceReference
    {
        return new InvoiceReference(
            'F202500003',
            InvoiceTypeCode::C_380,
            self::at('2025-07-01 15:10:00'),
            new DateTimeImmutable('2025-07-01'),
            Siren::identifier(self::SELLER_SIREN),
        );
    }

    /**
     * The seller as a recipient, with its status address.
     */
    public static function seller(): LifecycleParty
    {
        return LifecycleParty::seller(self::SELLER_SIREN, new Identifier(self::SELLER_SIREN.'_STATUTS', '0225'), 'VENDEUR');
    }

    /**
     * The buyer as a recipient, with its electronic address.
     */
    public static function buyer(): LifecycleParty
    {
        return LifecycleParty::buyer(self::BUYER_SIREN, new Identifier(self::BUYER_SIREN, '0225'), 'ACHETEUR');
    }

    /**
     * The seller as the issuer of a processing response.
     */
    public static function sellerIssuer(): LifecycleParty
    {
        return LifecycleParty::seller(self::SELLER_SIREN, name: 'VENDEUR');
    }

    /**
     * The buyer as the issuer of a processing response.
     */
    public static function buyerIssuer(): LifecycleParty
    {
        return LifecycleParty::buyer(self::BUYER_SIREN, name: 'ACHETEUR');
    }

    /**
     * A platform without identification, as in the FNFE examples.
     */
    public static function platform(): LifecycleParty
    {
        return new LifecycleParty(null, PartyRole::Platform);
    }

    /**
     * The FNFE example UC1_F202500003_07-CDV-212_Encaissee, value for value.
     */
    public static function uc1Collected(): LifecycleMessage
    {
        return Lifecycle::collected(
            self::invoice(),
            self::sellerIssuer(),
            self::buyer(),
            [new CollectedAmount(BigDecimal::of('12000'), BigDecimal::of('20.00'))],
            CurrencyCode::EUR,
            self::at('2025-08-02 10:00:00'),
        )
            ->withIssuedAt(self::at('2025-08-02 10:05:00'))
            ->withName('UC1_F202500003_07-CDV-212_Encaissee');
    }

    public static function deposited(): LifecycleMessage
    {
        return Lifecycle::deposited(self::invoice(), self::platform(), self::seller(), self::at('2025-07-01 15:10:00'))
            ->withIssuedAt(self::at('2025-07-01 15:15:00'));
    }

    public static function issued(): LifecycleMessage
    {
        return self::fromPlatform(LifecycleStatus::Issued, [self::seller()], '2025-07-01 15:20:00');
    }

    public static function received(): LifecycleMessage
    {
        return self::fromPlatform(LifecycleStatus::Received, [self::seller(), self::buyer()], '2025-07-01 16:10:00');
    }

    public static function madeAvailable(): LifecycleMessage
    {
        return self::fromPlatform(LifecycleStatus::MadeAvailable, [self::seller()], '2025-07-01 16:20:00');
    }

    public static function inProcess(): LifecycleMessage
    {
        return self::fromBuyer(LifecycleStatus::InProcess, '2025-07-01 17:00:00');
    }

    public static function approved(): LifecycleMessage
    {
        return self::fromBuyer(LifecycleStatus::Approved, '2025-07-02 09:00:00');
    }

    public static function partiallyApproved(): LifecycleMessage
    {
        return self::fromBuyer(LifecycleStatus::PartiallyApproved, '2025-07-02 09:00:00', [
            new StatusDetail(1, StatusReason::QTE_ERR, StatusReason::QTE_ERR->label(), characteristics: [
                new StatusCharacteristic(CharacteristicType::MAP, '10000.00', CurrencyCode::EUR, changed: true),
            ]),
        ]);
    }

    public static function disputed(): LifecycleMessage
    {
        return self::fromBuyer(LifecycleStatus::Disputed, '2025-07-01 18:00:00', [
            new StatusDetail(
                1,
                StatusReason::TX_TVA_ERR,
                StatusReason::TX_TVA_ERR->label(),
                action: RequestedAction::NIN,
                actionText: 'Créer une Facture Rectificative',
                characteristics: [
                    new StatusCharacteristic(CharacteristicType::DIV, percent: '10.00', changed: true, id: 'BT-152', name: 'Taux TVA'),
                    new StatusCharacteristic(CharacteristicType::DVA, percent: '20.00', changed: true, id: 'BT-152', name: 'Taux TVA'),
                ],
            ),
        ]);
    }

    public static function suspended(): LifecycleMessage
    {
        return self::fromBuyer(LifecycleStatus::Suspended, '2025-07-01 18:00:00', [
            new StatusDetail(1, StatusReason::JUSTIF_ABS, StatusReason::JUSTIF_ABS->label(), notes: [
                new StatusNote('Bon de livraison manquant', TextSubjectCode::AHN),
            ]),
        ]);
    }

    public static function completed(): LifecycleMessage
    {
        return self::fromSeller(LifecycleStatus::Completed, '2025-07-02 10:00:00');
    }

    public static function refused(): LifecycleMessage
    {
        return Lifecycle::refused(
            self::invoice(),
            self::buyerIssuer(),
            self::seller(),
            StatusReason::TX_TVA_ERR,
            'Le taux de TVA applicable est de 10 %.',
            self::at('2025-07-01 18:00:00'),
        )->withIssuedAt(self::at('2025-07-01 18:15:00'));
    }

    public static function paymentSent(): LifecycleMessage
    {
        return self::fromBuyer(LifecycleStatus::PaymentSent, '2025-07-30 10:00:00');
    }

    public static function collected(): LifecycleMessage
    {
        return Lifecycle::collected(
            self::invoice(),
            self::sellerIssuer(),
            self::buyer(),
            [new CollectedAmount('891.83', '20.00'), new CollectedAmount('108.18', '5.50')],
            CurrencyCode::EUR,
            self::at('2025-08-02 10:00:00'),
        );
    }

    public static function rejected(): LifecycleMessage
    {
        return Lifecycle::rejected(
            self::invoice(),
            self::platform(),
            [self::seller(), self::buyer(), LifecycleParty::ppf()],
            StatusReason::DOUBLON,
            'Facture identique reçue sur une autre adresse',
            self::at('2025-07-01 15:10:00'),
        )->withIssuedAt(self::at('2025-07-01 15:15:00'));
    }

    public static function endorsed(): LifecycleMessage
    {
        return self::fromBuyer(LifecycleStatus::Endorsed, '2025-07-02 09:00:00');
    }

    public static function cancelled(): LifecycleMessage
    {
        return self::fromSeller(LifecycleStatus::Cancelled, '2025-07-02 16:05:00');
    }

    public static function routingError(): LifecycleMessage
    {
        return self::fromPlatform(LifecycleStatus::RoutingError, [self::seller()], '2025-07-01 16:10:00', [
            new StatusDetail(1, StatusReason::ROUTAGE_ERR, StatusReason::ROUTAGE_ERR->label()),
        ]);
    }

    public static function directPaymentRequested(): LifecycleMessage
    {
        return self::fromSeller(LifecycleStatus::DirectPaymentRequested, '2025-07-03 10:00:00');
    }

    public static function factored(): LifecycleMessage
    {
        return self::fromSeller(LifecycleStatus::Factored, '2025-07-03 10:00:00');
    }

    public static function factoredConfidential(): LifecycleMessage
    {
        return self::fromSeller(LifecycleStatus::FactoredConfidential, '2025-07-03 10:00:00');
    }

    public static function paymentAccountChanged(): LifecycleMessage
    {
        return self::fromSeller(LifecycleStatus::PaymentAccountChanged, '2025-07-03 10:00:00');
    }

    public static function notFactored(): LifecycleMessage
    {
        return self::fromSeller(LifecycleStatus::NotFactored, '2025-07-03 10:00:00');
    }

    public static function inadmissible(): LifecycleMessage
    {
        return self::fromPlatform(LifecycleStatus::Inadmissible, [self::seller()], '2025-07-01 15:10:00', [
            new StatusDetail(1, StatusReason::IRR_SYNTAX, StatusReason::IRR_SYNTAX->label()),
        ]);
    }

    /**
     * The factory of a status.
     */
    public static function forStatus(LifecycleStatus $status): LifecycleMessage
    {
        return match ($status) {
            LifecycleStatus::Deposited => self::deposited(),
            LifecycleStatus::Issued => self::issued(),
            LifecycleStatus::Received => self::received(),
            LifecycleStatus::MadeAvailable => self::madeAvailable(),
            LifecycleStatus::InProcess => self::inProcess(),
            LifecycleStatus::Approved => self::approved(),
            LifecycleStatus::PartiallyApproved => self::partiallyApproved(),
            LifecycleStatus::Disputed => self::disputed(),
            LifecycleStatus::Suspended => self::suspended(),
            LifecycleStatus::Completed => self::completed(),
            LifecycleStatus::Refused => self::refused(),
            LifecycleStatus::PaymentSent => self::paymentSent(),
            LifecycleStatus::Collected => self::collected(),
            LifecycleStatus::Rejected => self::rejected(),
            LifecycleStatus::Endorsed => self::endorsed(),
            LifecycleStatus::Cancelled => self::cancelled(),
            LifecycleStatus::RoutingError => self::routingError(),
            LifecycleStatus::DirectPaymentRequested => self::directPaymentRequested(),
            LifecycleStatus::Factored => self::factored(),
            LifecycleStatus::FactoredConfidential => self::factoredConfidential(),
            LifecycleStatus::PaymentAccountChanged => self::paymentAccountChanged(),
            LifecycleStatus::NotFactored => self::notFactored(),
            LifecycleStatus::Inadmissible => self::inadmissible(),
        };
    }

    /**
     * Every status factory, keyed by status code and name ("212 Collected"), plus the FNFE example as "uc1Collected".
     *
     * @return array<string, LifecycleMessage>
     */
    public static function all(): array
    {
        $messages = [];
        foreach (LifecycleStatus::cases() as $status) {
            $messages[$status->value.' '.$status->name] = self::forStatus($status);
        }
        $messages['uc1Collected'] = self::uc1Collected();

        return $messages;
    }

    /**
     * A platform acknowledgement: sent and issued by the platform.
     *
     * @param  list<LifecycleParty>  $recipients
     * @param  list<StatusDetail>  $details
     */
    private static function fromPlatform(LifecycleStatus $status, array $recipients, string $at, array $details = []): LifecycleMessage
    {
        return Lifecycle::status($status, self::invoice(), self::platform(), self::platform(), $recipients, self::at($at), $details);
    }

    /**
     * A processing response issued by the buyer to the seller.
     *
     * @param  list<StatusDetail>  $details
     */
    private static function fromBuyer(LifecycleStatus $status, string $at, array $details = []): LifecycleMessage
    {
        return Lifecycle::status($status, self::invoice(), self::platform(), self::buyerIssuer(), [self::seller()], self::at($at), $details);
    }

    /**
     * A processing response issued by the seller to the buyer.
     *
     * @param  list<StatusDetail>  $details
     */
    private static function fromSeller(LifecycleStatus $status, string $at, array $details = []): LifecycleMessage
    {
        return Lifecycle::status($status, self::invoice(), self::platform(), self::sellerIssuer(), [self::buyer()], self::at($at), $details);
    }
}
