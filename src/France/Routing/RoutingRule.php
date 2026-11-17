<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France\Routing;

/**
 * Rule that decided the routing of an invoice.
 */
enum RoutingRule
{
    /** Domestic supply between taxable persons established in France. */
    case DomesticB2b;

    /** Supply to a taxable person outside France: intra-EU supply, export, foreign customer. */
    case InternationalB2b;

    /** Supply to a non-taxable person. */
    case B2c;

    /** Purchase by a French taxable person from a supplier not established in France. */
    case PurchaseFromForeignSupplier;

    /**
     * Domestic B2B supply whose recipient is absent from the directory; the issuer reports it as
     * a supply to a non-taxable person.
     */
    case AbsentRecipient;

    /** Outside both e-invoicing and e-reporting. */
    case OutOfScope;

    /** Invoice kept for archiving only, outside both e-invoicing and e-reporting. */
    case ArchiveOnly;

    public function legalBasis(): string
    {
        return match ($this) {
            self::DomesticB2b => 'CGI art. 289 bis',
            self::InternationalB2b => 'CGI art. 290 I-1°',
            self::B2c => 'CGI art. 290 I-2°',
            self::PurchaseFromForeignSupplier => 'CGI art. 290 I-3°',
            self::AbsentRecipient => 'BOFiP BOI-TVA-DECLA-20-30-50-10 §100',
            self::OutOfScope => 'Outside CGI arts. 289 bis, 290 and 290 A',
            self::ArchiveOnly => 'Outside CGI arts. 289 bis, 290 and 290 A (archive only)',
        };
    }
}
