<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use IntrAgile\EInvoicing\Syntax\Cii\CiiDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\FileOnlyEntityLoader;
use InvalidArgumentException;
use RuntimeException;

/**
 * Reduces a CII document to the elements and attributes a profile XML Schema allows.
 *
 * The schema and the files it imports are read into a table of types, each with the child
 * elements and the attributes it allows. The document is then walked depth-first from the root:
 * every attribute the element's type does not declare, and every child element the type has no
 * declaration for, is removed together with its subtree. The remaining nodes keep their order and
 * their content. Every removed node is reported by its path; nothing is removed silently. The
 * reduction looks only at which names a type allows, never at cardinality or values, so the
 * result can still lack an element the schema requires.
 *
 * Only the XML Schema constructs the Factur-X 1.09.2 profile schemas use are supported:
 * - xs:schema with targetNamespace, elementFormDefault="qualified" and optionally
 *   attributeFormDefault="unqualified";
 * - xs:import with namespace and schemaLocation, resolved next to the importing file;
 * - global xs:element with name and type;
 * - local xs:element with name, minOccurs, maxOccurs and either type or an anonymous
 *   xs:complexType;
 * - xs:complexType, named or anonymous, that is empty or holds one xs:sequence, xs:choice or
 *   xs:simpleContent;
 * - xs:sequence and xs:choice holding local xs:element declarations;
 * - xs:simpleContent holding one xs:extension of a built-in or named simple type, with
 *   xs:attribute children (name, type, use, default);
 * - named xs:simpleType holding one xs:restriction of a base type without facets.
 * Any other construct, attribute or unresolved type reference raises UnusableSchema naming it, so
 * an unexpected schema is never reduced by guesswork. Schema files are read only from local
 * paths, with the same file-only entity loader as SchemaValidator.
 *
 * Paths use the conventional CII prefixes (rsm, ram, udt, qdt) and carry no sibling positions,
 * so a repeated element that is removed is listed once.
 */
final class ProfileReducer
{
    private const XS = 'http://www.w3.org/2001/XMLSchema';

    private const PREFIXES = [
        CiiDocument::RSM => 'rsm',
        CiiDocument::RAM => 'ram',
        CiiDocument::UDT => 'udt',
        CiiDocument::QDT => 'qdt',
    ];

    /** Type key of every type without child elements or attributes. */
    private const SIMPLE = '#simple';

    /** Attributes each supported construct may carry. */
    private const ATTRIBUTES = [
        'schema' => ['targetNamespace', 'elementFormDefault', 'attributeFormDefault'],
        'import' => ['namespace', 'schemaLocation'],
        'global element' => ['name', 'type'],
        'local element' => ['name', 'type', 'minOccurs', 'maxOccurs'],
        'complexType' => [],
        'named complexType' => ['name'],
        'sequence' => [],
        'choice' => [],
        'simpleContent' => [],
        'extension' => ['base'],
        'attribute' => ['name', 'type', 'use', 'default'],
        'simpleType' => ['name'],
        'restriction' => ['base'],
    ];

    /**
     * Compiled schemas by top-level schema path.
     *
     * @var array<string, array{elements: array<string, string>, types: array<string, array{children: array<string, string>, attributes: array<string, true>}>}>
     */
    private array $schemas = [];

    /** @var array<string, DOMElement> named xs:complexType declarations by "{namespace}name" */
    private array $complexTypes = [];

    /** @var array<string, DOMElement> named xs:simpleType declarations by "{namespace}name" */
    private array $simpleTypes = [];

    /** @var array<string, DOMElement> global xs:element declarations by "{namespace}name" */
    private array $globalElements = [];

    /** @var array<string, array{children: array<string, string>, attributes: array<string, true>}> */
    private array $types = [];

    private int $anonymous = 0;

    /**
     * @throws UnusableSchema when the schema cannot be read or uses an unsupported construct
     * @throws InvalidArgumentException when the schema declares no element for the document root
     */
    public function reduce(string $ciiXml, string $xsdPath): ReducedXml
    {
        $schema = $this->schemas[$xsdPath] ??= $this->compile($xsdPath);

        $document = SafeXml::loadPreservingWhitespace($ciiXml);
        $root = $document->documentElement;
        if (! $root instanceof DOMElement) {
            throw new InvalidArgumentException('The document has no root element.');
        }
        $type = $schema['elements'][self::clark($root->namespaceURI, (string) $root->localName)]
            ?? throw new InvalidArgumentException(sprintf(
                'The XML Schema %s declares no element {%s}%s for the document root.',
                $xsdPath,
                (string) $root->namespaceURI,
                (string) $root->localName,
            ));

        $removed = [];
        $this->walk($root, $type, '/'.self::name($root), $schema['types'], $removed);
        if ($removed === []) {
            return new ReducedXml($ciiXml, []);
        }

        $removed = array_values(array_unique($removed));
        sort($removed, SORT_STRING);

        return new ReducedXml(self::serialise($document), $removed);
    }

    /**
     * @param  array<string, array{children: array<string, string>, attributes: array<string, true>}>  $types
     * @param  list<string>  $removed
     */
    private function walk(DOMElement $element, string $typeKey, string $path, array $types, array &$removed): void
    {
        $type = $types[$typeKey];

        foreach (self::attributes($element) as $attribute) {
            if ($attribute->namespaceURI === null && isset($type['attributes'][$attribute->name])) {
                continue;
            }
            $element->removeAttributeNode($attribute);
            $removed[] = $path.'/@'.self::attributeName($attribute);
        }

        $emptied = false;
        $child = $element->firstElementChild;
        while ($child !== null) {
            $next = $child->nextElementSibling;
            $childPath = $path.'/'.self::name($child);
            $childType = $type['children'][self::clark($child->namespaceURI, (string) $child->localName)] ?? null;
            if ($childType === null) {
                self::remove($child);
                $removed[] = $childPath;
                $emptied = true;
            } else {
                $this->walk($child, $childType, $childPath, $types, $removed);
            }
            $child = $next;
        }

        // An element whose children were all removed keeps no indentation, because a type with
        // empty content allows no character content at all, not even whitespace.
        if ($emptied && $element->firstElementChild === null) {
            for ($node = $element->firstChild; $node !== null; $node = $nextNode) {
                $nextNode = $node->nextSibling;
                if ($node instanceof DOMText && trim($node->data) === '') {
                    $node->remove();
                }
            }
        }
    }

    /**
     * Removes the element and the indentation in front of it.
     */
    private static function remove(DOMElement $element): void
    {
        $previous = $element->previousSibling;
        if ($previous instanceof DOMText && trim($previous->data) === '') {
            $previous->remove();
        }
        $element->remove();
    }

    /**
     * @return array{elements: array<string, string>, types: array<string, array{children: array<string, string>, attributes: array<string, true>}>}
     */
    private function compile(string $xsdPath): array
    {
        $this->reset();
        $this->collect($xsdPath, []);

        foreach ($this->simpleTypes as $declaration) {
            $this->checkSimpleType($declaration);
        }
        foreach (array_keys($this->complexTypes) as $key) {
            $this->namedComplexType($key);
        }
        $elements = [];
        foreach ($this->globalElements as $key => $declaration) {
            $elements[$key] = $this->resolveType($declaration, $declaration->getAttribute('type'));
        }

        return ['elements' => $elements, 'types' => $this->types];
    }

    private function reset(): void
    {
        $this->complexTypes = [];
        $this->simpleTypes = [];
        $this->globalElements = [];
        $this->types = [self::SIMPLE => ['children' => [], 'attributes' => []]];
        $this->anonymous = 0;
    }

    /**
     * Reads a schema file and, recursively, the files it imports.
     *
     * @param  array<string, true>  $seen
     * @return array<string, true>
     */
    private function collect(string $path, array $seen): array
    {
        $real = realpath($path);
        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            throw new UnusableSchema("The XML Schema {$path} does not exist or is not readable.");
        }
        if (isset($seen[$real])) {
            return $seen;
        }
        $seen[$real] = true;

        $schema = self::load($real);
        self::expect($schema, 'schema', 'schema');
        if ($schema->getAttribute('elementFormDefault') !== 'qualified') {
            self::unsupported($schema, 'elementFormDefault other than "qualified"');
        }
        if ($schema->hasAttribute('attributeFormDefault') && $schema->getAttribute('attributeFormDefault') !== 'unqualified') {
            self::unsupported($schema, 'attributeFormDefault other than "unqualified"');
        }
        $namespace = $schema->getAttribute('targetNamespace');

        foreach (self::children($schema) as $child) {
            switch ($child->localName) {
                case 'import':
                    self::expect($child, 'import', 'import', false);
                    $location = $child->getAttribute('schemaLocation');
                    if ($location === '' || preg_match('~^[a-z][a-z0-9+.-]+:~i', $location) === 1) {
                        self::unsupported($child, "xs:import with schemaLocation \"{$location}\"");
                    }
                    $seen = $this->collect(dirname($real).'/'.$location, $seen);
                    break;
                case 'element':
                    self::expect($child, 'element', 'global element', false);
                    $this->globalElements[self::clark($namespace, self::requiredName($child))] = $child;
                    break;
                case 'complexType':
                    $this->complexTypes[self::clark($namespace, self::requiredName($child))] = $child;
                    break;
                case 'simpleType':
                    $this->simpleTypes[self::clark($namespace, self::requiredName($child))] = $child;
                    break;
                default:
                    self::unsupported($child, 'xs:'.$child->localName.' in xs:schema');
            }
        }

        return $seen;
    }

    private static function load(string $path): DOMElement
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $refused = [];

        try {
            $document = new DOMDocument;
            $loaded = FileOnlyEntityLoader::run(static fn (): bool => $document->load($path, LIBXML_NONET), $refused);
            if ($refused !== []) {
                throw new UnusableSchema("The XML Schema {$path} cannot be used: network access is refused for ".implode(', ', array_unique($refused)).'.');
            }
            $error = libxml_get_last_error();
            if (! $loaded || ! $document->documentElement instanceof DOMElement) {
                throw new UnusableSchema("The XML Schema {$path} cannot be parsed".($error === false ? '.' : ': '.trim($error->message)));
            }

            return $document->documentElement;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function checkSimpleType(DOMElement $declaration): void
    {
        self::expect($declaration, 'simpleType', 'simpleType');
        $children = self::children($declaration);
        if (count($children) !== 1) {
            self::unsupported($declaration, 'xs:simpleType without exactly one xs:restriction');
        }
        self::expect($children[0], 'restriction', 'restriction', false);
    }

    /**
     * @return array{children: array<string, string>, attributes: array<string, true>}
     */
    private function complexType(DOMElement $declaration): array
    {
        self::expect($declaration, 'complexType', $declaration->hasAttribute('name') ? 'named complexType' : 'complexType');
        $content = self::children($declaration);
        if ($content === []) {
            return ['children' => [], 'attributes' => []];
        }
        if (count($content) > 1) {
            self::unsupported($content[1], 'xs:'.$content[1]->localName.' after xs:'.$content[0]->localName.' in xs:complexType');
        }

        $model = $content[0];
        if ($model->localName === 'simpleContent') {
            return ['children' => [], 'attributes' => $this->simpleContent($model)];
        }
        if ($model->localName !== 'sequence' && $model->localName !== 'choice') {
            self::unsupported($model, 'xs:'.$model->localName.' in xs:complexType');
        }
        self::expect($model, $model->localName, $model->localName);

        $children = [];
        foreach (self::children($model) as $element) {
            if ($element->localName !== 'element') {
                self::unsupported($element, 'xs:'.$element->localName.' in xs:'.$model->localName);
            }
            self::expect($element, 'element', 'local element');
            $key = self::clark(self::targetNamespace($element), self::requiredName($element));
            $type = $this->localElementType($element);
            if (isset($children[$key]) && $children[$key] !== $type) {
                self::unsupported($element, "two declarations of {$key} with different types");
            }
            $children[$key] = $type;
        }

        return ['children' => $children, 'attributes' => []];
    }

    /**
     * @return array<string, true>
     */
    private function simpleContent(DOMElement $simpleContent): array
    {
        self::expect($simpleContent, 'simpleContent', 'simpleContent');
        $children = self::children($simpleContent);
        if (count($children) !== 1 || $children[0]->localName !== 'extension') {
            self::unsupported($simpleContent, 'xs:simpleContent without exactly one xs:extension');
        }
        $extension = $children[0];
        self::expect($extension, 'extension', 'extension');
        if ($this->resolveType($extension, $extension->getAttribute('base')) !== self::SIMPLE) {
            self::unsupported($extension, 'xs:extension of the complex type '.$extension->getAttribute('base'));
        }

        $attributes = [];
        foreach (self::children($extension) as $attribute) {
            if ($attribute->localName !== 'attribute') {
                self::unsupported($attribute, 'xs:'.$attribute->localName.' in xs:extension');
            }
            self::expect($attribute, 'attribute', 'attribute', false);
            $attributes[self::requiredName($attribute)] = true;
        }

        return $attributes;
    }

    private function localElementType(DOMElement $element): string
    {
        $inline = self::children($element);
        if ($element->hasAttribute('type')) {
            if ($inline !== []) {
                self::unsupported($inline[0], 'xs:'.$inline[0]->localName.' in an xs:element with a type attribute');
            }

            return $this->resolveType($element, $element->getAttribute('type'));
        }
        if (count($inline) !== 1 || $inline[0]->localName !== 'complexType') {
            self::unsupported($element, 'xs:element without a type attribute or exactly one anonymous xs:complexType');
        }
        $key = self::clark(self::targetNamespace($element), '#anonymous-'.++$this->anonymous);
        $this->types[$key] = $this->complexType($inline[0]);

        return $key;
    }

    /**
     * Type key of a QName reference: SIMPLE for built-in and named simple types, otherwise the
     * key of the named complex type, which is compiled on first use.
     */
    private function resolveType(DOMElement $context, string $qname): string
    {
        if ($qname === '') {
            self::unsupported($context, 'xs:'.$context->localName.' without a type');
        }
        $colon = strpos($qname, ':');
        $prefix = $colon === false ? null : substr($qname, 0, $colon);
        $local = $colon === false ? $qname : substr($qname, $colon + 1);
        $namespace = $context->lookupNamespaceURI($prefix);
        if ($namespace === null && $prefix !== null) {
            self::unsupported($context, "the undeclared prefix of the type {$qname}");
        }
        $key = self::clark($namespace, $local);

        if ($namespace === self::XS) {
            if (in_array($local, ['anyType', 'anySimpleType'], true)) {
                self::unsupported($context, "the type xs:{$local}");
            }

            return self::SIMPLE;
        }
        if (isset($this->simpleTypes[$key])) {
            return self::SIMPLE;
        }
        if (isset($this->complexTypes[$key])) {
            return $this->namedComplexType($key);
        }

        self::unsupported($context, "the unresolved type reference {$qname}");
    }

    /**
     * Compiles a named complex type once. A placeholder entry is stored first, so a type that
     * refers to itself does not recurse endlessly.
     */
    private function namedComplexType(string $key): string
    {
        if (! isset($this->types[$key])) {
            $this->types[$key] = ['children' => [], 'attributes' => []];
            $this->types[$key] = $this->complexType($this->complexTypes[$key]);
        }

        return $key;
    }

    /**
     * Checks that the node is the expected xs: construct and carries only supported attributes
     * and, unless children are allowed, no child elements.
     */
    private static function expect(DOMElement $node, string $localName, string $construct, bool $childrenAllowed = true): void
    {
        if ($node->namespaceURI !== self::XS || $node->localName !== $localName) {
            self::unsupported($node, 'the element {'.(string) $node->namespaceURI.'}'.(string) $node->localName);
        }
        foreach (self::attributes($node) as $attribute) {
            if ($attribute->namespaceURI !== null || ! in_array($attribute->name, self::ATTRIBUTES[$construct], true)) {
                self::unsupported($node, "the attribute {$attribute->nodeName} on xs:{$localName}");
            }
        }
        if (! $childrenAllowed && self::children($node) !== []) {
            self::unsupported($node, 'content in xs:'.$localName);
        }
    }

    /**
     * Child elements, refusing any that are not in the XML Schema namespace; comments and
     * whitespace are skipped.
     *
     * @return list<DOMElement>
     */
    private static function children(DOMElement $node): array
    {
        $children = [];
        for ($child = $node->firstChild; $child !== null; $child = $child->nextSibling) {
            if ($child instanceof DOMElement) {
                if ($child->namespaceURI !== self::XS) {
                    self::unsupported($child, 'the element {'.(string) $child->namespaceURI.'}'.(string) $child->localName);
                }
                $children[] = $child;
            } elseif ($child instanceof DOMText && trim($child->data) !== '') {
                self::unsupported($node, 'text content in xs:'.$node->localName);
            }
        }

        return $children;
    }

    private static function requiredName(DOMElement $declaration): string
    {
        $name = $declaration->getAttribute('name');
        if ($name === '') {
            self::unsupported($declaration, 'xs:'.$declaration->localName.' without a name (e.g. a ref)');
        }

        return $name;
    }

    private static function targetNamespace(DOMNode $node): string
    {
        $schema = $node->ownerDocument?->documentElement;

        return $schema instanceof DOMElement ? $schema->getAttribute('targetNamespace') : '';
    }

    private static function unsupported(DOMElement $node, string $construct): never
    {
        throw new UnusableSchema(sprintf(
            'The XML Schema %s cannot be used for profile reduction: unsupported %s (line %d).',
            (string) $node->ownerDocument?->documentURI,
            $construct,
            $node->getLineNo(),
        ));
    }

    private static function clark(?string $namespace, string $localName): string
    {
        return '{'.($namespace ?? '').'}'.$localName;
    }

    /**
     * Path of an element or attribute in the notation of ReducedXml::$removed: element names
     * without sibling positions, an attribute as the last step.
     */
    public static function pathOf(DOMNode $node): string
    {
        if ($node instanceof DOMAttr) {
            return self::pathOf($node->ownerElement ?? throw new InvalidArgumentException('The attribute has no element.')).'/@'.self::attributeName($node);
        }
        $steps = [];
        for (; $node instanceof DOMElement; $node = $node->parentNode) {
            $steps[] = self::name($node);
        }

        return '/'.implode('/', array_reverse($steps));
    }

    private static function name(DOMElement $element): string
    {
        return self::qualified($element->namespaceURI, (string) $element->localName);
    }

    private static function attributeName(DOMAttr $attribute): string
    {
        return self::qualified($attribute->namespaceURI, $attribute->localName ?? $attribute->name);
    }

    /**
     * The attributes of an element as a list, so they can be removed while iterating.
     *
     * @return list<DOMAttr>
     */
    private static function attributes(DOMElement $element): array
    {
        return iterator_to_array($element->attributes, false);
    }

    private static function qualified(?string $namespace, string $localName): string
    {
        if ($namespace === null || $namespace === '') {
            return $localName;
        }
        $prefix = self::PREFIXES[$namespace] ?? null;

        return $prefix === null ? "Q{{$namespace}}{$localName}" : "{$prefix}:{$localName}";
    }

    private static function serialise(DOMDocument $document): string
    {
        $xml = $document->saveXML();
        if ($xml === false) {
            throw new RuntimeException('The reduced document could not be serialised.');
        }

        return $xml;
    }
}
