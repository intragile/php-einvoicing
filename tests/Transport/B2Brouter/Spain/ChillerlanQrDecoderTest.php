<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter\Spain;

use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\VerifactuQr;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Syntax\Verifactu\RecordReader;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\ChillerlanQrDecoder;
use IntrAgile\EInvoicing\Transport\B2Brouter\Spain\TaxReport;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\ChillerlanQrRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('verifactu-official')]
#[RequiresPhpExtension('gd')]
final class ChillerlanQrDecoderTest extends TestCase
{
    private const API_DIR = __DIR__.'/../../../fixtures/spain/b2brouter/api';

    public function test_is_available_with_the_package_and_gd(): void
    {
        self::assertTrue(ChillerlanQrDecoder::isAvailable());
    }

    public function test_renderer_output_round_trips(): void
    {
        $record = (new RecordReader)->read(Fixtures::verifactuB2Brouter()['record-1064493.xml'] ?? '');
        self::assertInstanceOf(RegistrationRecord::class, $record);
        $png = (new ChillerlanQrRenderer)->png(VerifactuQr::code($record, VerifactuEnvironment::Test));

        self::assertSame(VerifactuQr::url($record, VerifactuEnvironment::Test), (new ChillerlanQrDecoder)->decode($png));
    }

    public function test_captured_qr_decodes_to_the_record_url(): void
    {
        $data = json_decode((string) file_get_contents(self::API_DIR.'/tax-report.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        $report = TaxReport::fromArray($data);
        $record = $report->record();
        self::assertInstanceOf(RegistrationRecord::class, $record);
        self::assertNotNull($report->qrPng);

        $text = (new ChillerlanQrDecoder)->decode($report->qrPng);

        // B2Brouter prints the production ValidarQR host, in its sandbox too.
        self::assertSame(VerifactuQr::url($record, VerifactuEnvironment::Production), $text);
    }

    public function test_garbage_png_throws(): void
    {
        $this->expectException(RuntimeException::class);

        (new ChillerlanQrDecoder)->decode('not a png');
    }

    public function test_blank_image_throws(): void
    {
        $image = imagecreatetruecolor(64, 64);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $this->expectException(RuntimeException::class);

        (new ChillerlanQrDecoder)->decode($png);
    }
}
