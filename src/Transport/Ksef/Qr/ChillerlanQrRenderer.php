<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Qr;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\QRCode as ChillerlanQRCode;
use chillerlan\QRCode\QROptions;
use Closure;
use GdImage;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Renders KSeF QR codes with chillerlan/php-qrcode (^5), an optional dependency: install it with
 * "composer require chillerlan/php-qrcode". PNG output also needs ext-gd.
 *
 * chillerlan only encodes the link (byte mode, error correction level M, a 4-module quiet zone);
 * the image and the caption under the code are drawn here, so that both formats look the same.
 */
final class ChillerlanQrRenderer implements QrRenderer
{
    private const PACKAGE = 'chillerlan/php-qrcode';

    private const MAX_PIXELS_PER_MODULE = 100;

    /** The SVG caption's font size, in modules, when the caption fits the code's width. */
    private const SVG_FONT_SIZE = 4.0;

    /**
     * @throws LogicException when chillerlan/php-qrcode is not installed
     */
    public function __construct()
    {
        self::requirePackage(static fn (string $class): bool => class_exists($class));
    }

    /**
     * Fails unless the chillerlan/php-qrcode classes can be loaded.
     *
     * @internal the class lookup is injectable for tests only
     *
     * @param  Closure(class-string): bool  $classExists
     *
     * @throws LogicException when chillerlan/php-qrcode is not installed
     */
    public static function requirePackage(Closure $classExists): void
    {
        if (! $classExists(ChillerlanQRCode::class)) {
            throw new LogicException(sprintf(
                'Rendering KSeF QR codes needs the optional package %1$s (^5); install it with "composer require %1$s".',
                self::PACKAGE,
            ));
        }
    }

    public function svg(QrCode $code): string
    {
        $matrix = self::matrix($code->url);
        $size = $matrix->getSize();

        $path = '';
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix->check($x, $y)) {
                    $path .= sprintf('M%d %dh1v1h-1z', $x, $y);
                }
            }
        }

        $label = $code->label;
        $fontSize = $label === '' ? 0.0 : min(self::SVG_FONT_SIZE, $size * 0.95 / (0.6 * mb_strlen($label)));
        $height = $label === '' ? (float) $size : $size + $fontSize * 1.5;

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %s" shape-rendering="crispEdges">'
            .'<rect width="%1$d" height="%2$s" fill="#ffffff"/><path fill="#000000" d="%s"/>',
            $size,
            self::number($height),
            $path,
        );

        if ($label !== '') {
            $svg .= sprintf(
                '<text x="%s" y="%s" font-family="sans-serif" font-size="%s" text-anchor="middle" fill="#000000">%s</text>',
                self::number($size / 2),
                self::number($size + $fontSize),
                self::number($fontSize),
                htmlspecialchars($label, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            );
        }

        return $svg.'</svg>';
    }

    public function png(QrCode $code, int $pixelsPerModule = 5): string
    {
        if ($pixelsPerModule < 1 || $pixelsPerModule > self::MAX_PIXELS_PER_MODULE) {
            throw new InvalidArgumentException(sprintf('The module size must be between 1 and %d pixels; %d given.', self::MAX_PIXELS_PER_MODULE, $pixelsPerModule));
        }

        if (! extension_loaded('gd')) {
            throw new LogicException('Rendering KSeF QR codes as PNG needs the gd PHP extension.');
        }

        $matrix = self::matrix($code->url);
        $codeWidth = $matrix->getSize() * $pixelsPerModule;

        // The built-in GD fonts 1 to 5 grow with the module size; ASCII captions only.
        $font = min(5, $pixelsPerModule);
        $label = (string) preg_replace('/[^\x20-\x7E]/', '?', $code->label);
        $labelWidth = $label === '' ? 0 : strlen($label) * imagefontwidth($font);
        $labelHeight = $label === '' ? 0 : imagefontheight($font) + 2 * $pixelsPerModule;

        $width = max($codeWidth, $labelWidth + 2 * $pixelsPerModule);
        $height = $codeWidth + $labelHeight;
        if ($width < 1 || $height < 1) {
            throw new RuntimeException('The QR code has no modules to draw.');
        }

        $image = imagecreatetruecolor($width, $height);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('GD could not create the image.');
        }

        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $black = (int) imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        $left = intdiv($width - $codeWidth, 2);
        $size = $matrix->getSize();
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix->check($x, $y)) {
                    $px = $left + $x * $pixelsPerModule;
                    $py = $y * $pixelsPerModule;
                    imagefilledrectangle($image, $px, $py, $px + $pixelsPerModule - 1, $py + $pixelsPerModule - 1, $black);
                }
            }
        }

        if ($label !== '') {
            imagestring($image, $font, intdiv($width - $labelWidth, 2), $codeWidth, $label, $black);
        }

        ob_start();
        $written = imagepng($image);
        $png = (string) ob_get_clean();
        if (! $written || $png === '') {
            throw new RuntimeException('GD could not encode the PNG image.');
        }

        return $png;
    }

    private static function matrix(string $url): QRMatrix
    {
        $options = new QROptions([
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
        ]);

        return (new ChillerlanQRCode($options))->addByteSegment($url)->getQRMatrix();
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(sprintf('%.3F', $value), '0'), '.');
    }
}
