<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef;

use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\DocumentRejected;
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\Exception\RateLimited;
use IntrAgile\EInvoicing\Transport\Exception\TransportException;
use IntrAgile\EInvoicing\Transport\Exception\TransportUnavailable;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Ksef\KsefErrors;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KsefErrorsTest extends TestCase
{
    use KsefFixtures;

    private const OPERATION = 'The KSeF request POST /auth/xades-signature';

    public function test_spike_9105_maps_to_document_rejected_with_details(): void
    {
        $exception = self::errors()->exception(self::fixture(400, 'auth-xades-9105.json'), self::OPERATION, 'POST');

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertStringContainsString('HTTP 400', $exception->getMessage());
        self::assertStringContainsString('9105: Nieprawidłowy podpis.', $exception->getMessage());
        self::assertStringContainsString('X509SerialNumber', $exception->getMessage());
        $lines = $exception->errors['errors'] ?? null;
        self::assertIsArray($lines);
        self::assertCount(1, $lines);
        self::assertIsString($lines[0]);
        self::assertStringStartsWith('9105: Nieprawidłowy podpis. — The \'http://www.w3.org/2000/09/xmldsig#:X509SerialNumber\' element is invalid', $lines[0]);
        self::assertSame(9105, KsefErrors::code(self::decoded('auth-xades-9105.json')));
        self::assertSame(9105, KsefErrors::codeOf($exception));
    }

    public function test_authentication_refusal_is_told_apart_from_a_refused_permission(): void
    {
        $errors = self::errors();
        $refusal = $errors->exception(self::fixture(401, 'unauthorized-problem-details.json'), self::OPERATION, 'GET');
        $coded = $errors->exception(self::json(400, ['errors' => [['code' => 21301, 'description' => 'Brak autoryzacji.']]]), self::OPERATION, 'GET');
        $forbidden = $errors->exception(self::fixture(403, 'forbidden-problem-details.json'), self::OPERATION, 'GET');
        $echoed = $errors->exception(self::json(403, ['detail' => 'x failed: KSeF refused the authentication (HTTP 401)']), self::OPERATION, 'GET');

        self::assertTrue(KsefErrors::isAuthenticationRefusal($refusal));
        self::assertTrue(KsefErrors::isAuthenticationRefusal($coded));
        self::assertFalse(KsefErrors::isAuthenticationRefusal($forbidden));
        self::assertFalse(KsefErrors::isAuthenticationRefusal($echoed));
        self::assertFalse(KsefErrors::isAuthenticationRefusal($errors->exception(self::json(500, []), self::OPERATION, 'GET')));
    }

    public function test_21470_is_detectable(): void
    {
        $body = self::decoded('unknown-key-id-21470.json');
        self::assertSame(21470, KsefErrors::code($body));

        $exception = self::errors()->exception(self::fixture(400, 'unknown-key-id-21470.json'), 'The KSeF request POST /sessions/online', 'POST');
        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertSame(400, $exception->status);
        self::assertStringContainsString('21470', $exception->getMessage());
        self::assertSame(21470, KsefErrors::codeOf($exception));
    }

    public function test_every_code_is_read_not_only_the_first(): void
    {
        $body = ['errors' => [
            ['code' => 21999, 'description' => 'Inny błąd.'],
            ['code' => 21470, 'description' => 'Nieznany identyfikator klucza publicznego.'],
        ]];
        self::assertSame([21999, 21470], KsefErrors::codes($body));
        self::assertSame([], KsefErrors::codes([]));

        $unexpected = self::errors()->exception(self::json(400, $body), 'The KSeF request POST /sessions/online', 'POST');
        self::assertInstanceOf(UnexpectedResponse::class, $unexpected);
        self::assertSame([21999, 21470], KsefErrors::codesOf($unexpected));
        self::assertTrue(KsefErrors::hasCode($unexpected, 21470));
        self::assertFalse(KsefErrors::hasCode($unexpected, 21180));
        self::assertSame(21999, KsefErrors::codeOf($unexpected));

        $rejected = self::errors()->exception(self::fixture(400, 'bad-request-problem-details.json'), 'The KSeF request POST /sessions/batch', 'POST');
        self::assertSame([21405, 21157], KsefErrors::codesOf($rejected));

        // The excerpt may be cut short: the codes are read from the message as well.
        $cut = new UnexpectedResponse('The KSeF request POST /x failed with an unexpected answer from KSeF: 21999: Inny błąd.; 21180: Status sesji nie pozwala na wykonanie operacji.', 400, '{"errors": [');
        self::assertSame([21999, 21180], KsefErrors::codesOf($cut));
        self::assertSame([], KsefErrors::codesOf(new TransportUnavailable('down')));
    }

    public function test_code_is_null_without_a_ksef_error(): void
    {
        self::assertNull(KsefErrors::code([]));
        self::assertNull(KsefErrors::code(self::decoded('unauthorized-problem-details.json')));
        self::assertNull(KsefErrors::code(['exception' => ['exceptionDetailList' => [['exceptionCode' => 'x']]]]));
        self::assertNull(KsefErrors::codeOf(new TransportUnavailable('down')));
    }

    public function test_problem_details_shape_is_read(): void
    {
        $exception = self::errors()->exception(self::fixture(400, 'bad-request-problem-details.json'), 'The KSeF request POST /sessions/batch', 'POST');

        self::assertInstanceOf(DocumentRejected::class, $exception);
        self::assertSame(['errors' => [
            '21405: Błąd walidacji danych wejściowych. — Wskazany kod formularza nie jest wspierany.',
            '21157: Nieprawidłowy rozmiar części pakietu. — Rozmiar części 1 przekroczył dozwolony rozmiar 100MB.',
        ]], $exception->errors);
        self::assertSame(21405, KsefErrors::code(self::decoded('bad-request-problem-details.json')));

        $forbidden = self::errors()->exception(self::fixture(403, 'forbidden-problem-details.json'), 'The KSeF request GET /invoices/ksef/x', 'GET');
        self::assertInstanceOf(AuthenticationFailed::class, $forbidden);
        self::assertStringContainsString('no permission in context', $forbidden->getMessage());
        self::assertStringContainsString('missing-permissions', $forbidden->getMessage());

        $unauthorized = self::errors()->exception(self::fixture(401, 'unauthorized-problem-details.json'), 'The KSeF request GET /sessions', 'GET');
        self::assertInstanceOf(AuthenticationFailed::class, $unauthorized);
        self::assertStringContainsString('Wymagane jest uwierzytelnienie.', $unauthorized->getMessage());
    }

    /**
     * @return iterable<string, array{Response, string, class-string<TransportException>}>
     */
    public static function mapping(): iterable
    {
        $classic = static fn (int $code): string => json_encode(['exception' => ['exceptionDetailList' => [[
            'exceptionCode' => $code,
            'exceptionDescription' => 'Opis błędu.',
            'details' => null,
        ]]]], JSON_THROW_ON_ERROR);

        foreach ([21401, 21405, 21402, 21403, 21217, 9101, 9102, 9103] as $code) {
            yield "400 with {$code}" => [new Response(400, [], $classic($code)), 'POST', DocumentRejected::class];
        }
        yield '400 with 21301' => [new Response(400, [], $classic(21301)), 'POST', AuthenticationFailed::class];
        yield '400 with 21304' => [new Response(400, [], $classic(21304)), 'GET', AuthenticationFailed::class];
        yield '401 without a body' => [new Response(401), 'GET', AuthenticationFailed::class];
        yield '403 without a body' => [new Response(403), 'POST', AuthenticationFailed::class];
        yield '404' => [new Response(404), 'GET', UnexpectedResponse::class];
        foreach ([21164, 21165, 21173, 21178] as $code) {
            yield "400 with {$code}" => [new Response(400, [], $classic($code)), 'GET', UnexpectedResponse::class];
        }
        yield '429' => [new Response(429, ['Retry-After' => '30']), 'POST', RateLimited::class];
        yield '500 on GET' => [new Response(500), 'GET', TransportUnavailable::class];
        yield '502 on POST' => [new Response(502), 'POST', OutcomeUnknown::class];
        // An authentication code in a server error keeps the server-error mapping.
        yield '500 on POST with 21301' => [new Response(500, [], $classic(21301)), 'POST', OutcomeUnknown::class];
        yield '503 on GET with 21304' => [new Response(503, [], $classic(21304)), 'GET', TransportUnavailable::class];
        yield '400 with an unknown code' => [new Response(400, [], $classic(21999)), 'POST', UnexpectedResponse::class];
        yield '400 with a non-JSON body' => [new Response(400, [], '<html>bad</html>'), 'POST', UnexpectedResponse::class];
        yield '409' => [new Response(409), 'PUT', UnexpectedResponse::class];
    }

    /**
     * @param  class-string<TransportException>  $expected
     */
    #[DataProvider('mapping')]
    public function test_status_and_code_mapping(Response $response, string $method, string $expected): void
    {
        $exception = self::errors()->exception($response, 'The KSeF request '.$method.' /x', $method);

        self::assertInstanceOf($expected, $exception);
        self::assertStringStartsWith('The KSeF request '.$method.' /x failed', $exception->getMessage());
    }

    public function test_not_found_carries_the_status(): void
    {
        $exception = self::errors()->exception(new Response(404, [], '{}'), 'The KSeF request GET /sessions/x', 'GET');

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertSame(404, $exception->status);
    }

    public function test_503_on_get_is_unavailable_and_on_post_unknown(): void
    {
        $get = self::errors()->exception(new Response(503, [], 'Service Unavailable'), 'The KSeF request GET /sessions', 'GET');
        $post = self::errors()->exception(new Response(503, [], 'Service Unavailable'), 'The KSeF request POST /sessions/online', 'POST');
        $default = self::errors()->exception(new Response(503), 'The KSeF request POST /sessions/online');

        self::assertInstanceOf(TransportUnavailable::class, $get);
        self::assertTrue($get->isRetryable());
        self::assertStringContainsString('HTTP 503', $get->getMessage());
        self::assertInstanceOf(OutcomeUnknown::class, $post);
        self::assertFalse($post->isRetryable());
        self::assertStringContainsString('HTTP 503', $post->getMessage());
        self::assertInstanceOf(OutcomeUnknown::class, $default);
    }

    public function test_retry_after_is_read_as_seconds_or_http_date(): void
    {
        self::assertSame(30, KsefErrors::retryAfter(new Response(429, ['Retry-After' => '30'])));
        self::assertNull(KsefErrors::retryAfter(new Response(429)));
        self::assertNull(KsefErrors::retryAfter(new Response(429, ['Retry-After' => 'soon'])));
        self::assertSame(7, KsefErrors::retryAfter(new Response(429, [
            'Date' => 'Fri, 21 Nov 2025 09:00:00 GMT',
            'Retry-After' => 'Fri, 21 Nov 2025 09:00:07 GMT',
        ])));
        self::assertSame(0, KsefErrors::retryAfter(new Response(429, [
            'Date' => 'Fri, 21 Nov 2025 09:00:10 GMT',
            'Retry-After' => 'Fri, 21 Nov 2025 09:00:07 GMT',
        ])));

        $limited = self::errors()->exception(self::fixture(429, 'too-many-requests-problem-details.json', ['Retry-After' => '30']), 'The KSeF request GET /x', 'GET');
        self::assertInstanceOf(RateLimited::class, $limited);
        self::assertSame(30, $limited->retryAfterSeconds);
        self::assertStringContainsString('Przekroczono limit 20 żądań na minutę.', $limited->getMessage());
    }

    public function test_tokens_never_appear_in_exceptions(): void
    {
        $echo = json_encode(['exception' => ['exceptionDetailList' => [[
            'exceptionCode' => 21401,
            'exceptionDescription' => 'Echo '.self::BEARER,
            'details' => ['token '.self::KSEF_TOKEN, 'b64 '.base64_encode(self::KSEF_TOKEN)],
        ]]]], JSON_THROW_ON_ERROR);
        $errors = new KsefErrors(new Redactor([self::BEARER, self::KSEF_TOKEN]));

        foreach ([400, 401, 403, 404, 418, 429, 503] as $status) {
            foreach (['GET', 'POST'] as $method) {
                $exception = $errors->exception(new Response($status, [], $echo), 'The KSeF request '.$method.' /x', $method);
                self::assertNoSecret($exception);
            }
        }
    }

    private static function errors(): KsefErrors
    {
        return new KsefErrors(new Redactor([]));
    }
}
