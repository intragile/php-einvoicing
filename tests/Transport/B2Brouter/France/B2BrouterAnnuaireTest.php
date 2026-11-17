<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\France;

use IntrAgile\EInvoicing\France\Routing\AnnuaireStatus;
use IntrAgile\EInvoicing\France\Routing\Channel;
use IntrAgile\EInvoicing\France\Routing\Router;
use IntrAgile\EInvoicing\France\Routing\RoutingRule;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\Model\Identifier;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use IntrAgile\EInvoicing\Tests\Support\RecordingHttpClient;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\AnnuaireEntry;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\B2BrouterAnnuaire;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class B2BrouterAnnuaireTest extends TestCase
{
    use CapturesThrowable;

    private const KEY = 'test_FAKEKEY000000';

    private const BASE = 'https://api.b2brouter.net';

    private const SIREN = '100000009';

    /** @var list<int> */
    private array $sleeps = [];

    public function test_active_flag_is_active(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'directory-active.json'));

        $entry = $this->annuaire($recorder)->lookup('0002', self::SIREN);

        self::assertSame(AnnuaireStatus::Active, $entry->status);
        self::assertSame('Exemple Industries SA', $entry->name);
        self::assertSame('0225', $entry->participantScheme);
        self::assertSame(self::SIREN, $entry->participantValue);
        self::assertSame('xml.ubl.invoice.frcius.v1', $entry->invoiceDocumentTypeCode);
        self::assertSame(['FR_ASSUJETTI_ACTIVE'], $entry->flags);
        self::assertSame([], $this->sleeps);
    }

    public function test_wrapped_body_is_read(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'directory-active-wrapped.json'));

        $entry = $this->annuaire($recorder)->lookup('0009', '10000000900017');

        self::assertSame(AnnuaireStatus::Active, $entry->status);
        self::assertSame('Exemple Services SAS', $entry->name);
        self::assertSame('0225', $entry->participantScheme);
        self::assertSame('xml.ubl.invoice.frcius.v1', $entry->invoiceDocumentTypeCode);
        self::assertSame(self::BASE.'/directory/fr/0009/10000000900017', (string) $recorder->requests()[0]->getUri());
    }

    public function test_inactive_flag_is_inactive(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'directory-inactive.json'));

        $entry = $this->annuaire($recorder)->lookup('0002', self::SIREN);

        self::assertSame(AnnuaireStatus::Inactive, $entry->status);
        self::assertSame(['FR_ASSUJETTI_INACTIVE'], $entry->flags);
        self::assertNull($entry->invoiceDocumentTypeCode);
    }

    public function test_unknown_flag_is_not_listed(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'directory-unknown.json'));

        self::assertSame(AnnuaireStatus::NotListed, $this->annuaire($recorder)->status(self::SIREN));
    }

    public function test_not_found_is_not_listed(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(404, 'directory-not-found.json'));

        $entry = $this->annuaire($recorder)->lookup('0002', self::SIREN);

        self::assertEquals(new AnnuaireEntry(AnnuaireStatus::NotListed, null, null, null, null, []), $entry);
    }

    public function test_not_found_without_resource_missing_code_is_unknown(): void
    {
        $recorder = new RecordingHttpClient(new Response(404, ['Content-Type' => 'text/html'], '<html>Not Found</html>'));

        self::assertSame(AnnuaireStatus::Unknown, $this->annuaire($recorder)->status(self::SIREN));
    }

    public function test_processing_then_found_retries(): void
    {
        $recorder = new RecordingHttpClient(
            self::fixture(202, 'directory-processing.json'),
            self::fixture(200, 'directory-active.json'),
        );

        self::assertSame(AnnuaireStatus::Active, $this->annuaire($recorder)->status(self::SIREN));
        self::assertCount(2, $recorder->requests());
        self::assertSame([3], $this->sleeps);
        self::assertSame((string) $recorder->requests()[0]->getUri(), (string) $recorder->requests()[1]->getUri());
    }

    public function test_still_processing_after_attempts_is_unknown(): void
    {
        $recorder = new RecordingHttpClient(
            self::fixture(202, 'directory-processing.json'),
            self::fixture(202, 'directory-processing.json'),
            self::fixture(202, 'directory-processing.json'),
            self::fixture(202, 'directory-processing.json'),
        );

        self::assertSame(AnnuaireStatus::Unknown, $this->annuaire($recorder)->status(self::SIREN));
        self::assertCount(4, $recorder->requests());
        self::assertSame([3, 3, 3], $this->sleeps);
    }

    public function test_attempts_and_delay_are_configurable(): void
    {
        $recorder = new RecordingHttpClient(
            self::fixture(202, 'directory-processing.json'),
            self::fixture(202, 'directory-processing.json'),
        );

        self::assertSame(AnnuaireStatus::Unknown, $this->annuaire($recorder, 2, 5)->status(self::SIREN));
        self::assertCount(2, $recorder->requests());
        self::assertSame([5], $this->sleeps);
    }

    public function test_upstream_unavailable_is_unknown(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(424, 'directory-upstream-unavailable.json', ['Retry-After' => '900']));

        self::assertSame(AnnuaireStatus::Unknown, $this->annuaire($recorder)->status(self::SIREN));
        self::assertCount(1, $recorder->requests());
        self::assertSame([], $this->sleeps);
    }

    public function test_no_answer_is_unknown(): void
    {
        $recorder = new RecordingHttpClient(self::networkException('Connection timed out'));

        $entry = $this->annuaire($recorder)->lookup('0002', self::SIREN);

        self::assertSame(AnnuaireStatus::Unknown, $entry->status);
        self::assertCount(1, $recorder->requests());
    }

    public function test_missing_flag_is_unknown(): void
    {
        $data = self::decoded('directory-active.json');
        unset($data['information_flags']);
        $recorder = new RecordingHttpClient(self::json(200, $data));

        $entry = $this->annuaire($recorder)->lookup('0002', self::SIREN);

        self::assertSame(AnnuaireStatus::Unknown, $entry->status);
        self::assertSame('Exemple Industries SA', $entry->name);
        self::assertSame([], $entry->flags);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unusableFlags(): iterable
    {
        yield 'empty' => [[]];
        yield 'unknown flag' => [['FR_ASSUJETTI_SUSPENDED']];
        yield 'conflicting flags' => [['FR_ASSUJETTI_ACTIVE', 'FR_ASSUJETTI_INACTIVE']];
        yield 'not a list' => ['FR_ASSUJETTI_ACTIVE'];
        yield 'other case' => [['fr_assujetti_active']];
    }

    #[DataProvider('unusableFlags')]
    public function test_unusable_flags_are_unknown(mixed $flags): void
    {
        $data = self::decoded('directory-active.json');
        $data['information_flags'] = $flags;

        $status = $this->annuaire(new RecordingHttpClient(self::json(200, $data)))->status(self::SIREN);

        self::assertSame(AnnuaireStatus::Unknown, $status);
    }

    public function test_unrelated_flags_are_kept_and_ignored(): void
    {
        $data = self::decoded('directory-active.json');
        $data['information_flags'] = ['PEPPOL_LISTED', 'FR_ASSUJETTI_ACTIVE'];

        $entry = $this->annuaire(new RecordingHttpClient(self::json(200, $data)))->lookup('0002', self::SIREN);

        self::assertSame(AnnuaireStatus::Active, $entry->status);
        self::assertSame(['PEPPOL_LISTED', 'FR_ASSUJETTI_ACTIVE'], $entry->flags);
    }

    /**
     * @return iterable<string, array{Response}>
     */
    public static function unreadableAnswers(): iterable
    {
        yield 'not JSON' => [new Response(200, ['Content-Type' => 'text/html'], '<html>maintenance</html>')];
        yield 'JSON list' => [new Response(200, ['Content-Type' => 'application/json'], '[1, 2]')];
        yield 'wrapper not an object' => [new Response(200, ['Content-Type' => 'application/json'], '{"external_company": "x"}')];
        yield 'server error' => [new Response(503, [], '')];
        yield 'rate limited' => [new Response(429, ['Retry-After' => '10'], '')];
        yield 'redirect' => [new Response(302, ['Location' => 'https://example.com/'], '')];
        yield 'bad request' => [new Response(400, ['Content-Type' => 'application/json'], '{"error": {"code": "x"}}')];
    }

    #[DataProvider('unreadableAnswers')]
    public function test_unreadable_or_unexpected_answer_is_unknown(Response $response): void
    {
        $recorder = new RecordingHttpClient($response);

        self::assertSame(AnnuaireStatus::Unknown, $this->annuaire($recorder)->status(self::SIREN));
        self::assertCount(1, $recorder->requests());
    }

    public function test_unauthorized_lookup_throws(): void
    {
        $recorder = new RecordingHttpClient(new Response(401, ['Content-Type' => 'application/json'], '{"error": {"code": "invalid_api_key", "message": "The API key is not valid."}}'));

        $exception = self::capture(fn () => $this->annuaire($recorder)->status(self::SIREN));

        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertStringContainsString('HTTP 401', $exception->getMessage());
        self::assertStringNotContainsString(self::KEY, $exception->getMessage());
    }

    public function test_forbidden_lookup_throws(): void
    {
        $recorder = new RecordingHttpClient(new Response(403, [], ''));

        self::assertInstanceOf(AuthenticationFailed::class, self::capture(fn () => $this->annuaire($recorder)->status(self::SIREN)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidIdentifiers(): iterable
    {
        yield 'SIREN too short' => ['0002', '10000000'];
        yield 'SIREN with letters' => ['0002', '10000000A'];
        yield 'SIREN failing the check digit' => ['0002', '100000008'];
        yield 'SIREN with a newline' => ['0002', "100000009\n"];
        yield 'SIRET as SIREN' => ['0002', '10000000900017'];
        yield 'SIRET failing the check digit' => ['0009', '10000000900018'];
        yield 'SIREN as SIRET' => ['0009', self::SIREN];
        yield 'unsupported scheme' => ['0088', self::SIREN];
        yield 'empty scheme' => ['', self::SIREN];
        yield '0225 bare SIRET' => ['0225', '10000000900017'];
        yield '0225 SIRET of another SIREN' => ['0225', '100000009_20000000800013'];
        yield '0225 valid SIRET of another SIREN' => ['0225', '100000009_20000000800017'];
        yield '0225 SIRET failing the check digit' => ['0225', '100000009_10000000900018'];
        yield '0225 invalid SIREN' => ['0225', '100000008_COMPTA'];
        yield '0225 empty suffix' => ['0225', '100000009_'];
        yield '0225 empty routing code' => ['0225', '100000009_10000000900017_'];
        yield '0225 suffix with a space' => ['0225', '100000009_SERVICE COMPTA'];
        yield '0225 suffix too long' => ['0225', '100000009_'.str_repeat('A', 101)];
        yield '0225 routing code with a query' => ['0225', '100000009_10000000900017_A?b=c'];
    }

    #[DataProvider('invalidIdentifiers')]
    public function test_invalid_identifier_is_refused_without_request(string $scheme, string $value): void
    {
        $recorder = new RecordingHttpClient;

        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => $this->annuaire($recorder)->lookup($scheme, $value)));
        self::assertSame([], $recorder->requests());
    }

    public function test_invalid_siren_is_refused_without_request(): void
    {
        $recorder = new RecordingHttpClient;
        $annuaire = $this->annuaire($recorder);

        foreach (['123456789', '380 129 866', '', '10000000900017'] as $siren) {
            self::assertSame(AnnuaireStatus::Unknown, $annuaire->status($siren));
        }

        $exception = self::capture(fn () => $annuaire->lookup('0002', '123456789'));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('123456789', $exception->getMessage());
        self::assertSame([], $recorder->requests());
    }

    public function test_422_is_invalid_argument(): void
    {
        $recorder = new RecordingHttpClient(self::json(422, ['error' => [
            'code' => 'parameter_invalid',
            'message' => 'Identifier is not valid for the requested scheme',
            'type' => 'invalid_request_error',
        ]]));

        $exception = self::capture(fn () => $this->annuaire($recorder)->status(self::SIREN));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('"Identifier is not valid for the requested scheme"', $exception->getMessage());
        self::assertStringNotContainsString('parameter_invalid', $exception->getMessage());
        self::assertStringContainsString(self::SIREN, $exception->getMessage());
    }

    public function test_422_message_is_redacted(): void
    {
        $recorder = new RecordingHttpClient(self::json(422, ['error' => [
            'code' => 'invalid_identifier',
            'message' => 'Bad identifier for key '.self::KEY,
        ]]));

        $exception = self::capture(fn () => $this->annuaire($recorder)->status(self::SIREN));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringNotContainsString(self::KEY, $exception->getMessage());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validAddresses(): iterable
    {
        yield 'SIREN' => [self::SIREN];
        yield 'SIREN and SIRET' => ['100000009_10000000900017'];
        yield 'SIREN, SIRET and routing code' => ['100000009_10000000900017_COMPTA'];
        yield 'SIREN and suffix' => ['100000009_FACTURES'];
        yield 'routing code with separators' => ['100000009_10000000900017_A.b-c/d_e'];
    }

    #[DataProvider('validAddresses')]
    public function test_0225_address_shapes_are_accepted(string $value): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'directory-active.json'));

        self::assertSame(AnnuaireStatus::Active, $this->annuaire($recorder)->lookup('0225', $value)->status);
        self::assertSame(self::BASE.'/directory/fr/0225/'.rawurlencode($value), (string) $recorder->requests()[0]->getUri());
    }

    public function test_0225_refuses_bare_siret_and_siret_of_another_siren(): void
    {
        foreach (['10000000900017', '100000009_20000000800013'] as $value) {
            $recorder = new RecordingHttpClient;
            self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => $this->annuaire($recorder)->lookup('0225', $value)));
            self::assertSame([], $recorder->requests());
        }
    }

    public function test_la_poste_siret_follows_its_own_check(): void
    {
        // La Poste establishments (SIREN 356000000) use a digit sum divisible by 5 instead of
        // Luhn; the head office number 35600000000048 passes Luhn.
        foreach (['35600000000048', '35600000000015'] as $siret) {
            $recorder = new RecordingHttpClient(self::fixture(200, 'directory-active.json'));
            self::assertSame(AnnuaireStatus::Active, $this->annuaire($recorder)->lookup('0009', $siret)->status);
        }
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => $this->annuaire(new RecordingHttpClient)->lookup('0009', '35600000000049')));
    }

    public function test_request_carries_key_and_version_headers(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'directory-active.json'));

        $this->annuaire($recorder)->status(self::SIREN);

        $request = $recorder->requests()[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame(self::KEY, $request->getHeaderLine('X-B2B-API-Key'));
        self::assertSame('2026-06-26', $request->getHeaderLine('X-B2B-API-Version'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function test_status_uses_siren_scheme(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'directory-active.json'));

        $this->annuaire($recorder)->status(self::SIREN);

        self::assertStringEndsWith('/directory/fr/0002/100000009', (string) $recorder->requests()[0]->getUri());
        self::assertSame(self::BASE.'/directory/fr/0002/100000009', (string) $recorder->requests()[0]->getUri());
    }

    public function test_configured_base_url_is_used(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(200, 'directory-active.json'));
        $factory = new Psr17Factory;
        $annuaire = new B2BrouterAnnuaire(
            new B2BrouterConfig(self::KEY, 4711, 'https://api-staging.b2brouter.net/'),
            new HttpClient($recorder, $factory, $factory),
            sleep: fn (int $seconds) => null,
        );

        $annuaire->status(self::SIREN);

        self::assertSame('https://api-staging.b2brouter.net/directory/fr/0002/100000009', (string) $recorder->requests()[0]->getUri());
    }

    public function test_router_with_live_annuaire_keeps_e_invoice_when_unknown(): void
    {
        $recorder = new RecordingHttpClient(self::fixture(424, 'directory-upstream-unavailable.json', ['Retry-After' => '900']));

        $decision = (new Router($this->annuaire($recorder)))->route(FrenchInvoices::domesticB2b());

        self::assertSame(Channel::EInvoice, $decision->channel);
        self::assertSame(RoutingRule::DomesticB2b, $decision->rule);
        self::assertTrue($decision->needsDirectoryCheck);
        self::assertStringEndsWith('/directory/fr/0002/'.FrenchInvoices::BUYER_SIREN, (string) $recorder->requests()[0]->getUri());
    }

    public function test_router_with_live_annuaire_keeps_e_invoice_for_malformed_buyer_siren(): void
    {
        $recorder = new RecordingHttpClient;
        $invoice = FrenchInvoices::domesticB2b();
        $buyer = $invoice->buyer;
        $invoice = $invoice->withBuyer(new Party(
            name: $buyer->name,
            tradingName: $buyer->tradingName,
            identifiers: $buyer->identifiers,
            legalRegistration: new Identifier('380 129 866', Siren::SCHEME),
            vatIdentifier: $buyer->vatIdentifier,
            taxRegistration: $buyer->taxRegistration,
            additionalLegalInformation: $buyer->additionalLegalInformation,
            electronicAddress: $buyer->electronicAddress,
            address: $buyer->address,
            contact: $buyer->contact,
        ));

        $decision = (new Router($this->annuaire($recorder)))->route($invoice);

        self::assertSame(Channel::EInvoice, $decision->channel);
        self::assertSame(RoutingRule::DomesticB2b, $decision->rule);
        self::assertTrue($decision->needsDirectoryCheck);
        self::assertSame([], $recorder->requests());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidSettings(): iterable
    {
        yield 'no attempt' => [0, 3];
        yield 'negative delay' => [4, -1];
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_are_refused(int $attempts, int $delay): void
    {
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => $this->annuaire(new RecordingHttpClient, $attempts, $delay)));
    }

    private function annuaire(RecordingHttpClient $recorder, int $attempts = 4, int $delay = 3): B2BrouterAnnuaire
    {
        $factory = new Psr17Factory;

        // A plain HTTP client without secrets: the annuaire must add the API key to the redacted values.
        return new B2BrouterAnnuaire(
            new B2BrouterConfig(self::KEY, 4711),
            new HttpClient($recorder, $factory, $factory),
            $attempts,
            $delay,
            function (int $seconds): void {
                $this->sleeps[] = $seconds;
            },
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    private static function fixture(int $status, string $name, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, self::read($name));
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function json(int $status, array $data): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private static function decoded(string $name): array
    {
        /** @var array<string, mixed> */
        return json_decode(self::read($name), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function read(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../../../fixtures/b2brouter-france/'.$name);
        self::assertIsString($contents);

        return $contents;
    }

    private static function networkException(string $message): NetworkExceptionInterface
    {
        return new class(new Request('GET', self::BASE), $message) extends RuntimeException implements NetworkExceptionInterface
        {
            public function __construct(private readonly RequestInterface $request, string $message)
            {
                parent::__construct($message);
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        };
    }
}
