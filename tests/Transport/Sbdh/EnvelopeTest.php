<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Sbdh;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMNode;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;
use IntrAgile\EInvoicing\Transport\Sbdh\Envelope;
use IntrAgile\EInvoicing\Transport\Sbdh\EnvelopeReader;
use IntrAgile\EInvoicing\Transport\Sbdh\EnvelopeWriter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EnvelopeTest extends TestCase
{
    private const NS = 'http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader';

    private const PAYLOAD = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
                 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
                 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2">
          <!-- kept as written -->
          <cbc:ID>INV-1</cbc:ID>
          <cbc:Note>  two  spaces &amp; an ampersand  </cbc:Note>
          <cac:AccountingSupplierParty>
            <cac:Party><cbc:EndpointID schemeID="0208">0123456789</cbc:EndpointID></cac:Party>
          </cac:AccountingSupplierParty>
        </Invoice>
        XML;

    public function test_wrap_then_unwrap_round_trips_the_envelope_and_the_payload(): void
    {
        $document = self::document();
        $createdAt = new DateTimeImmutable('2026-11-12T09:30:00Z');

        $xml = (new EnvelopeWriter)->wrap($document, 'f81d4fae-7dec-41d0-a765-00a0c91e6bf6', $createdAt);
        [$envelope, $payload] = (new EnvelopeReader)->unwrap($xml);

        self::assertEquals(new Envelope(
            $document->sender,
            $document->receiver,
            $document->documentType,
            $document->process,
            'HU',
            'f81d4fae-7dec-41d0-a765-00a0c91e6bf6',
            $createdAt,
        ), $envelope);
        self::assertSame(self::c14n(self::PAYLOAD), self::c14n($payload));
    }

    public function test_written_header_follows_the_xsd_element_order(): void
    {
        $xml = (new EnvelopeWriter)->wrap(self::document(), 'id-1', new DateTimeImmutable('2026-11-12T09:30:00Z'));

        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $root = $dom->documentElement;
        self::assertInstanceOf(DOMElement::class, $root);
        self::assertSame(self::NS, $root->namespaceURI);
        self::assertSame(
            'StandardBusinessDocument(StandardBusinessDocumentHeader(HeaderVersion,Sender(Identifier),Receiver(Identifier),'
            .'DocumentIdentification(Standard,TypeVersion,InstanceIdentifier,Type,CreationDateAndTime),'
            .'BusinessScope(Scope(Type,InstanceIdentifier,Identifier),Scope(Type,InstanceIdentifier,Identifier),'
            .'Scope(Type,InstanceIdentifier))),Invoice)',
            self::shape($root),
        );

        $values = [];
        foreach (self::elements($root->getElementsByTagNameNS(self::NS, 'StandardBusinessDocumentHeader')->item(0)) as $element) {
            $values[] = $element->localName.'='.trim($element->textContent);
        }
        self::assertSame([
            'HeaderVersion=1.0',
            'Sender=0208:0123456789',
            'Identifier=0208:0123456789',
            'Receiver=0208:AbC-42',
            'Identifier=0208:AbC-42',
            'DocumentIdentification=urn:oasis:names:specification:ubl:schema:xsd:Invoice-22.1id-1Invoice2026-11-12T09:30:00Z',
            'Standard=urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            'TypeVersion=2.1',
            'InstanceIdentifier=id-1',
            'Type=Invoice',
            'CreationDateAndTime=2026-11-12T09:30:00Z',
            'BusinessScope=DOCUMENTID'.PeppolBis3::INVOICE.'busdox-docid-qnsPROCESSID'.PeppolBis3::PROCESS.'cenbii-procid-ublCOUNTRY_C1HU',
            'Scope=DOCUMENTID'.PeppolBis3::INVOICE.'busdox-docid-qns',
            'Type=DOCUMENTID',
            'InstanceIdentifier='.PeppolBis3::INVOICE,
            'Identifier=busdox-docid-qns',
            'Scope=PROCESSID'.PeppolBis3::PROCESS.'cenbii-procid-ubl',
            'Type=PROCESSID',
            'InstanceIdentifier='.PeppolBis3::PROCESS,
            'Identifier=cenbii-procid-ubl',
            'Scope=COUNTRY_C1HU',
            'Type=COUNTRY_C1',
            'InstanceIdentifier=HU',
        ], $values);

        foreach ($dom->getElementsByTagNameNS(self::NS, 'Identifier') as $identifier) {
            if (in_array($identifier->parentNode?->localName, ['Sender', 'Receiver'], true)) {
                self::assertSame('iso6523-actorid-upis', $identifier->getAttribute('Authority'));
            }
        }
    }

    public function test_creation_time_is_written_in_utc(): void
    {
        $createdAt = new DateTimeImmutable('2026-11-12T11:30:00', new DateTimeZone('+02:00'));

        $xml = (new EnvelopeWriter)->wrap(self::document(), 'id-1', $createdAt);

        self::assertStringContainsString('<CreationDateAndTime>2026-11-12T09:30:00Z</CreationDateAndTime>', $xml);
    }

    public function test_defaults_are_a_uuid_v4_and_the_current_time(): void
    {
        $before = new DateTimeImmutable('-1 second');
        [$first] = (new EnvelopeReader)->unwrap((new EnvelopeWriter)->wrap(self::document()));
        [$second] = (new EnvelopeReader)->unwrap((new EnvelopeWriter)->wrap(self::document()));

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first->instanceIdentifier);
        self::assertNotSame($first->instanceIdentifier, $second->instanceIdentifier);
        self::assertGreaterThanOrEqual($before, $first->createdAt);
        self::assertLessThanOrEqual(new DateTimeImmutable('+1 second'), $first->createdAt);
    }

    public function test_missing_country_c1_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('COUNTRY_C1 is mandatory');

        (new EnvelopeWriter)->wrap(self::document(null));
    }

    public function test_payload_must_match_the_document_type(): void
    {
        $document = self::document();
        $document = new OutboundDocument(
            $document->xml,
            $document->sender,
            $document->receiver,
            new DocumentTypeId(PeppolBis3::CREDIT_NOTE),
            $document->process,
            'HU',
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CreditNote');

        (new EnvelopeWriter)->wrap($document);
    }

    public function test_reads_a_prefixed_envelope_with_additional_scopes(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <sh:StandardBusinessDocument xmlns:sh="http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader"
                xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
              <sh:StandardBusinessDocumentHeader>
                <sh:HeaderVersion>1.0</sh:HeaderVersion>
                <sh:Sender><sh:Identifier Authority="iso6523-actorid-upis">0088:7315458756324</sh:Identifier></sh:Sender>
                <sh:Receiver><sh:Identifier Authority="iso6523-actorid-upis">0088:4562458856624</sh:Identifier></sh:Receiver>
                <sh:DocumentIdentification>
                  <sh:Standard>urn:oasis:names:specification:ubl:schema:xsd:Invoice-2</sh:Standard>
                  <sh:TypeVersion>2.1</sh:TypeVersion>
                  <sh:InstanceIdentifier>4a1f8a8e-2c62-4d43-9d8e-0d9f4d5b5c11</sh:InstanceIdentifier>
                  <sh:Type>Invoice</sh:Type>
                  <sh:CreationDateAndTime>2026-11-12T11:30:00.123+02:00</sh:CreationDateAndTime>
                </sh:DocumentIdentification>
                <sh:BusinessScope>
                  <sh:Scope><sh:Type>DOCUMENTID</sh:Type><sh:InstanceIdentifier>urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0::2.1</sh:InstanceIdentifier><sh:Identifier>busdox-docid-qns</sh:Identifier></sh:Scope>
                  <sh:Scope><sh:Type>PROCESSID</sh:Type><sh:InstanceIdentifier>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</sh:InstanceIdentifier><sh:Identifier>cenbii-procid-ubl</sh:Identifier></sh:Scope>
                  <sh:Scope><sh:Type>COUNTRY_C1</sh:Type><sh:InstanceIdentifier>BE</sh:InstanceIdentifier></sh:Scope>
                  <sh:Scope><sh:Type>MLS_TO</sh:Type><sh:InstanceIdentifier>0088:7315458756324</sh:InstanceIdentifier><sh:Identifier>iso6523-actorid-upis</sh:Identifier></sh:Scope>
                  <sh:Scope><sh:Type>MLS_TYPE</sh:Type><sh:InstanceIdentifier>FAILURE_ONLY</sh:InstanceIdentifier></sh:Scope>
                </sh:BusinessScope>
              </sh:StandardBusinessDocumentHeader>
              <Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"><cbc:ID>INV-7</cbc:ID></Invoice>
            </sh:StandardBusinessDocument>
            XML;

        [$envelope, $payload] = (new EnvelopeReader)->unwrap($xml);

        self::assertTrue($envelope->sender->equals(ParticipantId::parse('0088:7315458756324')));
        self::assertSame('0088:4562458856624', $envelope->receiver->toString());
        self::assertSame(PeppolBis3::INVOICE, $envelope->documentType->value);
        self::assertSame(PeppolBis3::PROCESS, $envelope->process->value);
        self::assertSame('BE', $envelope->countryC1);
        self::assertSame('4a1f8a8e-2c62-4d43-9d8e-0d9f4d5b5c11', $envelope->instanceIdentifier);
        self::assertEquals(new DateTimeImmutable('2026-11-12T09:30:00.123Z'), $envelope->createdAt);
        self::assertSame(['MLS_TO' => '0088:7315458756324', 'MLS_TYPE' => 'FAILURE_ONLY'], $envelope->additionalScopes);
        self::assertSame(
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" '
            .'xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><cbc:ID>INV-7</cbc:ID></Invoice>',
            self::c14n($payload),
        );
    }

    public function test_two_scopes_are_refused(): void
    {
        $xml = self::handWritten(scopes: <<<'XML'
            <Scope><Type>DOCUMENTID</Type><InstanceIdentifier>a::b##c::2.1</InstanceIdentifier><Identifier>busdox-docid-qns</Identifier></Scope>
            <Scope><Type>PROCESSID</Type><InstanceIdentifier>p</InstanceIdentifier><Identifier>cenbii-procid-ubl</Identifier></Scope>
            XML);

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('Scope');

        (new EnvelopeReader)->unwrap($xml);
    }

    public function test_duplicate_scope_type_is_refused(): void
    {
        $xml = self::handWritten(scopes: <<<'XML'
            <Scope><Type>DOCUMENTID</Type><InstanceIdentifier>a::b##c::2.1</InstanceIdentifier><Identifier>busdox-docid-qns</Identifier></Scope>
            <Scope><Type>PROCESSID</Type><InstanceIdentifier>p</InstanceIdentifier><Identifier>cenbii-procid-ubl</Identifier></Scope>
            <Scope><Type>COUNTRY_C1</Type><InstanceIdentifier>HU</InstanceIdentifier></Scope>
            <Scope><Type>COUNTRY_C1</Type><InstanceIdentifier>AT</InstanceIdentifier></Scope>
            XML);

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('COUNTRY_C1');

        (new EnvelopeReader)->unwrap($xml);
    }

    public function test_missing_country_scope_is_named(): void
    {
        $xml = self::handWritten(scopes: <<<'XML'
            <Scope><Type>DOCUMENTID</Type><InstanceIdentifier>a::b##c::2.1</InstanceIdentifier><Identifier>busdox-docid-qns</Identifier></Scope>
            <Scope><Type>PROCESSID</Type><InstanceIdentifier>p</InstanceIdentifier><Identifier>cenbii-procid-ubl</Identifier></Scope>
            <Scope><Type>MLS_TYPE</Type><InstanceIdentifier>FAILURE_ONLY</InstanceIdentifier></Scope>
            XML);

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('COUNTRY_C1');

        (new EnvelopeReader)->unwrap($xml);
    }

    public function test_missing_mandatory_element_is_named(): void
    {
        $xml = str_replace('<CreationDateAndTime>2026-11-12T09:30:00Z</CreationDateAndTime>', '', self::handWritten());

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('CreationDateAndTime');

        (new EnvelopeReader)->unwrap($xml);
    }

    public function test_creation_time_without_time_zone_is_refused(): void
    {
        $xml = str_replace('2026-11-12T09:30:00Z', '2026-11-12T09:30:00', self::handWritten());

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('CreationDateAndTime');

        (new EnvelopeReader)->unwrap($xml);
    }

    public function test_malformed_participant_is_unsupported(): void
    {
        $xml = str_replace('>0208:sender<', '>sender<', self::handWritten());

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('Sender');

        (new EnvelopeReader)->unwrap($xml);
    }

    public function test_missing_payload_is_refused(): void
    {
        $xml = str_replace('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>', '', self::handWritten());

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('payload');

        (new EnvelopeReader)->unwrap($xml);
    }

    public function test_is_envelope(): void
    {
        self::assertTrue(EnvelopeReader::isEnvelope(self::handWritten()));
        self::assertTrue(EnvelopeReader::isEnvelope((new EnvelopeWriter)->wrap(self::document())));
        self::assertFalse(EnvelopeReader::isEnvelope(self::PAYLOAD));
        self::assertFalse(EnvelopeReader::isEnvelope('<StandardBusinessDocument/>'));
        self::assertFalse(EnvelopeReader::isEnvelope('not xml'));
        self::assertFalse(EnvelopeReader::isEnvelope(''));
    }

    public function test_xxe_envelope_is_unsafe(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE StandardBusinessDocument [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            .'<StandardBusinessDocument xmlns="'.self::NS.'">&xxe;</StandardBusinessDocument>';

        $this->expectException(UnsafeXml::class);

        (new EnvelopeReader)->unwrap($xml);
    }

    private static function document(?string $country = 'HU'): OutboundDocument
    {
        return new OutboundDocument(
            self::PAYLOAD,
            ParticipantId::parse('iso6523-actorid-upis::0208:0123456789'),
            ParticipantId::parse('0208:AbC-42'),
            new DocumentTypeId(PeppolBis3::INVOICE),
            new ProcessId(PeppolBis3::PROCESS),
            $country,
        );
    }

    private static function handWritten(?string $scopes = null): string
    {
        $scopes ??= <<<'XML'
            <Scope><Type>DOCUMENTID</Type><InstanceIdentifier>a::b##c::2.1</InstanceIdentifier><Identifier>busdox-docid-qns</Identifier></Scope>
            <Scope><Type>PROCESSID</Type><InstanceIdentifier>p</InstanceIdentifier><Identifier>cenbii-procid-ubl</Identifier></Scope>
            <Scope><Type>COUNTRY_C1</Type><InstanceIdentifier>HU</InstanceIdentifier></Scope>
            XML;

        return '<StandardBusinessDocument xmlns="'.self::NS.'"><StandardBusinessDocumentHeader>'
            .'<HeaderVersion>1.0</HeaderVersion>'
            .'<Sender><Identifier Authority="iso6523-actorid-upis">0208:sender</Identifier></Sender>'
            .'<Receiver><Identifier Authority="iso6523-actorid-upis">0208:receiver</Identifier></Receiver>'
            .'<DocumentIdentification><Standard>a</Standard><TypeVersion>2.1</TypeVersion>'
            .'<InstanceIdentifier>id</InstanceIdentifier><Type>b</Type>'
            .'<CreationDateAndTime>2026-11-12T09:30:00Z</CreationDateAndTime></DocumentIdentification>'
            .'<BusinessScope>'.$scopes.'</BusinessScope>'
            .'</StandardBusinessDocumentHeader>'
            .'<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/></StandardBusinessDocument>';
    }

    private static function c14n(string $xml): string
    {
        $dom = new DOMDocument;
        self::assertTrue($dom->loadXML($xml));

        return (string) $dom->documentElement?->C14N();
    }

    /**
     * The element tree as "name(child,child)", the payload root by name only.
     */
    private static function shape(DOMElement $element): string
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName !== 'Invoice') {
                $children[] = self::shape($child);
            } elseif ($child instanceof DOMElement) {
                $children[] = $child->localName;
            }
        }

        return $element->localName.($children === [] ? '' : '('.implode(',', $children).')');
    }

    /**
     * @return list<DOMElement>
     */
    private static function elements(?DOMNode $root): array
    {
        $elements = [];
        if ($root instanceof DOMElement) {
            foreach ($root->getElementsByTagName('*') as $element) {
                $elements[] = $element;
            }
        }

        return $elements;
    }
}
