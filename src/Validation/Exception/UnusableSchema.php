<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Exception;

use RuntimeException;

/**
 * Thrown when an XML Schema cannot be used for validation: the file is missing or unreadable, is
 * not well-formed XML, or does not compile (an unresolved type, a missing import or include).
 * This is a fault in the installed schema files, never a finding about the validated document.
 */
final class UnusableSchema extends RuntimeException {}
