<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\EInvoiceBe;

use IntrAgile\EInvoicing\Transport\EInvoiceBe\CanonicalJson;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class CanonicalJsonTest extends TestCase
{
    /**
     * The example event and its canonical string, published by e-invoice.be on
     * https://docs.e-invoice.be/essentials/webhooks.md ("Event payload" and "Test vector"; research
     * note RESEARCH-PEPPOL-DRIVERS.md §2.5). They are a published fact, used verbatim.
     */
    public const DOCUMENTED_EVENT = <<<'JSON'
        {
          "id": "evt-e7wyc7gtpqx4z73x2wqmwhebhbb3r8n3ovfhcsdbulxr3s2awf49de76yglrnri3",
          "tenant_id": "ten-k3m9x2p7q4w8z5n1",
          "created_at": 1762780249,
          "type": "document.sent",
          "data": {
            "document_id": "doc-1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d"
          },
          "text": "\u26a1\ufe0f New webhook event: document.sent\n\n{\n  \"document_id\": \"doc-1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d\"\n}"
        }
        JSON;

    /** @see self::DOCUMENTED_EVENT */
    public const DOCUMENTED_CANONICAL = <<<'TEXT'
        {"created_at": 1762780249, "data": {"document_id": "doc-1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d"}, "id": "evt-e7wyc7gtpqx4z73x2wqmwhebhbb3r8n3ovfhcsdbulxr3s2awf49de76yglrnri3", "tenant_id": "ten-k3m9x2p7q4w8z5n1", "text": "\u26a1\ufe0f New webhook event: document.sent\n\n{\n  \"document_id\": \"doc-1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d\"\n}", "type": "document.sent"}
        TEXT;

    /**
     * The documented example event as the API sends it: compact, keys unsorted and non-ASCII
     * characters unescaped.
     */
    public static function documentedEventAsSent(): string
    {
        return '{"id":"evt-e7wyc7gtpqx4z73x2wqmwhebhbb3r8n3ovfhcsdbulxr3s2awf49de76yglrnri3","tenant_id":"ten-k3m9x2p7q4w8z5n1","created_at":1762780249,"type":"document.sent","data":{"document_id":"doc-1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d"},"text":"'
            ."\u{26A1}\u{FE0F}".' New webhook event: document.sent\n\n{\n  \"document_id\": \"doc-1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d\"\n}"}';
    }

    public function test_documented_vector(): void
    {
        self::assertSame(self::DOCUMENTED_CANONICAL, CanonicalJson::fromRaw(self::DOCUMENTED_EVENT));
        self::assertSame(self::DOCUMENTED_CANONICAL, CanonicalJson::fromRaw(self::documentedEventAsSent()));
    }

    public function test_unicode_and_key_order(): void
    {
        // U+FF5E sorts before U+1F600 by code point, as Python sorts str keys, although its UTF-16
        // unit is higher than the emoji's high surrogate. "10" sorts before "9" as a string.
        $raw = '{"b":{"z":1,"a":[true,false,null]},"a":"x","é":"é","😀":"😀","～":"ő","9":-3,"10":0}';

        self::assertSame(
            '{"10": 0, "9": -3, "a": "x", "b": {"a": [true, false, null], "z": 1}, "\u00e9": "\u00e9", "\uff5e": "\u0151", "\ud83d\ude00": "\ud83d\ude00"}',
            CanonicalJson::fromRaw($raw),
        );
    }

    public function test_control_chars_and_del(): void
    {
        $raw = '{"s":"\u0000\u0001a\u007f\n\r\t\b\f\"\\\\ \u001f~"}';

        self::assertSame('{"s": "\u0000\u0001a\u007f\n\r\t\b\f\"\\\\ \u001f~"}', CanonicalJson::fromRaw($raw));
        self::assertSame('{"s": "\u007f"}', CanonicalJson::fromRaw("{\"s\":\"\x7f\"}"), 'a raw DEL byte is escaped too');
    }

    public function test_slash_not_escaped(): void
    {
        self::assertSame('{"u": "https://a/b/c"}', CanonicalJson::fromRaw('{"u":"https:\/\/a\/b/c"}'));
    }

    public function test_empty_object_vs_list(): void
    {
        self::assertSame(
            '{"l": [], "n": [1, {"x": []}, {}], "o": {}}',
            CanonicalJson::fromRaw('{"o":{},"l":[],"n":[1,{"x":[]},{}]}'),
        );
        self::assertSame('{}', CanonicalJson::fromRaw(' {} '));
        self::assertSame('[]', CanonicalJson::fromRaw('[]'));
    }

    public function test_integers_in_range_are_kept(): void
    {
        self::assertSame(
            '{"max": 9223372036854775807, "min": -9223372036854775808, "zero": 0}',
            CanonicalJson::fromRaw('{"zero":-0,"max":9223372036854775807,"min":-9223372036854775808}'),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function floats(): iterable
    {
        yield 'fraction' => ['{"a":1.5}'];
        yield 'zero fraction' => ['{"a":1.0}'];
        yield 'exponent' => ['{"a":1e3}'];
        yield 'nested in a list' => ['{"a":[1,{"b":-0.25}]}'];
    }

    #[DataProvider('floats')]
    public function test_float_fails_closed(string $raw): void
    {
        $this->expectException(UnexpectedValueException::class);

        CanonicalJson::fromRaw($raw);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function outOfRangeIntegers(): iterable
    {
        yield 'above PHP_INT_MAX' => ['{"a":9223372036854775808}'];
        yield 'below PHP_INT_MIN' => ['{"a":-9223372036854775809}'];
        yield 'huge' => ['{"a":123456789012345678901234567890}'];
    }

    #[DataProvider('outOfRangeIntegers')]
    public function test_out_of_range_integer_fails_closed(string $raw): void
    {
        $this->expectException(UnexpectedValueException::class);

        CanonicalJson::fromRaw($raw);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidJson(): iterable
    {
        yield 'empty' => [''];
        yield 'truncated' => ['{"a":'];
        yield 'invalid UTF-8' => ["{\"a\":\"\xC3\x28\"}"];
        yield 'lone surrogate' => ['{"a":"\ud800"}'];
        yield 'too deep' => [str_repeat('[', 70).str_repeat(']', 70)];
    }

    #[DataProvider('invalidJson')]
    public function test_invalid_json_fails_closed(string $raw): void
    {
        try {
            CanonicalJson::fromRaw($raw);
            self::fail('No exception was thrown.');
        } catch (UnexpectedValueException $exception) {
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }
}
