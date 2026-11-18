<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\Fa3;

use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use LogicException;

/**
 * The FA(3) fields a round trip must keep, so a written document can be compared with its source
 * field by field instead of byte by byte.
 *
 * Each key is a path of local names below Faktura; its value lists the texts found at that path in
 * document order. Rows of FaWiersz are keyed by their position, and NrWierszaFa is left out because
 * the writer renumbers the rows. An element that is absent has no key, so a field that appears or
 * disappears is a difference.
 */
final class Fa3Fields
{
    /** Fields of one FaWiersz row. */
    private const ROW_FIELDS = ['P_7', 'P_8A', 'P_8B', 'P_9A', 'P_10', 'P_11', 'P_12', 'GTU', 'Procedura', 'StanPrzed'];

    /** Blocks of Fa compared with every leaf below them. */
    private const FA_BLOCKS = ['Adnotacje', 'DaneFaKorygowanej', 'ZaliczkaCzesciowa', 'FakturaZaliczkowa', 'Rozliczenie', 'Platnosc'];

    /**
     * @return array<string, list<string>>
     */
    public static function of(string $xml): array
    {
        $document = new DOMDocument;
        if (! $document->loadXML($xml)) {
            throw new LogicException('The document is not well-formed XML.');
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('fa', Fa3Format::NS);

        $fields = [];
        foreach (['KodWaluty', 'P_1', 'P_2', 'P_15', 'RodzajFaktury'] as $name) {
            self::collect($xpath, '/fa:Faktura/fa:Fa/fa:'.$name, 'Fa/'.$name, $fields);
        }
        foreach (self::elements($xpath, '/fa:Faktura/fa:Fa/*') as $element) {
            $name = $element->localName;
            if ($name !== null && preg_match('/^P_1[34]_/', $name) === 1) {
                $fields['Fa/'.$name][] = self::text($element);
            }
        }
        foreach (self::FA_BLOCKS as $block) {
            foreach (self::elements($xpath, '/fa:Faktura/fa:Fa/fa:'.$block) as $element) {
                self::leaves($element, 'Fa/'.$block, $fields);
            }
        }

        $rows = self::elements($xpath, '/fa:Faktura/fa:Fa/fa:FaWiersz');
        $fields['count(Fa/FaWiersz)'] = [(string) count($rows)];
        foreach ($rows as $index => $row) {
            foreach (self::ROW_FIELDS as $name) {
                self::collect($xpath, 'fa:'.$name, sprintf('Fa/FaWiersz[%d]/%s', $index + 1, $name), $fields, $row);
            }
        }

        self::collect($xpath, '/fa:Faktura/fa:Podmiot1/fa:DaneIdentyfikacyjne/fa:NIP', 'Podmiot1/DaneIdentyfikacyjne/NIP', $fields);
        self::collect($xpath, '/fa:Faktura/fa:Podmiot1/fa:DaneIdentyfikacyjne/fa:Nazwa', 'Podmiot1/DaneIdentyfikacyjne/Nazwa', $fields);
        foreach (self::elements($xpath, '/fa:Faktura/fa:Podmiot2/fa:DaneIdentyfikacyjne') as $element) {
            self::leaves($element, 'Podmiot2/DaneIdentyfikacyjne', $fields);
        }
        self::collect($xpath, '/fa:Faktura/fa:Podmiot2/fa:JST', 'Podmiot2/JST', $fields);
        self::collect($xpath, '/fa:Faktura/fa:Podmiot2/fa:GV', 'Podmiot2/GV', $fields);
        self::collect($xpath, '/fa:Faktura/fa:Podmiot3/fa:DaneIdentyfikacyjne/fa:NIP', 'Podmiot3/DaneIdentyfikacyjne/NIP', $fields);
        self::collect($xpath, '/fa:Faktura/fa:Podmiot3/fa:Rola', 'Podmiot3/Rola', $fields);
        foreach (self::elements($xpath, '/fa:Faktura/fa:Stopka') as $element) {
            self::leaves($element, 'Stopka', $fields);
        }

        return $fields;
    }

    /**
     * @param  array<string, list<string>>  $fields
     */
    private static function collect(DOMXPath $xpath, string $query, string $key, array &$fields, ?DOMElement $context = null): void
    {
        foreach (self::elements($xpath, $query, $context) as $element) {
            $fields[$key][] = self::text($element);
        }
    }

    /**
     * @param  array<string, list<string>>  $fields
     */
    private static function leaves(DOMElement $element, string $path, array &$fields): void
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }
        if ($children === []) {
            $fields[$path][] = self::text($element);

            return;
        }
        foreach ($children as $child) {
            self::leaves($child, $path.'/'.$child->localName, $fields);
        }
    }

    /**
     * @return list<DOMElement>
     */
    private static function elements(DOMXPath $xpath, string $query, ?DOMElement $context = null): array
    {
        $elements = [];
        foreach ($xpath->query($query, $context) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private static function text(DOMElement $element): string
    {
        return trim($element->textContent);
    }

    private function __construct() {}
}
