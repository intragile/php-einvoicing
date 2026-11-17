<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle;

use IntrAgile\EInvoicing\Schematron\RuleSet;
use RuntimeException;

/**
 * The ids a compiled French rule set gives its asserts, keyed the way an SVRL report identifies
 * an entry that carries no id: the context of the rule that fired it and its test.
 *
 * The FNFE stylesheets report the asserts of Flux 10 (all of them), one assert of EXTENDED-CTC-FR
 * CII and a few of the Factur-X EXTENDED fork without an id. The compiler gives such an assert the
 * code of its message, an ordinal or a hash of its raw test; none of these can be recomputed from
 * the report alone, whose test attribute is whitespace-normalised. Looking the entry up by its
 * context and normalised test recovers the id we assigned.
 */
final class FranceIds
{
    /** The value of a key that two asserts with different ids share. */
    public const AMBIGUOUS = '';

    /**
     * "<context>\n<normalize-space(test)>" => our id, for every assert and report of the rule
     * set. A key shared by asserts with different ids maps to AMBIGUOUS.
     *
     * @return array<string, string>
     */
    public static function map(RuleSet $ruleSet): array
    {
        $map = [];
        foreach ($ruleSet->patterns() as $pattern) {
            foreach ($pattern['rules'] as $rule) {
                foreach ($rule['asserts'] as $assert) {
                    $key = self::key($rule['context'], $assert['test']);
                    $map[$key] = isset($map[$key]) && $map[$key] !== $assert['id'] ? self::AMBIGUOUS : $assert['id'];
                }
            }
        }

        return $map;
    }

    /**
     * The key of an assert as the SVRL report shows it: its rule context, a line feed and its test
     * as the stylesheet writes it, collapsed like XPath normalize-space().
     *
     * The stylesheet writes the test into an attribute value template, so a brace pair is
     * evaluated as an XPath expression: the regular expression quantifier "{1,6}" in a test is
     * reported as "1 6", and a doubled brace as a single one. Only quantifiers (integer lists)
     * occur in the FNFE tests; any other brace content is kept as written, and then matches
     * nothing in the report.
     */
    public static function key(string $context, string $test): string
    {
        $written = (string) preg_replace_callback(
            '/\{\{|\}\}|\{([^{}]*)\}/',
            static function (array $match): string {
                if (! isset($match[1])) {
                    return $match[0][0];
                }
                $items = array_map(trim(...), explode(',', $match[1]));

                return array_filter($items, static fn (string $item): bool => preg_match('/^\d+$/', $item) !== 1) === []
                    ? implode(' ', $items)
                    : $match[0];
            },
            $test,
        );

        return $context."\n".trim((string) preg_replace('/[ \t\n\r]+/', ' ', $written), ' ');
    }

    /**
     * Our id of the assert with this key.
     *
     * @param  array<string, string>  $map  from map()
     *
     * @throws RuntimeException when no assert or more than one id has the key
     */
    public static function lookup(array $map, string $key): string
    {
        $id = $map[$key] ?? throw new RuntimeException("No assert of the rule set has the context and test:\n{$key}");
        if ($id === self::AMBIGUOUS) {
            throw new RuntimeException("Asserts with different ids share the context and test:\n{$key}");
        }

        return $id;
    }
}
