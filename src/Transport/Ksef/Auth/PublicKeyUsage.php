<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Auth;

/**
 * What a Ministry of Finance public key may be used for (PublicKeyCertificateUsage in the KSeF
 * API): encrypting a KSeF token during authentication, or the symmetric key of a session or
 * export.
 */
enum PublicKeyUsage: string
{
    case KsefTokenEncryption = 'KsefTokenEncryption';
    case SymmetricKeyEncryption = 'SymmetricKeyEncryption';
}
