<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Lookup;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\HostileXml;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Lookup\SmpClient;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmpClientTest extends TestCase
{
    use CapturesThrowable;

    private const PARTICIPANT_SEGMENT = 'iso6523-actorid-upis%3A%3A0208%3A0123456789';

    private const INVOICE_SEGMENT = 'busdox-docid-qns%3A%3Aurn%3Aoasis%3Anames%3Aspecification%3Aubl%3Aschema%3Axsd%3AInvoice-2%3A%3AInvoice%23%23urn%3Acen.eu%3Aen16931%3A2017%23compliant%23urn%3Afdc%3Apeppol.eu%3A2017%3Apoacc%3Abilling%3A3.0%3A%3A2.1';

    public function test_service_group_url_and_document_types(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::fixture('service-group.xml')));

        $types = self::client($recorder)->documentTypes('https://smp.example/peppol', self::participant());

        self::assertSame([PeppolBis3::INVOICE, PeppolBis3::CREDIT_NOTE], $types);
        self::assertSame('GET', $recorder->requests()[0]->getMethod());
        self::assertSame('https://smp.example/peppol/'.self::PARTICIPANT_SEGMENT, (string) $recorder->requests()[0]->getUri());
    }

    public function test_trailing_slash_of_the_base_url_is_normalised(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::fixture('service-group.xml')));

        self::client($recorder)->documentTypes('https://smp.example/peppol/', self::participant());

        self::assertSame('https://smp.example/peppol/'.self::PARTICIPANT_SEGMENT, (string) $recorder->requests()[0]->getUri());
    }

    public function test_service_metadata_url_encodes_each_segment(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::fixture('service-metadata.xml')));

        self::client($recorder)->endpoints('https://smp.example/peppol/', self::participant(), new DocumentTypeId(PeppolBis3::INVOICE));

        $url = (string) $recorder->requests()[0]->getUri();
        self::assertSame('https://smp.example/peppol/'.self::PARTICIPANT_SEGMENT.'/services/'.self::INVOICE_SEGMENT, $url);
        self::assertStringContainsString('%23%23urn', $url);
        self::assertStringNotContainsString('#', $url);
    }

    public function test_parses_endpoints_whatever_the_prefixes(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::fixture('service-metadata.xml')));

        $endpoints = self::client($recorder)->endpoints('https://smp.example/peppol/', self::participant(), new DocumentTypeId(PeppolBis3::INVOICE));

        self::assertCount(2, $endpoints);
        $first = $endpoints[0];
        self::assertSame(PeppolBis3::INVOICE, $first->documentType);
        self::assertSame(PeppolBis3::PROCESS, $first->process);
        self::assertSame('peppol-transport-as4-v2_0', $first->transportProfile);
        self::assertSame('https://ap.example/as4', $first->address);
        self::assertSame(
            'MIIBszCCAVmgAwIBAgIUQ2VydGlmaWNhdGVQbGFjZWhvbGRlcjAKBggqhkjOPQQDAjAgMR4wHAYDVQQDDBVQRVBQT0wgQUNDRVNTIFBPSU5UIENBMB4XDTI0MDEwMTAwMDAwMFoXDTI5MTIzMTIzNTk1OVow',
            $first->certificate,
        );
        self::assertEquals(new DateTimeImmutable('2024-01-01T00:00:00Z'), $first->activation);
        self::assertEquals(new DateTimeImmutable('2999-12-31T23:59:59+01:00'), $first->expiration);
        self::assertSame('https://ap.example/support', $first->technicalContactUrl);

        $second = $endpoints[1];
        self::assertSame('urn:peppol:bis:billing_with_response', $second->process);
        self::assertSame('https://ap.example/as4-response', $second->address);
        self::assertNull($second->activation);
        self::assertNull($second->expiration);
        self::assertSame('mailto:support@ap.example', $second->technicalContactUrl);
    }

    public function test_smp_404_is_not_registered(): void
    {
        $recorder = new RecordingHttpClient(new Response(404), new Response(404));
        $client = self::client($recorder);

        self::assertSame([], $client->documentTypes('https://smp.example/', self::participant()));
        self::assertSame([], $client->endpoints('https://smp.example/', self::participant(), new DocumentTypeId(PeppolBis3::INVOICE)));
    }

    public function test_smp_5xx_is_transport_unavailable(): void
    {
        $this->expectException(TransportUnavailable::class);

        self::client(new RecordingHttpClient(new Response(503, [], 'busy')))->documentTypes('https://smp.example/', self::participant());
    }

    public function test_other_statuses_are_unexpected(): void
    {
        $this->expectException(UnexpectedResponse::class);

        self::client(new RecordingHttpClient(new Response(403, [], 'no')))->documentTypes('https://smp.example/', self::participant());
    }

    public function test_follows_at_most_one_redirect(): void
    {
        $recorder = new RecordingHttpClient(
            new Response(200, [], self::fixture('redirect.xml')),
            new Response(200, [], self::fixture('service-metadata.xml')),
        );

        $endpoints = self::client($recorder)->endpoints('https://smp.example/', self::participant(), new DocumentTypeId(PeppolBis3::INVOICE));

        self::assertCount(2, $endpoints);
        self::assertSame('https://other-smp.example/'.self::PARTICIPANT_SEGMENT.'/services/'.self::INVOICE_SEGMENT, (string) $recorder->requests()[1]->getUri());

        $chained = new RecordingHttpClient(
            new Response(200, [], self::fixture('redirect.xml')),
            new Response(200, [], self::fixture('redirect.xml')),
        );
        try {
            self::client($chained)->endpoints('https://smp.example/', self::participant(), new DocumentTypeId(PeppolBis3::INVOICE));
            self::fail('A second redirect must be refused.');
        } catch (UnexpectedResponse $exception) {
            self::assertStringContainsString('redirect', $exception->getMessage());
            self::assertCount(2, $chained->requests());
        }
    }

    public function test_refuses_an_unsafe_redirect_target(): void
    {
        $xml = str_replace('https://other-smp.example/', 'http://other-smp.example/', self::fixture('redirect.xml'));
        $recorder = new RecordingHttpClient(new Response(200, [], $xml));

        try {
            self::client($recorder)->endpoints('https://smp.example/', self::participant(), new DocumentTypeId(PeppolBis3::INVOICE));
            self::fail('An http redirect target must be refused.');
        } catch (UnexpectedResponse $exception) {
            self::assertStringContainsString('https', $exception->getMessage());
            self::assertCount(1, $recorder->requests());
        }
    }

    public function test_smp_response_size_is_capped(): void
    {
        $body = str_repeat(' ', 2_097_153);
        $recorder = new RecordingHttpClient(new Response(200, [], $body));

        try {
            self::client($recorder)->documentTypes('https://smp.example/', self::participant());
            self::fail('An oversized SMP response must be refused.');
        } catch (UnexpectedResponse $exception) {
            self::assertStringContainsString('2097152 bytes', $exception->getMessage());
        }
    }

    public function test_smp_response_over_two_mib_is_refused_while_reading(): void
    {
        $resource = fopen('php://temp/maxmemory:1048576', 'w+b');
        self::assertIsResource($resource);
        $chunk = str_repeat(' ', 1_048_576);
        for ($i = 0; $i < 8; $i++) {
            fwrite($resource, $chunk);
        }
        rewind($resource);
        $stream = Stream::create($resource);
        $response = (new Response(200))->withBody($stream)->withoutHeader('Content-Length');

        $exception = self::capture(fn () => self::client(new RecordingHttpClient($response))->documentTypes('https://smp.example/', self::participant()));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('2097152 bytes', $exception->getMessage());
        // The client reads in 1 MiB chunks and stops at the first chunk past the cap.
        self::assertLessThanOrEqual(3 * 1_048_576, $stream->tell(), 'The body must not be read past the SMP cap.');
    }

    public function test_smp_document_identifier_mismatch_is_refused(): void
    {
        $metadata = self::fixture('service-metadata.xml');
        $cases = [
            'other document type' => str_replace('xsd:Invoice-2::Invoice##', 'xsd:CreditNote-2::CreditNote##', $metadata),
            'other scheme' => str_replace('scheme="busdox-docid-qns"', 'scheme="peppol-doctype-wildcard"', $metadata),
            'value in another case' => str_replace('xsd:Invoice-2::Invoice##', 'xsd:invoice-2::Invoice##', $metadata),
        ];

        foreach ($cases as $name => $xml) {
            self::assertNotSame($metadata, $xml, $name);
            $exception = self::capture(fn () => self::client(new RecordingHttpClient(new Response(200, [], $xml)))
                ->endpoints('https://smp.example/', self::participant(), new DocumentTypeId(PeppolBis3::INVOICE)));

            self::assertInstanceOf(UnexpectedResponse::class, $exception, $name);
            self::assertStringContainsString('DocumentIdentifier', $exception->getMessage(), $name);
        }
    }

    /**
     * Peppol identifier schemes are case-insensitive, so an SMP may publish the document type
     * scheme in any case; the identifier value itself is still compared exactly.
     */
    public function test_document_type_scheme_compare_is_case_insensitive(): void
    {
        $xml = str_replace('scheme="busdox-docid-qns"', 'scheme="BUSDOX-DOCID-QNS"', self::fixture('service-metadata.xml'));
        self::assertStringContainsString('scheme="BUSDOX-DOCID-QNS"', $xml);

        $endpoints = self::client(new RecordingHttpClient(new Response(200, [], $xml)))
            ->endpoints('https://smp.example/', self::participant(), new DocumentTypeId(PeppolBis3::INVOICE));

        self::assertCount(2, $endpoints);
        self::assertSame(PeppolBis3::INVOICE, $endpoints[0]->documentType);
    }

    public function test_missing_document_type_scheme_is_refused(): void
    {
        $xml = str_replace('<ns2:DocumentIdentifier scheme="busdox-docid-qns">', '<ns2:DocumentIdentifier>', self::fixture('service-metadata.xml'));
        self::assertStringContainsString('<ns2:DocumentIdentifier>', $xml);

        $exception = self::capture(fn () => self::client(new RecordingHttpClient(new Response(200, [], $xml)))
            ->endpoints('https://smp.example/', self::participant(), new DocumentTypeId(PeppolBis3::INVOICE)));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('DocumentIdentifier', $exception->getMessage());
    }

    public function test_href_with_raw_hash_keeps_the_document_type(): void
    {
        // The document type with its "#" characters left unencoded, as some SMPs publish it.
        $raw = 'busdox-docid-qns%3A%3A'.str_replace('%23', '#', rawurlencode(PeppolBis3::INVOICE));
        $xml = '<ServiceGroup xmlns="http://busdox.org/serviceMetadata/publishing/1.0/"><ServiceMetadataReferenceCollection>'
            .'<ServiceMetadataReference href="https://smp.example/peppol/'.self::PARTICIPANT_SEGMENT.'/services/'.$raw.'"/>'
            .'<ServiceMetadataReference href="https://smp.example/peppol/'.self::PARTICIPANT_SEGMENT.'/services/'.$raw.'?x=1"/>'
            .'</ServiceMetadataReferenceCollection></ServiceGroup>';

        $types = self::client(new RecordingHttpClient(new Response(200, [], $xml)))->documentTypes('https://smp.example/', self::participant());

        self::assertStringContainsString('##urn', $raw);
        self::assertSame([PeppolBis3::INVOICE], $types);
    }

    public function test_href_scheme_prefix_is_stripped_case_insensitively(): void
    {
        $segment = 'BUSDOX-DOCID-QNS%3A%3A'.rawurlencode(PeppolBis3::INVOICE);
        $xml = '<ServiceGroup xmlns="http://busdox.org/serviceMetadata/publishing/1.0/"><ServiceMetadataReferenceCollection>'
            .'<ServiceMetadataReference href="https://smp.example/peppol/'.self::PARTICIPANT_SEGMENT.'/services/'.$segment.'"/>'
            .'</ServiceMetadataReferenceCollection></ServiceGroup>';

        $types = self::client(new RecordingHttpClient(new Response(200, [], $xml)))->documentTypes('https://smp.example/', self::participant());

        self::assertSame([PeppolBis3::INVOICE], $types);
    }

    public function test_size_cap_is_configurable(): void
    {
        $this->expectException(UnexpectedResponse::class);
        $this->expectExceptionMessage('100 bytes');

        $http = new HttpClient(new RecordingHttpClient(new Response(200, [], self::fixture('service-group.xml'))), new Psr17Factory, new Psr17Factory);
        (new SmpClient($http, 100))->documentTypes('https://smp.example/', self::participant());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hostile(): array
    {
        return HostileXml::cases('ServiceGroup', 'http://busdox.org/serviceMetadata/publishing/1.0/');
    }

    #[DataProvider('hostile')]
    public function test_smp_xxe_is_refused(string $xml): void
    {
        $this->expectException(UnsafeXml::class);

        self::client(new RecordingHttpClient(new Response(200, [], $xml)))->documentTypes('https://smp.example/', self::participant());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformed(): array
    {
        return [
            'not XML' => ['<html>oops'],
            'wrong root' => ['<ServiceGroup xmlns="urn:other"/>'],
            'endpoint without address' => [str_replace('<ns4:Address>https://ap.example/as4</ns4:Address>', '', self::fixture('service-metadata.xml'))],
            'invalid date' => [str_replace('2024-01-01T00:00:00Z', 'tomorrow', self::fixture('service-metadata.xml'))],
        ];
    }

    #[DataProvider('malformed')]
    public function test_malformed_response_is_unexpected(string $xml): void
    {
        $this->expectException(UnexpectedResponse::class);

        self::client(new RecordingHttpClient(new Response(200, [], $xml)))->endpoints('https://smp.example/', self::participant(), new DocumentTypeId(PeppolBis3::INVOICE));
    }

    private static function client(RecordingHttpClient $recorder): SmpClient
    {
        return new SmpClient(new HttpClient($recorder, new Psr17Factory, new Psr17Factory));
    }

    private static function participant(): ParticipantId
    {
        return ParticipantId::parse('0208:0123456789');
    }

    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/../../fixtures/smp/'.$name);
    }
}
