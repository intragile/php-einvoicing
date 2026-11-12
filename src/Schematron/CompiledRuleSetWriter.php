<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use RuntimeException;

/**
 * Writes a compiled rule set as a PHP file that returns it, so loading it at runtime is a plain
 * require (cached by opcache) instead of parsing Schematron and XPath. The output depends only on
 * the compiled array and the origin: the same input always gives the same bytes.
 *
 * @internal
 *
 * @phpstan-import-type CompiledRuleSet from Compiler
 */
final class CompiledRuleSetWriter
{
    /**
     * @param  CompiledRuleSet  $compiled
     * @param  string  $origin  the upstream artefacts and their licence, named in the file header
     * @param  string  $regenerate  the command that regenerates the file, named in the file header
     */
    public static function write(array $compiled, string $path, string $origin, string $regenerate = 'composer compile-rules'): void
    {
        if (file_put_contents($path, self::render($compiled, $origin, $regenerate)) === false) {
            throw new RuntimeException("Cannot write the compiled rule set to \"{$path}\".");
        }
    }

    /**
     * @param  CompiledRuleSet  $compiled
     * @param  string  $origin  the upstream artefacts and their licence, named in the file header
     * @param  string  $regenerate  the command that regenerates the file, named in the file header
     */
    public static function render(array $compiled, string $origin, string $regenerate = 'composer compile-rules'): string
    {
        return "<?php\n\n"
            ."// Generated from {$origin} — do not edit.\n"
            ."// Source: {$compiled['source']} (SHA-256 {$compiled['sha256']}).\n"
            ."// Regenerate with: {$regenerate}\n\n"
            ."declare(strict_types=1);\n\n"
            .'return '.var_export($compiled, true).";\n";
    }
}
