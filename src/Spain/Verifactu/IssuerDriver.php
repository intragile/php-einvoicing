<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

/**
 * How the records of an issuer reach the tax authority.
 */
enum IssuerDriver: string
{
    /** Direct submission to the AEAT web service with the issuer's certificate. */
    case Aeat = 'aeat';

    /** Submission through the B2Brouter service. */
    case B2Brouter = 'b2brouter';
}
