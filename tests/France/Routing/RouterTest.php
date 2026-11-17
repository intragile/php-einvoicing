<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France\Routing;

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\TextSubjectCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\France\EReporting\DeclarantRole;
use IntrAgile\EInvoicing\France\France;
use IntrAgile\EInvoicing\France\Routing\Annuaire;
use IntrAgile\EInvoicing\France\Routing\AnnuaireStatus;
use IntrAgile\EInvoicing\France\Routing\Channel;
use IntrAgile\EInvoicing\France\Routing\PaymentReporting;
use IntrAgile\EInvoicing\France\Routing\Router;
use IntrAgile\EInvoicing\France\Routing\RoutingDecision;
use IntrAgile\EInvoicing\France\Routing\RoutingRule;
use IntrAgile\EInvoicing\France\Routing\StaticAnnuaire;
use IntrAgile\EInvoicing\France\Treatment;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Note;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class RouterTest extends TestCase
{
    public function test_domestic_b2b_goods_is_e_invoiced(): void
    {
        $decision = self::router()->route(FrenchInvoices::domesticB2b());

        self::assertDecision(Channel::EInvoice, PaymentReporting::None, RoutingRule::DomesticB2b, null, $decision);
    }

    public function test_domestic_b2b_services_on_collection_needs_collected_status(): void
    {
        $decision = self::router()->route(FrenchInvoices::services());

        self::assertDecision(Channel::EInvoice, PaymentReporting::CollectedStatus, RoutingRule::DomesticB2b, null, $decision);
    }

    public function test_vat_on_debits_services_need_no_payment_reporting(): void
    {
        $decision = self::router()->route(FrenchInvoices::vatOnDebits());

        self::assertDecision(Channel::EInvoice, PaymentReporting::None, RoutingRule::DomesticB2b, null, $decision);
    }

    public function test_intra_eu_b2b_is_transaction_report(): void
    {
        $decision = self::router()->route(self::intraEuB2b(FrenchInvoices::domesticB2b()));

        self::assertDecision(Channel::TransactionReport, PaymentReporting::None, RoutingRule::InternationalB2b, DeclarantRole::Seller, $decision);
    }

    public function test_intra_eu_vat_number_of_a_french_buyer_is_transaction_report(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $invoice = $invoice->withBuyer(self::party($invoice->buyer, vatIdentifier: 'DE123456789'));

        $decision = self::router()->route($invoice);

        self::assertDecision(Channel::TransactionReport, PaymentReporting::None, RoutingRule::InternationalB2b, DeclarantRole::Seller, $decision);
    }

    public function test_export_b2b_services_need_payment_report(): void
    {
        $invoice = FrenchInvoices::services();
        $invoice = self::withTreatment($invoice->withBuyer(self::party(
            $invoice->buyer,
            legalRegistration: null,
            vatIdentifier: 'CHE123456789',
            country: CountryCode::CH,
        )), Treatment::B2BINT);

        $decision = self::router()->route($invoice);

        self::assertDecision(Channel::TransactionReport, PaymentReporting::PaymentReport, RoutingRule::InternationalB2b, DeclarantRole::Seller, $decision);
    }

    public function test_reverse_charge_needs_no_payment_report(): void
    {
        $invoice = self::intraEuB2b(FrenchInvoices::services());
        $invoice = TotalsCalculator::complete($invoice->withVatBreakdown([])->withLines(array_map(
            static fn (InvoiceLine $line): InvoiceLine => $line->withVat(new LineVat(VatCategory::ReverseCharge, '0')),
            $invoice->lines,
        )));

        $decision = self::router()->route($invoice);

        self::assertDecision(Channel::TransactionReport, PaymentReporting::None, RoutingRule::InternationalB2b, DeclarantRole::Seller, $decision);
    }

    public function test_b2c_is_aggregated(): void
    {
        $decision = self::router()->route(self::b2c(FrenchInvoices::domesticB2b()));

        self::assertDecision(Channel::AggregatedTransactionReport, PaymentReporting::None, RoutingRule::B2c, DeclarantRole::Seller, $decision);
    }

    public function test_b2c_services_need_aggregated_payment_report(): void
    {
        $decision = self::router()->route(self::b2c(FrenchInvoices::services()));

        self::assertDecision(Channel::AggregatedTransactionReport, PaymentReporting::AggregatedPaymentReport, RoutingRule::B2c, DeclarantRole::Seller, $decision);
    }

    public function test_buyer_without_identifiers_is_b2c_whatever_the_treatment_note(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $invoice = $invoice->withBuyer(self::party($invoice->buyer, legalRegistration: null, vatIdentifier: null));

        $decision = self::router()->route($invoice);

        self::assertDecision(Channel::AggregatedTransactionReport, PaymentReporting::None, RoutingRule::B2c, DeclarantRole::Seller, $decision);
    }

    public function test_purchase_from_foreign_supplier_is_buyer_report(): void
    {
        $invoice = FrenchInvoices::services();
        $invoice = $invoice->withSeller(self::party(
            $invoice->seller,
            identifiers: [],
            legalRegistration: null,
            vatIdentifier: 'DE123456789',
            country: CountryCode::DE,
        ));
        $router = self::router();

        self::assertDecision(
            Channel::TransactionReport,
            PaymentReporting::None,
            RoutingRule::PurchaseFromForeignSupplier,
            DeclarantRole::Buyer,
            $router->routePurchase($invoice),
        );
        self::assertDecision(Channel::None, PaymentReporting::None, RoutingRule::OutOfScope, null, $router->route($invoice));
        self::assertDecision(
            Channel::None,
            PaymentReporting::None,
            RoutingRule::OutOfScope,
            null,
            $router->routePurchase(FrenchInvoices::domesticB2b()),
        );
    }

    public function test_absent_buyer_falls_back_to_b2c_report(): void
    {
        $router = new Router(new StaticAnnuaire([FrenchInvoices::BUYER_SIREN => AnnuaireStatus::NotListed]));

        self::assertDecision(
            Channel::AggregatedTransactionReport,
            PaymentReporting::None,
            RoutingRule::AbsentRecipient,
            DeclarantRole::Seller,
            $router->route(FrenchInvoices::domesticB2b()),
        );
        self::assertDecision(
            Channel::AggregatedTransactionReport,
            PaymentReporting::AggregatedPaymentReport,
            RoutingRule::AbsentRecipient,
            DeclarantRole::Seller,
            $router->route(FrenchInvoices::services()),
        );
    }

    public function test_inactive_buyer_falls_back_to_b2c_report(): void
    {
        $router = new Router(new StaticAnnuaire([FrenchInvoices::BUYER_SIREN => AnnuaireStatus::Inactive]));

        self::assertDecision(
            Channel::AggregatedTransactionReport,
            PaymentReporting::None,
            RoutingRule::AbsentRecipient,
            DeclarantRole::Seller,
            $router->route(FrenchInvoices::domesticB2b()),
        );
    }

    public function test_unknown_directory_status_keeps_e_invoice(): void
    {
        $decision = (new Router(new StaticAnnuaire))->route(FrenchInvoices::domesticB2b());

        self::assertDecision(Channel::EInvoice, PaymentReporting::None, RoutingRule::DomesticB2b, null, $decision, needsDirectoryCheck: true);
    }

    public function test_domestic_buyer_without_siren_is_unknown_without_a_lookup(): void
    {
        $annuaire = new class implements Annuaire
        {
            /** @var list<string> */
            public array $lookups = [];

            public function status(string $siren): AnnuaireStatus
            {
                $this->lookups[] = $siren;

                return AnnuaireStatus::NotListed;
            }
        };
        $invoice = FrenchInvoices::domesticB2b();
        $invoice = $invoice->withBuyer(self::party($invoice->buyer, legalRegistration: new Identifier('12345', '0009')));

        $decision = (new Router($annuaire))->route($invoice);

        self::assertDecision(Channel::EInvoice, PaymentReporting::None, RoutingRule::DomesticB2b, null, $decision, needsDirectoryCheck: true);
        self::assertSame([], $annuaire->lookups);
    }

    public function test_mainland_to_reunion_is_e_invoiced(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $invoice = $invoice->withBuyer(self::party($invoice->buyer, country: CountryCode::RE));

        self::assertDecision(Channel::EInvoice, PaymentReporting::None, RoutingRule::DomesticB2b, null, self::router()->route($invoice));

        $invoice = FrenchInvoices::domesticB2b();
        $invoice = $invoice->withSeller(self::party($invoice->seller, country: CountryCode::GP));

        self::assertDecision(Channel::EInvoice, PaymentReporting::None, RoutingRule::DomesticB2b, null, self::router()->route($invoice));
    }

    public function test_territories_outside_routing_france_are_foreign(): void
    {
        $invoice = FrenchInvoices::domesticB2b();
        $invoice = $invoice->withBuyer(self::party($invoice->buyer, country: CountryCode::GF));

        self::assertDecision(Channel::TransactionReport, PaymentReporting::None, RoutingRule::InternationalB2b, DeclarantRole::Seller, self::router()->route($invoice));
    }

    public function test_bar_note_overrides_derivation(): void
    {
        $router = self::router();

        self::assertDecision(
            Channel::TransactionReport,
            PaymentReporting::None,
            RoutingRule::InternationalB2b,
            DeclarantRole::Seller,
            $router->route(self::withTreatment(FrenchInvoices::domesticB2b(), Treatment::B2BINT)),
        );
        self::assertDecision(
            Channel::AggregatedTransactionReport,
            PaymentReporting::None,
            RoutingRule::B2c,
            DeclarantRole::Seller,
            $router->route(self::withTreatment(FrenchInvoices::domesticB2b(), Treatment::B2C)),
        );
        self::assertDecision(
            Channel::AggregatedTransactionReport,
            PaymentReporting::None,
            RoutingRule::B2c,
            DeclarantRole::Seller,
            $router->route(self::withTreatment(FrenchInvoices::domesticB2b(), Treatment::B2CINT)),
        );
    }

    public function test_archive_only_and_out_of_scope(): void
    {
        $router = self::router();

        self::assertDecision(
            Channel::None,
            PaymentReporting::None,
            RoutingRule::ArchiveOnly,
            null,
            $router->route(self::withTreatment(FrenchInvoices::services(), Treatment::ARCHIVEONLY)),
        );
        self::assertDecision(
            Channel::None,
            PaymentReporting::None,
            RoutingRule::OutOfScope,
            null,
            $router->route(self::withTreatment(FrenchInvoices::services(), Treatment::OUTOFSCOPE)),
        );
    }

    public function test_router_makes_no_network_call(): void
    {
        $constructor = (new ReflectionClass(Router::class))->getConstructor();
        self::assertNotNull($constructor);
        $parameters = $constructor->getParameters();
        self::assertCount(1, $parameters);
        $type = $parameters[0]->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame(Annuaire::class, $type->getName());
        self::assertTrue((new ReflectionClass(Annuaire::class))->isInterface());

        self::assertSame(AnnuaireStatus::Unknown, (new StaticAnnuaire)->status(FrenchInvoices::BUYER_SIREN));
        self::assertSame(
            AnnuaireStatus::Active,
            (new StaticAnnuaire([FrenchInvoices::BUYER_SIREN => AnnuaireStatus::Active]))->status(FrenchInvoices::BUYER_SIREN),
        );
        self::assertSame(Channel::EInvoice, self::router()->route(FrenchInvoices::domesticB2b())->channel);
    }

    public function test_every_rule_names_its_legal_basis(): void
    {
        self::assertSame('CGI art. 289 bis', RoutingRule::DomesticB2b->legalBasis());
        self::assertSame('CGI art. 290 I-1°', RoutingRule::InternationalB2b->legalBasis());
        self::assertSame('CGI art. 290 I-2°', RoutingRule::B2c->legalBasis());
        self::assertSame('CGI art. 290 I-3°', RoutingRule::PurchaseFromForeignSupplier->legalBasis());
        self::assertSame('BOFiP BOI-TVA-DECLA-20-30-50-10 §100', RoutingRule::AbsentRecipient->legalBasis());

        foreach (RoutingRule::cases() as $rule) {
            self::assertNotSame('', $rule->legalBasis());
        }
    }

    private static function router(): Router
    {
        return new Router(new StaticAnnuaire([FrenchInvoices::BUYER_SIREN => AnnuaireStatus::Active]));
    }

    private static function assertDecision(
        Channel $channel,
        PaymentReporting $payment,
        RoutingRule $rule,
        ?DeclarantRole $declarant,
        RoutingDecision $decision,
        bool $needsDirectoryCheck = false,
    ): void {
        self::assertSame(
            [$channel, $payment, $rule, $declarant, $needsDirectoryCheck],
            [$decision->channel, $decision->payment, $decision->rule, $decision->declarant, $decision->needsDirectoryCheck],
        );
    }

    private static function intraEuB2b(Invoice $invoice): Invoice
    {
        return self::withTreatment($invoice->withBuyer(self::party(
            $invoice->buyer,
            legalRegistration: null,
            vatIdentifier: 'DE123456789',
            country: CountryCode::DE,
        )), Treatment::B2BINT);
    }

    private static function b2c(Invoice $invoice): Invoice
    {
        return self::withTreatment(
            $invoice->withBuyer(new Party(name: 'JEAN DUPONT', address: $invoice->buyer->address)),
            Treatment::B2C,
        );
    }

    private static function withTreatment(Invoice $invoice, Treatment $treatment): Invoice
    {
        $notes = array_values(array_filter(
            $invoice->notes,
            static fn (Note $note): bool => $note->subjectCode !== TextSubjectCode::BAR,
        ));

        return $invoice->withNotes([...$notes, France::treatmentNote($treatment)]);
    }

    /**
     * Copies a party, replacing the given fields; a null country keeps the address.
     *
     * @param  list<Identifier>|false  $identifiers
     */
    private static function party(
        Party $party,
        array|false $identifiers = false,
        Identifier|false|null $legalRegistration = false,
        string|false|null $vatIdentifier = false,
        ?CountryCode $country = null,
    ): Party {
        $address = $party->address;
        if ($country !== null) {
            $address = new PostalAddress(country: $country, line1: $address?->line1, city: $address?->city, postCode: $address?->postCode);
        }

        return new Party(
            name: $party->name,
            tradingName: $party->tradingName,
            identifiers: $identifiers === false ? $party->identifiers : $identifiers,
            legalRegistration: $legalRegistration === false ? $party->legalRegistration : $legalRegistration,
            vatIdentifier: $vatIdentifier === false ? $party->vatIdentifier : $vatIdentifier,
            taxRegistration: $party->taxRegistration,
            additionalLegalInformation: $party->additionalLegalInformation,
            electronicAddress: $party->electronicAddress,
            address: $address,
            contact: $party->contact,
        );
    }
}
