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

    /** The TE host of the QR verification pages (KOD I and KOD II). */
    public const QR_HOST = 'qr-test.ksef.mf.gov.pl';

    public const QR_BASE_URL = 'https://qr-test.ksef.mf.gov.pl';

    /** The TE host of Latarnia, the KSeF availability service. */
    public const LATARNIA_HOST = 'api-latarnia-test.ksef.mf.gov.pl';

    /** KOD II verdicts of the TE QR page (the page answers HTTP 200 text/html in every case). */
    public const KOD2_VALID = 'Weryfikacja prawidłowa';

    public const KOD2_INVALID = 'Weryfikacja nieprawidłowa';

    public const KOD2_REVOKED = 'Certyfikat został unieważniony';

    /** KOD I verdicts of the TE QR page. */
    public const KOD1_FOUND = 'Faktura znajduje się w KSeF';

    public const KOD1_NOT_FOUND = 'Faktura nie została znaleziona w KSeF';

    /** The daily TE maintenance window, Europe/Warsaw time, during which no live call is made. */
    public const MAINTENANCE_FROM_HOUR = 16;

    public const MAINTENANCE_TO_HOUR = 18;

    private const ZONE = 'Europe/Warsaw';

    /** Minutes before the window in which no live test starts, so none runs into it. */
    public const MAINTENANCE_MARGIN_MINUTES = 5;

    /**
     * Why a live test must not start now, or null: during the TE maintenance window, and in the
     * minutes before it.
     */
    public static function maintenanceReason(?DateTimeImmutable $now = null): ?string
    {
        $warsaw = ($now ?? new DateTimeImmutable)->setTimezone(new DateTimeZone(self::ZONE));
        $minutes = (int) $warsaw->format('G') * 60 + (int) $warsaw->format('i');
        $from = self::MAINTENANCE_FROM_HOUR * 60 - self::MAINTENANCE_MARGIN_MINUTES;
        if ($minutes >= $from && $minutes < self::MAINTENANCE_TO_HOUR * 60) {
            return sprintf(
                'KSeF TE is in or within %d minutes of its daily maintenance window (%d:00-%d:00 Europe/Warsaw, now %s).',
                self::MAINTENANCE_MARGIN_MINUTES,
                self::MAINTENANCE_FROM_HOUR,
                self::MAINTENANCE_TO_HOUR,
                $warsaw->format('H:i'),
            );
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
     * A redactor for the configuration's credentials and any further secrets, such as tokens.
     */
    public static function redactor(KsefConfig $config, string ...$secrets): Redactor
    {
        return new Redactor([...$config->secrets(), ...array_values($secrets)]);
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
     * The visible text of a TE QR verification page, read through the tap.
     *
     * @throws LogicException when the link does not point at the TE QR host
     */
    public static function qrPage(KsefTap $tap, string $url): string
    {
        if (! str_starts_with($url, self::QR_BASE_URL.'/')) {
            throw new LogicException('The KSeF live tests open QR verification links of TE only.');
        }
        $response = $tap->sendRequest((new Psr17Factory)->createRequest('GET', $url)->withHeader('Accept', 'text/html'));
        Assert::assertSame(200, $response->getStatusCode(), 'The QR verification page answers HTTP 200 for every link.');

        return self::pageText((string) $response->getBody());
    }

    /**
     * The visible text of an HTML page: scripts and styles dropped, tags replaced by spaces,
     * entities decoded (the QR page writes some Polish letters as &#x142; and the like) and every
     * run of white space, non-breaking ones included, collapsed into one space.
     */
    public static function pageText(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1\s*>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<!--.*?-->#s', ' ', $html) ?? $html;
        $text = preg_replace('#<[^>]*>#', ' ', $html) ?? $html;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text) ?? $text);
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
