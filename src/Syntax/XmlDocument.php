<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax;

use BackedEnum;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use IntrAgile\EInvoicing\CodeList\MimeCode;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Attachment;
use IntrAgile\EInvoicing\Model\Identifier;

/**
 * A loaded syntax document with typed value accessors and bookkeeping of what was mapped.
 *
 * Every accessor that maps a value marks the element as used. After mapping, ignored() walks the
 * tree and reports each element that neither it nor any descendant was used for, so nothing in
 * an inbound file is dropped silently.
 *
 * Free text and identifiers are returned exactly as written; codes, numbers, dates and
 * attributes are whitespace-trimmed, as the official artefacts normalise them.
 *
 * @internal
 */
abstract class XmlDocument
{
    /** An xsd:decimal lexical value. */
    private const DECIMAL = '/^[+-]?(\d+(\.\d*)?|\.\d+)$/';

    public readonly DOMElement $root;

    private readonly DOMXPath $xpath;

    /**
     * Used elements and their ancestors, keyed by object id. Holding the wrappers keeps their
     * identity stable, because ext-dom hands out the same object for a node while one is alive.
     *
     * @var array<int, DOMElement>
     */
    private array $touched = [];

    /** @var array<int, DOMElement> Elements already reported with a reason, keyed by object id. */
    private array $handled = [];

    /** @var list<array{DOMElement, ?string, string}> Element, attribute and reason of each rejection. */
    private array $reasons = [];

    /**
     * Path segment of each child, per parent, keyed by object id; parents and children are held
     * so the ids stay unique.
     *
     * @var array<int, array{DOMElement, array<int, array{DOMElement, string}>}>
     */
    private array $segments = [];

    /**
     * @param  array<string, string>  $prefixes  Conventional prefix per namespace, used in XPath
     *                                           expressions and in reported paths.
     */
    public function __construct(DOMDocument $document, private readonly array $prefixes)
    {
        $root = $document->documentElement;
        if ($root === null) {
            throw new UnsupportedDocument('The document has no root element.');
        }
        $this->root = $root;

        $this->xpath = new DOMXPath($document);
        foreach ($prefixes as $namespace => $prefix) {
            $this->xpath->registerNamespace($prefix, $namespace);
        }
    }

    /**
     * Parses the date held by the element, or returns null when it is not a valid date.
     */
    abstract protected function parseDate(DOMElement $element): ?DateTimeImmutable;

    /**
     * Why the element holds no valid date, without the path, e.g. "invalid date '2026-13-01'".
     */
    protected function invalidDate(DOMElement $element): string
    {
        return sprintf("invalid date '%s'", trim($element->textContent));
    }

    /**
     * @return list<DOMElement>
     */
    public function all(string $expression, ?DOMNode $context = null): array
    {
        $nodes = $this->xpath->query($expression, $context ?? $this->root);
        $elements = [];
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $elements[] = $node;
                }
            }
        }

        return $elements;
    }

    public function one(string $expression, ?DOMNode $context = null): ?DOMElement
    {
        return $this->all($expression, $context)[0] ?? null;
    }

    /**
     * Marks the element and its ancestors as used. The walk stops at the first ancestor already
     * marked, whose own ancestors are marked too, so marking costs amortised O(1) per element.
     */
    public function use(DOMElement $element): void
    {
        for ($node = $element; $node instanceof DOMElement; $node = $node->parentNode) {
            $id = spl_object_id($node);
            if (isset($this->touched[$id])) {
                return;
            }
            $this->touched[$id] = $node;
        }
    }

    /**
     * Raw text of the first match; an empty element counts as absent.
     */
    public function text(string $expression, ?DOMNode $context = null): ?string
    {
        $element = $this->one($expression, $context);
        if ($element === null) {
            return null;
        }
        $this->use($element);

        return $element->textContent === '' ? null : $element->textContent;
    }

    /**
     * Trimmed text of the first match; an empty element counts as absent.
     */
    public function token(string $expression, ?DOMNode $context = null): ?string
    {
        $element = $this->one($expression, $context);
        if ($element === null) {
            return null;
        }
        $this->use($element);
        $value = trim($element->textContent);

        return $value === '' ? null : $value;
    }

    public function requiredText(string $expression, DOMNode $context, string $term): string
    {
        return $this->text($expression, $context) ?? throw $this->missing($expression, $context, $term);
    }

    public function attribute(DOMElement $element, string $name): ?string
    {
        if (! $element->hasAttribute($name)) {
            return null;
        }
        $value = trim($element->getAttribute($name));

        return $value === '' ? null : $value;
    }

    /**
     * An identifier with its optional schemeID attribute; an empty element counts as absent.
     */
    public function identifier(?DOMElement $element): ?Identifier
    {
        if ($element === null) {
            return null;
        }
        $this->use($element);
        if ($element->textContent === '') {
            return null;
        }

        return new Identifier(value: $element->textContent, scheme: $this->attribute($element, 'schemeID'));
    }

    public function decimal(string $expression, ?DOMNode $context = null): ?BigDecimal
    {
        $element = $this->one($expression, $context);

        return $element === null ? null : $this->decimalOf($element);
    }

    public function decimalOf(DOMElement $element): ?BigDecimal
    {
        $value = trim($element->textContent);
        if ($value === '') {
            $this->use($element);

            return null;
        }
        if (preg_match(self::DECIMAL, $value) !== 1) {
            $this->reject($element, "invalid number '{$value}'");

            return null;
        }
        $this->use($element);

        return BigDecimal::of($value);
    }

    /**
     * The decimal value of the element without marking it as used, or null when it holds none.
     */
    public function peekDecimal(DOMElement $element): ?BigDecimal
    {
        $value = trim($element->textContent);

        return preg_match(self::DECIMAL, $value) === 1 ? BigDecimal::of($value) : null;
    }

    public function requiredDecimal(string $expression, DOMNode $context, string $term): BigDecimal
    {
        $element = $this->one($expression, $context) ?? throw $this->missing($expression, $context, $term);
        $value = trim($element->textContent);
        if (preg_match(self::DECIMAL, $value) !== 1) {
            throw new UnsupportedDocument(sprintf("Invalid number '%s' for %s at %s.", $value, $term, $this->path($element)));
        }
        $this->use($element);

        return BigDecimal::of($value);
    }

    public function date(string $expression, ?DOMNode $context = null): ?DateTimeImmutable
    {
        $element = $this->one($expression, $context);
        if ($element === null) {
            return null;
        }
        $date = $this->parseDate($element);
        if ($date === null) {
            $this->reject($element, $this->invalidDate($element));

            return null;
        }
        $this->use($element);

        return $date;
    }

    public function requiredDate(string $expression, DOMNode $context, string $term): DateTimeImmutable
    {
        $element = $this->one($expression, $context) ?? throw $this->missing($expression, $context, $term);
        $date = $this->parseDate($element)
            ?? throw new UnsupportedDocument(sprintf('%s for %s at %s.', ucfirst($this->invalidDate($element)), $term, $this->path($element)));
        $this->use($element);

        return $date;
    }

    /**
     * Resolves a code held in the element text or in one of its attributes.
     *
     * A value the enum rejects is reported as "<path> (invalid code '<value>')" and yields null.
     * A case-insensitive list is matched in upper case, as the official artefacts do for it.
     *
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    public function code(string $enum, ?DOMElement $element, ?string $attribute = null, bool $caseInsensitive = false): ?BackedEnum
    {
        if ($element === null) {
            return null;
        }
        $value = $attribute === null ? trim($element->textContent) : $this->attribute($element, $attribute);
        if ($value === null || $value === '') {
            return null;
        }
        $case = $enum::tryFrom($caseInsensitive ? strtoupper($value) : $value);
        if ($case === null) {
            $this->reject($element, "invalid code '{$value}'", $attribute);

            return null;
        }
        $this->use($element);

        return $case;
    }

    /**
     * Resolves a code the model cannot do without; a missing or invalid value is unsupported.
     *
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T
     */
    public function requiredCode(string $enum, ?DOMElement $element, ?string $attribute, string $term, string $expression, DOMNode $context): BackedEnum
    {
        if ($element === null) {
            throw $this->missing($expression, $context, $term);
        }
        $value = $attribute === null ? trim($element->textContent) : $this->attribute($element, $attribute);
        $where = fn (): string => $this->path($element).($attribute === null ? '' : '/@'.$attribute);
        if ($value === null || $value === '') {
            throw new UnsupportedDocument(sprintf('Missing required %s at %s.', $term, $where()));
        }
        $case = $enum::tryFrom($value)
            ?? throw new UnsupportedDocument(sprintf("Invalid code '%s' for required %s at %s.", $value, $term, $where()));
        $this->use($element);

        return $case;
    }

    /**
     * Reads the xsd:boolean charge indicator of an allowance or charge; a missing or unrecognised
     * value drops the allowance or charge and reports why.
     */
    public function chargeIndicator(string $expression, DOMElement $allowanceCharge): ?bool
    {
        $indicator = match ($this->token($expression, $allowanceCharge)) {
            'true', '1' => true,
            'false', '0' => false,
            default => null,
        };
        if ($indicator === null) {
            $this->reject($allowanceCharge, 'dropped: no valid charge indicator');
        }

        return $indicator;
    }

    /**
     * A Base64 binary object with mimeCode and filename attributes (BT-125 with BT-125-1 and
     * BT-125-2), as both syntaxes carry it. A missing attribute, a code outside the closed list or
     * invalid Base64 drops the attachment and reports why.
     */
    public function attachment(?DOMElement $object): ?Attachment
    {
        if ($object === null) {
            return null;
        }
        if ($this->attribute($object, 'mimeCode') === null) {
            $this->reject($object, 'missing mime code', 'mimeCode');

            return null;
        }
        $mimeCode = $this->code(MimeCode::class, $object, 'mimeCode');
        if ($mimeCode === null) {
            return null;
        }
        $filename = $this->attribute($object, 'filename');
        if ($filename === null) {
            $this->reject($object, 'missing filename', 'filename');

            return null;
        }
        $content = base64_decode((string) preg_replace('/\s+/', '', $object->textContent), true);
        if ($content === false) {
            $this->reject($object, 'invalid Base64 content');

            return null;
        }
        $this->use($object);

        return new Attachment(content: $content, mimeCode: $mimeCode, filename: $filename);
    }

    /**
     * Reports the element (or one of its attributes) as not mapped, with a reason.
     */
    public function reject(DOMElement $element, string $reason, ?string $attribute = null): void
    {
        $this->reasons[] = [$element, $attribute, $reason];
        $this->handled[spl_object_id($element)] = $element;
        if ($element->parentNode instanceof DOMElement) {
            $this->use($element->parentNode);
        }
    }

    /**
     * XPath-like path with conventional prefixes and a position only where siblings share a name.
     *
     * Sibling positions are computed once per parent, so a path costs O(depth) after that. The
     * root carries a prefix only when its namespace has a conventional one.
     */
    public function path(DOMElement $element): string
    {
        $segments = [];
        for ($node = $element; $node instanceof DOMElement; $node = $node->parentNode) {
            if ($node->isSameNode($this->root)) {
                $prefix = $this->prefixes[(string) $node->namespaceURI] ?? null;
                $segments[] = ($prefix === null ? '' : $prefix.':').$node->localName;
                break;
            }
            $segments[] = $this->segment($node);
        }

        return '/'.implode('/', array_reverse($segments));
    }

    /**
     * Paths of everything not mapped, in the order found.
     *
     * @return list<string>
     */
    public function ignored(): array
    {
        $ignored = [];
        foreach ($this->reasons as [$element, $attribute, $reason]) {
            $ignored[] = sprintf('%s%s (%s)', $this->path($element), $attribute === null ? '' : '/@'.$attribute, $reason);
        }
        $this->walk($this->root, $ignored);

        return $ignored;
    }

    protected function missing(string $expression, DOMNode $context, string $term): UnsupportedDocument
    {
        $where = $context instanceof DOMElement ? $this->path($context) : '/';

        return new UnsupportedDocument(sprintf('Missing required %s (%s) in %s.', $term, $expression, $where));
    }

    /**
     * @param  list<string>  $ignored
     */
    private function walk(DOMElement $element, array &$ignored): void
    {
        for ($child = $element->firstElementChild; $child !== null; $child = $child->nextElementSibling) {
            $id = spl_object_id($child);
            if (isset($this->handled[$id])) {
                continue;
            }
            if (isset($this->touched[$id])) {
                $this->walk($child, $ignored);

                continue;
            }
            $ignored[] = $this->path($child);
        }
    }

    private function segment(DOMElement $element): string
    {
        $parent = $element->parentNode;
        if (! $parent instanceof DOMElement) {
            return $this->name($element);
        }

        $parentId = spl_object_id($parent);
        if (! isset($this->segments[$parentId])) {
            $this->segments[$parentId] = [$parent, $this->childSegments($parent)];
        }

        return $this->segments[$parentId][1][spl_object_id($element)][1] ?? $this->name($element);
    }

    /**
     * Segments of all child elements in one pass over the children, keyed by object id.
     *
     * @return array<int, array{DOMElement, string}>
     */
    private function childSegments(DOMElement $parent): array
    {
        $children = [];
        $counts = [];
        for ($child = $parent->firstElementChild; $child !== null; $child = $child->nextElementSibling) {
            $name = $this->name($child);
            $counts[$name] = ($counts[$name] ?? 0) + 1;
            $children[] = [$child, $name, $counts[$name]];
        }

        $segments = [];
        foreach ($children as [$child, $name, $position]) {
            $segments[spl_object_id($child)] = [$child, $counts[$name] > 1 ? "{$name}[{$position}]" : $name];
        }

        return $segments;
    }

    private function name(DOMElement $element): string
    {
        $namespace = (string) $element->namespaceURI;
        $local = (string) $element->localName;
        $prefix = $this->prefixes[$namespace] ?? ($element->prefix !== '' ? $element->prefix : null);

        return $prefix === null ? $local : $prefix.':'.$local;
    }
}
