<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter;

use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\XRechnungPeppol;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class B2BrouterConfigTest extends TestCase
{
    /** A Peppol document type the driver has no B2Brouter code for. */
    private const ORDER = 'urn:oasis:names:specification:ubl:schema:xsd:Order-2::Order##urn:fdc:peppol.eu:poacc:trns:order:3::2.1';

    use CapturesThrowable;

    private const KEY = 'test_FAKEKEY000000';

    public function test_defaults(): void
    {
        $config = new B2BrouterConfig('  '.self::KEY."\n", 4711);

        self::assertSame('https://api.b2brouter.net', B2BrouterConfig::DEFAULT_BASE_URL);
        self::assertSame('2026-06-26', B2BrouterConfig::API_VERSION);
        self::assertSame('https://api.b2brouter.net', $config->baseUrl);
        self::assertSame(4711, $config->accountId);
        self::assertSame([
            'X-B2B-API-Key' => self::KEY,
            'X-B2B-API-Version' => '2026-06-26',
            'Accept' => 'application/json',
        ], $config->headers());
        self::assertSame([self::KEY], $config->secrets());
        self::assertSame('xml.ubl.invoice.bis3', $config->documentTypeCode(PeppolBis3::INVOICE));
        self::assertSame('xml.ubl.credit_note.bis3', $config->documentTypeCode(PeppolBis3::CREDIT_NOTE));
        self::assertSame('xml.ubl.invoice.xrechnung.v3.0', $config->documentTypeCode(XRechnungPeppol::UBL_INVOICE));
        self::assertSame('xml.ubl.credit_note.xrechnung.v3.0', $config->documentTypeCode(XRechnungPeppol::UBL_CREDIT_NOTE));
        self::assertSame('xml.cii.cross_industry_invoice.xrechnung.v3.0', $config->documentTypeCode(XRechnungPeppol::CII_INVOICE));
        self::assertSame('xml.ubl.invoice.xrechnung.extension.v3.0', $config->documentTypeCode(XRechnungPeppol::UBL_INVOICE_EXTENSION));
        self::assertSame('xml.cii.cross_industry_invoice.xrechnung.extension.v3.0', $config->documentTypeCode(XRechnungPeppol::CII_INVOICE_EXTENSION));
        self::assertNull($config->documentTypeCode(self::ORDER));
    }

    public function test_account_id_as_digits_and_trailing_slash(): void
    {
        $config = new B2BrouterConfig(self::KEY, '0815', 'https://api-staging.b2brouter.net/');

        self::assertSame(815, $config->accountId);
        self::assertSame('https://api-staging.b2brouter.net', $config->baseUrl);
    }

    public function test_document_type_codes_are_merged_over_the_defaults(): void
    {
        $config = new B2BrouterConfig(self::KEY, 1, documentTypeCodes: [
            XRechnungPeppol::UBL_INVOICE => 'xml.ubl.invoice.xrechnung.v30',
            PeppolBis3::INVOICE => 'xml.ubl.invoice.bis3.custom',
        ]);

        self::assertSame('xml.ubl.invoice.xrechnung.v30', $config->documentTypeCode(XRechnungPeppol::UBL_INVOICE));
        self::assertSame('xml.ubl.invoice.bis3.custom', $config->documentTypeCode(PeppolBis3::INVOICE));
        self::assertSame('xml.ubl.credit_note.bis3', $config->documentTypeCode(PeppolBis3::CREDIT_NOTE));
    }

    public function test_sandbox_key_is_recognised_by_its_prefix(): void
    {
        self::assertTrue((new B2BrouterConfig(self::KEY, 1))->isSandboxKey());
        self::assertTrue((new B2BrouterConfig(' '.self::KEY, 1))->isSandboxKey());
        self::assertFalse((new B2BrouterConfig('prod_FAKEKEY000000', 1))->isSandboxKey());
        self::assertFalse((new B2BrouterConfig('stag_FAKEKEY000000', 1))->isSandboxKey());
        self::assertFalse((new B2BrouterConfig('FAKEKEY000000test_', 1))->isSandboxKey());
        self::assertFalse((new B2BrouterConfig('TEST_FAKEKEY000000', 1))->isSandboxKey());
    }

    public function test_staging_key_is_detected(): void
    {
        self::assertTrue((new B2BrouterConfig('stag_FAKEKEY000000', 1))->isStagingKey());
        self::assertTrue((new B2BrouterConfig(' stag_FAKEKEY000000', 1))->isStagingKey());
        self::assertFalse((new B2BrouterConfig(self::KEY, 1))->isStagingKey());
        self::assertFalse((new B2BrouterConfig('prod_FAKEKEY000000', 1))->isStagingKey());
        self::assertFalse((new B2BrouterConfig('STAG_FAKEKEY000000', 1))->isStagingKey());
        self::assertFalse((new B2BrouterConfig('FAKEKEY000000stag_', 1))->isStagingKey());
    }

    public function test_staging_base_url_constant(): void
    {
        self::assertSame('https://api-staging.b2brouter.net', B2BrouterConfig::STAGING_BASE_URL);
        self::assertSame(B2BrouterConfig::STAGING_BASE_URL, (new B2BrouterConfig('stag_FAKEKEY000000', 1, B2BrouterConfig::STAGING_BASE_URL))->baseUrl);
    }

    public function test_config_dump_hides_secrets(): void
    {
        $config = new B2BrouterConfig(self::KEY, 4711);

        foreach ([
            print_r($config, true),
            var_export($config, true),
            json_encode($config, JSON_THROW_ON_ERROR),
            serialize(self::dump($config)),
        ] as $output) {
            self::assertStringNotContainsString(self::KEY, (string) $output);
        }
        self::assertStringContainsString('4711', print_r($config, true));
    }

    /**
     * @return iterable<string, array{string|int, string}>
     */
    public static function badAccounts(): iterable
    {
        yield 'zero' => [0, 'account id'];
        yield 'negative' => [-3, 'account id'];
        yield 'empty' => ['', 'account id'];
        yield 'letters' => ['acc_1', 'account id'];
        yield 'signed digits' => ['+12', 'account id'];
        yield 'padded' => [' 12', 'account id'];
        yield 'zero digits' => ['000', 'account id'];
        yield 'overflow' => ['99999999999999999999', 'account id'];
    }

    #[DataProvider('badAccounts')]
    public function test_config_refuses_bad_account_and_base_url(string|int $account, string $fragment): void
    {
        $exception = self::capture(fn () => new B2BrouterConfig(self::KEY, $account));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString($fragment, $exception->getMessage());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badBaseUrls(): iterable
    {
        yield 'http' => ['http://api.b2brouter.net'];
        yield 'user info' => ['https://user:'.self::KEY.'@api.b2brouter.net'];
        yield 'query' => ['https://api.b2brouter.net?api_key=x'];
        yield 'fragment' => ['https://api.b2brouter.net#x'];
        yield 'no host' => ['https://'];
    }

    #[DataProvider('badBaseUrls')]
    public function test_bad_base_url_is_refused(string $url): void
    {
        $exception = self::capture(fn () => new B2BrouterConfig(self::KEY, 1, $url));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('B2Brouter base URL', $exception->getMessage());
        self::assertStringNotContainsString(self::KEY, $exception->getMessage());
    }

    public function test_short_key_is_refused_without_quoting_it(): void
    {
        $exception = self::capture(fn () => new B2BrouterConfig(' test_ab ', 1));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('B2Brouter API key', $exception->getMessage());
        self::assertStringNotContainsString('test_ab', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function badCodes(): iterable
    {
        yield 'upper case' => [[XRechnungPeppol::UBL_INVOICE => 'XML.UBL']];
        yield 'too short' => [[XRechnungPeppol::UBL_INVOICE => 'ab']];
        yield 'too long' => [[XRechnungPeppol::UBL_INVOICE => str_repeat('a', 101)]];
        yield 'query injection' => [[XRechnungPeppol::UBL_INVOICE => 'xml.ubl&issued=false']];
        yield 'trailing newline' => [[XRechnungPeppol::UBL_INVOICE => "xml.ubl.invoice\n"]];
        yield 'not a string' => [[XRechnungPeppol::UBL_INVOICE => 5]];
        yield 'integer key' => [['xml.ubl.invoice.bis3']];
        yield 'empty key' => [['' => 'xml.ubl.invoice.bis3']];
    }

    /**
     * @param  array<mixed>  $codes
     */
    #[DataProvider('badCodes')]
    public function test_bad_document_type_code_is_refused(array $codes): void
    {
        // @phpstan-ignore argument.type (invalid input on purpose)
        $exception = self::capture(fn () => new B2BrouterConfig(self::KEY, 1, documentTypeCodes: $codes));

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertStringContainsString('document type code', $exception->getMessage());
    }

    public function test_largest_code_length_is_accepted(): void
    {
        $code = str_repeat('a', 100);

        self::assertSame($code, (new B2BrouterConfig(self::KEY, 1, documentTypeCodes: ['x' => $code]))->documentTypeCode('x'));
    }

    /**
     * @return array<mixed>
     */
    private static function dump(B2BrouterConfig $config): array
    {
        return $config->__debugInfo();
    }
}
