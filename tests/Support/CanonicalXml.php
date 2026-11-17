<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DOMDocument;
use DOMNode;
use DOMXPath;
use RuntimeException;

/**
 * Canonical form of an XML document for comparisons that ignore layout: the byte order mark,
 * comments and whitespace-only text nodes are removed, then the document is serialised with
 * exclusive XML canonicalisation (which also drops unused namespace declarations).
 */
final class CanonicalXml
{
    public static function of(string $xml): string
    {
        if (str_starts_with($xml, "\u{FEFF}")) {
            $xml = substr($xml, 3);
        }

        $document = new DOMDocument;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The XML could not be parsed.');
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//comment() | //text()[normalize-space(.) = \'\']');
        if ($nodes === false) {
            throw new RuntimeException('The XML could not be queried.');
        }
        /** @var list<DOMNode> $removable */
        $removable = iterator_to_array($nodes, false);
        foreach ($removable as $node) {
            $node->parentNode?->removeChild($node);
        }

        $canonical = $document->C14N(true, false);
        if ($canonical === false) {
            throw new RuntimeException('The XML could not be canonicalised.');
        }

        return $canonical;
    }
}
