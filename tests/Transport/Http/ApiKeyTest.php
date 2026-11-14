<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Http;

use IntrAgile\EInvoicing\Transport\Http\ApiKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiKeyTest extends TestCase
{
    private const KEY = 'test_FAKEKEY000000';

    /**
     * @return iterable<string, array{string}>
     */
    public static function shortKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['          '];
        yield 'seven characters' => ['FAKE123'];
        yield 'seven characters padded' => ['  FAKE123  '];
    }

    #[DataProvider('shortKeys')]
    public function test_short_key_is_refused_without_quoting(string $value): void
    {
        try {
            new ApiKey($value, 'Example API key');
            self::fail('No exception was thrown.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('The Example API key must be at least 8 characters.', $exception->getMessage());
            if (trim($value) !== '') {
                self::assertStringNotContainsString(trim($value), $exception->getMessage());
            }
        }
    }

    public function test_minimum_length_is_eight(): void
    {
        self::assertSame(8, ApiKey::MIN_LENGTH);
        self::assertSame('FAKE1234', (new ApiKey('FAKE1234', 'key'))->reveal());
    }

    public function test_reveal_returns_the_trimmed_value(): void
    {
        $key = new ApiKey("  \t".self::KEY."\n", 'Example API key');

        self::assertSame(self::KEY, $key->reveal());
        self::assertSame('Example API key', $key->label);
    }

    public function test_dump_and_json_hide_the_value(): void
    {
        $key = new ApiKey(self::KEY, 'Example API key');

        self::assertSame(['value' => '[redacted]', 'label' => 'Example API key'], $key->__debugInfo());
        ob_start();
        var_dump($key);
        $dump = (string) ob_get_clean();
        foreach ([var_export($key, true), print_r($key, true), (string) json_encode($key), $dump] as $output) {
            self::assertStringNotContainsString(self::KEY, $output);
        }
        self::assertStringContainsString('[redacted]', $dump);
    }
}
