<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Lookup;

use IntrAgile\EInvoicing\Transport\Lookup\DnsGetRecordResolver;
use PHPUnit\Framework\TestCase;
use ValueError;

final class DnsGetRecordResolverTest extends TestCase
{
    protected function setUp(): void
    {
        if (! defined('DNS_NAPTR')) {
            self::markTestSkipped('This PHP build has no NAPTR support.');
        }
    }

    public function test_failed_lookup_is_null_and_its_warning_is_suppressed(): void
    {
        $resolver = new DnsGetRecordResolver(static function (string $host, int $type): false {
            trigger_error('dns_get_record(): A temporary server error occurred.', E_USER_WARNING);

            return false;
        });

        self::assertNull($resolver->naptr('participant.example'));
    }

    public function test_value_error_is_a_failed_lookup(): void
    {
        $resolver = new DnsGetRecordResolver(static function (string $host, int $type): array|false {
            throw new ValueError('dns_get_record(): Argument #1 ($hostname) cannot be empty');
        });

        self::assertNull($resolver->naptr(''));
    }

    public function test_platform_without_naptr_support_fails_every_lookup(): void
    {
        $called = false;
        $resolver = new DnsGetRecordResolver(
            static function (string $host, int $type) use (&$called): array {
                $called = true;

                return [];
            },
            'DNS_CONSTANT_THAT_DOES_NOT_EXIST',
        );

        self::assertNull($resolver->naptr('participant.example'));
        self::assertFalse($called, 'Nothing is queried without the record type constant.');
    }

    public function test_rows_are_mapped_and_an_empty_answer_is_no_record(): void
    {
        $queried = [];
        $resolver = new DnsGetRecordResolver(static function (string $host, int $type) use (&$queried): array {
            $queried[] = [$host, $type];

            return $host === 'empty.example' ? [] : [
                ['type' => 'NAPTR', 'order' => 100, 'pref' => 10, 'flags' => 'U', 'services' => 'Meta:SMP', 'regex' => '!.*!https://smp.example/!', 'replacement' => '.'],
                ['type' => 'A', 'ip' => '192.0.2.1'],
            ];
        });

        $records = $resolver->naptr('participant.example');

        self::assertNotNull($records);
        self::assertCount(1, $records);
        self::assertSame('!.*!https://smp.example/!', $records[0]->regexp);
        self::assertSame([], $resolver->naptr('empty.example'));
        self::assertSame(['participant.example', (int) constant('DNS_NAPTR')], $queried[0]);
    }
}
