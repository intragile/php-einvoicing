<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Exception;

use RuntimeException;

/**
 * Thrown when a downloaded or supplied rule source file does not have the pinned SHA-256
 * checksum, so it is not the published file this engine was verified against.
 */
final class RuleSourceMismatch extends RuntimeException {}
