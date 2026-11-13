<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use InvalidArgumentException;

/**
 * Relaxations of the compiler for schemas that leave out what the engine otherwise requires.
 *
 * By default an assert or report without a flag, or without an id, is refused. With $defaultFlag
 * it gets that flag instead (one of Compiler::FLAGS). With $missingIdPrefix it gets the id
 * "{prefix}-" followed by the first ten hex digits of the SHA-256 of its rule's context, a line
 * feed and its test, both as attribute values after XML decoding. The id is therefore stable
 * across recompiles and can be recomputed from an SVRL report of the official stylesheet.
 *
 * @internal
 */
final readonly class CompileOptions
{
    public function __construct(
        public ?string $defaultFlag = null,
        public ?string $missingIdPrefix = null,
    ) {
        if ($defaultFlag !== null && ! in_array($defaultFlag, Compiler::FLAGS, true)) {
            throw new InvalidArgumentException("The default flag \"{$defaultFlag}\" is not one of ".implode(', ', Compiler::FLAGS).'.');
        }
        if ($missingIdPrefix === '') {
            throw new InvalidArgumentException('The prefix of synthesized ids must not be empty.');
        }
    }

    /**
     * The id given to an assert or report without one.
     */
    public static function synthesizedId(string $prefix, string $ruleContext, string $test): string
    {
        return "{$prefix}-".substr(hash('sha256', $ruleContext."\n".$test), 0, 10);
    }
}
