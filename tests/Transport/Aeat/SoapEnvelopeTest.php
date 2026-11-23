<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Aeat;

use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Submission;
use IntrAgile\EInvoicing\Spain\Verifactu\SubmissionHeader;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Verifactu\SubmissionWriter;
use IntrAgile\EInvoicing\Syntax\Verifactu\VerifactuFormat;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use IntrAgile\EInvoicing\Transport\Aeat\AeatFormat;
use IntrAgile\EInvoicing\Transport\Aeat\SoapEnvelope;
use IntrAgile\EInvoicing\Transport\Aeat\SoapFault;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class SoapEnvelopeTest extends TestCase
{
    #[Group('verifactu-official')]
    public function test_submission_envelope_body_is_schema_valid(): void
    {
        $schemas = Fixtures::verifactuSchemas();
        $first = RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024'),
            ChainLink::first(),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-01T19:20:30+01:00'),
        );
        $second = RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345679/G34', '01-01-2024'),
            ChainLink::after($first->invoiceId(), $first->fingerprint()),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-01T19:20:35+01:00'),
        );
        $submission = new Submission(
            new SubmissionHeader(Nif::parse(VerifactuSamples::ISSUER_NIF), 'Test Issuer S.L.'),
            [$first, $second],
        );

        $opened = SoapEnvelope::open(SoapEnvelope::wrap((new SubmissionWriter)->write($submission)));

        $this->assertInstanceOf(DOMElement::class, $opened);
        $this->assertSame(VerifactuFormat::LR_NS, $opened->namespaceURI);
        $body = new DOMDocument;
        $body->appendChild($body->importNode($opened, true));
        $errors = (new SchemaValidator)->validate($body, $schemas->submissionXsd(), $schemas->localPrefixes());
        $this->assertSame([], array_map(static fn ($error): string => $error->message, $errors));
    }

    public function test_wrap_writes_soapenv_with_empty_header(): void
    {
        $xml = SoapEnvelope::wrap('<a:Root xmlns:a="urn:x"><a:Child/></a:Root>');
        $root = SafeXml::load($xml)->documentElement;

        $this->assertInstanceOf(DOMElement::class, $root);
        $this->assertSame('soapenv:Envelope', $root->tagName);
        $this->assertSame(AeatFormat::SOAP_NS, $root->namespaceURI);
        $names = [];
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $names[] = $child->tagName;
            }
        }
        $this->assertSame(['soapenv:Header', 'soapenv:Body'], $names);
        $header = $root->getElementsByTagName('Header')->item(0);
        $this->assertInstanceOf(DOMElement::class, $header);
        $this->assertSame(0, $header->childNodes->length);
        $payload = $root->getElementsByTagName('Body')->item(0)?->firstChild;
        $this->assertInstanceOf(DOMElement::class, $payload);
        $this->assertSame('a:Root', $payload->tagName);
        $this->assertSame('urn:x', $payload->namespaceURI);
    }

    public function test_open_returns_fault_with_code(): void
    {
        $fault = SoapEnvelope::open(<<<'XML'
            <env:Envelope xmlns:env="http://schemas.xmlsoap.org/soap/envelope/">
              <env:Header/>
              <env:Body>
                <env:Fault>
                  <faultcode>env:Client</faultcode>
                  <faultstring>Codigo[4104].El NIF del titular
                     no esta identificado.</faultstring>
                </env:Fault>
              </env:Body>
            </env:Envelope>
            XML);

        $this->assertInstanceOf(SoapFault::class, $fault);
        $this->assertSame('Client', $fault->faultCode);
        $this->assertSame(4104, $fault->code);
        $this->assertStringStartsWith('Codigo[4104].', $fault->faultString);
        $this->assertStringContainsString('titular no esta', $fault->faultString);
        $this->assertTrue($fault->isClient());
        $this->assertFalse($fault->isServer());
    }

    public function test_fault_without_code_has_null_code(): void
    {
        $fault = SoapEnvelope::open($this->fault('soapenv:Client', 'Something went wrong'));

        $this->assertInstanceOf(SoapFault::class, $fault);
        $this->assertNull($fault->code);
        $this->assertSame('Something went wrong', $fault->faultString);
    }

    public function test_server_fault(): void
    {
        $fault = SoapEnvelope::open($this->fault('soapenv:Server', 'Codigo[3]. Down'));

        $this->assertInstanceOf(SoapFault::class, $fault);
        $this->assertTrue($fault->isServer());
        $this->assertFalse($fault->isClient());
        $this->assertSame(3, $fault->code);
    }

    public function test_other_fault_code_is_neither_client_nor_server(): void
    {
        $fault = SoapEnvelope::open($this->fault('Other', 'x'));

        $this->assertInstanceOf(SoapFault::class, $fault);
        $this->assertSame('Other', $fault->faultCode);
        $this->assertFalse($fault->isClient());
        $this->assertFalse($fault->isServer());
    }

    public function test_dotted_subcodes_keep_their_class(): void
    {
        $client = SoapEnvelope::open($this->fault('soapenv:Client.Authentication', 'Codigo[4112]. Refused'));
        $server = SoapEnvelope::open($this->fault('soapenv:Server.Busy', 'Down'));
        $lookalike = SoapEnvelope::open($this->fault('soapenv:ClientSide', 'x'));

        $this->assertInstanceOf(SoapFault::class, $client);
        $this->assertInstanceOf(SoapFault::class, $server);
        $this->assertInstanceOf(SoapFault::class, $lookalike);
        $this->assertSame('Client.Authentication', $client->faultCode);
        $this->assertTrue($client->isClient());
        $this->assertFalse($client->isServer());
        $this->assertTrue($server->isServer());
        $this->assertFalse($server->isClient());
        $this->assertFalse($lookalike->isClient());
    }

    public function test_doctype_is_refused(): void
    {
        $this->expectException(UnsafeXml::class);
        SoapEnvelope::open('<!DOCTYPE e [<!ENTITY x "y">]><soapenv:Envelope xmlns:soapenv="'.AeatFormat::SOAP_NS.'"><soapenv:Body><a/></soapenv:Body></soapenv:Envelope>');
    }

    public function test_non_envelope_root_is_unsupported(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('Envelope');
        SoapEnvelope::open('<Root xmlns="urn:x"/>');
    }

    public function test_empty_body_is_unsupported(): void
    {
        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('Body');
        SoapEnvelope::open('<soapenv:Envelope xmlns:soapenv="'.AeatFormat::SOAP_NS.'"><soapenv:Body/></soapenv:Envelope>');
    }

    private function fault(string $code, string $text): string
    {
        return '<soapenv:Envelope xmlns:soapenv="'.AeatFormat::SOAP_NS.'"><soapenv:Body><soapenv:Fault>'
            .'<faultcode>'.$code.'</faultcode><faultstring>'.$text.'</faultstring>'
            .'</soapenv:Fault></soapenv:Body></soapenv:Envelope>';
    }
}
