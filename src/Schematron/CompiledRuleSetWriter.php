<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Schematron;

use RuntimeException;

/**
 * Writes a compiled rule set as a PHP file that returns it, so loading it at runtime is a plain
 * require (cached by opcache) instead of parsing Schematron and XPath. The output depends only on
 * the compiled array: the same input always gives the same bytes.
 *
 * @phpstan-import-type CompiledRuleSet from Compiler
 */
final class CompiledRuleSetWriter
{
    /**
     * @param  CompiledRuleSet  $compiled
     */
    public static function write(array $compiled, string $path): void
    {
        if (file_put_contents($path, self::render($compiled)) === false) {
            throw new RuntimeException("Cannot write the compiled rule set to \"{$path}\".");
        }
    }

    /**
     * @param  CompiledRuleSet  $compiled
     */
    public static function render(array $compiled): string
    {
        return "<?php\n\n"
            ."// Generated from CEN EN 16931 validation artefacts {$compiled['version']} (EUPL-1.2) — do not edit.\n"
            ."// Source: {$compiled['source']} (SHA-256 {$compiled['sha256']}).\n"
            ."// Regenerate with: composer compile-rules\n\n"
            ."declare(strict_types=1);\n\n"
            .'return '.var_export($compiled, true).";\n";
    }
}
