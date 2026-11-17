<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\France\Lifecycle;

use IntrAgile\EInvoicing\France\Lifecycle\AcknowledgementType;
use IntrAgile\EInvoicing\France\Lifecycle\CdvProfile;
use IntrAgile\EInvoicing\France\Lifecycle\CharacteristicType;
use IntrAgile\EInvoicing\France\Lifecycle\LifecycleStatus;
use IntrAgile\EInvoicing\France\Lifecycle\PartyRole;
use IntrAgile\EInvoicing\France\Lifecycle\RegulatoryScope;
use IntrAgile\EInvoicing\France\Lifecycle\RequestedAction;
use IntrAgile\EInvoicing\France\Lifecycle\StatusReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CodeListsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, LifecycleStatus, string, AcknowledgementType, ?string}>
     */
    public static function statuses(): iterable
    {
        $platform = AcknowledgementType::Platform;
        $processing = AcknowledgementType::Processing;

        yield '200' => ['200', LifecycleStatus::Deposited, 'Déposée', $platform, '10'];
        yield '201' => ['201', LifecycleStatus::Issued, 'Emise par la plateforme', $platform, null];
        yield '202' => ['202', LifecycleStatus::Received, 'Reçue par la plateforme', $platform, '43'];
        yield '203' => ['203', LifecycleStatus::MadeAvailable, 'Mise à disposition', $platform, '48'];
        yield '204' => ['204', LifecycleStatus::InProcess, 'Prise en charge', $processing, '45'];
        yield '205' => ['205', LifecycleStatus::Approved, 'Approuvée', $processing, '1'];
        yield '206' => ['206', LifecycleStatus::PartiallyApproved, 'Approuvée partiellement', $processing, null];
        yield '207' => ['207', LifecycleStatus::Disputed, 'En litige', $processing, '46'];
        yield '208' => ['208', LifecycleStatus::Suspended, 'Suspendue', $processing, null];
        yield '209' => ['209', LifecycleStatus::Completed, 'Complétée', $processing, null];
        yield '210' => ['210', LifecycleStatus::Refused, 'Refusée', $processing, '50'];
        yield '211' => ['211', LifecycleStatus::PaymentSent, 'Paiement transmis', $processing, '47'];
        yield '212' => ['212', LifecycleStatus::Collected, 'Encaissée', $processing, '47'];
        yield '213' => ['213', LifecycleStatus::Rejected, 'Rejetée', $platform, '8'];
        yield '214' => ['214', LifecycleStatus::Endorsed, 'Visée', $processing, null];
        yield '220' => ['220', LifecycleStatus::Cancelled, 'Annulée', $processing, null];
        yield '221' => ['221', LifecycleStatus::RoutingError, 'Erreur de routage', $platform, null];
        yield '224' => ['224', LifecycleStatus::DirectPaymentRequested, 'Demande de paiement direct', $processing, null];
        yield '225' => ['225', LifecycleStatus::Factored, 'Affacturée', $processing, null];
        yield '226' => ['226', LifecycleStatus::FactoredConfidential, 'Affacturée confidentiel', $processing, null];
        yield '227' => ['227', LifecycleStatus::PaymentAccountChanged, 'Changement de compte à payer', $processing, null];
        yield '228' => ['228', LifecycleStatus::NotFactored, 'Non affacturée', $processing, null];
        yield '501' => ['501', LifecycleStatus::Inadmissible, 'Irrecevable', $platform, null];
    }

    #[DataProvider('statuses')]
    public function test_status_codes_and_labels(string $code, LifecycleStatus $status, string $label, AcknowledgementType $type, ?string $statusCode): void
    {
        self::assertSame($status, LifecycleStatus::from($code));
        self::assertSame($label, $status->label());
        self::assertSame($type, $status->acknowledgementType());
        self::assertSame($statusCode, $status->statusCode());
    }

    public function test_status_table_covers_every_case(): void
    {
        self::assertCount(count(LifecycleStatus::cases()), iterator_to_array(self::statuses()));
    }

    public function test_mandatory_statuses(): void
    {
        $mandatory = array_values(array_filter(LifecycleStatus::cases(), static fn (LifecycleStatus $s) => $s->isMandatory()));

        self::assertSame([LifecycleStatus::Deposited, LifecycleStatus::Refused, LifecycleStatus::Collected, LifecycleStatus::Rejected], $mandatory);
    }

    public function test_statuses_requiring_a_reason(): void
    {
        $codes = array_values(array_map(
            static fn (LifecycleStatus $s) => $s->value,
            array_filter(LifecycleStatus::cases(), static fn (LifecycleStatus $s) => $s->requiresReason()),
        ));

        self::assertSame(['206', '207', '208', '210', '213', '501'], $codes);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ppfFlowCodes(): iterable
    {
        foreach (['250', '251', '300', '301', '400', '401', '500', '601'] as $code) {
            yield $code => [$code];
        }
    }

    #[DataProvider('ppfFlowCodes')]
    public function test_ppf_flow_codes_are_not_lifecycle_statuses(string $code): void
    {
        self::assertNull(LifecycleStatus::tryFrom($code));
    }

    public function test_206_is_partially_approved_and_208_suspended(): void
    {
        self::assertSame(LifecycleStatus::PartiallyApproved, LifecycleStatus::from('206'));
        self::assertSame(LifecycleStatus::Suspended, LifecycleStatus::from('208'));
    }

    public function test_acknowledgement_types(): void
    {
        self::assertSame('23', AcknowledgementType::Processing->value);
        self::assertSame('305', AcknowledgementType::Platform->value);
    }

    public function test_cdv_profiles(): void
    {
        self::assertSame('urn.cpro.gouv.fr:1p0:CDV:invoice', CdvProfile::Invoice->value);
        self::assertSame('urn.cpro.gouv.fr:1p0:CDV:einvoicingF2', CdvProfile::EinvoicingF2->value);
    }

    public function test_regulatory_scopes(): void
    {
        self::assertSame(
            ['REGULATED', 'NON_REGULATED', 'B2C', 'B2CINT', 'B2BINT', 'OUTOFSCOPE'],
            array_map(static fn (RegulatoryScope $s) => $s->value, RegulatoryScope::cases()),
        );
        self::assertSame(RegulatoryScope::NonRegulated, RegulatoryScope::from('NON_REGULATED'));
        self::assertSame(RegulatoryScope::B2cInt, RegulatoryScope::from('B2CINT'));
    }

    public function test_requested_actions_characteristic_types_and_roles(): void
    {
        self::assertSame(['NOA', 'PIN', 'NIN', 'CNF', 'CNP', 'CNA', 'OTH'], array_map(static fn (RequestedAction $a) => $a->value, RequestedAction::cases()));
        self::assertSame(
            ['MEN', 'MPA', 'RAP', 'ESC', 'RAB', 'REM', 'MAP', 'MAPTTC', 'MNA', 'MNATTC', 'CBB', 'DIV', 'DVA', 'MAJ'],
            array_map(static fn (CharacteristicType $t) => $t->value, CharacteristicType::cases()),
        );
        self::assertSame(
            ['BY', 'AB', 'DL', 'SE', 'SR', 'WK', 'PE', 'PR', 'II', 'IV', 'DFH'],
            array_map(static fn (PartyRole $r) => $r->value, PartyRole::cases()),
        );
        self::assertSame(PartyRole::Platform, PartyRole::from('WK'));
        self::assertSame(PartyRole::Seller, PartyRole::from('SE'));
    }

    /**
     * @return iterable<string, array{StatusReason, ?string}>
     */
    public static function reasonLabels(): iterable
    {
        yield 'A7_04' => [StatusReason::TX_TVA_ERR, 'Taux de TVA erroné'];
        yield 'A7_04 with trailing space in the code' => [StatusReason::MONTANTTOTAL_ERR, 'Montant Total Erroné'];
        yield 'A7_04 apostrophe' => [StatusReason::ADR_ERR, "L'adresse de facturation électronique erronée"];
        yield 'A7_04 B2G' => [StatusReason::CONTACT_ACHTR, 'Autres : contacter votre acheteur'];
        yield 'DSE Tableau 11' => [StatusReason::REJ_SEMAN, 'Contrôle du format sémantique'];
        yield 'DSE Tableau 11 typographic apostrophe' => [StatusReason::REJ_UNI, 'Contrôle d’unicité'];
        yield 'DSE Tableau 3' => [StatusReason::IRR_EXT_DOC, 'Contrôle de type et extension des pièces jointes'];
        yield 'unlabelled REJ_ADR' => [StatusReason::REJ_ADR, null];
        yield 'unlabelled REJ_CONT_B2G' => [StatusReason::REJ_CONT_B2G, null];
        yield 'unlabelled REJ_REF_PJ' => [StatusReason::REJ_REF_PJ, null];
        yield 'unlabelled REJ_ASS_PJ' => [StatusReason::REJ_ASS_PJ, null];
        yield 'unlabelled NON_TRANSMISE' => [StatusReason::NON_TRANSMISE, null];
        yield 'unlabelled CMD_ERR' => [StatusReason::CMD_ERR, null];
        yield 'unlabelled RETRAIT_MAN_SERV' => [StatusReason::RETRAIT_MAN_SERV, null];
    }

    #[DataProvider('reasonLabels')]
    public function test_reason_labels(StatusReason $reason, ?string $label): void
    {
        self::assertSame($label, $reason->label());
    }

    public function test_only_the_listed_reasons_lack_a_label(): void
    {
        $unlabelled = array_values(array_map(
            static fn (StatusReason $r) => $r->value,
            array_filter(StatusReason::cases(), static fn (StatusReason $r) => $r->label() === null),
        ));
        sort($unlabelled);

        self::assertCount(51, StatusReason::cases());
        self::assertSame(['CMD_ERR', 'NON_TRANSMISE', 'REJ_ADR', 'REJ_ASS_PJ', 'REJ_CONT_B2G', 'REJ_REF_PJ', 'RETRAIT_MAN_SERV'], $unlabelled);
    }
}
