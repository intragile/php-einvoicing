<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Auth;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\GeneratedToken;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\KsefTokens;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\TokenPermission;
use InvalidArgumentException;
use LogicException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class KsefTokensTest extends TestCase
{
    use AuthFixtures;

    private const BASE = 'https://api-test.ksef.mf.gov.pl/v2';

    private const TOKEN_REFERENCE = '20251010-EC-1DCE3E3000-12ECB5B36E-45';

    private const NEW_TOKEN = '20251010-EC-1DCE3E3000-12ECB5B36E-45|internalId-5265877635-12345|919f704466624ce29cd5ac7b65ded5e7cccc112eee314f2aaa76e02cd16df7b9';

    public function test_generate_token_rev2_body(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::json(202, ['referenceNumber' => self::TOKEN_REFERENCE, 'token' => self::NEW_TOKEN]),
            self::json(202, ['referenceNumber' => self::TOKEN_REFERENCE, 'token' => self::NEW_TOKEN]),
        ]);
        $tokens = new KsefTokens($this->authorizedApi($recorder));

        $generated = $tokens->generate(
            [TokenPermission::InvoiceRead, TokenPermission::InvoiceWrite],
            'Wystawianie i przeglądanie faktur.',
            new DateTimeImmutable('2026-12-01T10:30:00.5+01:00'),
            new DateTimeImmutable('2027-03-01T00:00:00', new DateTimeZone('UTC')),
        );

        self::assertSame(self::TOKEN_REFERENCE, $generated->referenceNumber);
        self::assertSame(self::NEW_TOKEN, $generated->token());
        $request = $recorder->requests()[5];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE.'/tokens/rev2', (string) $request->getUri());
        self::assertSame('Bearer '.self::ACCESS_TOKEN, $request->getHeaderLine('Authorization'));
        self::assertSame([
            'permissions' => ['InvoiceRead', 'InvoiceWrite'],
            'description' => 'Wystawianie i przeglądanie faktur.',
            'validFrom' => '2026-12-01T09:30:00.500+00:00',
            'validTo' => '2027-03-01T00:00:00.000+00:00',
        ], self::body($request));

        $tokens->generate([TokenPermission::CredentialsRead], 'Odczyt uprawnień');
        self::assertSame(['permissions' => ['CredentialsRead'], 'description' => 'Odczyt uprawnień'], self::body($recorder->requests()[6]));
    }

    public function test_permission_enum_has_the_openapi_values(): void
    {
        self::assertSame(
            ['InvoiceRead', 'InvoiceWrite', 'CredentialsRead', 'CredentialsManage', 'SubunitManage', 'EnforcementOperations', 'Introspection', 'CollectiveIdentifierManage'],
            array_map(static fn (TokenPermission $p): string => $p->value, TokenPermission::cases()),
        );
    }

    public function test_token_description_length_enforced(): void
    {
        $tokens = new KsefTokens($this->authorizedApi(new RecordingHttpClient));

        // Invalid UTF-8 is refused here, not left to the JSON encoder.
        foreach (['Opis', str_repeat('a', 257), '    ', 'ąćęł', "Opis \xFF tokena"] as $description) {
            $exception = self::capture(fn () => $tokens->generate([TokenPermission::InvoiceRead], $description));
            self::assertInstanceOf(InvalidArgumentException::class, $exception, $description);
        }
    }

    public function test_description_bounds_count_characters(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::json(202, ['referenceNumber' => self::TOKEN_REFERENCE, 'token' => self::NEW_TOKEN]),
            self::json(202, ['referenceNumber' => self::TOKEN_REFERENCE, 'token' => self::NEW_TOKEN]),
        ]);
        $tokens = new KsefTokens($this->authorizedApi($recorder));

        $tokens->generate([TokenPermission::InvoiceRead], 'ąćęłń');
        $tokens->generate([TokenPermission::InvoiceRead], str_repeat('ż', 256));

        self::assertCount(7, $recorder->requests());
    }

    public function test_invalid_arguments_are_refused_before_any_request(): void
    {
        $tokens = new KsefTokens($this->authorizedApi(new RecordingHttpClient));

        $cases = [
            fn () => $tokens->generate([], 'Opis tokena'),
            fn () => $tokens->generate([TokenPermission::InvoiceRead], 'Opis tokena', new DateTimeImmutable('2027-01-02'), new DateTimeImmutable('2027-01-01')),
            fn () => $tokens->status('../sessions'),
        ];
        foreach ($cases as $index => $case) {
            self::assertInstanceOf(InvalidArgumentException::class, self::capture($case), (string) $index);
        }
    }

    public function test_a_token_passed_as_reference_stays_out_of_the_message(): void
    {
        $tokens = new KsefTokens($this->authorizedApi(new RecordingHttpClient));
        $secretPart = '919f704466624ce29cd5ac7b65ded5e7cccc112eee314f2aaa76e02cd16df7b9';

        foreach ([
            fn () => $tokens->status(self::NEW_TOKEN),
            fn () => $tokens->approve(self::NEW_TOKEN),
            fn () => $tokens->revoke(self::NEW_TOKEN),
        ] as $call) {
            $exception = self::capture($call);
            self::assertInstanceOf(InvalidArgumentException::class, $exception);
            self::assertStringNotContainsString($secretPart, $exception->getMessage());
            self::assertStringNotContainsString('internalId-5265877635', $exception->getMessage());
        }
    }

    public function test_status_approve_and_revoke(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::json(200, [
                'referenceNumber' => self::TOKEN_REFERENCE,
                'authorIdentifier' => ['type' => 'Nip', 'value' => '7762811692'],
                'contextIdentifier' => ['type' => 'Nip', 'value' => '5265877635'],
                'description' => 'Wystawianie i przeglądanie faktur.',
                'requestedPermissions' => [['code' => 'InvoiceRead', 'rawCode' => 'InvoiceRead', 'displayName' => 'Przeglądanie faktur']],
                'dateCreated' => '2025-07-11T12:23:56.0154302+00:00',
                'validFrom' => '2025-07-11T12:23:56.0154302+00:00',
                'validTo' => '2025-10-09T12:23:56.0154302+00:00',
                // Observed on TE: the legacy GET /tokens/{ref} reports such a token as "Active".
                'status' => ['code' => 'AwaitingApproval', 'rawCode' => 'AwaitingApproval', 'displayName' => 'Token oczekuje na zatwierdzenie'],
            ]),
            new Response(204),
            new Response(204),
        ]);
        $tokens = new KsefTokens($this->authorizedApi($recorder));

        self::assertSame('AwaitingApproval', $tokens->status(self::TOKEN_REFERENCE));
        $tokens->approve(self::TOKEN_REFERENCE);
        $tokens->revoke(self::TOKEN_REFERENCE);

        $requests = array_slice($recorder->requests(), 5);
        self::assertSame(
            ['GET /tokens/rev2/'.self::TOKEN_REFERENCE, 'POST /tokens/'.self::TOKEN_REFERENCE.'/approve', 'DELETE /tokens/'.self::TOKEN_REFERENCE],
            array_map(static fn ($r): string => $r->getMethod().' '.substr((string) $r->getUri(), strlen(self::BASE)), $requests),
        );
        foreach ($requests as $request) {
            self::assertSame('Bearer '.self::ACCESS_TOKEN, $request->getHeaderLine('Authorization'));
        }
    }

    public function test_status_prefers_the_raw_code_of_a_value_unknown_to_the_revision(): void
    {
        $recorder = new RecordingHttpClient(...[
            ...self::tokenAuthResponses(),
            self::json(200, ['referenceNumber' => self::TOKEN_REFERENCE, 'status' => ['code' => 'FutureValue', 'rawCode' => 'Suspended', 'displayName' => 'Zawieszony']]),
            self::json(200, ['referenceNumber' => self::TOKEN_REFERENCE, 'status' => 'Active']),
        ]);
        $tokens = new KsefTokens($this->authorizedApi($recorder));

        self::assertSame('Suspended', $tokens->status(self::TOKEN_REFERENCE));
        // A plain string status, as the legacy answer has it, is read too.
        self::assertSame('Active', $tokens->status(self::TOKEN_REFERENCE));
    }

    public function test_generated_token_is_redacted_and_not_serializable(): void
    {
        $generated = new GeneratedToken(self::TOKEN_REFERENCE, self::NEW_TOKEN);
        $secretPart = '919f704466624ce29cd5ac7b65ded5e7cccc112eee314f2aaa76e02cd16df7b9';

        foreach ([print_r($generated, true), var_export($generated, true), (string) json_encode($generated)] as $dump) {
            self::assertStringNotContainsString($secretPart, $dump);
        }
        self::assertStringContainsString(self::TOKEN_REFERENCE, print_r($generated, true));

        $this->expectException(LogicException::class);
        serialize($generated);
    }
}
