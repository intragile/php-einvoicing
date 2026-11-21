<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Session;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Transport\Ksef\KsefAnswer;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\MessageType;
use IntrAgile\EInvoicing\Validation\MessageValidationReport;
use IntrAgile\EInvoicing\Validation\SchemaValidator;

/**
 * A KSeF official receipt (UPO, Urzędowe Poświadczenie Odbioru), schema version 4-3.
 *
 * The raw bytes are kept unchanged as the proof of receipt; the parsed fields are a convenience.
 * The Ministry of Finance signature enveloped in the receipt is not verified. A receipt for one
 * invoice lists one document; a session receipt may list up to 10,000, in which case the
 * per-document fields documentHash and acceptedAt are null.
 */
final readonly class Upo
{
    public const NAMESPACE = 'http://upo.schematy.mf.gov.pl/KSeF/v4-3';

    private const ROOT = 'Potwierdzenie';

    private const DS_NAMESPACE = 'http://www.w3.org/2000/09/xmldsig#';

    private const SCHEMA_RUN = 'upo-xsd 4-3';

    /** The receiver name the schema fixes. */
    private const RECEIVER = 'Ministerstwo Finansów';

    /** The receiver name the TEST environment (TE) writes instead. */
    private const TEST_RECEIVER = 'Ministerstwo Finansów - środowisko testowe (TE)';

    /**
     * @param  string  $xml  the receipt as received
     * @param  string  $sessionReference  the reference number of the session (NumerReferencyjnySesji)
     * @param  string  $contextNip  the NIP of the authentication context (IdKontekstu)
     * @param  list<string>  $ksefNumbers  the KSeF numbers of the listed documents, in document order
     * @param  string|null  $documentHash  the Base64 SHA-256 digest of the only listed document
     * @param  DateTimeImmutable|null  $acceptedAt  when the KSeF number of the only listed document
     *                                              was assigned
     */
    private function __construct(
        public string $xml,
        public string $sessionReference,
        public string $contextNip,
        public array $ksefNumbers,
        public ?string $documentHash,
        public ?DateTimeImmutable $acceptedAt,
    ) {}

    /**
     * @throws UnsafeXml when the receipt carries a DOCTYPE declaration
     * @throws UnsupportedDocument when it is not well-formed XML, its document element is not a
     *                             UPO 4-3 Potwierdzenie, or it lacks the session reference or the
     *                             context NIP
     */
    public static function parse(string $xml): self
    {
        $root = self::root(SafeXml::load($xml));

        $sessionReference = self::text($root, 'NumerReferencyjnySesji') ?? throw new UnsupportedDocument('The UPO has no NumerReferencyjnySesji.');
        $context = self::child(self::child($root, 'Uwierzytelnienie'), 'IdKontekstu');
        $contextNip = self::contextNip($context) ?? throw new UnsupportedDocument('The UPO names no NIP in Uwierzytelnienie/IdKontekstu.');

        $ksefNumbers = [];
        $documents = [];
        foreach (self::children($root, 'Dokument') as $document) {
            $documents[] = $document;
            $number = self::text($document, 'NumerKSeFDokumentu');
            if ($number !== null) {
                $ksefNumbers[] = $number;
            }
        }
        $only = count($documents) === 1 ? $documents[0] : null;
        $acceptedAt = $only === null ? null : self::text($only, 'DataNadaniaNumeruKSeF');

        return new self(
            $xml,
            $sessionReference,
            $contextNip,
            $ksefNumbers,
            $only === null ? null : self::text($only, 'SkrotDokumentu'),
            $acceptedAt === null ? null : KsefAnswer::parseDateTime($acceptedAt),
        );
    }

    /**
     * Validates the receipt against the bundled UPO 4-3 XML Schema, without the enveloped
     * signature the schema does not declare. For the TEST environment the receiver name TE
     * writes is accepted in place of the fixed "Ministerstwo Finansów".
     *
     * @throws UnusableSchema when the bundled schema cannot be used
     */
    public function validate(KsefEnvironment $environment): MessageValidationReport
    {
        $document = SafeXml::loadPreservingWhitespace($this->xml);
        $root = self::root($document);
        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node instanceof DOMElement && $node->namespaceURI === self::DS_NAMESPACE && $node->localName === 'Signature') {
                $root->removeChild($node);
            }
        }
        if ($environment === KsefEnvironment::Test) {
            $receiver = self::child($root, 'NazwaPodmiotuPrzyjmujacego');
            if ($receiver !== null && trim($receiver->textContent) === self::TEST_RECEIVER) {
                $receiver->textContent = self::RECEIVER;
            }
        }

        return new MessageValidationReport(
            MessageType::Upo,
            [self::SCHEMA_RUN],
            [],
            [],
            (new SchemaValidator)->validate($document, dirname(__DIR__, 4).'/resources/poland/upo-4-3/upo-v4-3.xsd'),
        );
    }

    /**
     * @throws UnsupportedDocument
     */
    private static function root(DOMDocument $document): DOMElement
    {
        $root = $document->documentElement;
        if ($root === null || $root->namespaceURI !== self::NAMESPACE || $root->localName !== self::ROOT) {
            throw new UnsupportedDocument(sprintf(
                'The document element {%s}%s is not a KSeF UPO; expected {%s}%s.',
                $root->namespaceURI ?? '',
                $root->localName ?? '',
                self::NAMESPACE,
                self::ROOT,
            ));
        }

        return $root;
    }

    /**
     * The NIP of the context: the Nip element, or the NIP that an internal id or a NIP-VAT UE
     * composite id starts with.
     */
    private static function contextNip(?DOMElement $context): ?string
    {
        $nip = self::text($context, 'Nip');
        if ($nip !== null) {
            return $nip;
        }
        foreach (['IdWewnetrzny', 'IdZlozonyVatUE'] as $name) {
            $id = self::text($context, $name);
            if ($id !== null && preg_match('/\A(\d{10})-/', $id, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    /**
     * The trimmed text of the first child element with the given local name, or null when it is
     * missing or empty.
     */
    private static function text(?DOMElement $parent, string $name): ?string
    {
        $element = self::child($parent, $name);
        $text = $element === null ? '' : trim($element->textContent);

        return $text === '' ? null : $text;
    }

    private static function child(?DOMElement $parent, string $name): ?DOMElement
    {
        foreach (self::children($parent, $name) as $child) {
            return $child;
        }

        return null;
    }

    /**
     * @return iterable<DOMElement>
     */
    private static function children(?DOMElement $parent, string $name): iterable
    {
        if ($parent === null) {
            return;
        }
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->namespaceURI === self::NAMESPACE && $node->localName === $name) {
                yield $node;
            }
        }
    }
}
