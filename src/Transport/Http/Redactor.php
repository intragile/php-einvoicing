<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Http;

use Closure;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;

/**
 * Removes credentials from text that came back from the network, before it reaches an exception
 * message, a body excerpt or a log.
 *
 * Each secret is replaced raw, percent-encoded (rawurlencode) and Base64-encoded. Only whole
 * occurrences are replaced, longest first, so that a value is replaced before any part of it. The
 * secrets are kept in a closure, so they stay out of var_export(), print_r(), var_dump() and
 * json_encode() output.
 *
 * @internal
 */
final class Redactor
{
    public const REDACTED = '[redacted]';

    /** @var Closure(): list<string> */
    private readonly Closure $secrets;

    /**
     * @param  list<string>  $secrets  blank values are ignored; there is no length filter here
     */
    public function __construct(#[SensitiveParameter] array $secrets)
    {
        $kept = array_values(array_unique(array_filter(
            $secrets,
            static fn (string $secret): bool => trim($secret) !== '',
        )));
        $this->secrets = static fn (): array => $kept;
    }

    /**
     * The text with every variant of every secret replaced by "[redacted]".
     */
    public function redact(string $text): string
    {
        return self::replace($text, self::variants(($this->secrets)()));
    }

    /**
     * The value with redact() applied to every string inside it, at any depth.
     */
    public function redactValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->redact($value);
        }
        if (is_array($value)) {
            return array_map($this->redactValue(...), $value);
        }

        return $value;
    }

    /**
     * An UnexpectedResponse with the secrets removed from its message and body excerpt. When the
     * full body is given, the excerpt is cut from it after redaction, so that a secret cut in two
     * by the excerpt limit cannot survive in part.
     */
    public function sanitize(UnexpectedResponse $exception, ?string $body = null): UnexpectedResponse
    {
        $excerpt = $body === null
            ? $this->redact($exception->bodyExcerpt)
            : substr($this->redact($body), 0, UnexpectedResponse::EXCERPT_BYTES);

        return new UnexpectedResponse(
            $this->redact($exception->getMessage()),
            $exception->status,
            $excerpt,
            $exception->getPrevious(),
        );
    }

    /**
     * An UnexpectedResponse for the response, with the secrets removed from the reason and from
     * the body before its excerpt is cut.
     */
    public function unexpected(ResponseInterface $response, string $reason): UnexpectedResponse
    {
        return UnexpectedResponse::forResponse(
            $response,
            $this->redact($reason),
            $this->redact((string) $response->getBody()),
        );
    }

    /**
     * A redactor that removes these secrets and the given ones, such as a signed URL that is
     * itself a credential.
     *
     * @param  list<string>  $more
     */
    public function with(#[SensitiveParameter] array $more): self
    {
        return new self([...($this->secrets)(), ...$more]);
    }

    /**
     * Each secret raw, percent-encoded and Base64-encoded.
     *
     * @param  list<string>  $secrets
     * @return list<string>
     */
    public static function variants(#[SensitiveParameter] array $secrets): array
    {
        $variants = [];
        foreach ($secrets as $secret) {
            array_push($variants, $secret, rawurlencode($secret), base64_encode($secret));
        }

        return $variants;
    }

    /**
     * The text with each value replaced by "[redacted]": blank values are dropped and the longest
     * go first, so that a whole value is replaced before a part of it.
     *
     * @param  list<string>  $values
     */
    public static function replace(string $text, #[SensitiveParameter] array $values): string
    {
        $values = array_filter($values, static fn (string $value): bool => trim($value) !== '');
        usort($values, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return str_replace($values, self::REDACTED, $text);
    }

    /**
     * @return array{secrets: string}
     */
    public function __debugInfo(): array
    {
        return ['secrets' => self::REDACTED];
    }
}
