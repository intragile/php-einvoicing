<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

/**
 * Derives deterministic, well-formed variants of an invoice that break it in the ways received
 * files tend to be broken: missing or repeated elements, empty values, non-numeric or shifted
 * amounts, unknown codes, another currency, reversed periods and a space plus a comment in front of
 * a value (" <!--x-->O"), whose whitespace text node belongs to the string value.
 *
 * PEPPOL_KINDS adds two Peppol-specific breakages: a wrong check digit in an identifier whose
 * scheme carries one (GLN, Italian and Swedish schemes) and another business process (ProfileID).
 * They are opt-in, so the seeded mutations of the default kinds stay unchanged.
 */
final class Mutator
{
    public const KINDS = ['remove', 'duplicate', 'empty', 'amount-abc', 'amount-shift', 'code-zzz', 'currency', 'swap-dates', 'comment-space'];

    public const PEPPOL_KINDS = [...self::KINDS, 'check-digit', 'profile-id'];

    /** Identifier schemes with a check digit: GLN, Italian (0201, 0210, 0211, 9907) and Swedish (0007). */
    private const CHECK_DIGIT_SCHEMES = ['0088', '0201', '0210', '0211', '9907', '0007'];

    private const IDENTIFIERS = ['EndpointID', 'CompanyID', 'ID', 'URIID', 'GlobalID'];

    private const PROFILE = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    private const OTHER_PROFILE = 'urn:fdc:peppol.eu:2017:poacc:selfbilling:01:1.0';

    private const CII_PROCESS = 'BusinessProcessSpecifiedDocumentContextParameter';

    private const START = ['StartDate' => 'EndDate', 'StartDateTime' => 'EndDateTime'];

    /**
     * Up to $count mutations with distinct labels, the same for the same $xml and $seed. A label is
     * "<kind> <location path>", such as "amount-abc /Invoice[1]/cbc:Note[1]" or
     * "currency /Invoice[1]/cac:TaxTotal[1]/cbc:TaxAmount[1]/@currencyID".
     *
     * @param  list<string>  $kinds  a subset of PEPPOL_KINDS
     * @return list<array{string, string}> label and mutated document
     */
    public static function mutations(string $xml, int $seed, int $count, array $kinds = self::KINDS): array
    {
        return array_map(
            static fn (array $step): array => [$step[0], self::mutate($xml, $step[1], $step[2])],
            self::plan($xml, $seed, $count, $kinds),
        );
    }

    /**
     * The labels mutations() returns for the same arguments, without building the documents.
     *
     * @param  list<string>  $kinds  a subset of PEPPOL_KINDS
     * @return list<string>
     */
    public static function labels(string $xml, int $seed, int $count, array $kinds = self::KINDS): array
    {
        return array_column(self::plan($xml, $seed, $count, $kinds), 0);
    }

    /**
     * The document mutations() returns under $label for the same arguments.
     *
     * @param  list<string>  $kinds  a subset of PEPPOL_KINDS
     *
     * @throws RuntimeException when the arguments yield no mutation with that label
     */
    public static function mutation(string $xml, int $seed, int $count, string $label, array $kinds = self::KINDS): string
    {
        foreach (self::plan($xml, $seed, $count, $kinds) as [$planned, $kind, $target]) {
            if ($planned === $label) {
                return self::mutate($xml, $kind, $target);
            }
        }

        throw new RuntimeException("No mutation is labelled \"{$label}\".");
    }

    /**
     * @param  list<string>  $kinds
     * @return list<array{string, string, int}> label, kind and element index of each mutation
     */
    private static function plan(string $xml, int $seed, int $count, array $kinds): array
    {
        $unknown = array_diff($kinds, self::PEPPOL_KINDS);
        if ($unknown !== []) {
            throw new RuntimeException('Unknown mutation kinds: '.implode(', ', $unknown).'.');
        }
        $document = self::load($xml);
        $elements = $document->getElementsByTagName('*');
        $candidates = self::candidates($document);
        $kinds = array_values(array_filter(array_values(array_unique($kinds)), static fn (string $kind): bool => $candidates[$kind] !== []));
        if ($kinds === []) {
            return [];
        }

        $state = ($seed ^ 0x9E3779B9) & 0xFFFFFFFF ?: 1;
        $plan = [];
        for ($attempt = 0; count($plan) < $count && $attempt < $count * 100; $attempt++) {
            $kind = $kinds[self::next($state, count($kinds))];
            $target = $candidates[$kind][self::next($state, count($candidates[$kind]))];
            $element = $elements->item($target) ?? throw new RuntimeException("Element {$target} not found.");
            $label = $kind.' '.self::path($element).($kind === 'currency' ? '/@currencyID' : '');
            $plan[$label] ??= [$label, $kind, $target];
        }

        return array_values($plan);
    }

    private static function mutate(string $xml, string $kind, int $target): string
    {
        $document = self::load($xml);
        $element = $document->getElementsByTagName('*')->item($target);
        if (! $element instanceof DOMElement) {
            throw new RuntimeException("Element {$target} not found.");
        }
        self::apply($kind, $element);

        return (string) $document->saveXML();
    }

    /**
     * Document-order indexes (among all elements) of the elements each kind can change.
     *
     * @return array<string, list<int>>
     */
    private static function candidates(DOMDocument $document): array
    {
        $candidates = array_fill_keys(self::PEPPOL_KINDS, []);
        foreach ($document->getElementsByTagName('*') as $index => $element) {
            $name = (string) $element->localName;
            $leaf = self::isLeaf($element);
            $text = trim($element->textContent);

            if ($element !== $document->documentElement) {
                $candidates['remove'][] = $index;
                $candidates['duplicate'][] = $index;
            }
            if ($leaf && $text !== '') {
                $candidates['empty'][] = $index;
                $candidates['comment-space'][] = $index;
            }
            if ($leaf && str_ends_with($name, 'Amount')) {
                $candidates['amount-abc'][] = $index;
                if (self::decimal($text) !== null) {
                    $candidates['amount-shift'][] = $index;
                }
            }
            if ($leaf && str_ends_with($name, 'Code') && $text !== 'ZZZ') {
                $candidates['code-zzz'][] = $index;
            }
            if ($element->hasAttribute('currencyID')) {
                $candidates['currency'][] = $index;
            }
            if (self::period($element) !== null) {
                $candidates['swap-dates'][] = $index;
            }
            if ($leaf && in_array($name, self::IDENTIFIERS, true)
                && in_array($element->getAttribute('schemeID'), self::CHECK_DIGIT_SCHEMES, true)
                && preg_match('/\d$/', $text) === 1) {
                $candidates['check-digit'][] = $index;
            }
            if ($leaf && self::isProfile($element)) {
                $candidates['profile-id'][] = $index;
            }
        }

        return $candidates;
    }

    private static function apply(string $kind, DOMElement $element): void
    {
        $parent = $element->parentNode ?? throw new RuntimeException('Detached element.');
        match ($kind) {
            'remove' => $parent->removeChild($element),
            'duplicate' => $parent->insertBefore($element->cloneNode(true), $element->nextSibling),
            'empty' => $element->textContent = '',
            'amount-abc' => $element->textContent = 'abc',
            'amount-shift' => $element->textContent = (string) self::decimal(trim($element->textContent))?->plus('0.01'),
            'code-zzz' => $element->textContent = 'ZZZ',
            'currency' => $element->setAttribute('currencyID', $element->getAttribute('currencyID') === 'EUR' ? 'USD' : 'EUR'),
            'comment-space' => self::prependCommentAndSpace($element),
            'swap-dates' => self::swap(...(self::period($element) ?? throw new RuntimeException('No period.'))),
            'check-digit' => $element->textContent = self::otherLastDigit(trim($element->textContent)),
            'profile-id' => $element->textContent = trim($element->textContent) === self::PROFILE ? self::OTHER_PROFILE : self::PROFILE,
            default => throw new RuntimeException("Unknown mutation kind {$kind}."),
        };
    }

    /**
     * The start and end elements of a period element whose dates differ.
     *
     * @return array{DOMElement, DOMElement}|null
     */
    private static function period(DOMElement $element): ?array
    {
        foreach (self::START as $startName => $endName) {
            $start = $end = null;
            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMElement && $child->localName === $startName) {
                    $start ??= $child;
                } elseif ($child instanceof DOMElement && $child->localName === $endName) {
                    $end ??= $child;
                }
            }
            if ($start !== null && $end !== null && trim($start->textContent) !== trim($end->textContent)) {
                return [$start, $end];
            }
        }

        return null;
    }

    /**
     * Whether an element holds the business process: cbc:ProfileID in UBL, the ID of the business
     * process context parameter in CII.
     */
    private static function isProfile(DOMElement $element): bool
    {
        $parent = $element->parentNode;

        return $element->localName === 'ProfileID'
            || ($element->localName === 'ID' && $parent instanceof DOMElement && $parent->localName === self::CII_PROCESS);
    }

    /**
     * The identifier with its last digit replaced by the next digit (9 becomes 0).
     */
    private static function otherLastDigit(string $identifier): string
    {
        return substr($identifier, 0, -1).(((int) substr($identifier, -1)) + 1) % 10;
    }

    /**
     * Turns "<a>O</a>" into "<a> <!--x-->O</a>".
     */
    private static function prependCommentAndSpace(DOMElement $element): void
    {
        $document = $element->ownerDocument ?? throw new RuntimeException('Detached element.');
        $first = $element->firstChild;
        $element->insertBefore($document->createTextNode(' '), $first);
        $element->insertBefore($document->createComment('x'), $first);
    }

    /**
     * Exchanges the contents (text or child elements) of two elements.
     */
    private static function swap(DOMElement $first, DOMElement $second): void
    {
        $firstChildren = iterator_to_array($first->childNodes);
        $secondChildren = iterator_to_array($second->childNodes);
        foreach ($secondChildren as $child) {
            $first->appendChild($child);
        }
        foreach ($firstChildren as $child) {
            $second->appendChild($child);
        }
    }

    private static function isLeaf(DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return false;
            }
        }

        return true;
    }

    private static function decimal(string $text): ?BigDecimal
    {
        if (preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $text) !== 1) {
            return null;
        }
        try {
            return BigDecimal::of($text);
        } catch (MathException) {
            return null;
        }
    }

    /**
     * Location such as /Invoice[1]/cac:TaxTotal[1]/cbc:TaxAmount[1], positions counted among
     * siblings of the same name.
     */
    private static function path(DOMElement $element): string
    {
        $path = '';
        for ($node = $element; $node instanceof DOMElement; $node = $node->parentNode) {
            $position = 1;
            for ($sibling = $node->previousSibling; $sibling instanceof DOMNode; $sibling = $sibling->previousSibling) {
                if ($sibling instanceof DOMElement && $sibling->nodeName === $node->nodeName) {
                    $position++;
                }
            }
            $path = "/{$node->nodeName}[{$position}]".$path;
        }

        return $path;
    }

    /**
     * xorshift32 step; returns a value in [0, $bound).
     */
    private static function next(int &$state, int $bound): int
    {
        $state ^= ($state << 13) & 0xFFFFFFFF;
        $state ^= $state >> 17;
        $state ^= ($state << 5) & 0xFFFFFFFF;

        return $state % $bound;
    }

    private static function load(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The document is not well-formed XML.');
        }

        return $document;
    }
}
