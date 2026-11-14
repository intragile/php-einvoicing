<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Webhook;

use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookInput;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookInputTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, string|list<string>>, ?string}>
     */
    public static function headerCases(): iterable
    {
        yield 'exact name' => [['X-Signature' => 'abc'], 'abc'];
        yield 'other case' => [['x-SIGNATURE' => 'abc'], 'abc'];
        yield 'single-value list' => [['x-signature' => ['abc']], 'abc'];
        yield 'empty value is kept' => [['X-Signature' => ''], ''];
        yield 'missing' => [['X-Other' => 'abc'], null];
        yield 'no headers' => [[], null];
        yield 'empty list' => [['X-Signature' => []], null];
        yield 'two different values' => [['X-Signature' => ['abc', 'def']], null];
        yield 'two identical values' => [['X-Signature' => ['abc', 'abc']], null];
        yield 'two keys differing in case' => [['X-Signature' => 'abc', 'x-signature' => 'abc'], null];
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    #[DataProvider('headerCases')]
    public function test_header_requires_exactly_one_value(array $headers, ?string $expected): void
    {
        self::assertSame($expected, WebhookInput::header($headers, 'X-Signature'));
    }

    public function test_object_decodes_a_json_object(): void
    {
        self::assertSame(['a' => 1, 'b' => ['c' => 'd']], WebhookInput::object(" \n{\"a\":1,\"b\":{\"c\":\"d\"}}", 'Example'));
        self::assertSame([], WebhookInput::object('{}', 'Example'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonObjects(): iterable
    {
        yield 'not JSON' => ['not json', 'The Example webhook notification is not valid JSON.'];
        yield 'empty body' => ['', 'The Example webhook notification is not valid JSON.'];
        yield 'string' => ['"x"', 'The Example webhook notification is not a JSON object.'];
        yield 'number' => ['42', 'The Example webhook notification is not a JSON object.'];
        yield 'null' => ['null', 'The Example webhook notification is not a JSON object.'];
        yield 'list' => ['[1,2]', 'The Example webhook notification is not a JSON object.'];
        yield 'empty list' => ['[]', 'The Example webhook notification is not a JSON object.'];
    }

    #[DataProvider('nonObjects')]
    public function test_object_refuses_anything_else(string $raw, string $message): void
    {
        try {
            WebhookInput::object($raw, 'Example');
            self::fail('No exception was thrown.');
        } catch (UnexpectedResponse $exception) {
            self::assertSame($message, $exception->getMessage());
            self::assertSame(0, $exception->status);
            self::assertSame($raw, $exception->bodyExcerpt);
        }
    }

    public function test_invalid_json_keeps_the_decoder_error_as_previous(): void
    {
        try {
            WebhookInput::object('{', 'Example');
            self::fail('No exception was thrown.');
        } catch (UnexpectedResponse $exception) {
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }

    public function test_excerpt_is_cut_at_the_limit(): void
    {
        $raw = '['.str_repeat('1,', 400).'1]';

        try {
            WebhookInput::object($raw, 'Example');
            self::fail('No exception was thrown.');
        } catch (UnexpectedResponse $exception) {
            self::assertSame(substr($raw, 0, UnexpectedResponse::EXCERPT_BYTES), $exception->bodyExcerpt);
        }
    }

    public function test_malformed_names_the_provider_and_the_problem(): void
    {
        $exception = WebhookInput::malformed('Example', 'has no event type', '{"a":1}');

        self::assertSame('The Example webhook notification has no event type.', $exception->getMessage());
        self::assertSame('{"a":1}', $exception->bodyExcerpt);
    }
}
