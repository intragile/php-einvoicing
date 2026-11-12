<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Sbdh;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMNode;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;
use InvalidArgumentException;

/**
 * Wraps a business document in a Peppol Business Message Envelope (Standard Business Document),
 * with the header elements in the order of the UN/CEFACT SBDH schema.
 */
final class EnvelopeWriter
{
    public const NAMESPACE_URI = 'http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader';

    /**
     * @param  string|null  $instanceIdentifier  globally unique envelope identifier; a random UUID v4 when null
     * @param  DateTimeImmutable|null  $createdAt  creation time, written in UTC; the current time when null
     *
     * @throws InvalidArgumentException when the sender country is unknown, the document type is malformed or does not match the payload root
     * @throws UnsafeXml when the payload carries a DOCTYPE declaration
     * @throws UnsupportedDocument when the payload is not well-formed XML
     */
    public function wrap(OutboundDocument $document, ?string $instanceIdentifier = null, ?DateTimeImmutable $createdAt = null): string
    {
        if ($document->countryC1 === null) {
            throw new InvalidArgumentException('COUNTRY_C1 is mandatory in a Peppol envelope; pass the sender country (C1) to the outbound document.');
        }
        $instanceIdentifier ??= self::uuid4();
        if (trim($instanceIdentifier) === '') {
            throw new InvalidArgumentException('The envelope instance identifier must not be empty.');
        }
        $createdAt ??= new DateTimeImmutable;
        $parts = $document->documentType->sbdhParts();

        $payload = SafeXml::loadPreservingWhitespace($document->xml)->documentElement
            ?? throw new UnsupportedDocument('The payload has no root element.');
        if ($payload->namespaceURI !== $parts['standard'] || $payload->localName !== $parts['type']) {
            throw new InvalidArgumentException(sprintf(
                'The payload root {%s}%s does not match the document type, which expects {%s}%s.',
                (string) $payload->namespaceURI,
                (string) $payload->localName,
                $parts['standard'],
                $parts['type'],
            ));
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $root = $dom->appendChild($dom->createElementNS(self::NAMESPACE_URI, 'StandardBusinessDocument'));
        $header = self::append($root, 'StandardBusinessDocumentHeader');
        self::append($header, 'HeaderVersion', '1.0');
        self::participant(self::append($header, 'Sender'), $document->sender);
        self::participant(self::append($header, 'Receiver'), $document->receiver);

        $identification = self::append($header, 'DocumentIdentification');
        self::append($identification, 'Standard', $parts['standard']);
        self::append($identification, 'TypeVersion', $parts['typeVersion']);
        self::append($identification, 'InstanceIdentifier', $instanceIdentifier);
        self::append($identification, 'Type', $parts['type']);
        self::append(
            $identification,
            'CreationDateAndTime',
            $createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        );

        $scopes = self::append($header, 'BusinessScope');
        self::scope($scopes, 'DOCUMENTID', $document->documentType->value, DocumentTypeId::SCHEME);
        self::scope($scopes, 'PROCESSID', $document->process->value, ProcessId::SCHEME);
        self::scope($scopes, 'COUNTRY_C1', $document->countryC1);

        $root->appendChild($dom->importNode($payload, true));

        return (string) $dom->saveXML();
    }

    private static function participant(DOMElement $parent, ParticipantId $participant): void
    {
        self::append($parent, 'Identifier', $participant->toString())
            ->setAttribute('Authority', ParticipantId::SCHEME);
    }

    private static function scope(DOMElement $parent, string $type, string $instanceIdentifier, ?string $identifier = null): void
    {
        $scope = self::append($parent, 'Scope');
        self::append($scope, 'Type', $type);
        self::append($scope, 'InstanceIdentifier', $instanceIdentifier);
        if ($identifier !== null) {
            self::append($scope, 'Identifier', $identifier);
        }
    }

    private static function append(DOMNode $parent, string $name, ?string $text = null): DOMElement
    {
        $document = $parent instanceof DOMDocument ? $parent : $parent->ownerDocument;
        assert($document instanceof DOMDocument);
        $element = $document->createElementNS(self::NAMESPACE_URI, $name);
        if ($text !== null) {
            $element->appendChild($document->createTextNode($text));
        }
        $parent->appendChild($element);

        return $element;
    }

    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
