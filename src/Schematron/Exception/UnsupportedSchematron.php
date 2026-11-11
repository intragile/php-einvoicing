<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron\Exception;

use InvalidArgumentException;

/**
 * Thrown when a Schematron schema uses a construct the compiler does not support, or when one of
 * its XPath expressions cannot be compiled.
 */
final class UnsupportedSchematron extends InvalidArgumentException {}
