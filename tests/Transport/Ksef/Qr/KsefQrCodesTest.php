<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Qr;

use IntrAgile\EInvoicing\Poland\KsefEnvironment;
use IntrAgile\EInvoicing\Poland\VerificationLink;
use IntrAgile\EInvoicing\Transport\Ksef\Auth\ContextIdentifier;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\KsefQrCodes;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\QrCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class KsefQrCodesTest extends TestCase
{
    private const KSEF_NUMBER = '5265877635-20250826-0100001AF629-AF';

    public function test_online_label_is_ksef_number_or_offline(): void
    {
        $xml = QrFixtures::invoice('2025-09-01');
        $kod1 = VerificationLink::forInvoiceXml(KsefEnvironment::Test, $xml);

        $sent = KsefQrCodes::online(KsefEnvironment::Test, $xml, self::KSEF_NUMBER);
        self::assertSame($kod1, $sent->url);
        self::assertSame(self::KSEF_NUMBER, $sent->label);

        $pending = KsefQrCodes::online(KsefEnvironment::Test, $xml, null);
        self::assertSame($kod1, $pending->url);
        self::assertSame('OFFLINE', $pending->label);
    }

    public function test_ill_formed_ksef_number_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        KsefQrCodes::online(KsefEnvironment::Test, QrFixtures::invoice('2025-09-01'), '9999999999-20260714-D5FB0C9ED490-9A');
    }

    public function test_offline_returns_kod1_and_kod2(): void
    {
        $xml = QrFixtures::invoiceIssuedToday();
        $context = ContextIdentifier::nip(QrFixtures::SELLER_NIP);

        [$kod1, $kod2] = KsefQrCodes::offline(KsefEnvironment::Test, $context, $xml, QrFixtures::certificate('ec'));

        self::assertInstanceOf(QrCode::class, $kod1);
        self::assertSame(VerificationLink::forInvoiceXml(KsefEnvironment::Test, $xml), $kod1->url);
        self::assertSame('OFFLINE', $kod1->label);

        $prefix = 'https://qr-test.ksef.mf.gov.pl/certificate/Nip/'.QrFixtures::SELLER_NIP.'/'.QrFixtures::SELLER_NIP.'/'.QrFixtures::DOCS_SERIAL.'/'.VerificationLink::invoiceHash($xml).'/';
        self::assertStringStartsWith($prefix, $kod2->url);
        self::assertSame('CERTYFIKAT', $kod2->label);

        [$sentKod1] = KsefQrCodes::offline(KsefEnvironment::Test, $context, $xml, QrFixtures::certificate('ec'), self::KSEF_NUMBER);
        self::assertSame(self::KSEF_NUMBER, $sentKod1->label);
    }
}
