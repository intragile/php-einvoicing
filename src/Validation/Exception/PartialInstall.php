<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown when installing compiled rules failed after some of the files had already replaced the
 * installed ones, so the rules folder now holds a mix of old and new files.
 */
final class PartialInstall extends RuntimeException
{
    /**
     * @param  list<string>  $writtenPaths  the files that were replaced before the failure
     */
    public function __construct(string $message, public readonly array $writtenPaths, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
