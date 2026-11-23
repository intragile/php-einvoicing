<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Spain\Verifactu;

use chillerlan\QRCode\QRCode;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\VerifactuQr;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Tests\Support\VerifactuSamples;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\ChillerlanQrRenderer;
use PHPUnit\Framework\TestCase;

final class VerifactuQrTest extends TestCase
{
    private function record(string $number, string $date, Amount $total): RegistrationRecord
    {
        $base = VerifactuSamples::vectorDraft($number, $date);
        $draft = new RegistrationDraft(
            id: $base->id,
            issuerName: 'Test Issuer S.L.',
            type: InvoiceType::F1,
            correctionType: null,
            corrected: [],
            substituted: [],
            correctionAmounts: null,
            operationDate: null,
            description: 'Vector invoice',
            simplifiedArt7273: false,
            withoutRecipientArt61d: false,
            macrodato: false,
            issuedBy: null,
            thirdParty: null,
            recipients: [VerifactuSamples::recipient()],
            coupon: false,
            breakdown: [VerifactuSamples::line()],
            totalTax: Amount::of('12.35'),
            total: $total,
            externalReference: null,
            agreementNumber: null,
            systemAgreementId: null,
        );

        return RegistrationRecord::create(
            $draft,
            ChainLink::first(),
            VerifactuSamples::system(),
            RecordTimestamp::fromLiteral('2024-09-01T19:20:30+02:00'),
        );
    }

    public function test_official_example_url(): void
    {
        $record = $this->record('12345678-G33', '01-09-2024', Amount::fromLiteral('241.4'));

        self::assertSame(
            'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=89890001K&numserie=12345678-G33&fecha=01-09-2024&importe=241.4',
            VerifactuQr::url($record, VerifactuEnvironment::Test),
        );
    }

    public function test_canonical_amount_in_url(): void
    {
        $record = $this->record('12345678-G33', '01-09-2024', Amount::of('241.4'));

        self::assertStringEndsWith('&importe=241.40', VerifactuQr::url($record, VerifactuEnvironment::Test));
    }

    public function test_ampersand_is_url_encoded(): void
    {
        $record = $this->record('12345678&G33', '01-09-2024', Amount::of('241.4'));

        self::assertStringContainsString('&numserie=12345678%26G33&', VerifactuQr::url($record, VerifactuEnvironment::Test));
    }

    public function test_slash_is_url_encoded(): void
    {
        $record = $this->record('12345678/G33', '01-09-2024', Amount::of('241.4'));

        self::assertStringContainsString('&numserie=12345678%2FG33&', VerifactuQr::url($record, VerifactuEnvironment::Test));
    }

    public function test_production_host(): void
    {
        $record = $this->record('12345678-G33', '01-09-2024', Amount::of('241.4'));

        self::assertStringStartsWith(
            'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR?nif=',
            VerifactuQr::url($record, VerifactuEnvironment::Production),
        );
    }

    public function test_code_has_empty_label(): void
    {
        $record = $this->record('12345678-G33', '01-09-2024', Amount::of('241.4'));
        $code = VerifactuQr::code($record, VerifactuEnvironment::Test);

        self::assertSame('', $code->label);
        self::assertSame(VerifactuQr::url($record, VerifactuEnvironment::Test), $code->url);
    }

    public function test_svg_renders_without_caption(): void
    {
        if (! class_exists(QRCode::class)) {
            self::markTestSkipped('chillerlan/php-qrcode is not installed.');
        }

        $record = $this->record('12345678-G33', '01-09-2024', Amount::of('241.4'));
        $svg = (new ChillerlanQrRenderer)->svg(VerifactuQr::code($record, VerifactuEnvironment::Test));

        self::assertStringStartsWith('<svg', $svg);
        self::assertStringNotContainsString('<text', $svg);
    }
}
