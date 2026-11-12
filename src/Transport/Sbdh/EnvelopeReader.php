<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Sbdh;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use Exception;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;
use InvalidArgumentException;

/**
 * Reads a Peppol Business Message Envelope (Standard Business Document) and separates the header
 * metadata from the business document it carries. Elements are matched by namespace, whatever
 * prefix the sender chose.
 */
final class EnvelopeReader
{
    private const NAMESPACE_URI = EnvelopeWriter::NAMESPACE_URI;

    /** Scopes mapped to Envelope properties; every other scope type is an additional scope. */
    private const MANDATORY_SCOPES = ['DOCUMENTID', 'PROCESSID', 'COUNTRY_C1'];

    /** Lower bound of BusinessScope/Scope in the Peppol envelope specification. */
    private const MINIMUM_SCOPES = 3;

    /**
     * @return array{Envelope, string} envelope and the bare payload XML
     *
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the document is not a Standard Business Document or lacks mandatory header data
     */
    public function unwrap(string $xml): array
    {
        $root = SafeXml::loadPreservingWhitespace($xml)->documentElement
            ?? throw new UnsupportedDocument('The document has no root element.');
        if (! self::isEnvelopeRoot($root)) {
            throw new UnsupportedDocument(sprintf(
                'The document root {%s}%s is not a Standard Business Document.',
                (string) $root->namespaceURI,
                (string) $root->localName,
            ));
        }

        $header = self::required($root, 'StandardBusinessDocumentHeader', 'StandardBusinessDocument');
        $path = 'StandardBusinessDocumentHeader';
        self::required($header, 'HeaderVersion', $path);
        $sender = self::participant($header, 'Sender');
        $receiver = self::participant($header, 'Receiver');

        $identification = self::required($header, 'DocumentIdentification', $path);
        $identificationPath = $path.'/DocumentIdentification';
        foreach (['Standard', 'TypeVersion', 'Type'] as $name) {
            self::text($identification, $name, $identificationPath);
        }
        $instanceIdentifier = self::text($identification, 'InstanceIdentifier', $identificationPath);
        $createdAt = self::dateTime(self::text($identification, 'CreationDateAndTime', $identificationPath));

        $scopes = self::scopes(self::required($header, 'BusinessScope', $path));

        $envelope = new Envelope(
            $sender,
            $receiver,
            new DocumentTypeId($scopes['DOCUMENTID']),
            new ProcessId($scopes['PROCESSID']),
            $scopes['COUNTRY_C1'],
            $instanceIdentifier,
            $createdAt,
            array_diff_key($scopes, array_flip(self::MANDATORY_SCOPES)),
        );

        return [$envelope, self::payload($root, $header)];
    }

    /**
     * Whether the XML is a Standard Business Document. Malformed or unsafe XML is not.
     */
    public static function isEnvelope(string $xml): bool
    {
        try {
            $root = SafeXml::load($xml)->documentElement;
        } catch (UnsafeXml|UnsupportedDocument) {
            return false;
        }

        return $root !== null && self::isEnvelopeRoot($root);
    }

    private static function isEnvelopeRoot(DOMElement $root): bool
    {
        return $root->namespaceURI === self::NAMESPACE_URI && $root->localName === 'StandardBusinessDocument';
    }

    private static function participant(DOMElement $header, string $role): ParticipantId
    {
        $path = 'StandardBusinessDocumentHeader/'.$role;
        $identifier = self::required(self::required($header, $role, 'StandardBusinessDocumentHeader'), 'Identifier', $path);
        $authority = $identifier->getAttribute('Authority');
        if ($authority !== '' && $authority !== ParticipantId::SCHEME) {
            throw new UnsupportedDocument(sprintf(
                'The %s/Identifier authority "%s" is not %s.',
                $path,
                $authority,
                ParticipantId::SCHEME,
            ));
        }

        try {
            return ParticipantId::parse($identifier->textContent);
        } catch (InvalidArgumentException $exception) {
            throw new UnsupportedDocument(sprintf('The %s/Identifier is malformed: %s', $path, $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * @return array<string, string> scope type mapped to its instance identifier, including the mandatory ones
     */
    private static function scopes(DOMElement $businessScope): array
    {
        $path = 'StandardBusinessDocumentHeader/BusinessScope/Scope';
        $elements = self::children($businessScope, 'Scope');
        if (count($elements) < self::MINIMUM_SCOPES) {
            throw new UnsupportedDocument(sprintf(
                'The envelope has %d %s element(s); at least %d (DOCUMENTID, PROCESSID, COUNTRY_C1) are required.',
                count($elements),
                $path,
                self::MINIMUM_SCOPES,
            ));
        }

        $scopes = [];
        foreach ($elements as $scope) {
            $type = self::text($scope, 'Type', $path);
            if (isset($scopes[$type])) {
                throw new UnsupportedDocument(sprintf('The envelope repeats the %s with Type "%s"; scope types must be unique.', $path, $type));
            }
            $scopes[$type] = self::text($scope, 'InstanceIdentifier', $path);
        }

        foreach (self::MANDATORY_SCOPES as $type) {
            if (! isset($scopes[$type])) {
                throw new UnsupportedDocument(sprintf('The envelope lacks the mandatory %s with Type "%s".', $path, $type));
            }
        }

        return $scopes;
    }

    private static function payload(DOMElement $root, DOMElement $header): string
    {
        $payloads = array_values(array_filter(
            self::children($root),
            static fn (DOMElement $element): bool => ! $element->isSameNode($header),
        ));
        if (count($payloads) !== 1) {
            throw new UnsupportedDocument(sprintf(
                'The Standard Business Document must carry exactly one payload element after the header, found %d.',
                count($payloads),
            ));
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($payloads[0], true));

        return (string) $document->saveXML();
    }

    private static function dateTime(string $value): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $value) === 1) {
            try {
                return new DateTimeImmutable($value);
            } catch (Exception) {
                // Reported below as a malformed value.
            }
        }

        throw new UnsupportedDocument(sprintf(
            'The StandardBusinessDocumentHeader/DocumentIdentification/CreationDateAndTime "%s" is not a date and time with a time zone.',
            $value,
        ));
    }

    private static function text(DOMElement $parent, string $name, string $path): string
    {
        $value = trim(self::required($parent, $name, $path)->textContent);
        if ($value === '') {
            throw new UnsupportedDocument(sprintf('The envelope element %s/%s is empty.', $path, $name));
        }

        return $value;
    }

    private static function required(DOMElement $parent, string $name, string $path): DOMElement
    {
        return self::children($parent, $name)[0]
            ?? throw new UnsupportedDocument(sprintf('The envelope lacks the mandatory element %s/%s.', $path, $name));
    }

    /**
     * Child elements in the envelope namespace with the given local name, or every child element
     * when no name is given.
     *
     * @return list<DOMElement>
     */
    private static function children(DOMElement $parent, ?string $name = null): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            if ($name === null || ($child->namespaceURI === self::NAMESPACE_URI && $child->localName === $name)) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
