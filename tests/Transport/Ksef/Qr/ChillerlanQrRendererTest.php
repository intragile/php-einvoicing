<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Ksef\Qr;

use chillerlan\QRCode\QRCode as ChillerlanQRCode;
use chillerlan\QRCode\QROptions;
use DOMDocument;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\ChillerlanQrRenderer;
use IntrAgile\EInvoicing\Transport\Ksef\Qr\QrCode;
use LogicException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

final class ChillerlanQrRendererTest extends TestCase
{
    private const URL = 'https://qr-test.ksef.mf.gov.pl/invoice/2222222222/01-09-2025/UtQp9Gpc51y-u3xApZjIjgkpZ01js-J8KflSPW8WzIE';

    #[RequiresPhpExtension('gd')]
    public function test_png_decodes_back_to_url(): void
    {
        $png = (new ChillerlanQrRenderer)->png(new QrCode(self::URL, '5265877635-20250826-0100001AF629-AF'));

        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $reader = new ChillerlanQRCode(new QROptions(['readerUseImagickIfAvailable' => false]));
        self::assertSame(self::URL, (string) $reader->readFromBlob($png));
    }

    #[RequiresPhpExtension('gd')]
    public function test_png_scale_follows_pixels_per_module(): void
    {
        $renderer = new ChillerlanQrRenderer;
        $code = new QrCode(self::URL, 'OFFLINE');

        $small = getimagesizefromstring($renderer->png($code, 2));
        $large = getimagesizefromstring($renderer->png($code, 6));
        self::assertIsArray($small);
        self::assertIsArray($large);
        self::assertGreaterThan($small[0], $large[0]);
    }

    public function test_svg_is_well_formed_and_carries_label(): void
    {
        $svg = (new ChillerlanQrRenderer)->svg(new QrCode(self::URL, 'CERTYFIKAT'));

        $document = new DOMDocument;
        self::assertTrue($document->loadXML($svg));
        self::assertSame('svg', $document->documentElement?->localName);
        self::assertStringContainsString('CERTYFIKAT', $svg);
    }

    public function test_svg_label_is_escaped(): void
    {
        $svg = (new ChillerlanQrRenderer)->svg(new QrCode(self::URL, '<&>'));

        self::assertStringContainsString('&lt;&amp;&gt;', $svg);
        self::assertTrue((new DOMDocument)->loadXML($svg));
    }

    public function test_renderer_without_chillerlan_throws(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('chillerlan/php-qrcode');

        ChillerlanQrRenderer::requirePackage(static fn (string $class): bool => false);
    }

    public function test_renderer_with_chillerlan_passes_the_check(): void
    {
        ChillerlanQrRenderer::requirePackage(static fn (string $class): bool => class_exists($class));

        $this->addToAssertionCount(1);
    }
}
