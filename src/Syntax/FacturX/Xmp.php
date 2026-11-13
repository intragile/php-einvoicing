<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;

/**
 * Builds the XMP metadata packet of a Factur-X / ZUGFeRD PDF from the document's existing one.
 *
 * Every existing property is kept. The PDF/A identification is raised to part 3 (an existing
 * conformance level is kept, B otherwise), the four fx: properties of the Factur-X extension
 * schema and the PDF/A extension schema description that declares them are set, the modification
 * dates are updated, and the properties that mirror the PDF Info dictionary are filled in from
 * it where the XMP lacks them.
 */
final class Xmp
{
    public const string FX = 'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#';

    /** Namespace of the ZUGFeRD 2.0 extension schema, which also carries fx:ConformanceLevel. */
    public const string ZUGFERD_2 = 'urn:zugferd:pdfa:CrossIndustryDocument:invoice:2p0#';

    private const string X = 'adobe:ns:meta/';

    private const string RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    private const string XML = 'http://www.w3.org/XML/1998/namespace';

    private const string PDFAID = 'http://www.aiim.org/pdfa/ns/id/';

    private const string DC = 'http://purl.org/dc/elements/1.1/';

    private const string XMP = 'http://ns.adobe.com/xap/1.0/';

    private const string PDF = 'http://ns.adobe.com/pdf/1.3/';

    private const string EXTENSION = 'http://www.aiim.org/pdfa/ns/extension/';

    private const string SCHEMA = 'http://www.aiim.org/pdfa/ns/schema#';

    private const string PROPERTY = 'http://www.aiim.org/pdfa/ns/property#';

    private const array PREFIXES = [
        self::PDFAID => 'pdfaid',
        self::DC => 'dc',
        self::XMP => 'xmp',
        self::PDF => 'pdf',
        self::EXTENSION => 'pdfaExtension',
        self::FX => 'fx',
    ];

    /** The Factur-X extension schema as the FeRD reference files describe it. */
    private const string SCHEMA_NAME = 'Factur-X PDFA Extension Schema';

    /** @var array<string, string> property name => description */
    private const array FX_PROPERTIES = [
        'DocumentFileName' => 'The name of the embedded XML document',
        'DocumentType' => 'The type of the hybrid document in capital letters, e.g. INVOICE or ORDER',
        'Version' => 'The actual version of the standard applying to the embedded XML document',
        'ConformanceLevel' => 'The conformance level of the embedded XML document',
    ];

    private const string PACKET_ID = 'W5M0MpCehiHzreSzNTczkc9d';

    /** @var array<string, DOMElement> namespace => description created for it */
    private array $descriptions = [];

    private function __construct(private readonly DOMDocument $document, private readonly DOMElement $rdf, private readonly string $about) {}

    /**
     * The merged XMP packet.
     *
     * @param  array<string, string>  $info  decoded Info dictionary text entries (Title, Author,
     *                                       Subject, Keywords, Creator, Producer) and CreationDate
     *                                       as an ISO 8601 date
     *
     * @throws UnsafeXml when the existing XMP carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the existing XMP is not well-formed or not XMP
     */
    public static function merge(?string $existingXmp, FacturXProfile $profile, string $attachmentName, DateTimeImmutable $modified, array $info): string
    {
        $xmp = self::open($existingXmp);

        $xmp->set(self::PDFAID, 'part', '3');
        $xmp->setIfMissing(self::PDFAID, 'conformance', 'B');

        foreach ([
            'DocumentType' => 'INVOICE',
            'DocumentFileName' => $attachmentName,
            'Version' => $profile->xmpVersion(),
            'ConformanceLevel' => $profile->value,
        ] as $name => $value) {
            $xmp->set(self::FX, $name, $value);
        }
        $xmp->describeFacturXSchema();

        $date = $modified->format('Y-m-d\TH:i:sP');
        $xmp->set(self::XMP, 'ModifyDate', $date);
        $xmp->set(self::XMP, 'MetadataDate', $date);

        $xmp->setIfMissing(self::DC, 'title', $info['Title'] ?? '', 'Alt');
        $xmp->setIfMissing(self::DC, 'creator', $info['Author'] ?? '', 'Seq');
        $xmp->setIfMissing(self::DC, 'description', $info['Subject'] ?? '', 'Alt');
        $xmp->setIfMissing(self::PDF, 'Keywords', $info['Keywords'] ?? '');
        $xmp->setIfMissing(self::XMP, 'CreatorTool', $info['Creator'] ?? '');
        $xmp->setIfMissing(self::PDF, 'Producer', $info['Producer'] ?? '');
        $xmp->setIfMissing(self::XMP, 'CreateDate', $info['CreationDate'] ?? '');

        return $xmp->packet();
    }

    /**
     * The pdfaid:part the XMP declares, or null when it declares none or cannot be read.
     *
     * @throws UnsafeXml when the XMP carries a DOCTYPE declaration
     */
    public static function declaredPart(?string $xmp): ?int
    {
        $part = self::declared($xmp, [self::PDFAID], 'part');

        return $part !== null && ctype_digit($part) ? (int) $part : null;
    }

    /**
     * The ConformanceLevel of the Factur-X (or ZUGFeRD 2.0) extension schema, trimmed, or null
     * when the XMP declares none or cannot be read.
     *
     * @throws UnsafeXml when the XMP carries a DOCTYPE declaration
     */
    public static function declaredConformanceLevel(?string $xmp): ?string
    {
        return self::declared($xmp, [self::FX, self::ZUGFERD_2], 'ConformanceLevel');
    }

    /**
     * The trimmed text of the first property $name in one of $namespaces, in element or
     * attribute form, or null when there is none or the XMP cannot be read. Every rdf:RDF element
     * is searched: some producers write a second one beside the first.
     *
     * @param  list<string>  $namespaces
     *
     * @throws UnsafeXml when the XMP carries a DOCTYPE declaration
     */
    private static function declared(?string $xmp, array $namespaces, string $name): ?string
    {
        if ($xmp === null || trim($xmp) === '') {
            return null;
        }
        try {
            $document = SafeXml::load($xmp);
        } catch (UnsupportedDocument) {
            return null;
        }
        foreach ($namespaces as $namespace) {
            foreach ($document->getElementsByTagNameNS(self::RDF, 'RDF') as $rdf) {
                $value = self::findIn($rdf, $namespace, $name);
                if ($value !== null) {
                    return trim($value->textContent);
                }
            }
        }

        return null;
    }

    private static function open(?string $existing): self
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $meta = $document->createElementNS(self::X, 'x:xmpmeta');
        $document->appendChild($meta);

        $source = $existing === null || trim($existing) === '' ? null : SafeXml::load($existing)->getElementsByTagNameNS(self::RDF, 'RDF')->item(0);
        if ($existing !== null && trim($existing) !== '' && $source === null) {
            throw new UnsupportedDocument('The existing XMP metadata has no rdf:RDF element.');
        }
        $rdf = $source !== null ? $document->importNode($source, true) : $document->createElementNS(self::RDF, 'rdf:RDF');
        assert($rdf instanceof DOMElement);
        $meta->appendChild($rdf);

        $about = '';
        foreach (self::descriptionsOf($rdf) as $description) {
            $about = $description->getAttributeNS(self::RDF, 'about');
            break;
        }

        return new self($document, $rdf, $about);
    }

    /** @return list<DOMElement> */
    private static function descriptionsOf(DOMElement $rdf): array
    {
        $descriptions = [];
        foreach ($rdf->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::RDF && $child->localName === 'Description') {
                $descriptions[] = $child;
            }
        }

        return $descriptions;
    }

    /** The property as an element or as an attribute (the XMP shorthand form), or null. */
    private function find(string $namespace, string $name): ?DOMNode
    {
        return self::findIn($this->rdf, $namespace, $name);
    }

    private static function findIn(DOMElement $rdf, string $namespace, string $name): ?DOMNode
    {
        foreach (self::descriptionsOf($rdf) as $description) {
            $attribute = $description->getAttributeNodeNS($namespace, $name);
            if ($attribute instanceof DOMNode) {
                return $attribute;
            }
            foreach ($description->childNodes as $child) {
                if ($child instanceof DOMElement && $child->namespaceURI === $namespace && $child->localName === $name) {
                    return $child;
                }
            }
        }

        return null;
    }

    private function set(string $namespace, string $name, string $value): void
    {
        while (($existing = $this->find($namespace, $name)) !== null) {
            if ($existing instanceof DOMElement) {
                $existing->parentNode?->removeChild($existing);
            } else {
                $owner = $existing->parentNode;
                assert($owner instanceof DOMElement);
                $owner->removeAttributeNS($namespace, $name);
            }
        }
        $this->append($namespace, $name, $value);
    }

    /**
     * Adds a property unless it exists already or $value is empty. $container makes it an
     * rdf:Alt (with an x-default language) or rdf:Seq of one item.
     */
    private function setIfMissing(string $namespace, string $name, string $value, ?string $container = null): void
    {
        $value = self::clean($value);
        if ($value === '' || $this->find($namespace, $name) !== null) {
            return;
        }
        if ($container === null) {
            $this->append($namespace, $name, $value);

            return;
        }
        $property = $this->append($namespace, $name, null);
        $list = $property->appendChild($this->element(self::RDF, 'rdf:'.$container));
        $item = $list->appendChild($this->element(self::RDF, 'rdf:li', $value));
        if ($container === 'Alt' && $item instanceof DOMElement) {
            $item->setAttributeNS(self::XML, 'xml:lang', 'x-default');
        }
    }

    private function append(string $namespace, string $name, ?string $value): DOMElement
    {
        $prefix = self::PREFIXES[$namespace];
        $element = $this->element($namespace, $prefix.':'.$name, $value);
        $this->description($namespace)->appendChild($element);

        return $element;
    }

    private function description(string $namespace): DOMElement
    {
        if (! isset($this->descriptions[$namespace])) {
            $description = $this->element(self::RDF, 'rdf:Description');
            $description->setAttributeNS(self::RDF, 'rdf:about', $this->about);
            $description->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:'.self::PREFIXES[$namespace], $namespace);
            $this->rdf->appendChild($description);
            $this->descriptions[$namespace] = $description;
        }

        return $this->descriptions[$namespace];
    }

    /** Replaces any existing description of the fx schema in pdfaExtension:schemas with the reference one. */
    private function describeFacturXSchema(): void
    {
        $bag = null;
        foreach (iterator_to_array($this->rdf->getElementsByTagNameNS(self::EXTENSION, 'schemas')) as $schemas) {
            foreach ($schemas->childNodes as $candidate) {
                if ($candidate instanceof DOMElement && $candidate->namespaceURI === self::RDF && $candidate->localName === 'Bag') {
                    $bag ??= $candidate;
                    foreach (iterator_to_array($candidate->childNodes) as $item) {
                        if ($item instanceof DOMElement && $this->describesFacturX($item)) {
                            $candidate->removeChild($item);
                        }
                    }
                }
            }
        }
        $empty = $this->rdf->getElementsByTagNameNS(self::EXTENSION, 'schemas')->item(0);
        if ($bag === null && $empty instanceof DOMElement) {
            // An existing pdfaExtension:schemas without a bag gets one, not a second property.
            $bag = $this->element(self::RDF, 'rdf:Bag');
            $empty->appendChild($bag);
        }
        if ($bag === null) {
            $schemas = $this->append(self::EXTENSION, 'schemas', null);
            $description = $schemas->parentNode;
            assert($description instanceof DOMElement);
            $description->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:pdfaSchema', self::SCHEMA);
            $description->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:pdfaProperty', self::PROPERTY);
            $bag = $this->element(self::RDF, 'rdf:Bag');
            $schemas->appendChild($bag);
        }

        $schema = $this->resource();
        $schema->appendChild($this->element(self::SCHEMA, 'pdfaSchema:schema', self::SCHEMA_NAME));
        $schema->appendChild($this->element(self::SCHEMA, 'pdfaSchema:namespaceURI', self::FX));
        $schema->appendChild($this->element(self::SCHEMA, 'pdfaSchema:prefix', 'fx'));
        $properties = $this->element(self::RDF, 'rdf:Seq');
        foreach (self::FX_PROPERTIES as $name => $text) {
            $property = $this->resource();
            $property->appendChild($this->element(self::PROPERTY, 'pdfaProperty:name', $name));
            $property->appendChild($this->element(self::PROPERTY, 'pdfaProperty:valueType', 'Text'));
            $property->appendChild($this->element(self::PROPERTY, 'pdfaProperty:category', 'external'));
            $property->appendChild($this->element(self::PROPERTY, 'pdfaProperty:description', $text));
            $properties->appendChild($property);
        }
        $schema->appendChild($this->element(self::SCHEMA, 'pdfaSchema:property'))->appendChild($properties);
        $bag->appendChild($schema);
    }

    private function describesFacturX(DOMElement $item): bool
    {
        foreach ($item->getElementsByTagNameNS(self::SCHEMA, 'namespaceURI') as $uri) {
            if (trim($uri->textContent) === self::FX) {
                return true;
            }
        }

        return $item->getAttributeNS(self::SCHEMA, 'namespaceURI') === self::FX;
    }

    /** An rdf:li with rdf:parseType="Resource". */
    private function resource(): DOMElement
    {
        $item = $this->element(self::RDF, 'rdf:li');
        $item->setAttributeNS(self::RDF, 'rdf:parseType', 'Resource');

        return $item;
    }

    private function element(string $namespace, string $qualifiedName, ?string $text = null): DOMElement
    {
        $element = $this->document->createElementNS($namespace, $qualifiedName);
        if ($text !== null) {
            $element->appendChild($this->document->createTextNode($text));
        }

        return $element;
    }

    private function packet(): string
    {
        // Elements built detached carry their own namespace declarations; a reparse with
        // LIBXML_NSCLEAN drops the redundant ones.
        $clean = new DOMDocument('1.0', 'UTF-8');
        $clean->formatOutput = true;
        $clean->loadXML((string) $this->document->saveXML($this->document->documentElement), LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_NOBLANKS);
        $xml = $clean->saveXML($clean->documentElement);

        return '<?xpacket begin="'."\u{FEFF}".'" id="'.self::PACKET_ID.'"?>'."\n".$xml."\n".'<?xpacket end="w"?>';
    }

    /** Valid UTF-8 without the control characters XML 1.0 forbids. */
    private static function clean(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', mb_scrub($value, 'UTF-8')));
    }
}
