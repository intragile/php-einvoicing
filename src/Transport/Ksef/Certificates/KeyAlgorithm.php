<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Certificates;

/**
 * The key pair generated for a KSeF certificate. KSeF accepts no other key types or sizes.
 */
enum KeyAlgorithm
{
    /** ECDSA on NIST P-256 (secp256r1), the type the Ministry of Finance recommends. */
    case Ec;

    /** RSA with a 2048-bit modulus. */
    case Rsa;
}
