<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Exception;

use RuntimeException;

/**
 * Thrown when an inbound document is not well-formed, is not of a supported type, or lacks data
 * the invoice model cannot do without.
 */
class UnsupportedDocument extends RuntimeException {}
