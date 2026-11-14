<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Http;

use IntrAgile\EInvoicing\Transport\Http\MultipartBody;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MultipartBodyTest extends TestCase
{
    private const XML = "<?xml version=\"1.0\"?>\r\n<Invoice>\n  <cbc:ID>INV-1</cbc:ID>\r\n</Invoice>\n";

    public function test_body_parses_with_a_reference_parser(): void
    {
        $multipart = MultipartBody::file('file', 'invoice-1.xml', 'application/xml', self::XML);

        self::assertMatchesRegularExpression('#\Amultipart/form-data; boundary=([0-9a-f]{32})\z#', $multipart->contentType());
        $boundary = substr($multipart->contentType(), strlen('multipart/form-data; boundary='));
        $parts = self::parse($multipart->body(), $boundary);

        self::assertCount(1, $parts);
        self::assertSame([
            'content-disposition' => 'form-data; name="file"; filename="invoice-1.xml"',
            'content-type' => 'application/xml',
        ], $parts[0]['headers']);
        self::assertSame(self::XML, $parts[0]['content']);
    }

    public function test_boundary_never_occurs_in_content(): void
    {
        $seeded = str_repeat("\xAB", 16);
        $content = 'before '.bin2hex($seeded).' after';
        $calls = [];
        $randomBytes = static function (int $length) use ($seeded, &$calls): string {
            $calls[] = $length;

            return count($calls) === 1 ? $seeded : str_repeat("\x01", $length);
        };

        $multipart = MultipartBody::file('file', 'a.xml', 'application/xml', $content, $randomBytes);

        self::assertSame([16, 16], $calls);
        self::assertSame('multipart/form-data; boundary='.str_repeat('01', 16), $multipart->contentType());
        $parts = self::parse($multipart->body(), str_repeat('01', 16));
        self::assertSame($content, $parts[0]['content']);
    }

    public function test_a_source_that_keeps_colliding_is_refused(): void
    {
        $seeded = str_repeat("\xAB", 16);

        $this->expectException(\RuntimeException::class);

        MultipartBody::file('file', 'a.xml', 'application/xml', bin2hex($seeded), static fn (int $length): string => $seeded);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unsafeNames(): iterable
    {
        yield 'field with quote' => ['fi"le', 'a.xml', 'field name'];
        yield 'field with CRLF' => ["file\r\nX-Injected: 1", 'a.xml', 'field name'];
        yield 'empty field' => ['', 'a.xml', 'field name'];
        yield 'field with space' => ['my file', 'a.xml', 'field name'];
        yield 'filename with quote' => ['file', 'a".xml', 'file name'];
        yield 'filename with slash' => ['file', '../a.xml', 'file name'];
        yield 'filename with semicolon' => ['file', 'a.xml; x=1', 'file name'];
        yield 'filename with non-ASCII' => ['file', 'számla.xml', 'file name'];
        yield 'filename over 100 characters' => ['file', str_repeat('a', 97).'.xml', 'file name'];
        yield 'filename with trailing newline' => ['file', "a.xml\n", 'file name'];
    }

    #[DataProvider('unsafeNames')]
    public function test_unsafe_field_names_are_refused(string $field, string $filename, string $what): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($what);

        MultipartBody::file($field, $filename, 'application/xml', self::XML);
    }

    public function test_longest_safe_names_are_accepted(): void
    {
        $name = str_repeat('a', 96).'.xml';

        $parts = self::parseBody(MultipartBody::file('A-z_0.9', $name, 'application/xml', ''));

        self::assertSame('form-data; name="A-z_0.9"; filename="'.$name.'"', $parts[0]['headers']['content-disposition']);
        self::assertSame('', $parts[0]['content']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeContentTypes(): iterable
    {
        yield 'empty' => [''];
        yield 'CRLF injection' => ["application/xml\r\nX-Injected: 1"];
        yield 'non-ASCII' => ['application/xmlé'];
    }

    #[DataProvider('unsafeContentTypes')]
    public function test_unsafe_content_type_is_refused(string $contentType): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('content type');

        MultipartBody::file('file', 'a.xml', $contentType, self::XML);
    }

    /**
     * @return list<array{headers: array<string, string>, content: string}>
     */
    private static function parseBody(MultipartBody $multipart): array
    {
        return self::parse($multipart->body(), substr($multipart->contentType(), strlen('multipart/form-data; boundary=')));
    }

    /**
     * A minimal RFC 7578 reader: the body is split on the delimiter lines, each part into its
     * header block and content.
     *
     * @return list<array{headers: array<string, string>, content: string}>
     */
    private static function parse(string $body, string $boundary): array
    {
        $delimiter = '--'.$boundary;
        self::assertStringStartsWith($delimiter."\r\n", $body);
        self::assertStringEndsWith("\r\n".$delimiter."--\r\n", $body);
        $inner = substr($body, strlen($delimiter) + 2, -strlen("\r\n".$delimiter."--\r\n"));

        $parts = [];
        foreach (explode("\r\n".$delimiter."\r\n", $inner) as $part) {
            $split = strpos($part, "\r\n\r\n");
            self::assertNotFalse($split);
            $headers = [];
            foreach (explode("\r\n", substr($part, 0, $split)) as $line) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower($name)] = trim($value);
            }
            $parts[] = ['headers' => $headers, 'content' => substr($part, $split + 4)];
        }

        return $parts;
    }
}
