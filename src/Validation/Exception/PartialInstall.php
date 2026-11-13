<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown when moving compiled rules into place failed and restoring the previously installed
 * rules failed as well, so the rules folder now holds a mix of old and new files.
 */
final class PartialInstall extends RuntimeException
{
    /**
     * @param  list<string>  $writtenPaths  the files left in the new state (or missing) after the
     *                                      failed restore
     * @param  list<string>  $backupPaths  the .bak-* backups left in the rules folder; each holds
     *                                     the previous version of its file and can be renamed back
     */
    public function __construct(
        string $message,
        public readonly array $writtenPaths,
        ?Throwable $previous = null,
        public readonly array $backupPaths = [],
    ) {
        parent::__construct($message, 0, $previous);
    }
}
