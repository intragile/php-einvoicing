<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\France;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\France\EReporting\DailyTransactions;
use IntrAgile\EInvoicing\France\EReporting\Declarant;
use IntrAgile\EInvoicing\France\EReporting\DeclarantRole;
use IntrAgile\EInvoicing\France\EReporting\EReport;
use IntrAgile\EInvoicing\France\EReporting\ReportPeriod;
use IntrAgile\EInvoicing\France\EReporting\ReportType;
use IntrAgile\EInvoicing\France\EReporting\TransactionCategory;
use IntrAgile\EInvoicing\France\EReporting\TransactionsReport;
use IntrAgile\EInvoicing\France\EReporting\VatSubtotal;
use IntrAgile\EInvoicing\France\Routing\AnnuaireStatus;
use IntrAgile\EInvoicing\France\Siren;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Tests\Support\EReports;
use IntrAgile\EInvoicing\Tests\Support\FrenchInvoices;
use IntrAgile\EInvoicing\Tests\Support\LiveTransport;
use IntrAgile\EInvoicing\Tests\Support\StreamHttpClient;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\B2BrouterAnnuaire;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\B2BrouterFrance;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\FrenchDocument;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\LedgerState;
use IntrAgile\EInvoicing\Transport\B2Brouter\France\PlatformStatus;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Checks the French flow against a real B2Brouter environment.
 *
 * Needs B2BROUTER_API_KEY and B2BROUTER_ACCOUNT_ID, optionally B2BROUTER_BASE_URL, and skips
 * without them. Only two environments are accepted: the sandbox (a "test_" key on
 * https://api.b2brouter.net) and staging (a "stag_" key on https://api-staging.b2brouter.net).
 * Any other key, or a key on a host other than its own, is refused before any request is made,
 * so the test can never reach production.
 *
 * The directory lookup runs in both environments. Sending an invoice and depositing a Flux 10
 * file need a French staging account: B2BROUTER_FR_ACCOUNT_ID with a "stag_" key on the staging
 * host; without one these cases skip. Each staging case reads the account company's SIREN and
 * makes it the seller (invoice) or the declarant (e-report), because B2Brouter accepts only
 * documents of its own account company.
 *
 * Output never contains the API key, the account ids or the account company's tax identifiers.
 */
#[Group('b2brouter-france-live')]
final class B2BrouterFranceLiveTest extends TestCase
{
    /** Why the send and e-reporting cases skip outside a French staging account. */
    public const STAGING_SKIP = 'needs a B2Brouter French staging account';

    /** A SIREN with a valid check digit that belongs to no company. */
    private const FICTITIOUS_SIREN = '842917361';

    private const TAG = 'b2brouter-france-live';

    private B2BrouterConfig $config;

    private Redactor $redactor;

    /**
     * Why the configuration must not be used by the live test, or null when it is a sandbox key on
     * the sandbox host or a staging key on the staging host. The message names neither the key nor
     * the account.
     */
    public static function keyRefusal(B2BrouterConfig $config): ?string
    {
        if ($config->isSandboxKey() && $config->baseUrl === B2BrouterConfig::DEFAULT_BASE_URL) {
            return null;
        }
        if ($config->isStagingKey() && $config->baseUrl === B2BrouterConfig::STAGING_BASE_URL) {
            return null;
        }

        return sprintf(
            'The French live test runs only with a "test_" key on %s (sandbox) or a "stag_" key on %s (staging); it refuses any other key or host, production included.',
            B2BrouterConfig::DEFAULT_BASE_URL,
            B2BrouterConfig::STAGING_BASE_URL,
        );
    }

    /**
     * Why the staging cases skip, or null when the configuration is a staging key on the staging
     * host and a French staging account id is given.
     */
    public static function stagingSkip(B2BrouterConfig $config, string $frenchAccountId): ?string
    {
        $staging = $config->isStagingKey() && $config->baseUrl === B2BrouterConfig::STAGING_BASE_URL;

        return $staging && $frenchAccountId !== '' ? null : self::STAGING_SKIP;
    }

    protected function setUp(): void
    {
        $apiKey = (string) getenv('B2BROUTER_API_KEY');
        $accountId = (string) getenv('B2BROUTER_ACCOUNT_ID');
        if ($apiKey === '' || $accountId === '') {
            self::markTestSkipped('Set B2BROUTER_API_KEY and B2BROUTER_ACCOUNT_ID of a B2Brouter sandbox or staging account.');
        }

        $baseUrl = (string) getenv('B2BROUTER_BASE_URL');
        $this->config = new B2BrouterConfig($apiKey, $accountId, $baseUrl !== '' ? $baseUrl : B2BrouterConfig::DEFAULT_BASE_URL);
        $refusal = self::keyRefusal($this->config);
        if ($refusal !== null) {
            self::fail($refusal);
        }
        $this->redactor = new Redactor($this->secrets());
    }

    public function test_directory_lookup_of_fictitious_siren_is_not_listed_or_unknown(): void
    {
        LiveTransport::failRedacted($this->redactor, function (): void {
            $entry = (new B2BrouterAnnuaire($this->config, $this->http()))->lookup(Siren::SCHEME, self::FICTITIOUS_SIREN);

            LiveTransport::report(self::TAG, 'Directory lookup of the fictitious SIREN: '.$entry->status->name.'.', $this->redactor);
            self::assertContains($entry->status, [AnnuaireStatus::NotListed, AnnuaireStatus::Unknown]);
        });
    }

    public function test_staging_send_reaches_deposited(): void
    {
        [$config, $siren] = $this->frenchStagingAccount();

        LiveTransport::failRedacted($this->redactor, function () use ($config, $siren): void {
            $france = new B2BrouterFrance($config, $this->http());
            $result = $france->send(FrenchDocument::fromInvoice(self::fromSeller(FrenchInvoices::domesticB2b(), $siren)));
            self::assertNotSame('', $result->documentId);

            $status = $france->lifecycle($result->documentId);
            for ($attempt = 2; $attempt <= LiveTransport::STATUS_ATTEMPTS && ! self::isFinal($status); $attempt++) {
                sleep(LiveTransport::STATUS_WAIT_SECONDS);
                $status = $france->lifecycle($result->documentId);
            }

            LiveTransport::report(self::TAG, sprintf(
                'Staging invoice: platform state "%s", lifecycle %s, delivery %s.',
                $status->platformState,
                $status->lifecycle === null ? '(none)' : $status->lifecycle->name,
                $status->delivery->state->name,
            ), $this->redactor);
            // Deposited or any later lifecycle status counts; a failed delivery does not.
            self::assertNotNull($status->lifecycle, sprintf('The staging invoice has no lifecycle status yet (platform state "%s").', $status->platformState));
            self::assertNotSame(DeliveryState::Failed, $status->delivery->state, (string) $status->delivery->failure?->message);
        });
    }

    public function test_staging_flux10_deposit_is_accepted(): void
    {
        [$config, $siren] = $this->frenchStagingAccount();

        LiveTransport::failRedacted($this->redactor, function () use ($config, $siren): void {
            $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Paris'));
            $report = EReport::transactions(
                'TR-'.bin2hex(random_bytes(6)),
                new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')),
                ReportType::Initial,
                EReports::platform(),
                new Declarant($siren, 'LE VENDEUR', DeclarantRole::Seller),
                new TransactionsReport(ReportPeriod::month((int) $today->format('Y'), (int) $today->format('n')), [], [new DailyTransactions(
                    date: $today,
                    currency: CurrencyCode::EUR,
                    category: TransactionCategory::Goods,
                    taxExclusive: '500.00',
                    taxTotal: '100.00',
                    subtotals: [new VatSubtotal('20', '500.00', '100.00')],
                    count: 3,
                    vatDueDateCode: '3',
                )]),
            );

            $deposit = (new B2BrouterFrance($config, $this->http()))->depositReport($report);

            LiveTransport::report(self::TAG, sprintf('Staging Flux 10 deposit: state "%s".', $deposit->state->value), $this->redactor);
            self::assertContains($deposit->state, [LedgerState::New, LedgerState::Sent], (string) $deposit->errorDescription);
        });
    }

    /**
     * The configuration of the French staging account and its company's SIREN; skips without the
     * account and refuses a key or host other than staging before any request.
     *
     * @return array{B2BrouterConfig, string}
     */
    private function frenchStagingAccount(): array
    {
        $accountId = (string) getenv('B2BROUTER_FR_ACCOUNT_ID');
        $skip = self::stagingSkip($this->config, $accountId);
        if ($skip !== null) {
            self::markTestSkipped($skip);
        }
        $config = new B2BrouterConfig($this->apiKeyFromEnvironment(), $accountId, B2BrouterConfig::STAGING_BASE_URL);
        $refusal = self::keyRefusal($config);
        if ($refusal !== null) {
            self::fail($refusal);
        }
        $this->redactor = new Redactor([...$this->secrets(), $accountId]);

        $siren = null;
        LiveTransport::failRedacted($this->redactor, function () use ($config, &$siren): void {
            $response = $this->http()->send('GET', sprintf('%s/accounts/%d', $config->baseUrl, $config->accountId), $config->headers());
            // The status only: the account body names the company.
            self::assertSame(200, $response->getStatusCode(), sprintf('GET /accounts/{account} answered HTTP %d.', $response->getStatusCode()));
            $data = json_decode((string) $response->getBody(), true);
            $account = is_array($data) && is_array($data['account'] ?? null) ? $data['account'] : [];
            $tin = $account['tin_value'] ?? null;
            $siren = is_string($tin) ? self::sirenOf($tin) : null;
        });
        if (! is_string($siren)) {
            self::fail('The French staging account has no SIREN, SIRET or French VAT number as its tax identifier.');
        }
        $tin = self::frenchVat($siren);
        $this->redactor = new Redactor([...$this->secrets(), $accountId, $siren, $tin]);

        return [$config, $siren];
    }

    /**
     * The invoice with a fresh number, dated today, sold by the company with the given SIREN.
     */
    private static function fromSeller(Invoice $invoice, string $siren): Invoice
    {
        $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Paris'));
        $seller = $invoice->seller;

        return $invoice
            ->withNumber(LiveTransport::invoiceNumber())
            ->withIssueDate($today)
            ->withDueDate($today->modify('+30 days'))
            ->withSeller(new Party(
                name: $seller->name,
                tradingName: $seller->tradingName,
                identifiers: [],
                legalRegistration: Siren::identifier($siren),
                vatIdentifier: self::frenchVat($siren),
                taxRegistration: $seller->taxRegistration,
                additionalLegalInformation: $seller->additionalLegalInformation,
                electronicAddress: new ElectronicAddress($siren, ElectronicAddressScheme::C_0225),
                address: $seller->address,
                contact: $seller->contact,
            ));
    }

    /**
     * Whether the invoice reached the deposited status or a later one, or failed for good.
     */
    private static function isFinal(PlatformStatus $status): bool
    {
        return $status->lifecycle !== null || $status->delivery->state === DeliveryState::Failed;
    }

    /**
     * The SIREN in a tax identifier given as a SIREN, a SIRET or a French VAT number.
     */
    private static function sirenOf(string $tin): ?string
    {
        $tin = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $tin));
        if (preg_match('/\A(?:FR[0-9A-Z]{2})?([0-9]{9})(?:[0-9]{5})?\z/', $tin, $match) !== 1) {
            return null;
        }

        return Siren::isValid($match[1]) ? $match[1] : null;
    }

    /**
     * The French VAT number of a SIREN, with its computed key.
     */
    private static function frenchVat(string $siren): string
    {
        return sprintf('FR%02d%s', (12 + 3 * ((int) $siren % 97)) % 97, $siren);
    }

    /**
     * Every value kept out of the output that is known before a request: the API key and the
     * account id.
     *
     * @return list<string>
     */
    private function secrets(): array
    {
        return [...$this->config->secrets(), (string) $this->config->accountId];
    }

    private function apiKeyFromEnvironment(): string
    {
        return (string) getenv('B2BROUTER_API_KEY');
    }

    private function http(): HttpClient
    {
        $factory = new Psr17Factory;

        // Every request has a 30-second timeout.
        return (new HttpClient(new StreamHttpClient(30.0), $factory, $factory))->withSecrets($this->secrets());
    }
}
