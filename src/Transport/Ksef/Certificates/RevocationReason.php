<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Certificates;

/**
 * Why a KSeF certificate is revoked (CertificateRevocationReason in the KSeF API 2.0).
 */
enum RevocationReason: string
{
    case Unspecified = 'Unspecified';

    /** The certificate was replaced by another one. */
    case Superseded = 'Superseded';

    /** The private key of the certificate was compromised. */
    case KeyCompromise = 'KeyCompromise';
}
