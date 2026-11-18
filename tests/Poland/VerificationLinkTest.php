<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Poland;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Poland\VerificationLink;
use IntrAgile\EInvoicing\Tests\Support\PolishSamples;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VerificationLinkTest extends TestCase
{
    private const DOCS_HASH = 'UtQp9Gpc51y-u3xApZjIjgkpZ01js-J8KflSPW8WzIE';

    public function test_docs_example_link(): void
    {
        self::assertSame(
            'https://qr-test.ksef.mf.gov.pl/invoice/1111111111/01-02-2026/UtQp9Gpc51y-u3xApZjIjgkpZ01js-J8KflSPW8WzIE',
            VerificationLink::invoiceFromHash(KsefEnvironment::Test, '1111111111', new DateTimeImmutable('2026-02-01'), self::DOCS_HASH),
        );
    }

    public function test_hash_of_empty_input(): void
    {
        self::assertSame('47DEQpj8HBSa-_TImW-5JCeuQeRkm5NMpJWZG3hSuFU', VerificationLink::invoiceHash(''));
    }

    public function test_hash_covers_exact_bytes(): void
    {
        $xml = PolishSamples::official('invoice-template-fa-3.xml');
        $hash = VerificationLink::invoiceHash($xml);

        self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $hash);
        self::assertNotSame($hash, VerificationLink::invoiceHash($xml."\n"));
        self::assertNotSame($hash, VerificationLink::invoiceHash("\xEF\xBB\xBF".$xml));
    }

    public function test_link_from_invoice_xml(): void
    {
        $xml = PolishSamples::official('invoice-template-fa-3.xml');

        self::assertSame(
            'https://qr-test.ksef.mf.gov.pl/invoice/2222222222/01-09-2025/'.VerificationLink::invoiceHash($xml),
            VerificationLink::forInvoiceXml(KsefEnvironment::Test, $xml),
        );
        self::assertSame(
            VerificationLink::forInvoiceXml(KsefEnvironment::Demo, $xml),
            VerificationLink::invoice(KsefEnvironment::Demo, '2222222222', new DateTimeImmutable('2025-09-01'), $xml),
        );
    }

    public function test_production_host(): void
    {
        self::assertSame('https://qr.ksef.mf.gov.pl', KsefEnvironment::Production->qrBaseUrl());
        self::assertSame('https://qr-demo.ksef.mf.gov.pl', KsefEnvironment::Demo->qrBaseUrl());
        self::assertSame('https://qr-test.ksef.mf.gov.pl', KsefEnvironment::Test->qrBaseUrl());
        self::assertStringStartsWith(
            'https://qr.ksef.mf.gov.pl/invoice/1111111111/',
            VerificationLink::invoiceFromHash(KsefEnvironment::Production, '1111111111', new DateTimeImmutable('2026-02-01'), self::DOCS_HASH),
        );
    }

    public function test_ill_formed_nip_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('NIP');
        VerificationLink::invoiceFromHash(KsefEnvironment::Test, '0123456789', new DateTimeImmutable('2026-02-01'), self::DOCS_HASH);
    }

    public function test_ill_formed_hash_is_refused(): void
    {
        foreach ([self::DOCS_HASH.'=', substr(self::DOCS_HASH, 1), 'UtQp9Gpc51y+u3xApZjIjgkpZ01js/J8KflSPW8WzIE'] as $hash) {
            try {
                VerificationLink::invoiceFromHash(KsefEnvironment::Test, '1111111111', new DateTimeImmutable('2026-02-01'), $hash);
                self::fail('Accepted '.$hash);
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('hash', $e->getMessage());
            }
        }
    }

    public function test_invoice_without_seller_nip_or_issue_date_is_refused(): void
    {
        $xml = str_replace('<P_1>2025-09-01</P_1>', '', PolishSamples::official('invoice-template-fa-3.xml'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('P_1');
        VerificationLink::forInvoiceXml(KsefEnvironment::Test, $xml);
    }
}
