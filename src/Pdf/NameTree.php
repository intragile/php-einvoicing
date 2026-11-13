<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;

/**
 * Reads a PDF name tree (ISO 32000-1, 7.9.6), such as the /EmbeddedFiles tree of the /Names
 * dictionary, with bounded depth and node count and a cycle check, since the tree comes from
 * untrusted input.
 */
final class NameTree
{
    public const int MAX_DEPTH = 32;

    public const int MAX_NODES = 10_000;

    /**
     * Every key and value of the tree, depth-first in key order. Values are returned unresolved.
     *
     * @return list<array{PdfString, mixed}>
     *
     * @throws UnreadablePdf with code MALFORMED when a key is not a string, and with code REFUSED
     *                       when the tree is too deep, too large or contains a cycle
     */
    public static function entries(PdfDocument $document, PdfDictionary $root): array
    {
        $pairs = [];
        $nodes = 0;
        $stack = [[$root, 0]];
        $visited = [];
        while ($stack !== []) {
            [$node, $depth] = array_pop($stack);
            if (++$nodes > self::MAX_NODES || $depth > self::MAX_DEPTH) {
                throw UnreadablePdf::refused('A name tree is too large or too deep.');
            }
            $names = self::listOf($document->resolve($node->get('Names')));
            for ($i = 0; $i + 1 < count($names); $i += 2) {
                // A skipped key would hide its value, which may be the very file a caller looks for.
                if (! $names[$i] instanceof PdfString) {
                    throw UnreadablePdf::malformed('A name tree key is not a string.');
                }
                $pairs[] = [$names[$i], $names[$i + 1]];
            }
            $kids = self::listOf($document->resolve($node->get('Kids')));
            foreach (array_reverse($kids) as $kid) {
                if ($kid instanceof PdfRef) {
                    if (isset($visited[$kid->number])) {
                        throw UnreadablePdf::refused('A name tree contains a cycle.');
                    }
                    $visited[$kid->number] = true;
                }
                $child = $document->resolve($kid);
                if ($child instanceof PdfDictionary) {
                    $stack[] = [$child, $depth + 1];
                }
            }
        }

        return $pairs;
    }

    /** @return list<mixed> */
    private static function listOf(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
