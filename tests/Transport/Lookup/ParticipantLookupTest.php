<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Lookup;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Tests\Support\HostileXml;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Lookup\LookupResult;
use IntrAgile\EInvoicing\Transport\Lookup\LookupStatus;
use IntrAgile\EInvoicing\Transport\Lookup\NaptrRecord;
use IntrAgile\EInvoicing\Transport\Lookup\ParticipantLookup;
use IntrAgile\EInvoicing\Transport\Lookup\ServiceEndpoint;
use IntrAgile\EInvoicing\Transport\Lookup\SmlLocator;
use IntrAgile\EInvoicing\Transport\Lookup\SmpClient;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class ParticipantLookupTest extends TestCase
{
    private const SMP = 'https://smp.example/peppol/';

    public function test_registered_participant_with_endpoints(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::fixture('service-metadata.xml')));

        $result = self::lookup(self::registered(), $recorder)->lookup(self::participant(), self::invoice());

        self::assertSame(LookupStatus::Registered, $result->status);
        self::assertSame(self::SMP, $result->smpUrl);
        self::assertCount(2, $result->endpoints);
        self::assertNull($result->reason);
        self::assertTrue($result->supports(self::invoice(), new ProcessId(PeppolBis3::PROCESS)));
    }

    public function test_without_a_document_type_only_the_service_group_is_read(): void
    {
        $recorder = new RecordingHttpClient(new Response(200, [], self::fixture('service-group.xml')));

        $result = self::lookup(self::registered(), $recorder)->lookup(self::participant());

        self::assertSame(LookupStatus::Registered, $result->status);
        self::assertSame([], $result->endpoints);
        self::assertCount(1, $recorder->requests());
        self::assertStringEndsWith('iso6523-actorid-upis%3A%3A0208%3A0123456789', (string) $recorder->requests()[0]->getUri());
    }

    public function test_resolver_failure_is_unknown_not_unregistered(): void
    {
        $result = self::lookup(new FakeNaptrResolver(null), new RecordingHttpClient)->lookup(self::participant(), self::invoice());

        self::assertSame(LookupStatus::Unknown, $result->status);
        self::assertNull($result->smpUrl);
        self::assertStringContainsString('DNS', (string) $result->reason);
    }

    public function test_no_naptr_record_is_not_registered(): void
    {
        $result = self::lookup(new FakeNaptrResolver([]), new RecordingHttpClient)->lookup(self::participant(), self::invoice());

        self::assertSame(LookupStatus::NotRegistered, $result->status);
        self::assertNotNull($result->reason);
    }

    public function test_rejected_smp_url_is_unknown_with_the_reason(): void
    {
        $resolver = new FakeNaptrResolver([new NaptrRecord(100, 10, 'U', 'Meta:SMP', '!.*!http://smp.example/!', '')]);

        $result = self::lookup($resolver, new RecordingHttpClient)->lookup(self::participant(), self::invoice());

        self::assertSame(LookupStatus::Unknown, $result->status);
        self::assertStringContainsString('https', (string) $result->reason);
    }

    public function test_smp_404_is_not_registered(): void
    {
        foreach ([null, self::invoice()] as $type) {
            $result = self::lookup(self::registered(), new RecordingHttpClient(new Response(404)))->lookup(self::participant(), $type);

            self::assertSame(LookupStatus::NotRegistered, $result->status);
            self::assertSame(self::SMP, $result->smpUrl);
            self::assertNotNull($result->reason);
        }
    }

    public function test_smp_5xx_is_unknown(): void
    {
        $result = self::lookup(self::registered(), new RecordingHttpClient(new Response(500, [], 'down')))->lookup(self::participant(), self::invoice());

        self::assertSame(LookupStatus::Unknown, $result->status);
        self::assertStringContainsString('500', (string) $result->reason);
    }

    public function test_smp_timeout_is_unknown(): void
    {
        $timeout = new class('Connection timed out') extends RuntimeException implements NetworkExceptionInterface
        {
            public function getRequest(): RequestInterface
            {
                return new Request('GET', 'https://smp.example/');
            }
        };

        $result = self::lookup(self::registered(), new RecordingHttpClient($timeout))->lookup(self::participant(), self::invoice());

        self::assertSame(LookupStatus::Unknown, $result->status);
        self::assertStringContainsString('timed out', (string) $result->reason);
    }

    public function test_hostile_smp_response_is_unknown(): void
    {
        $xml = HostileXml::cases('ServiceGroup', 'http://busdox.org/serviceMetadata/publishing/1.0/')['external entity'][0];

        $result = self::lookup(self::registered(), new RecordingHttpClient(new Response(200, [], $xml)))->lookup(self::participant());

        self::assertSame(LookupStatus::Unknown, $result->status);
        self::assertStringContainsString('DOCTYPE', (string) $result->reason);
    }

    public function test_supports_matches_document_type_process_and_profile(): void
    {
        $result = new LookupResult(LookupStatus::Registered, self::SMP, [self::endpoint()], null);

        self::assertTrue($result->supports(self::invoice(), new ProcessId(PeppolBis3::PROCESS)));
        self::assertFalse($result->supports(self::invoice(), new ProcessId(PeppolBis3::PROCESS), 'busdox-transport-as2-ver2p0'));
        self::assertFalse($result->supports(new DocumentTypeId(PeppolBis3::CREDIT_NOTE), new ProcessId(PeppolBis3::PROCESS)));
        self::assertFalse($result->supports(self::invoice(), new ProcessId('urn:other')));
    }

    public function test_supports_ignores_endpoints_outside_their_service_period(): void
    {
        $expired = new LookupResult(LookupStatus::Registered, self::SMP, [self::endpoint(expiration: new DateTimeImmutable('-1 day'))], null);
        $future = new LookupResult(LookupStatus::Registered, self::SMP, [self::endpoint(activation: new DateTimeImmutable('+1 day'))], null);
        $current = new LookupResult(LookupStatus::Registered, self::SMP, [self::endpoint(new DateTimeImmutable('-1 day'), new DateTimeImmutable('+1 day'))], null);

        self::assertFalse($expired->supports(self::invoice(), new ProcessId(PeppolBis3::PROCESS)));
        self::assertFalse($future->supports(self::invoice(), new ProcessId(PeppolBis3::PROCESS)));
        self::assertTrue($current->supports(self::invoice(), new ProcessId(PeppolBis3::PROCESS)));
    }

    private static function endpoint(?DateTimeImmutable $activation = null, ?DateTimeImmutable $expiration = null): ServiceEndpoint
    {
        return new ServiceEndpoint(PeppolBis3::INVOICE, PeppolBis3::PROCESS, 'peppol-transport-as4-v2_0', 'https://ap.example/as4', null, $activation, $expiration, null);
    }

    private static function lookup(FakeNaptrResolver $resolver, RecordingHttpClient $recorder): ParticipantLookup
    {
        return new ParticipantLookup(new SmlLocator($resolver), new SmpClient(new HttpClient($recorder, new Psr17Factory, new Psr17Factory)));
    }

    private static function registered(): FakeNaptrResolver
    {
        return new FakeNaptrResolver([new NaptrRecord(100, 10, 'U', 'Meta:SMP', '!.*!'.self::SMP.'!', '')]);
    }

    private static function participant(): ParticipantId
    {
        return ParticipantId::parse('0208:0123456789');
    }

    private static function invoice(): DocumentTypeId
    {
        return new DocumentTypeId(PeppolBis3::INVOICE);
    }

    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/../../fixtures/smp/'.$name);
    }
}
