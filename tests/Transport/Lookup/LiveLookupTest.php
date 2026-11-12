<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Lookup;

use IntrAgile\EInvoicing\Tests\Support\StreamHttpClient;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Lookup\DnsGetRecordResolver;
use IntrAgile\EInvoicing\Transport\Lookup\LookupStatus;
use IntrAgile\EInvoicing\Transport\Lookup\ParticipantLookup;
use IntrAgile\EInvoicing\Transport\Lookup\SmlLocator;
use IntrAgile\EInvoicing\Transport\Lookup\SmpClient;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Real DNS and HTTPS lookups against the production Peppol SML and public SMPs (unauthenticated).
 */
#[Group('network')]
final class LiveLookupTest extends TestCase
{
    public function test_known_participant_is_registered(): void
    {
        $result = self::lookup()->lookup(ParticipantId::parse('9910:hu11200589'));

        self::assertSame(LookupStatus::Registered, $result->status, (string) $result->reason);
        self::assertStringStartsWith('https://', (string) $result->smpUrl);
    }

    public function test_known_participant_endpoints(): void
    {
        $result = self::lookup()->lookup(ParticipantId::parse('9910:hu11200589'), new DocumentTypeId(PeppolBis3::INVOICE));

        self::assertSame(LookupStatus::Registered, $result->status, (string) $result->reason);
        self::assertNotSame([], $result->endpoints);
    }

    public function test_random_participant_is_not_registered(): void
    {
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));

        $result = self::lookup()->lookup(ParticipantId::parse('0088:'.$uuid));

        self::assertSame(LookupStatus::NotRegistered, $result->status, (string) $result->reason);
    }

    private static function lookup(): ParticipantLookup
    {
        $factory = new Psr17Factory;

        return new ParticipantLookup(
            new SmlLocator(new DnsGetRecordResolver),
            new SmpClient(new HttpClient(new StreamHttpClient, $factory, $factory, 2_000_000)),
        );
    }
}
