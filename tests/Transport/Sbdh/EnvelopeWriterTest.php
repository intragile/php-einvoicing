<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Sbdh;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Tests\Support\InvoiceFactory;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\XRechnungPeppol;
use IntrAgile\EInvoicing\Transport\Sbdh\EnvelopeReader;
use IntrAgile\EInvoicing\Transport\Sbdh\EnvelopeWriter;
use PHPUnit\Framework\TestCase;

final class EnvelopeWriterTest extends TestCase
{
    private const CII = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';

    public function test_cii_payload_envelope_parts(): void
    {
        $document = self::ciiDocument();

        $xml = (new EnvelopeWriter)->wrap($document, 'f81d4fae-7dec-41d0-a765-00a0c91e6bf6', new DateTimeImmutable('2026-11-13T09:30:00Z'));

        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('sh', EnvelopeWriter::NAMESPACE_URI);
        $xpath->registerNamespace('rsm', self::CII);
        $identification = 'sh:StandardBusinessDocumentHeader/sh:DocumentIdentification/';
        $scope = static fn (string $type): string => "sh:StandardBusinessDocumentHeader/sh:BusinessScope/sh:Scope[sh:Type='{$type}']/sh:InstanceIdentifier";

        self::assertSame(self::CII, self::text($xpath, $identification.'sh:Standard'));
        self::assertSame('CrossIndustryInvoice', self::text($xpath, $identification.'sh:Type'));
        self::assertSame('D16B', self::text($xpath, $identification.'sh:TypeVersion'));
        self::assertSame(XRechnungPeppol::CII_INVOICE, self::text($xpath, $scope('DOCUMENTID')));
        self::assertSame(PeppolBis3::PROCESS, self::text($xpath, $scope('PROCESSID')));
        self::assertSame('HU', self::text($xpath, $scope('COUNTRY_C1')));
        $payload = $xpath->query('/sh:StandardBusinessDocument/rsm:CrossIndustryInvoice');
        self::assertNotFalse($payload);
        self::assertSame(1, $payload->length);

        [$envelope] = (new EnvelopeReader)->unwrap($xml);
        self::assertSame(XRechnungPeppol::CII_INVOICE, $envelope->documentType->value);
        self::assertTrue($envelope->sender->equals($document->sender));
        self::assertTrue($envelope->receiver->equals($document->receiver));
    }

    private static function ciiDocument(): OutboundDocument
    {
        $invoice = TotalsCalculator::complete(InvoiceFactory::mixedVat())->withProcessControl(XRechnung::processControl());
        $invoice = $invoice
            ->withSeller(self::withAddress($invoice->seller, new ElectronicAddress('0123456789', ElectronicAddressScheme::C_0208)))
            ->withBuyer(self::withAddress($invoice->buyer, new ElectronicAddress('DE123456789', ElectronicAddressScheme::C_9930)));

        return OutboundDocument::fromInvoice($invoice, null, Syntax::Cii);
    }

    private static function text(DOMXPath $xpath, string $path): string
    {
        $nodes = $xpath->query('/sh:StandardBusinessDocument/'.$path);
        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length, $path);

        return (string) $nodes->item(0)?->nodeValue;
    }

    private static function withAddress(Party $party, ElectronicAddress $address): Party
    {
        return new Party(
            name: $party->name,
            tradingName: $party->tradingName,
            identifiers: $party->identifiers,
            legalRegistration: $party->legalRegistration,
            vatIdentifier: $party->vatIdentifier,
            taxRegistration: $party->taxRegistration,
            additionalLegalInformation: $party->additionalLegalInformation,
            electronicAddress: $address,
            address: $party->address,
            contact: $party->contact,
        );
    }
}
