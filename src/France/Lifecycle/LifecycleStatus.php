<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Lifecycle;

/**
 * Status of an invoice in its lifecycle (MDT-105, MDT-115).
 *
 * Labels of 200 to 213 are those of the DSE "Les statuts d'une facture" table; 220 and 221 follow
 * the FNFE examples and rule messages, and 501 the status list of the external specifications.
 * The labels of 214 and 224 to 228 are unverified (AFNOR XP Z12-012 not available).
 *
 * The codes the public portal emits for other flows (250, 251, 300, 301, 400, 401, 500, 601) are
 * not invoice statuses and are not cases of this enum.
 */
enum LifecycleStatus: string
{
    case Deposited = '200';
    case Issued = '201';
    case Received = '202';
    case MadeAvailable = '203';
    case InProcess = '204';
    case Approved = '205';
    case PartiallyApproved = '206';
    case Disputed = '207';
    case Suspended = '208';
    case Completed = '209';
    case Refused = '210';
    case PaymentSent = '211';
    case Collected = '212';
    case Rejected = '213';
    case Endorsed = '214';
    case Cancelled = '220';
    case RoutingError = '221';
    case DirectPaymentRequested = '224';
    case Factored = '225';
    case FactoredConfidential = '226';
    case PaymentAccountChanged = '227';
    case NotFactored = '228';
    case Inadmissible = '501';

    /**
     * The French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Deposited => 'Déposée',
            self::Issued => 'Emise par la plateforme',
            self::Received => 'Reçue par la plateforme',
            self::MadeAvailable => 'Mise à disposition',
            self::InProcess => 'Prise en charge',
            self::Approved => 'Approuvée',
            self::PartiallyApproved => 'Approuvée partiellement',
            self::Disputed => 'En litige',
            self::Suspended => 'Suspendue',
            self::Completed => 'Complétée',
            self::Refused => 'Refusée',
            self::PaymentSent => 'Paiement transmis',
            self::Collected => 'Encaissée',
            self::Rejected => 'Rejetée',
            self::Endorsed => 'Visée',
            self::Cancelled => 'Annulée',
            self::RoutingError => 'Erreur de routage',
            self::DirectPaymentRequested => 'Demande de paiement direct',
            self::Factored => 'Affacturée',
            self::FactoredConfidential => 'Affacturée confidentiel',
            self::PaymentAccountChanged => 'Changement de compte à payer',
            self::NotFactored => 'Non affacturée',
            self::Inadmissible => 'Irrecevable',
        };
    }

    /**
     * Whether the status must be sent to the public portal.
     */
    public function isMandatory(): bool
    {
        return match ($this) {
            self::Deposited, self::Refused, self::Collected, self::Rejected => true,
            default => false,
        };
    }

    /**
     * Whether a message with this status must give a reason (MDT-113).
     */
    public function requiresReason(): bool
    {
        return match ($this) {
            self::PartiallyApproved, self::Disputed, self::Suspended, self::Refused, self::Rejected, self::Inadmissible => true,
            default => false,
        };
    }

    /**
     * The message type (MDT-77) that carries this status: a platform acknowledgement for the
     * transmission-phase statuses, a processing response for the others.
     */
    public function acknowledgementType(): AcknowledgementType
    {
        return match ($this) {
            self::Deposited, self::Issued, self::Received, self::MadeAvailable,
            self::Rejected, self::RoutingError, self::Inadmissible => AcknowledgementType::Platform,
            default => AcknowledgementType::Processing,
        };
    }

    /**
     * The referenced document status code (MDT-88) the FNFE examples write with this status, or
     * null when they write none.
     */
    public function statusCode(): ?string
    {
        return match ($this) {
            self::Deposited => '10',
            self::Received => '43',
            self::MadeAvailable => '48',
            self::InProcess => '45',
            self::Approved => '1',
            self::Disputed => '46',
            self::Refused => '50',
            self::PaymentSent, self::Collected => '47',
            self::Rejected => '8',
            default => null,
        };
    }
}
