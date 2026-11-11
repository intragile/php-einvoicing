<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax;

use DOMDocument;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use XMLReader;

/**
 * Loads untrusted XML without resolving entities or touching the network.
 *
 * Any DOCTYPE declaration is refused before the document is built, in three layers:
 * - the prolog (UTF-8, or UTF-16 decoded for the scan) is scanned as text, so no parser sees the
 *   internal subset at all;
 * - the prolog is streamed with XMLReader, which stops at the first element, so a DOCTYPE in any
 *   other encoding libxml understands is found after decoding; a parser error about entities
 *   there is reported as unsafe as well;
 * - the parsed document is checked for a doctype node.
 * The document is parsed with LIBXML_NONET and never with LIBXML_NOENT or LIBXML_DTDLOAD.
 */
final class SafeXml
{
    private const OPTIONS = LIBXML_NONET | LIBXML_NOBLANKS;

    /**
     * Loads the document and drops whitespace-only text nodes (LIBXML_NOBLANKS), which suits
     * readers that only look at element content.
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration or declares entities
     * @throws UnsupportedDocument when the document is empty or not well-formed XML
     */
    public static function load(string $xml): DOMDocument
    {
        return self::parse($xml, self::OPTIONS);
    }

    /**
     * Loads the document with every text node kept, as an XSLT processor without xsl:strip-space
     * sees it. libxml drops whitespace-only text next to a comment under LIBXML_NOBLANKS, which
     * would change string values such as "<a> <!--x-->O</a>".
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration or declares entities
     * @throws UnsupportedDocument when the document is empty or not well-formed XML
     */
    public static function loadPreservingWhitespace(string $xml): DOMDocument
    {
        return self::parse($xml, self::OPTIONS & ~LIBXML_NOBLANKS);
    }

    private static function parse(string $xml, int $options): DOMDocument
    {
        if (trim($xml) === '') {
            throw new UnsupportedDocument('The document is empty.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            self::assertPrologHasNoDoctype($xml);
            self::assertNoDoctype($xml);

            $document = new DOMDocument;
            if (! $document->loadXML($xml, $options) || $document->documentElement === null) {
                throw new UnsupportedDocument('The document is not well-formed XML: '.self::lastError());
            }
            if ($document->doctype !== null) {
                throw new UnsafeXml('The document contains a DOCTYPE declaration, which is refused.');
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function assertNoDoctype(string $xml): void
    {
        $reader = new XMLReader;
        if (! $reader->XML($xml, null, self::OPTIONS)) {
            throw new UnsupportedDocument('The document is not well-formed XML: '.self::lastError());
        }

        try {
            while (self::readNext($reader)) {
                if ($reader->nodeType === XMLReader::DOC_TYPE) {
                    throw new UnsafeXml('The document contains a DOCTYPE declaration, which is refused.');
                }
                if ($reader->nodeType === XMLReader::ELEMENT) {
                    return;
                }
            }
        } finally {
            $reader->close();
        }

        throw new UnsupportedDocument('The document is not well-formed XML: '.self::lastError());
    }

    private static function readNext(XMLReader $reader): bool
    {
        if ($reader->read()) {
            return true;
        }

        $error = self::lastError();
        if (stripos($error, 'entity') !== false) {
            throw new UnsafeXml('The document declares entities, which are refused: '.$error);
        }

        return false;
    }

    /**
     * Scans the text before the first element for a DOCTYPE declaration.
     */
    private static function assertPrologHasNoDoctype(string $xml): void
    {
        $text = self::toUtf8ForScan($xml);
        $length = strlen($text);
        $position = str_starts_with($text, "\xEF\xBB\xBF") ? 3 : 0;

        while ($position < $length) {
            $position += strspn($text, " \t\r\n", $position);

            if (substr_compare($text, '<!DOCTYPE', $position, 9) === 0) {
                throw new UnsafeXml('The document contains a DOCTYPE declaration, which is refused.');
            }

            [$terminator, $offset] = match (true) {
                substr_compare($text, '<?', $position, 2) === 0 => ['?>', 2],
                substr_compare($text, '<!--', $position, 4) === 0 => ['-->', 4],
                default => [null, 0],
            };
            $end = $terminator === null ? false : strpos($text, $terminator, $position + $offset);
            if ($end === false) {
                return;
            }
            $position = $end + strlen((string) $terminator);
        }
    }

    private static function toUtf8ForScan(string $xml): string
    {
        $encoding = match (true) {
            str_starts_with($xml, "\xFF\xFE"), str_starts_with($xml, "<\x00") => 'UTF-16LE',
            str_starts_with($xml, "\xFE\xFF"), str_starts_with($xml, "\x00<") => 'UTF-16BE',
            default => null,
        };
        if ($encoding === null) {
            return $xml;
        }

        $bomLength = in_array(substr($xml, 0, 2), ["\xFF\xFE", "\xFE\xFF"], true) ? 2 : 0;

        return mb_convert_encoding(substr($xml, $bomLength), 'UTF-8', $encoding);
    }

    private static function lastError(): string
    {
        $error = libxml_get_last_error();

        return $error === false ? 'no root element' : trim($error->message);
    }
}
