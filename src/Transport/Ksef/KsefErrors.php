<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use JsonException;
use Psr\Http\Message\ResponseInterface;

/**
 * Maps unsuccessful KSeF answers to transport exceptions.
 *
 * KSeF reports errors in two shapes, and both are read: the Problem Details format
 * (application/problem+json, BadRequestProblemDetails and its siblings, chosen with the
 * "X-Error-Format: problem-details" request header) with an "errors" list of
 * {code, description, details}, and the classic ExceptionResponse with
 * "exception.exceptionDetailList" entries of {exceptionCode, exceptionDescription, details}.
 * Every text taken from a response has the configured secrets removed.
 *
 * @internal
 */
final class KsefErrors
{
    /** KSeF codes of a request body KSeF refused: schema, validation, size, hash, encoding, signature. */
    private const REJECTED_CODES = [21401, 21402, 21403, 21405, 21217, 9101, 9102, 9103, 9105];

    /** The KSeF code of a technical correction refused because the invoice already has an accepted one. */
    public const TECHNICAL_CORRECTION_UNAVAILABLE = 21166;

    /** KSeF codes of a missing authorisation or authentication. */
    private const AUTHENTICATION_CODES = [21301, 21304];

    /** KSeF codes of an invoice, session or UPO that does not exist or is not available yet. */
    private const NOT_FOUND_CODES = [21164, 21165, 21173, 21178];

    /** Methods that never change state on the server, so a failed request may be repeated. */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** Bytes of KSeF error text quoted in an exception message. */
    private const QUOTED_BYTES = 500;

    /** The words of an AuthenticationFailed for a missing or no longer valid authentication. */
    private const AUTHENTICATION_REFUSED = 'KSeF refused the authentication';

    private const HTTP_DATE = 'D, d M Y H:i:s \G\M\T';

    public function __construct(private readonly Redactor $redactor) {}

    /**
     * The exception an unsuccessful answer stands for: 401, or a 4xx with KSeF code 21301/21304,
     * is AuthenticationFailed, 403 is AuthenticationFailed (no permission in the context), 429 is
     * RateLimited, a 5xx status is TransportUnavailable for a safe method and OutcomeUnknown
     * otherwise, 400 with a refused-body code or with 21166 (a technical correction already
     * accepted) is DocumentRejected, and 404, a not-found code or anything else is
     * UnexpectedResponse.
     *
     * @param  string  $operation  what was attempted, phrased to start a sentence
     * @param  string  $method  the HTTP method of the request; a server error on a request that
     *                          may change state is reported as OutcomeUnknown
     */
    public function exception(ResponseInterface $response, string $operation, string $method = 'POST'): TransportException
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = self::decode($body);
        $entries = $data === null ? [] : self::entries($data);
        $codes = array_values(array_filter(array_column($entries, 'code'), is_int(...)));
        $lines = array_map(fn (array $entry): string => $this->line($entry), $entries);
        $detail = $this->detail($data, $lines);

        if ($status >= 300 && $status <= 399) {
            // No body excerpt: a redirect body usually repeats the Location, which may carry a token.
            return UnexpectedResponse::forResponse($response, sprintf('%s failed: KSeF answered with a redirect, which is not followed', $operation), '');
        }
        // An authentication code counts only in a client error; a 5xx keeps its own mapping.
        if ($status === 401 || ($status >= 400 && $status <= 499 && array_intersect($codes, self::AUTHENTICATION_CODES) !== [])) {
            return new AuthenticationFailed(sprintf('%s failed: %s (HTTP %d)%s.', $operation, self::AUTHENTICATION_REFUSED, $status, $detail));
        }
        if ($status === 403) {
            return new AuthenticationFailed(sprintf('%s failed: KSeF refused the request, no permission in context (HTTP 403)%s.', $operation, $detail));
        }
        if ($status === 429) {
            return new RateLimited(
                sprintf('%s failed: KSeF rate limit reached (HTTP 429)%s.', $operation, $detail),
                self::retryAfter($response),
            );
        }
        if ($status >= 500 && $status <= 599) {
            if (in_array(strtoupper($method), self::SAFE_METHODS, true)) {
                return new TransportUnavailable(sprintf('%s failed: KSeF is unavailable (HTTP %d)%s.', $operation, $status, $detail));
            }

            return new OutcomeUnknown(sprintf(
                '%s failed with a KSeF server error (HTTP %d), so KSeF may or may not have processed it%s. Check its state before repeating it; KSeF answers a resent invoice with status 440 and the original KSeF number.',
                $operation,
                $status,
                $detail,
            ));
        }
        if ($status === 400 && in_array(self::TECHNICAL_CORRECTION_UNAVAILABLE, $codes, true)) {
            return new DocumentRejected(
                sprintf('%s failed: KSeF refused the technical correction, because a technical correction was already accepted for the corrected invoice (HTTP 400)%s.', $operation, $detail),
                ['errors' => $lines],
            );
        }
        if ($status === 400 && array_intersect($codes, self::REJECTED_CODES) !== []) {
            return new DocumentRejected(sprintf('%s failed: KSeF rejected the request (HTTP 400)%s.', $operation, $detail), ['errors' => $lines]);
        }
        if ($status === 404 || array_intersect($codes, self::NOT_FOUND_CODES) !== []) {
            return $this->redactor->unexpected($response, sprintf('%s failed: KSeF does not know the resource%s', $operation, $detail));
        }

        return $this->redactor->unexpected($response, sprintf('%s failed with an unexpected answer from KSeF%s', $operation, $detail));
    }

    /**
     * The first KSeF error code of an error body in either shape, or null when it has none.
     *
     * @param  array<mixed>  $body
     */
    public static function code(array $body): ?int
    {
        return self::codes($body)[0] ?? null;
    }

    /**
     * Every KSeF error code of an error body in either shape, in the order listed.
     *
     * @param  array<mixed>  $body
     * @return list<int>
     */
    public static function codes(array $body): array
    {
        return array_values(array_filter(array_column(self::entries($body), 'code'), is_int(...)));
    }

    /**
     * The first KSeF error code behind an exception from exception(), or null when it carries
     * none; see codesOf().
     */
    public static function codeOf(TransportException $exception): ?int
    {
        return self::codesOf($exception)[0] ?? null;
    }

    /**
     * True when the exception carries the given KSeF error code in any position.
     */
    public static function hasCode(TransportException $exception, int $code): bool
    {
        return in_array($code, self::codesOf($exception), true);
    }

    /**
     * Every KSeF error code behind an exception from exception(), in the order listed: read from
     * the error lines of a DocumentRejected and from the body excerpt or the message of an
     * UnexpectedResponse. Empty for any other exception.
     *
     * @return list<int>
     */
    public static function codesOf(TransportException $exception): array
    {
        if ($exception instanceof DocumentRejected) {
            $codes = [];
            $lines = $exception->errors['errors'] ?? null;
            foreach (is_array($lines) ? $lines : [] as $line) {
                if (is_string($line) && preg_match('/\A(\d{1,9}): /', $line, $match) === 1) {
                    $codes[] = (int) $match[1];
                }
            }

            return $codes;
        }
        if (! $exception instanceof UnexpectedResponse) {
            return [];
        }
        $data = self::decode($exception->bodyExcerpt);
        $codes = $data === null ? [] : self::codes($data);
        if ($codes !== []) {
            return $codes;
        }
        // The excerpt may be cut short; the message lists the codes as "<code>: <description>".
        preg_match_all('/(?:: |; )(\d{1,9}): /', $exception->getMessage(), $matches);

        return array_map(intval(...), $matches[1]);
    }

    /**
     * True when an exception from exception() stands for a missing or no longer valid
     * authentication (HTTP 401 or KSeF code 21301/21304), which a fresh access token may cure;
     * false for a refused permission (HTTP 403) and for any other failure.
     */
    public static function isAuthenticationRefusal(TransportException $exception): bool
    {
        if (! $exception instanceof AuthenticationFailed) {
            return false;
        }
        // Only the words right after the operation count, not text KSeF sent in the detail.
        $message = $exception->getMessage();
        $at = strpos($message, ' failed: ');

        return $at !== false && str_starts_with(substr($message, $at + 9), self::AUTHENTICATION_REFUSED.' (HTTP ');
    }

    /**
     * The Retry-After header in seconds, given either as a number of seconds or as an HTTP date;
     * a date is measured from the response's Date header, or from the local clock without one.
     * Null when the header is absent or unreadable.
     */
    public static function retryAfter(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $until = self::httpDate($value);
        if ($until === null) {
            return null;
        }
        $now = self::httpDate(trim($response->getHeaderLine('Date')))?->getTimestamp() ?? time();

        return max(0, $until->getTimestamp() - $now);
    }

    /**
     * The error entries of a body in either shape, normalised.
     *
     * @param  array<mixed>  $body
     * @return list<array{code: ?int, description: string, details: list<string>}>
     */
    private static function entries(array $body): array
    {
        $entries = [];
        $problems = $body['errors'] ?? null;
        if (is_array($problems)) {
            foreach ($problems as $problem) {
                if (is_array($problem)) {
                    $entries[] = self::entry($problem['code'] ?? null, $problem['description'] ?? null, $problem['details'] ?? null);
                }
            }
        }
        $exception = $body['exception'] ?? null;
        $list = is_array($exception) ? ($exception['exceptionDetailList'] ?? null) : null;
        if (is_array($list)) {
            foreach ($list as $item) {
                if (is_array($item)) {
                    $entries[] = self::entry($item['exceptionCode'] ?? null, $item['exceptionDescription'] ?? null, $item['details'] ?? null);
                }
            }
        }

        return $entries;
    }

    /**
     * @return array{code: ?int, description: string, details: list<string>}
     */
    private static function entry(mixed $code, mixed $description, mixed $details): array
    {
        return [
            'code' => is_int($code) ? $code : null,
            'description' => is_string($description) ? trim($description) : '',
            'details' => is_array($details)
                ? array_values(array_filter(array_map(static fn (mixed $d): string => is_string($d) ? trim($d) : '', $details), static fn (string $d): bool => $d !== ''))
                : [],
        ];
    }

    /**
     * "<code>: <description> — <details>", redacted.
     *
     * @param  array{code: ?int, description: string, details: list<string>}  $entry
     */
    private function line(array $entry): string
    {
        $line = ($entry['code'] === null ? '' : $entry['code'].': ').$entry['description'];
        if ($entry['details'] !== []) {
            $line .= ' — '.implode('; ', $entry['details']);
        }

        return $this->redactor->redact(trim($line));
    }

    /**
     * ": " and the error lines, or the Problem Details detail and reason code when there are no
     * lines; an empty string without either. Redacted and cut to QUOTED_BYTES.
     *
     * @param  array<mixed>|null  $data
     * @param  list<string>  $lines
     */
    private function detail(?array $data, array $lines): string
    {
        $text = implode('; ', array_filter($lines, static fn (string $line): bool => $line !== ''));
        if ($text === '' && $data !== null) {
            $parts = [];
            foreach (['reasonCode', 'detail'] as $key) {
                if (is_string($data[$key] ?? null) && trim($data[$key]) !== '') {
                    $parts[] = trim($data[$key]);
                }
            }
            $text = $this->redactor->redact(implode(': ', $parts));
        }
        $text = rtrim($text, '. ');
        if ($text === '') {
            return '';
        }
        if (strlen($text) > self::QUOTED_BYTES) {
            $text = mb_strcut($text, 0, self::QUOTED_BYTES, 'UTF-8').'...';
        }

        return ': '.$text;
    }

    /**
     * @return array<mixed>|null
     */
    private static function decode(string $body): ?array
    {
        if (trim($body) === '') {
            return null;
        }
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    private static function httpDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat(self::HTTP_DATE, $value, new DateTimeZone('UTC'));

        return $date === false ? null : $date;
    }
}
