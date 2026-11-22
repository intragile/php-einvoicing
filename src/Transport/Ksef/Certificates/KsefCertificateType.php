<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Certificates;

/**
 * The type of a KSeF certificate (KsefCertificateType in the KSeF API 2.0).
 */
enum KsefCertificateType: string
{
    /** Authenticates in KSeF, through a XAdES-signed authentication request. */
    case Authentication = 'Authentication';

    /** Only signs the issuer's link (KOD II) on invoices issued in offline mode. */
    case Offline = 'Offline';
}
