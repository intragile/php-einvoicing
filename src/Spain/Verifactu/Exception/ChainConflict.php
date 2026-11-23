<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu\Exception;

use RuntimeException;

/**
 * A write that would fork, rewind or overwrite a VERI*FACTU chain; nothing was stored.
 */
final class ChainConflict extends RuntimeException {}
