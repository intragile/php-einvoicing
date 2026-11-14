<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Http;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * A multipart/form-data request body (RFC 7578) that carries one file.
 *
 * The boundary is 16 random bytes in hexadecimal and is drawn again if it occurs anywhere in the
 * content, so the content can never end the part early. Field and file names are limited to
 * letters, digits, ".", "_" and "-", which need no quoting or encoding in the part header.
 */
final class MultipartBody
{
    private const NAME_PATTERN = '/\A[A-Za-z0-9._-]{1,100}\z/';

    private const BOUNDARY_BYTES = 16;

    /** Draws before giving up; a collision with 128 random bits does not happen by chance. */
    private const MAX_DRAWS = 8;

    private function __construct(
        private readonly string $body,
        private readonly string $boundary,
    ) {}

    /**
     * @param  string  $contentType  the media type of the file, for example "application/xml"
     * @param  (Closure(int): string)|null  $randomBytes  @internal for tests only; the source of
     *                                                    random bytes, random_bytes() by default
     *
     * @throws InvalidArgumentException when the field or file name is not 1 to 100 letters, digits,
     *                                  ".", "_" or "-", or the content type is empty or not
     *                                  printable ASCII
     * @throws RuntimeException when no boundary that is absent from the content could be drawn
     */
    public static function file(string $field, string $filename, string $contentType, string $content, ?Closure $randomBytes = null): self
    {
        if (preg_match(self::NAME_PATTERN, $field) !== 1) {
            throw new InvalidArgumentException('The multipart field name must be 1 to 100 letters, digits, ".", "_" or "-".');
        }
        if (preg_match(self::NAME_PATTERN, $filename) !== 1) {
            throw new InvalidArgumentException('The multipart file name must be 1 to 100 letters, digits, ".", "_" or "-".');
        }
        if (preg_match('/\A[\x20-\x7E]+\z/', $contentType) !== 1) {
            throw new InvalidArgumentException('The multipart content type must be printable ASCII and not empty.');
        }

        $randomBytes ??= random_bytes(...);
        $boundary = null;
        for ($draw = 0; $draw < self::MAX_DRAWS && $boundary === null; $draw++) {
            $candidate = bin2hex($randomBytes(self::BOUNDARY_BYTES));
            if (! str_contains($content, $candidate)) {
                $boundary = $candidate;
            }
        }
        if ($boundary === null) {
            throw new RuntimeException('No multipart boundary absent from the content could be drawn.');
        }

        $body = '--'.$boundary."\r\n"
            .sprintf('Content-Disposition: form-data; name="%s"; filename="%s"', $field, $filename)."\r\n"
            .'Content-Type: '.$contentType."\r\n"
            ."\r\n"
            .$content."\r\n"
            .'--'.$boundary."--\r\n";

        return new self($body, $boundary);
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * The value of the request's Content-Type header, naming the boundary.
     */
    public function contentType(): string
    {
        return 'multipart/form-data; boundary='.$this->boundary;
    }
}
