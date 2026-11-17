<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Routing;

use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\EReporting\DeclarantRole;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\France\Treatment;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;

/**
 * Decides, without network access, whether an invoice is e-invoiced or e-reported in France and
 * how its collection is reported.
 *
 * France for routing means metropolitan France, Guadeloupe, Martinique and Réunion (FR, GP, MQ,
 * RE): supplies between them are e-invoiced. Other territories (French Guiana, Mayotte, Monaco,
 * the overseas collectivities) are treated as foreign, which is a known limitation.
 *
 * A party's location is its postal address country (BT-40, BT-55), else the prefix of its VAT
 * identifier (BT-31, BT-48). A party whose location cannot be determined counts as French.
 */
final class Router
{
    /** Countries that make up France for routing. */
    private const TERRITORY = ['FR', 'GP', 'MQ', 'RE'];

    /** UNTDID 2005 VAT point date code for the invoice date, used for the VAT-on-debits option. */
    private const VAT_ON_DEBITS = '3';

    public function __construct(private readonly Annuaire $annuaire) {}

    /**
     * Routes an invoice from the issuer's (seller's) side. The first matching rule wins:
     * treatment note ARCHIVEONLY or OUTOFSCOPE; seller outside France; buyer without SIREN and VAT
     * identifier, or treatment B2C/B2CINT; buyer outside France, treatment B2BINT or a non-French
     * buyer VAT identifier; otherwise a domestic B2B supply decided by the buyer's directory status.
     */
    public function route(Invoice $invoice): RoutingDecision
    {
        $treatment = self::treatment($invoice);

        if ($treatment === Treatment::ARCHIVEONLY) {
            return new RoutingDecision(Channel::None, PaymentReporting::None, RoutingRule::ArchiveOnly, null);
        }
        if ($treatment === Treatment::OUTOFSCOPE || ! self::inTerritory($invoice->seller)) {
            return new RoutingDecision(Channel::None, PaymentReporting::None, RoutingRule::OutOfScope, null);
        }

        $buyer = $invoice->buyer;
        $siren = self::siren($buyer);
        $vatIdentifier = self::vatIdentifier($buyer);

        if (($siren === null && $vatIdentifier === null) || $treatment === Treatment::B2C || $treatment === Treatment::B2CINT) {
            return self::decision($invoice, Channel::AggregatedTransactionReport, RoutingRule::B2c, DeclarantRole::Seller);
        }

        $buyerCountry = $buyer->address?->country->value;
        if (
            ($buyerCountry !== null && ! in_array($buyerCountry, self::TERRITORY, true))
            || $treatment === Treatment::B2BINT
            || ($vatIdentifier !== null && ! str_starts_with($vatIdentifier, 'FR'))
        ) {
            return self::decision($invoice, Channel::TransactionReport, RoutingRule::InternationalB2b, DeclarantRole::Seller);
        }

        $status = $siren === null ? AnnuaireStatus::Unknown : $this->annuaire->status($siren);

        return match ($status) {
            AnnuaireStatus::NotListed, AnnuaireStatus::Inactive => self::decision(
                $invoice,
                Channel::AggregatedTransactionReport,
                RoutingRule::AbsentRecipient,
                DeclarantRole::Seller,
            ),
            AnnuaireStatus::Active => self::decision($invoice, Channel::EInvoice, RoutingRule::DomesticB2b, null),
            AnnuaireStatus::Unknown => self::decision($invoice, Channel::EInvoice, RoutingRule::DomesticB2b, null, true),
        };
    }

    /**
     * Routes a received invoice from the buyer's side. Only a purchase by a buyer in France from a
     * seller outside it needs a report; receiving a domestic e-invoice needs no action.
     */
    public function routePurchase(Invoice $invoice): RoutingDecision
    {
        if (! self::inTerritory($invoice->seller) && self::inTerritory($invoice->buyer)) {
            return new RoutingDecision(
                Channel::TransactionReport,
                PaymentReporting::None,
                RoutingRule::PurchaseFromForeignSupplier,
                DeclarantRole::Buyer,
            );
        }

        return new RoutingDecision(Channel::None, PaymentReporting::None, RoutingRule::OutOfScope, null);
    }

    private static function decision(
        Invoice $invoice,
        Channel $channel,
        RoutingRule $rule,
        ?DeclarantRole $declarant,
        bool $needsDirectoryCheck = false,
    ): RoutingDecision {
        return new RoutingDecision($channel, self::payment($invoice, $channel), $rule, $declarant, $needsDirectoryCheck);
    }

    /**
     * Payment reporting applies to services (BT-23 starting with S or M) whose VAT is due on
     * collection: no VAT-on-debits option (BT-8) and no reverse-charge VAT breakdown.
     */
    private static function payment(Invoice $invoice, Channel $channel): PaymentReporting
    {
        $framework = $invoice->processControl->businessProcessType ?? '';
        $services = str_starts_with($framework, 'S') || str_starts_with($framework, 'M');
        if (! $services || $invoice->taxPointDateCode === self::VAT_ON_DEBITS) {
            return PaymentReporting::None;
        }
        foreach ($invoice->vatBreakdown as $breakdown) {
            if ($breakdown->category === VatCategory::ReverseCharge) {
                return PaymentReporting::None;
            }
        }

        return match ($channel) {
            Channel::EInvoice => PaymentReporting::CollectedStatus,
            Channel::TransactionReport => PaymentReporting::PaymentReport,
            Channel::AggregatedTransactionReport => PaymentReporting::AggregatedPaymentReport,
            Channel::None => PaymentReporting::None,
        };
    }

    /** Treatment from the first note with subject code BAR whose content is a known treatment. */
    private static function treatment(Invoice $invoice): ?Treatment
    {
        foreach ($invoice->notes as $note) {
            if ($note->subjectCode === TextSubjectCode::BAR) {
                $treatment = Treatment::tryFrom(trim($note->text));
                if ($treatment !== null) {
                    return $treatment;
                }
            }
        }

        return null;
    }

    private static function siren(Party $party): ?string
    {
        $registration = $party->legalRegistration;

        return $registration !== null && $registration->scheme === Siren::SCHEME && $registration->value !== ''
            ? $registration->value
            : null;
    }

    private static function vatIdentifier(Party $party): ?string
    {
        $vatIdentifier = strtoupper(trim($party->vatIdentifier ?? ''));

        return $vatIdentifier === '' ? null : $vatIdentifier;
    }

    private static function inTerritory(Party $party): bool
    {
        $country = $party->address?->country->value;
        if ($country === null) {
            $vatIdentifier = self::vatIdentifier($party);
            $country = $vatIdentifier !== null && preg_match('/\A[A-Z]{2}/', $vatIdentifier) === 1
                ? substr($vatIdentifier, 0, 2)
                : null;
        }

        return $country === null || in_array($country, self::TERRITORY, true);
    }
}
