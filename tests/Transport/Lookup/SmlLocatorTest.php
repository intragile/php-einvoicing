<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Lookup;

use IntrAgile\EInvoicing\Transport\Lookup\DnsGetRecordResolver;
use IntrAgile\EInvoicing\Transport\Lookup\NaptrRecord;
use IntrAgile\EInvoicing\Transport\Lookup\SmlLocator;
use IntrAgile\EInvoicing\Transport\Lookup\SmlZone;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmlLocatorTest extends TestCase
{
    private const WORKED_EXAMPLE = 'Y7DZFXAF3D4CJZ4KCGRXTEC6TWVCGA4KY7ZWA5BOIF6MSWD4TDRQ.iso6523-actorid-upis.participant.sml.prod.tech.peppol.org';

    public function test_hash_uses_lowercased_value(): void
    {
        self::assertSame(self::WORKED_EXAMPLE, SmlLocator::hostname(ParticipantId::parse('iso6523-actorid-upis::0088:123ABC'), SmlZone::Production));
        self::assertSame(self::WORKED_EXAMPLE, SmlLocator::hostname(ParticipantId::parse('0088:123abc'), SmlZone::Production));
    }

    public function test_hostname_uses_the_zone(): void
    {
        self::assertSame(
            'Y7DZFXAF3D4CJZ4KCGRXTEC6TWVCGA4KY7ZWA5BOIF6MSWD4TDRQ.iso6523-actorid-upis.participant.sml.test.tech.peppol.org',
            SmlLocator::hostname(ParticipantId::parse('0088:123abc'), SmlZone::Test),
        );
    }

    public function test_queries_the_hashed_hostname_and_applies_the_regexp(): void
    {
        $resolver = new FakeNaptrResolver([self::record('!.*!https://smp.tickstar.com/peppol/!')]);
        $locator = new SmlLocator($resolver);

        self::assertSame('https://smp.tickstar.com/peppol/', $locator->smpUrl(ParticipantId::parse('0088:123ABC')));
        self::assertSame([self::WORKED_EXAMPLE], $resolver->queried);
        self::assertNull($locator->lastError());
    }

    public function test_regexp_with_back_reference_and_case_insensitive_flag(): void
    {
        $locator = new SmlLocator(new FakeNaptrResolver([self::record('!^([A-Z0-9]+)\..*$!https://smp.example/\1/!i')]));

        self::assertSame('https://smp.example/Y7DZFXAF3D4CJZ4KCGRXTEC6TWVCGA4KY7ZWA5BOIF6MSWD4TDRQ/', $locator->smpUrl(ParticipantId::parse('0088:123abc')));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeUrls(): array
    {
        return [
            'plain http' => ['http://smp.example/'],
            'user info' => ['https://u:p@h/'],
            'query string' => ['https://h/?q'],
            'empty query string' => ['https://h/?'],
            'fragment' => ['https://h/#f'],
            'no host' => ['https:///path'],
            'not a URL' => ['smp.example'],
            'whitespace' => ['https://smp.example/a b'],
        ];
    }

    #[DataProvider('unsafeUrls')]
    public function test_rejects_unsafe_smp_urls(string $url): void
    {
        $locator = new SmlLocator(new FakeNaptrResolver([self::record('!.*!'.$url.'!')]));

        self::assertNull($locator->smpUrl(ParticipantId::parse('0208:0123456789')));
        self::assertNotNull($locator->lastError());
        self::assertStringContainsString('SMP URL', (string) $locator->lastError());
    }

    public function test_resolver_failure_is_unknown_not_unregistered(): void
    {
        $locator = new SmlLocator(new FakeNaptrResolver(null));

        self::assertNull($locator->smpUrl(ParticipantId::parse('0208:0123456789')));
        self::assertStringContainsString('DNS', (string) $locator->lastError());
    }

    public function test_no_record_is_not_registered(): void
    {
        $locator = new SmlLocator(new FakeNaptrResolver([]));

        self::assertFalse($locator->smpUrl(ParticipantId::parse('0208:0123456789')));
        self::assertNull($locator->lastError());
    }

    public function test_ignores_records_that_are_not_meta_smp(): void
    {
        $locator = new SmlLocator(new FakeNaptrResolver([
            self::record('!.*!https://wrong-service.example/!', order: 1, services: 'Meta:Other'),
            self::record('!.*!https://wrong-flag.example/!', order: 1, flags: 'S'),
            self::record('!.*!https://right.example/!', order: 50, flags: 'u', services: 'meta:smp'),
        ]));

        self::assertSame('https://right.example/', $locator->smpUrl(ParticipantId::parse('0208:0123456789')));
    }

    public function test_only_foreign_records_is_not_registered(): void
    {
        $locator = new SmlLocator(new FakeNaptrResolver([self::record('!.*!https://x.example/!', services: 'Meta:Other')]));

        self::assertFalse($locator->smpUrl(ParticipantId::parse('0208:0123456789')));
    }

    public function test_selects_lowest_order_then_preference(): void
    {
        $locator = new SmlLocator(new FakeNaptrResolver([
            self::record('!.*!https://order-20.example/!', order: 20, preference: 1),
            self::record('!.*!https://order-10-pref-20.example/!', order: 10, preference: 20),
            self::record('!.*!https://order-10-pref-5.example/!', order: 10, preference: 5),
        ]));

        self::assertSame('https://order-10-pref-5.example/', $locator->smpUrl(ParticipantId::parse('0208:0123456789')));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedRegexps(): array
    {
        return [
            'empty' => [''],
            'too few delimiters' => ['!.*!https://x.example/'],
            'unknown flag' => ['!.*!https://x.example/!g'],
            'invalid pattern' => ['!(!https://x.example/!'],
            'no match' => ['!^nomatch$!https://x.example/!'],
        ];
    }

    #[DataProvider('malformedRegexps')]
    public function test_malformed_regexp_is_unknown(string $regexp): void
    {
        $locator = new SmlLocator(new FakeNaptrResolver([self::record($regexp)]));

        self::assertNull($locator->smpUrl(ParticipantId::parse('0208:0123456789')));
        self::assertNotNull($locator->lastError());
    }

    public function test_last_error_is_cleared_by_the_next_lookup(): void
    {
        $resolver = new FakeNaptrResolver(null);
        $locator = new SmlLocator($resolver);
        $locator->smpUrl(ParticipantId::parse('0208:0123456789'));
        $resolver->records = [self::record('!.*!https://smp.example/!')];

        self::assertSame('https://smp.example/', $locator->smpUrl(ParticipantId::parse('0208:0123456789')));
        self::assertNull($locator->lastError());
    }

    public function test_dns_get_record_rows_are_mapped(): void
    {
        $records = DnsGetRecordResolver::toRecords([
            ['host' => 'h', 'class' => 'IN', 'ttl' => 77, 'type' => 'NAPTR', 'order' => 100, 'pref' => 10, 'flags' => 'U', 'services' => 'Meta:SMP', 'regex' => '!.*!https://smp.tickstar.com/peppol/!', 'replacement' => ''],
            ['host' => 'h', 'class' => 'IN', 'ttl' => 77, 'type' => 'A', 'ip' => '127.0.0.1'],
        ]);

        self::assertEquals([new NaptrRecord(100, 10, 'U', 'Meta:SMP', '!.*!https://smp.tickstar.com/peppol/!', '')], $records);
    }

    private static function record(string $regexp, int $order = 100, int $preference = 10, string $flags = 'U', string $services = 'Meta:SMP'): NaptrRecord
    {
        return new NaptrRecord($order, $preference, $flags, $services, $regexp, '');
    }
}
