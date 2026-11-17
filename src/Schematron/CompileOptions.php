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
 * With $idFromMessage an assert or report without an id first takes the code in a leading "[CODE]"
 * of its message text (the text before the first name or value-of element). When several of them
 * in the file share a code, they get "CODE-1" to "CODE-n" in document order. Those without a
 * message code fall back to $missingIdPrefix. With $normalizeTestWhitespace the test is collapsed
 * like XPath normalize-space() before it is hashed, so that formatting of the test does not change
 * the id. An explicit id always wins.
 *
 * @internal
 */
final readonly class CompileOptions
{
    public function __construct(
        public ?string $defaultFlag = null,
        public ?string $missingIdPrefix = null,
        public bool $idFromMessage = false,
        public bool $normalizeTestWhitespace = false,
    ) {
        if ($defaultFlag !== null && ! in_array($defaultFlag, Compiler::FLAGS, true)) {
            throw new InvalidArgumentException("The default flag \"{$defaultFlag}\" is not one of ".implode(', ', Compiler::FLAGS).'.');
        }
        if ($missingIdPrefix === '') {
            throw new InvalidArgumentException('The prefix of synthesized ids must not be empty.');
        }
    }

    /**
     * The code in a leading "[CODE]" of a message, or null; the code starts with a letter or digit
     * and continues with letters, digits, dot, underscore and hyphen. Leading whitespace is ignored.
     */
    public static function messageCode(string $message): ?string
    {
        return preg_match('/^[ \t\n\r]*\[([A-Za-z0-9][A-Za-z0-9._-]*)\]/', $message, $match) === 1 ? $match[1] : null;
    }

    /**
     * The id given to an assert or report without one; $normalize collapses whitespace runs of the
     * test first, as XPath normalize-space() does.
     */
    public static function synthesizedId(string $prefix, string $ruleContext, string $test, bool $normalize = false): string
    {
        if ($normalize) {
            $test = trim((string) preg_replace('/[ \t\n\r]+/', ' ', $test), ' ');
        }

        return "{$prefix}-".substr(hash('sha256', $ruleContext."\n".$test), 0, 10);
    }
}
