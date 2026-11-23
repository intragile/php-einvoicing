<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Aeat;

use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Spain\CertificateKind;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Submission;
use IntrAgile\EInvoicing\Spain\Verifactu\SubmissionHeader;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Syntax\Verifactu\VerifactuFormat;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Tests\Support\RequestCounter;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use IntrAgile\EInvoicing\Transport\Aeat\AeatClient;
use IntrAgile\EInvoicing\Transport\Aeat\AeatConfig;
use IntrAgile\EInvoicing\Transport\Aeat\AeatErrors;
use IntrAgile\EInvoicing\Transport\Aeat\LookupRequest;
use IntrAgile\EInvoicing\Transport\Aeat\LookupState;
use IntrAgile\EInvoicing\Transport\Aeat\RecordOutcome;
use IntrAgile\EInvoicing\Transport\Aeat\SoapEnvelope;
use IntrAgile\EInvoicing\Transport\Aeat\SubmissionState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * The AEAT SOAP client, proven against recorded fixtures only: no test here reaches an AEAT host.
 */
final class AeatClientTest extends TestCase
{
    private const DIR = __DIR__.'/../../fixtures/spain/aeat';

    private const SEAL_TEST_ENDPOINT = 'https://prewww10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

    private const RESEND_ADVICE = 'resend the same bytes: AEAT answers a duplicate with RegistroDuplicado, or call lookup()';

    private ?string $tempDir = null;

    protected function tearDown(): void
    {
        if ($this->tempDir !== null) {
            self::removeTree($this->tempDir);
        }
    }

    #[Group('verifactu-official')]
    public function test_submit_posts_soap_to_the_configured_port(): void
    {
        $schemas = Fixtures::verifactuSchemas();
        $recorder = new RecordingHttpClient(self::soap(200, 'correcto.xml'));

        self::client($recorder, $schemas)->submit(self::submission());

        $requests = $recorder->requests();
        $this->assertCount(1, $requests);
        $request = $requests[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(self::SEAL_TEST_ENDPOINT, (string) $request->getUri());
        $this->assertSame('text/xml; charset=utf-8', $request->getHeaderLine('Content-Type'));
        $this->assertSame('""', $request->getHeaderLine('SOAPAction'));
        $this->assertSame('text/xml', $request->getHeaderLine('Accept'));

        $payload = SoapEnvelope::open((string) $request->getBody());
        $this->assertInstanceOf(DOMElement::class, $payload);
        $this->assertSame(VerifactuFormat::LR_NS, $payload->namespaceURI);
        $document = new DOMDocument;
        $document->appendChild($document->importNode($payload, true));
        $errors = (new SchemaValidator)->validate($document, $schemas->submissionXsd(), $schemas->localPrefixes());
        $this->assertSame([], array_map(static fn ($error): string => $error->message, $errors));
    }

    #[Group('verifactu-official')]
    public function test_personal_port_uses_the_personal_host(): void
    {
        $recorder = new RecordingHttpClient(self::soap(200, 'correcto.xml'));
        $config = new AeatConfig(VerifactuEnvironment::Test, CertificateKind::Personal, Fixtures::verifactuSchemas());

        (new AeatClient($config, self::http($recorder)))->submit(self::submission());

        $this->assertSame(
            'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
            (string) $recorder->requests()[0]->getUri(),
        );
    }

    #[Group('verifactu-official')]
    public function test_correcto_is_returned(): void
    {
        $submission = self::submission();
        $response = self::client(new RecordingHttpClient(self::soap(200, 'correcto.xml')))->submit($submission);

        $this->assertSame(SubmissionState::Correcto, $response->state);
        $this->assertNotNull($response->csv);
        $this->assertSame([RecordOutcome::Accepted, RecordOutcome::Accepted], $response->outcomes($submission));
    }

    #[Group('verifactu-official')]
    public function test_resend_after_lost_answer_is_already_registered(): void
    {
        $submission = self::submission(1);
        $recorder = new RecordingHttpClient(self::networkError(), self::soap(200, 'duplicado-correcta.xml'));
        $client = self::client($recorder);

        $lost = self::thrown(static fn () => $client->submit($submission));
        $this->assertInstanceOf(OutcomeUnknown::class, $lost);
        $this->assertStringContainsString('RegistroDuplicado', $lost->getMessage());
        $this->assertStringContainsString(self::RESEND_ADVICE, $lost->getMessage());
        $this->assertStringContainsString(VerifactuSamples::ISSUER_NIF, $lost->getMessage());
        $this->assertStringContainsString('1 record', $lost->getMessage());
        $this->assertNotNull($lost->getPrevious());

        $response = $client->submit($submission);

        $requests = $recorder->requests();
        $this->assertCount(2, $requests);
        $this->assertSame((string) $requests[0]->getBody(), (string) $requests[1]->getBody());
        $this->assertNull($response->csv);
        $this->assertSame([RecordOutcome::AlreadyRegistered], $response->outcomes($submission));
    }

    #[Group('verifactu-official')]
    public function test_client_fault_4104_is_document_rejected_with_code(): void
    {
        $exception = self::thrown(fn () => self::client(new RecordingHttpClient(self::soap(500, 'fault-client-4104.xml')))->submit(self::submission()));

        $this->assertInstanceOf(DocumentRejected::class, $exception);
        $lines = $exception->errors['errors'] ?? null;
        $this->assertIsArray($lines);
        $this->assertCount(1, $lines);
        $this->assertIsString($lines[0]);
        $this->assertStringStartsWith('4104: Codigo[4104]', $lines[0]);
        $this->assertSame(4104, $exception->providerCode);
        $this->assertSame(4104, AeatErrors::codeOf($exception));
        $this->assertStringContainsString(VerifactuSamples::ISSUER_NIF, $exception->getMessage());
    }

    #[Group('verifactu-official')]
    public function test_client_fault_4112_is_authentication_failed(): void
    {
        $exception = self::thrown(fn () => self::client(new RecordingHttpClient(self::soap(500, 'fault-client-4112.xml')))->submit(self::submission()));

        $this->assertInstanceOf(AuthenticationFailed::class, $exception);
        $this->assertSame(4112, AeatErrors::codeOf($exception));
        $this->assertStringContainsString('Codigo[4112]', $exception->getMessage());
    }

    #[Group('verifactu-official')]
    public function test_transient_client_fault_is_outcome_unknown_on_submit_and_unavailable_on_lookup(): void
    {
        $submitted = self::thrown(fn () => self::client(new RecordingHttpClient(self::clientFault(4134)))->submit(self::submission()));
        $looked = self::thrown(fn () => self::client(new RecordingHttpClient(self::clientFault(4134)))->lookup(self::lookupRequest()));

        $this->assertInstanceOf(OutcomeUnknown::class, $submitted);
        $this->assertStringContainsString('4134', $submitted->getMessage());
        $this->assertStringContainsString(self::RESEND_ADVICE, $submitted->getMessage());
        $this->assertInstanceOf(TransportUnavailable::class, $looked);
        $this->assertStringContainsString('4134', $looked->getMessage());
    }

    #[Group('verifactu-official')]
    public function test_body_decides_whatever_the_http_status(): void
    {
        $rejected = self::thrown(fn () => self::client(new RecordingHttpClient(self::soap(200, 'fault-client-4104.xml')))->submit(self::submission()));
        $submission = self::submission();
        $accepted = self::client(new RecordingHttpClient(self::soap(500, 'correcto.xml')))->submit($submission);

        $this->assertInstanceOf(DocumentRejected::class, $rejected);
        $this->assertSame(4104, $rejected->providerCode);
        $this->assertSame(SubmissionState::Correcto, $accepted->state);
        $this->assertSame([RecordOutcome::Accepted, RecordOutcome::Accepted], $accepted->outcomes($submission));
    }

    #[Group('verifactu-official')]
    public function test_aeat_text_in_messages_is_bounded(): void
    {
        $long = 'Codigo[4104].'.str_repeat('Texto muy largo de la AEAT. ', 100);
        $xml = str_replace(
            'Codigo[4104].Error en la cabecera: NIF del obligado no identificado (texto de prueba).',
            $long,
            self::fixture('fault-client-4104.xml'),
        );
        $serverXml = str_replace('Error interno del servicio (texto de prueba).', str_repeat('Interno. ', 300), self::fixture('fault-server.xml'));
        $authXml = str_replace('Codigo[4104]', 'Codigo[4112]', $xml);

        $exceptions = [
            'client fault' => self::thrown(fn () => self::client(new RecordingHttpClient(new Response(500, [], $xml)))->submit(self::submission())),
            'server fault' => self::thrown(fn () => self::client(new RecordingHttpClient(new Response(500, [], $serverXml)))->submit(self::submission())),
            'authentication fault' => self::thrown(fn () => self::client(new RecordingHttpClient(new Response(500, [], $authXml)))->submit(self::submission())),
            'lookup fault' => self::thrown(fn () => self::client(new RecordingHttpClient(new Response(500, [], $xml)))->lookup(self::lookupRequest())),
        ];

        foreach ($exceptions as $case => $exception) {
            $this->assertLessThan(1200, strlen($exception->getMessage()), $case);
            $this->assertStringContainsString('...', $exception->getMessage(), $case);
        }
        $this->assertInstanceOf(DocumentRejected::class, $exceptions['client fault']);
        $lines = $exceptions['client fault']->errors['errors'] ?? null;
        $this->assertIsArray($lines);
        $this->assertIsString($lines[0]);
        $this->assertLessThan(400, strlen($lines[0]));
    }

    #[Group('verifactu-official')]
    public function test_server_fault_is_outcome_unknown(): void
    {
        $exception = self::thrown(fn () => self::client(new RecordingHttpClient(self::soap(500, 'fault-server.xml')))->submit(self::submission()));

        $this->assertInstanceOf(OutcomeUnknown::class, $exception);
        $this->assertStringContainsString(self::RESEND_ADVICE, $exception->getMessage());
        $this->assertStringContainsString('2 records', $exception->getMessage());
        $this->assertStringContainsString(VerifactuSamples::ISSUER_NIF, $exception->getMessage());
    }

    #[Group('verifactu-official')]
    public function test_html_502_is_outcome_unknown(): void
    {
        $html = new Response(502, ['Content-Type' => 'text/html'], (string) file_get_contents(self::DIR.'/html-502.html'));

        $exception = self::thrown(fn () => self::client(new RecordingHttpClient($html))->submit(self::submission()));

        $this->assertInstanceOf(OutcomeUnknown::class, $exception);
        $this->assertStringContainsString('HTTP 502', $exception->getMessage());
        $this->assertStringContainsString(self::RESEND_ADVICE, $exception->getMessage());
        $this->assertInstanceOf(UnsupportedDocument::class, $exception->getPrevious());
    }

    #[Group('verifactu-official')]
    public function test_unreadable_200_is_outcome_unknown_with_lookup_advice(): void
    {
        // A well-formed SOAP answer whose payload is a query response, not a submission response.
        $exception = self::thrown(fn () => self::client(new RecordingHttpClient(self::soap(200, 'consulta-sin-datos.xml')))->submit(self::submission()));

        $this->assertInstanceOf(OutcomeUnknown::class, $exception);
        $this->assertStringContainsString('lookup()', $exception->getMessage());
        $this->assertStringContainsString(self::RESEND_ADVICE, $exception->getMessage());
        $this->assertInstanceOf(UnsupportedDocument::class, $exception->getPrevious());
    }

    #[Group('verifactu-official')]
    public function test_echoed_invalid_nif_is_outcome_unknown(): void
    {
        $xml = str_replace('<tik:IDEmisorFactura>89890001K<', '<tik:IDEmisorFactura>89890001X<', self::fixture('correcto.xml'));
        $this->assertNotSame(self::fixture('correcto.xml'), $xml);

        $exception = self::thrown(fn () => self::client(new RecordingHttpClient(new Response(200, [], $xml)))->submit(self::submission()));

        $this->assertInstanceOf(OutcomeUnknown::class, $exception);
        $this->assertStringContainsString(self::RESEND_ADVICE, $exception->getMessage());
        $this->assertInstanceOf(UnsupportedDocument::class, $exception->getPrevious());
    }

    #[Group('verifactu-official')]
    public function test_oversized_answer_is_outcome_unknown(): void
    {
        $config = new AeatConfig(VerifactuEnvironment::Test, CertificateKind::Seal, Fixtures::verifactuSchemas(), 100);

        $exception = self::thrown(fn () => (new AeatClient($config, self::http(new RecordingHttpClient(self::soap(200, 'correcto.xml')))))->submit(self::submission()));

        $this->assertInstanceOf(OutcomeUnknown::class, $exception);
        $this->assertStringContainsString('100 bytes', $exception->getMessage());
        $this->assertStringContainsString(self::RESEND_ADVICE, $exception->getMessage());
        $this->assertInstanceOf(UnexpectedResponse::class, $exception->getPrevious());
    }

    #[Group('verifactu-official')]
    public function test_doctype_answer_is_refused_without_expansion(): void
    {
        $xml = str_replace(
            '<env:Envelope',
            "<!DOCTYPE env:Envelope [<!ENTITY x \"EXPANDED\">]>\n<env:Envelope",
            str_replace('<tikR:EstadoEnvio>Correcto</tikR:EstadoEnvio>', '<tikR:EstadoEnvio>&x;</tikR:EstadoEnvio>', self::fixture('correcto.xml')),
        );
        $this->assertStringContainsString('&x;', $xml);

        $exception = self::thrown(fn () => self::client(new RecordingHttpClient(new Response(200, [], $xml)))->submit(self::submission()));

        $this->assertInstanceOf(OutcomeUnknown::class, $exception);
        $this->assertInstanceOf(UnsafeXml::class, $exception->getPrevious());
        $this->assertStringNotContainsString('EXPANDED', $exception->getMessage());
        $this->assertStringContainsString(self::RESEND_ADVICE, $exception->getMessage());
    }

    #[Group('verifactu-official')]
    public function test_403_without_soap_is_authentication_failed(): void
    {
        $exception = self::thrown(fn () => self::client(new RecordingHttpClient(new Response(403, ['Content-Type' => 'text/html'], '<html><body>Forbidden</body></html>')))->submit(self::submission()));

        $this->assertInstanceOf(AuthenticationFailed::class, $exception);
        $this->assertStringContainsString('HTTP 403', $exception->getMessage());
        $this->assertNull(AeatErrors::codeOf($exception));
    }

    #[Group('verifactu-official')]
    public function test_429_is_rate_limited_with_retry_after(): void
    {
        $exception = self::thrown(fn () => self::client(new RecordingHttpClient(new Response(429, ['Retry-After' => '60'], 'Too Many Requests')))->submit(self::submission()));

        $this->assertInstanceOf(RateLimited::class, $exception);
        $this->assertSame(60, $exception->retryAfterSeconds);
    }

    public function test_schema_invalid_submission_is_refused_without_request(): void
    {
        $counter = new RequestCounter(new RecordingHttpClient);
        $config = new AeatConfig(VerifactuEnvironment::Test, CertificateKind::Seal, $this->schemasDeclaringOnlyOther());

        $exception = self::thrown(fn () => (new AeatClient($config, self::http($counter)))->submit(self::submission()));

        $this->assertInstanceOf(InvalidRecord::class, $exception);
        $this->assertStringContainsString('SuministroLR.xsd', $exception->getMessage());
        $this->assertSame(0, $counter->count());
    }

    #[Group('verifactu-official')]
    public function test_lookup_found_and_not_found(): void
    {
        $schemas = Fixtures::verifactuSchemas();
        $recorder = new RecordingHttpClient(self::soap(200, 'consulta-con-datos.xml'), self::soap(200, 'consulta-sin-datos.xml'));
        $client = self::client($recorder, $schemas);

        $found = $client->lookup(self::lookupRequest());
        $missing = $client->lookup(self::lookupRequest());

        $this->assertNotNull($found);
        $this->assertSame('12345678/G33', $found->invoiceId->number);
        $this->assertInstanceOf(LookupState::class, $found->state);
        $this->assertNull($missing);

        $request = $recorder->requests()[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(self::SEAL_TEST_ENDPOINT, (string) $request->getUri());
        $this->assertSame('""', $request->getHeaderLine('SOAPAction'));
        $payload = SoapEnvelope::open((string) $request->getBody());
        $this->assertInstanceOf(DOMElement::class, $payload);
        $document = new DOMDocument;
        $document->appendChild($document->importNode($payload, true));
        $errors = (new SchemaValidator)->validate($document, $schemas->queryXsd(), $schemas->localPrefixes());
        $this->assertSame([], array_map(static fn ($error): string => $error->message, $errors));
    }

    #[Group('verifactu-official')]
    public function test_lookup_failures_are_transport_unavailable(): void
    {
        $html = new Response(502, ['Content-Type' => 'text/html'], (string) file_get_contents(self::DIR.'/html-502.html'));
        $answers = [
            'network' => self::networkError(),
            'server fault' => self::soap(500, 'fault-server.xml'),
            'html' => $html,
            'client fault 4104' => self::soap(500, 'fault-client-4104.xml'),
            'submission response' => self::soap(200, 'correcto.xml'),
            'oversized' => self::soap(200, 'consulta-con-datos.xml'),
            'doctype' => new Response(200, [], str_replace('<env:Envelope', "<!DOCTYPE env:Envelope [<!ENTITY x \"EXPANDED\">]>\n<env:Envelope", self::fixture('consulta-con-datos.xml'))),
        ];

        foreach ($answers as $case => $answer) {
            $client = $case === 'oversized'
                ? new AeatClient(new AeatConfig(VerifactuEnvironment::Test, CertificateKind::Seal, Fixtures::verifactuSchemas(), 100), self::http(new RecordingHttpClient($answer)))
                : self::client(new RecordingHttpClient($answer));
            $exception = self::thrown(fn () => $client->lookup(self::lookupRequest()));

            $this->assertInstanceOf(TransportUnavailable::class, $exception, $case);
            $this->assertStringContainsString('12345678/G33', $exception->getMessage(), $case);
            $this->assertStringContainsString(VerifactuSamples::ISSUER_NIF, $exception->getMessage(), $case);
        }
    }

    #[Group('verifactu-official')]
    public function test_lookup_client_fault_4112_is_authentication_failed(): void
    {
        $exception = self::thrown(fn () => self::client(new RecordingHttpClient(self::soap(500, 'fault-client-4112.xml')))->lookup(self::lookupRequest()));

        $this->assertInstanceOf(AuthenticationFailed::class, $exception);
        $this->assertSame(4112, AeatErrors::codeOf($exception));
    }

    #[Group('verifactu-official')]
    public function test_lookup_403_and_429_without_soap(): void
    {
        $forbidden = self::thrown(fn () => self::client(new RecordingHttpClient(new Response(403, [], 'Forbidden')))->lookup(self::lookupRequest()));
        $limited = self::thrown(fn () => self::client(new RecordingHttpClient(new Response(429, ['Retry-After' => '5'], 'Slow down')))->lookup(self::lookupRequest()));

        $this->assertInstanceOf(AuthenticationFailed::class, $forbidden);
        $this->assertInstanceOf(RateLimited::class, $limited);
        $this->assertSame(5, $limited->retryAfterSeconds);
    }

    private static function client(RecordingHttpClient $recorder, ?VerifactuSchemas $schemas = null): AeatClient
    {
        $config = new AeatConfig(VerifactuEnvironment::Test, CertificateKind::Seal, $schemas ?? Fixtures::verifactuSchemas());

        return new AeatClient($config, self::http($recorder));
    }

    private static function http(RecordingHttpClient|RequestCounter $client): HttpClient
    {
        $factory = new Psr17Factory;

        return new HttpClient($client, $factory, $factory);
    }

    private static function submission(int $records = 2): Submission
    {
        $first = RegistrationRecord::create(
            VerifactuSamples::vectorDraft('12345678/G33', '01-01-2024'),
            ChainLink::first(),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-01-01T19:20:30+01:00'),
        );
        $list = [$first];
        if ($records > 1) {
            $list[] = RegistrationRecord::create(
                VerifactuSamples::vectorDraft('12345679/G34', '01-01-2024'),
                ChainLink::after($first->invoiceId(), $first->fingerprint()),
                VerifactuSamples::system(),
                RecordTimestamp::fromLiteral('2024-01-01T19:20:35+01:00'),
            );
        }

        return new Submission(new SubmissionHeader(Nif::parse(VerifactuSamples::ISSUER_NIF), 'Test Issuer S.L.'), $list);
    }

    private static function lookupRequest(): LookupRequest
    {
        return new LookupRequest(
            Nif::parse(VerifactuSamples::ISSUER_NIF),
            'Test Issuer S.L.',
            VerifactuSamples::invoiceId('12345678/G33', '01-01-2024'),
        );
    }

    private static function fixture(string $name): string
    {
        return (string) file_get_contents(self::DIR.'/'.$name);
    }

    private static function soap(int $status, string $fixture): Response
    {
        return new Response($status, ['Content-Type' => 'text/xml; charset=utf-8'], self::fixture($fixture));
    }

    private static function clientFault(int $code): Response
    {
        return new Response(500, ['Content-Type' => 'text/xml; charset=utf-8'], str_replace('Codigo[4104]', sprintf('Codigo[%d]', $code), self::fixture('fault-client-4104.xml')));
    }

    private static function networkError(): NetworkExceptionInterface
    {
        return new class(new Request('POST', self::SEAL_TEST_ENDPOINT), 'Connection reset by peer') extends RuntimeException implements NetworkExceptionInterface
        {
            public function __construct(private readonly Request $request, string $message)
            {
                parent::__construct($message);
            }

            public function getRequest(): Request
            {
                return $this->request;
            }
        };
    }

    private static function thrown(callable $action): Throwable
    {
        try {
            $action();
        } catch (Throwable $exception) {
            return $exception;
        }

        self::fail('Expected an exception, none was thrown.');
    }

    /**
     * A schema set whose SuministroLR.xsd declares nothing but an element named Other, so every
     * written submission fails it.
     */
    private function schemasDeclaringOnlyOther(): VerifactuSchemas
    {
        $base = getenv('TMPDIR') ?: sys_get_temp_dir();
        $this->tempDir = rtrim($base, '/').'/aeat-client-'.bin2hex(random_bytes(6));
        $root = $this->tempDir.'/'.VerifactuSchemas::DIR;
        if (! mkdir($root, 0o700, true)) {
            self::fail('Cannot create '.$root);
        }
        file_put_contents($root.'/SuministroLR.xsd', <<<'XSD'
            <?xml version="1.0" encoding="UTF-8"?>
            <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" elementFormDefault="qualified">
              <xs:element name="Other" type="xs:string"/>
            </xs:schema>
            XSD);

        return new VerifactuSchemas($this->tempDir);
    }

    private static function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) ? self::removeTree($path) : unlink($path);
        }
        rmdir($dir);
    }
}
