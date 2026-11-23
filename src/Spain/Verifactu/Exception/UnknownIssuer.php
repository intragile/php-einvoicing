<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu\Exception;

use RuntimeException;

/**
 * An issuer NIF that the resolver has no data for.
 */
final class UnknownIssuer extends RuntimeException {}
