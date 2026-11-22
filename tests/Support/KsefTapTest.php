<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The host guard of the KSeF live tests, checked without any network access.
 */
final class KsefTapTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedHosts(): iterable
    {
        yield 'TE API' => ['api-test.ksef.mf.gov.pl'];
        yield 'TE QR pages' => ['qr-test.ksef.mf.gov.pl'];
        yield 'TE Latarnia' => ['api-latarnia-test.ksef.mf.gov.pl'];
        yield 'TE API, upper case' => ['API-TEST.KSEF.MF.GOV.PL'];
        yield 'a storage host the TE API returns' => ['ksef-test-storage.blob.core.windows.net'];
    }

    #[DataProvider('allowedHosts')]
    public function test_te_hosts_are_allowed(string $host): void
    {
        self::assertNull(KsefTap::refusal('https', $host));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedHosts(): iterable
    {
        yield 'DEMO API' => ['api-demo.ksef.mf.gov.pl'];
        yield 'DEMO QR pages' => ['qr-demo.ksef.mf.gov.pl'];
        yield 'PROD API' => ['api.ksef.mf.gov.pl'];
        yield 'PROD QR pages' => ['qr.ksef.mf.gov.pl'];
        yield 'PROD Latarnia' => ['api-latarnia.ksef.mf.gov.pl'];
        yield 'PROD Latarnia, trailing dot' => ['api-latarnia.ksef.mf.gov.pl.'];
        yield 'the bare KSeF domain' => ['ksef.mf.gov.pl'];
        yield 'an unknown KSeF host' => ['ksef-test.ksef.mf.gov.pl'];
    }

    #[DataProvider('refusedHosts')]
    public function test_every_other_ksef_host_is_refused(string $host): void
    {
        self::assertNotNull(KsefTap::refusal('https', $host));
    }

    public function test_plain_http_is_refused(): void
    {
        self::assertNotNull(KsefTap::refusal('http', 'qr-test.ksef.mf.gov.pl'));
    }

    public function test_a_refused_request_is_not_sent(): void
    {
        $tap = new KsefTap(timeoutSeconds: 0.001);

        try {
            $tap->sendRequest((new Psr17Factory)->createRequest('GET', 'https://api-latarnia.ksef.mf.gov.pl/status'));
            self::fail('A request to PROD Latarnia must be refused.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('api-latarnia.ksef.mf.gov.pl', $exception->getMessage());
        }
        self::assertSame(0, $tap->countFor('api-latarnia.ksef.mf.gov.pl'));
        self::assertSame(0, $tap->count());
    }

    public function test_page_text_strips_tags_decodes_entities_and_collapses_white_space(): void
    {
        $html = <<<'HTML'
            <html><head><title>KSeF</title><style>.x { color: red }</style>
            <script>var verdict = "Weryfikacja nieprawidłowa";</script></head>
            <body><!-- Weryfikacja nieprawidłowa -->
            <div class="akmf-alert__title h6">Weryfikacja
                prawidłowa</div>
            <span class="text">Podpis wystawcy jest prawid&#x142;owy</span>
            <span>Faktura&nbsp;znajduje si&#281; w&#xA0;KSeF</span>
            </body></html>
            HTML;

        $text = KsefLive::pageText($html);

        self::assertStringContainsString(KsefLive::KOD2_VALID, $text);
        self::assertStringNotContainsString(KsefLive::KOD2_INVALID, $text);
        self::assertStringContainsString('Podpis wystawcy jest prawidłowy', $text);
        self::assertStringContainsString(KsefLive::KOD1_FOUND, $text);
        self::assertStringNotContainsString('color', $text);
    }

    public function test_valid_verdict_is_not_part_of_the_invalid_one(): void
    {
        self::assertStringNotContainsString(KsefLive::KOD2_VALID, KsefLive::KOD2_INVALID);
        self::assertStringNotContainsString(KsefLive::KOD1_FOUND, KsefLive::KOD1_NOT_FOUND);
    }

    public function test_qr_page_refuses_a_link_of_another_environment(): void
    {
        $this->expectException(LogicException::class);

        KsefLive::qrPage(new KsefTap(timeoutSeconds: 0.001), 'https://qr.ksef.mf.gov.pl/invoice/1234567890/22-11-2026/abc');
    }
}
