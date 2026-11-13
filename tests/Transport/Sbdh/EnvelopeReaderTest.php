<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Sbdh;

use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Sbdh\EnvelopeReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnvelopeReaderTest extends TestCase
{
    private const NS = 'http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader';

    private const UBL_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    private const PAYLOAD = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>';

    public function test_valid_envelope_is_read(): void
    {
        [$envelope, $payload] = (new EnvelopeReader)->unwrap(self::envelope());

        self::assertSame('HU', $envelope->countryC1);
        self::assertSame(PeppolBis3::INVOICE, $envelope->documentType->value);
        self::assertStringContainsString('<Invoice', $payload);
    }

    public function test_absent_authority_and_scheme_prefix_are_tolerated(): void
    {
        $xml = str_replace(
            '<Identifier Authority="iso6523-actorid-upis">0208:sender</Identifier>',
            '<Identifier>iso6523-actorid-upis::0208:sender</Identifier>',
            self::envelope(),
        );

        [$envelope] = (new EnvelopeReader)->unwrap($xml);

        self::assertSame('0208:sender', $envelope->sender->toString());
    }

    public function test_header_must_be_the_first_element_child(): void
    {
        $xml = str_replace(
            ['<StandardBusinessDocumentHeader>', self::PAYLOAD.'</StandardBusinessDocument>'],
            [self::PAYLOAD.'<StandardBusinessDocumentHeader>', '</StandardBusinessDocument>'],
            self::envelope(),
        );

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('The StandardBusinessDocumentHeader must be the first element of the Standard Business Document.');

        (new EnvelopeReader)->unwrap($xml);
    }

    public function test_second_header_is_refused(): void
    {
        $envelope = self::envelope();
        $header = substr($envelope, (int) strpos($envelope, '<StandardBusinessDocumentHeader>'));
        $header = substr($header, 0, (int) strpos($header, '</StandardBusinessDocumentHeader>') + strlen('</StandardBusinessDocumentHeader>'));
        $xml = str_replace($header, $header.$header, $envelope);

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('The Standard Business Document carries 2 StandardBusinessDocumentHeader elements; exactly one is allowed.');

        (new EnvelopeReader)->unwrap($xml);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badCountries(): iterable
    {
        yield 'lower case' => ['hu'];
        yield 'three letters' => ['HUN'];
        yield 'digit' => ['H1'];
        yield 'one letter' => ['H'];
    }

    #[DataProvider('badCountries')]
    public function test_country_c1_must_be_two_upper_case_letters(string $country): void
    {
        $xml = str_replace('<InstanceIdentifier>HU</InstanceIdentifier>', '<InstanceIdentifier>'.$country.'</InstanceIdentifier>', self::envelope());

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('COUNTRY_C1');

        (new EnvelopeReader)->unwrap($xml);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function identificationMismatches(): iterable
    {
        yield 'Standard' => ['<Standard>'.self::UBL_INVOICE.'</Standard>', '<Standard>urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2</Standard>'];
        yield 'Type' => ['<Type>Invoice</Type>', '<Type>CreditNote</Type>'];
    }

    #[DataProvider('identificationMismatches')]
    public function test_standard_and_type_must_match_the_payload_root(string $search, string $replace): void
    {
        $xml = str_replace($search, $replace, self::envelope());
        self::assertNotSame(self::envelope(), $xml);

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('does not match the payload root {'.self::UBL_INVOICE.'}Invoice');

        (new EnvelopeReader)->unwrap($xml);
    }

    public function test_rolled_over_creation_date_is_refused(): void
    {
        $xml = str_replace('2026-11-12T09:30:00Z', '2026-02-30T09:30:00Z', self::envelope());

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('CreationDateAndTime "2026-02-30T09:30:00Z"');

        (new EnvelopeReader)->unwrap($xml);
    }

    public function test_two_scopes_are_refused_with_the_count(): void
    {
        $xml = self::envelope(scopes: self::scope('DOCUMENTID', PeppolBis3::INVOICE).self::scope('PROCESSID', PeppolBis3::PROCESS));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('The envelope has 2 StandardBusinessDocumentHeader/BusinessScope/Scope element(s); at least 3 (DOCUMENTID, PROCESSID, COUNTRY_C1) are required.');

        (new EnvelopeReader)->unwrap($xml);
    }

    public function test_duplicate_scope_is_refused_with_its_type(): void
    {
        $xml = self::envelope(scopes: self::scope('DOCUMENTID', PeppolBis3::INVOICE).self::scope('PROCESSID', PeppolBis3::PROCESS)
            .self::scope('COUNTRY_C1', 'HU').self::scope('COUNTRY_C1', 'AT'));

        $this->expectException(UnsupportedDocument::class);
        $this->expectExceptionMessage('The envelope repeats the StandardBusinessDocumentHeader/BusinessScope/Scope with Type "COUNTRY_C1"; scope types must be unique.');

        (new EnvelopeReader)->unwrap($xml);
    }

    public function test_is_envelope_stream(): void
    {
        self::assertTrue(EnvelopeReader::isEnvelopeStream(self::envelope()));
        self::assertTrue(EnvelopeReader::isEnvelopeStream("<?xml version=\"1.0\"?>\n<!-- c --><sh:StandardBusinessDocument xmlns:sh=\"".self::NS.'"/>'));
        self::assertFalse(EnvelopeReader::isEnvelopeStream(self::PAYLOAD));
        self::assertFalse(EnvelopeReader::isEnvelopeStream('<StandardBusinessDocument/>'));
        self::assertFalse(EnvelopeReader::isEnvelopeStream('<!DOCTYPE StandardBusinessDocument><StandardBusinessDocument xmlns="'.self::NS.'"/>'));
        self::assertFalse(EnvelopeReader::isEnvelopeStream('not xml'));
        self::assertFalse(EnvelopeReader::isEnvelopeStream("  \n"));
    }

    private static function scope(string $type, string $value): string
    {
        return '<Scope><Type>'.$type.'</Type><InstanceIdentifier>'.$value.'</InstanceIdentifier></Scope>';
    }

    private static function envelope(?string $scopes = null): string
    {
        $scopes ??= self::scope('DOCUMENTID', PeppolBis3::INVOICE).self::scope('PROCESSID', PeppolBis3::PROCESS).self::scope('COUNTRY_C1', 'HU');

        return '<StandardBusinessDocument xmlns="'.self::NS.'"><StandardBusinessDocumentHeader>'
            .'<HeaderVersion>1.0</HeaderVersion>'
            .'<Sender><Identifier Authority="iso6523-actorid-upis">0208:sender</Identifier></Sender>'
            .'<Receiver><Identifier Authority="iso6523-actorid-upis">0208:receiver</Identifier></Receiver>'
            .'<DocumentIdentification><Standard>'.self::UBL_INVOICE.'</Standard><TypeVersion>2.1</TypeVersion>'
            .'<InstanceIdentifier>id-1</InstanceIdentifier><Type>Invoice</Type>'
            .'<CreationDateAndTime>2026-11-12T09:30:00Z</CreationDateAndTime></DocumentIdentification>'
            .'<BusinessScope>'.$scopes.'</BusinessScope>'
            .'</StandardBusinessDocumentHeader>'
            .self::PAYLOAD.'</StandardBusinessDocument>';
    }
}
