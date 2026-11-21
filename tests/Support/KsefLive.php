<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Poland\CorrectedInvoice;
use IntrAgile\EInvoicing\Poland\Correction;
use IntrAgile\EInvoicing\Poland\CorrectionEffect;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Writer;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Authenticator;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\Credentials;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\PublicKeyRegistry;
use IntrAgile\EInvoicing\Transport\Ksef\KsefApi;
use IntrAgile\EInvoicing\Transport\Ksef\KsefConfig;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Assert;

/**
 * Helpers of the KSeF live tests: the maintenance-window skip, the TE-only configuration, the
 * HTTP client, synthetic FA(3) invoices and report lines.
 *
 * Every invoice is synthetic: the NIPs are random and checksum-valid, the names and addresses are
 * invented, and there is no bank account.
 */
final class KsefLive
{
    public const TAG = 'ksef-live';

    /** The only KSeF host the live tests reach. */
    public const HOST = 'api-test.ksef.mf.gov.pl';

    public const BASE_URL = 'https://api-test.ksef.mf.gov.pl/v2';

    /** The daily TE maintenance window, Europe/Warsaw time, during which no live call is made. */
    public const MAINTENANCE_FROM_HOUR = 16;

    public const MAINTENANCE_TO_HOUR = 18;

    private const ZONE = 'Europe/Warsaw';

    /**
     * Why a live call must not be made now, or null outside the TE maintenance window.
     */
    public static function maintenanceReason(?DateTimeImmutable $now = null): ?string
    {
        $warsaw = ($now ?? new DateTimeImmutable)->setTimezone(new DateTimeZone(self::ZONE));
        $hour = (int) $warsaw->format('G');
        if ($hour >= self::MAINTENANCE_FROM_HOUR && $hour < self::MAINTENANCE_TO_HOUR) {
            return sprintf('KSeF TE is in its daily maintenance window (%d:00-%d:00 Europe/Warsaw, now %s).', self::MAINTENANCE_FROM_HOUR, self::MAINTENANCE_TO_HOUR, $warsaw->format('H:i'));
        }

        return null;
    }

    public static function skipDuringMaintenance(): void
    {
        $reason = self::maintenanceReason();
        if ($reason !== null) {
            Assert::markTestSkipped($reason);
        }
    }

    /**
     * A configuration for KSeF TE, the only environment the live tests use.
     *
     * @throws LogicException when the configuration would reach another base URL
     */
    public static function config(string $nip, Credentials $credentials, int $batchPartBytes = 100_000_000): KsefConfig
    {
        $config = new KsefConfig(
            KsefEnvironment::Test,
            ContextIdentifier::nip($nip),
            $credentials,
            statusPollAttempts: 30,
            statusPollWaitMs: 2000,
            exportPollAttempts: 60,
            exportPollWaitMs: 2000,
            batchPartBytes: $batchPartBytes,
        );
        if ($config->baseUrl !== self::BASE_URL) {
            throw new LogicException('The KSeF live tests run only against KSeF TE.');
        }

        return $config;
    }

    public static function http(KsefTap $tap): HttpClient
    {
        $factory = new Psr17Factory;

        return new HttpClient($tap, $factory, $factory);
    }

    /**
     * A client that authenticates with the configuration, and its authenticator, for calls the
     * transport does not expose.
     *
     * @return array{KsefApi, Authenticator, PublicKeyRegistry}
     */
    public static function api(KsefConfig $config, HttpClient $http): array
    {
        $plain = new KsefApi($config, $http);
        $keys = new PublicKeyRegistry($plain);
        $authenticator = new Authenticator($plain, $config, $keys);

        return [$plain->withAuthenticator($authenticator), $authenticator, $keys];
    }

    /**
     * A redactor for the configuration's credentials.
     */
    public static function redactor(KsefConfig $config): Redactor
    {
        return new Redactor($config->secrets());
    }

    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', new DateTimeZone(self::ZONE));
    }

    /**
     * A unique invoice number for one run.
     */
    public static function invoiceNumber(): string
    {
        return 'FV/LIVE/'.strtoupper(bin2hex(random_bytes(5)));
    }

    /**
     * A synthetic domestic VAT invoice in PLN from the seller NIP to the buyer NIP, issued today.
     */
    public static function invoice(string $sellerNip, string $buyerNip, ?string $number = null): Invoice
    {
        return TotalsCalculator::complete((new Invoice(
            number: $number ?? self::invoiceNumber(),
            issueDate: self::today(),
            type: InvoiceTypeCode::C_380,
            currency: CurrencyCode::PLN,
            seller: self::seller($sellerNip),
            buyer: self::buyer($buyerNip),
            lines: [
                PolishInvoices::line('1', 'Usługa testowa', '2', UnitCode::HUR, '100.00', VatCategory::StandardRate, '23'),
                PolishInvoices::line('2', 'Materiał testowy', '3', UnitCode::H87, '10.00', VatCategory::StandardRate, '8'),
            ],
            dueDate: self::today()->modify('+14 days'),
        ))->withExtension(new PolishInvoice(placeOfIssue: 'Testowo')));
    }

    /**
     * A synthetic corrective invoice (KOR) of the invoice with the given number, date and KSeF
     * number: one of the two hours of service is credited.
     */
    public static function correction(string $sellerNip, string $buyerNip, string $correctedNumber, DateTimeImmutable $correctedDate, string $ksefNumber): Invoice
    {
        $line = PolishInvoices::line('1', 'Usługa testowa', '2', UnitCode::HUR, '100.00', VatCategory::StandardRate, '23');
        $before = $line->withQuantity($line->quantity->negated())->withExtension(new PolishLine(stateBefore: true));
        $after = PolishInvoices::line('2', 'Usługa testowa', '1', UnitCode::HUR, '100.00', VatCategory::StandardRate, '23')->withExtension(new PolishLine);

        return TotalsCalculator::complete((new Invoice(
            number: 'FK/LIVE/'.strtoupper(bin2hex(random_bytes(5))),
            issueDate: self::today(),
            type: InvoiceTypeCode::C_384,
            currency: CurrencyCode::PLN,
            seller: self::seller($sellerNip),
            buyer: self::buyer($buyerNip),
            lines: [$before, $after],
            dueDate: self::today()->modify('+14 days'),
        ))->withExtension(new PolishInvoice(
            placeOfIssue: 'Testowo',
            correction: new Correction(
                invoices: [new CorrectedInvoice($correctedNumber, $correctedDate, KsefNumber::parse($ksefNumber))],
                reason: 'Korekta testowa',
                effect: CorrectionEffect::CorrectionDate,
            ),
        ))->withPrecedingInvoices([new PrecedingInvoiceReference($correctedNumber, $correctedDate)]));
    }

    /**
     * The FA(3) file of an invoice.
     */
    public static function fa3(Invoice $invoice): string
    {
        return (new Fa3Writer)->write($invoice);
    }

    /**
     * Writes a finding to STDERR, tagged ksef-live.
     */
    public static function report(string $line, ?Redactor $redactor = null): void
    {
        LiveTransport::report(self::TAG, $line, $redactor);
    }

    /**
     * Bounded polling: runs the probe until it returns non-null, at most the given number of times
     * with the given wait between attempts, and returns its last result.
     *
     * @template T
     *
     * @param  \Closure(): (T|null)  $probe
     * @return T|null
     */
    public static function poll(\Closure $probe, int $attempts, int $waitSeconds): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            $result = $probe();
            if ($result !== null || $attempt >= $attempts) {
                return $result;
            }
            sleep($waitSeconds);
        }
    }

    private static function seller(string $nip): Party
    {
        return new Party(
            name: 'Testowy Sprzedawca '.substr($nip, -4).' Sp. z o.o.',
            vatIdentifier: 'PL'.$nip,
            address: new PostalAddress(country: CountryCode::PL, line1: 'ul. Przykładowa 1', city: 'Testowo', postCode: '00-950'),
        );
    }

    private static function buyer(string $nip): Party
    {
        return new Party(
            name: 'Testowy Nabywca '.substr($nip, -4).' S.A.',
            vatIdentifier: 'PL'.$nip,
            address: new PostalAddress(country: CountryCode::PL, line1: 'ul. Wymyślona 2', city: 'Testowo', postCode: '00-950'),
        );
    }

    private function __construct() {}
}
