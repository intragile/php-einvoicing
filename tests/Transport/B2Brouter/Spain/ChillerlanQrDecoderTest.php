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

    public function test_error_handler_turns_only_reported_warnings_and_notices_into_exceptions(): void
    {
        $forwarded = [];
        $previous = static function (int $level, string $message) use (&$forwarded): bool {
            $forwarded[] = $level;

            return true;
        };
        $handler = (new \ReflectionMethod(ChillerlanQrDecoder::class, 'errorHandler'))->invoke(null, $previous);
        self::assertInstanceOf(\Closure::class, $handler);

        foreach ([E_WARNING, E_NOTICE] as $level) {
            $thrown = null;
            try {
                $handler($level, 'imagecreatefromstring(): bad data');
            } catch (RuntimeException $e) {
                $thrown = $e;
            }
            self::assertInstanceOf(RuntimeException::class, $thrown);
        }

        self::assertTrue($handler(E_DEPRECATED, 'deprecated'));
        self::assertTrue($handler(E_USER_DEPRECATED, 'deprecated'));
        self::assertTrue(@$handler(E_WARNING, 'silenced'));
        self::assertSame([E_DEPRECATED, E_USER_DEPRECATED, E_WARNING], $forwarded);

        $alone = (new \ReflectionMethod(ChillerlanQrDecoder::class, 'errorHandler'))->invoke(null, null);
        self::assertInstanceOf(\Closure::class, $alone);
        self::assertFalse($alone(E_DEPRECATED, 'deprecated'));
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
