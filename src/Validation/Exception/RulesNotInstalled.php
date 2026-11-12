<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Exception;

use RuntimeException;

/**
 * Thrown when a rule set that is compiled on the user's machine, such as Peppol BIS Billing, has
 * not been installed in the given folder. The message names the command that installs it.
 */
final class RulesNotInstalled extends RuntimeException {}
