<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

/**
 * The kind of client certificate used with the AEAT web services.
 */
enum CertificateKind: string
{
    /** A certificate issued to a natural person or a representative. */
    case Personal = 'personal';

    /** An electronic seal certificate of a legal entity. */
    case Seal = 'seal';
}
