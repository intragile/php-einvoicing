<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Http;

use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RedactorTest extends TestCase
{
    private const SECRET = 'secret_01/top+value';

    public function test_variants_are_raw_percent_encoded_and_base64(): void
    {
        self::assertSame(
            [self::SECRET, rawurlencode(self::SECRET), base64_encode(self::SECRET), 'other_01', 'other_01', base64_encode('other_01')],
            Redactor::variants([self::SECRET, 'other_01']),
        );
    }

    public function test_redact_replaces_every_variant(): void
    {
        $text = sprintf('raw %s, encoded %s, base64 %s.', self::SECRET, rawurlencode(self::SECRET), base64_encode(self::SECRET));

        self::assertSame('raw [redacted], encoded [redacted], base64 [redacted].', (new Redactor([self::SECRET]))->redact($text));
    }

    public function test_replace_goes_longest_first_and_drops_blanks(): void
    {
        // The shorter value is a part of the longer one; replacing it first would leave the rest.
        self::assertSame('a [redacted] b', Redactor::replace('a key_01:pass_01 b', ['key_01', ' ', '', 'key_01:pass_01']));
        self::assertSame('unchanged', Redactor::replace('unchanged', ['', "\t"]));
    }

    public function test_blank_secrets_are_ignored(): void
    {
        self::assertSame('a b', (new Redactor(['', '  ']))->redact('a b'));
    }

    public function test_redact_value_reaches_nested_strings_only(): void
    {
        $value = ['message' => 'bad '.self::SECRET, 'nested' => [['field' => self::SECRET, 'code' => 7, 'ok' => true]]];

        self::assertSame(
            ['message' => 'bad [redacted]', 'nested' => [['field' => '[redacted]', 'code' => 7, 'ok' => true]]],
            (new Redactor([self::SECRET]))->redactValue($value),
        );
        self::assertSame(42, (new Redactor([self::SECRET]))->redactValue(42));
    }

    public function test_sanitize_cuts_the_excerpt_after_redaction(): void
    {
        // The secret straddles the excerpt limit: cutting first would leave its start behind.
        $body = str_repeat('x', UnexpectedResponse::EXCERPT_BYTES - 5).self::SECRET.' tail';
        $original = new UnexpectedResponse('Refused '.self::SECRET.' (HTTP 400).', 400, substr($body, 0, UnexpectedResponse::EXCERPT_BYTES), new RuntimeException('cause'));

        $sanitized = (new Redactor([self::SECRET]))->sanitize($original, $body);

        self::assertSame('Refused [redacted] (HTTP 400).', $sanitized->getMessage());
        self::assertSame(400, $sanitized->status);
        self::assertSame(substr(str_repeat('x', UnexpectedResponse::EXCERPT_BYTES - 5).'[redacted] tail', 0, UnexpectedResponse::EXCERPT_BYTES), $sanitized->bodyExcerpt);
        self::assertStringNotContainsString(substr(self::SECRET, 0, 5), $sanitized->bodyExcerpt);
        self::assertSame($original->getPrevious(), $sanitized->getPrevious());
    }

    public function test_sanitize_without_body_redacts_the_existing_excerpt(): void
    {
        $sanitized = (new Redactor([self::SECRET]))->sanitize(new UnexpectedResponse('m', 500, 'echo '.self::SECRET));

        self::assertSame('echo [redacted]', $sanitized->bodyExcerpt);
    }

    public function test_unexpected_redacts_reason_and_body(): void
    {
        $response = new Response(418, [], '{"echo":"'.self::SECRET.'"}');

        $exception = (new Redactor([self::SECRET]))->unexpected($response, 'Odd answer for '.self::SECRET);

        self::assertSame('Odd answer for [redacted] (HTTP 418).', $exception->getMessage());
        self::assertSame(418, $exception->status);
        self::assertSame('{"echo":"[redacted]"}', $exception->bodyExcerpt);
    }

    public function test_with_adds_secrets_and_keeps_the_original(): void
    {
        $redactor = new Redactor([self::SECRET]);
        $url = 'https://files.example/doc?sig=abcdef123456';

        $extended = $redactor->with([$url]);

        self::assertSame('[redacted] [redacted]', $extended->redact(self::SECRET.' '.$url));
        self::assertSame('[redacted] '.$url, $redactor->redact(self::SECRET.' '.$url));
    }

    public function test_dumps_hide_the_secrets(): void
    {
        $redactor = new Redactor([self::SECRET]);

        self::assertSame(['secrets' => '[redacted]'], $redactor->__debugInfo());
        foreach ([var_export($redactor, true), print_r($redactor, true), (string) json_encode($redactor), self::dump($redactor)] as $output) {
            self::assertStringNotContainsString(self::SECRET, $output);
        }
    }

    private static function dump(mixed $value): string
    {
        ob_start();
        var_dump($value);

        return (string) ob_get_clean();
    }
}
