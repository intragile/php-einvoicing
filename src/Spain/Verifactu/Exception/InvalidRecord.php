<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu\Exception;

use InvalidArgumentException;

/**
 * A VERI*FACTU billing record that violates the AEAT specification.
 */
final class InvalidRecord extends InvalidArgumentException {}
