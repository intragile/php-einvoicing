<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

use chillerlan\QRCode\QRCode as ChillerlanQRCode;
use chillerlan\QRCode\QROptions;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Reads QR codes with chillerlan/php-qrcode (^5), an optional dependency: install it with
 * "composer require chillerlan/php-qrcode". Reading also needs ext-gd or ext-imagick; GD is used
 * when both are loaded.
 */
final class ChillerlanQrDecoder implements QrDecoder
{
    private const PACKAGE = 'chillerlan/php-qrcode';

    /**
     * @throws LogicException when chillerlan/php-qrcode or both ext-gd and ext-imagick are missing
     */
    public function __construct()
    {
        if (! self::isAvailable()) {
            throw new LogicException(sprintf(
                'Reading QR codes needs the optional package %1$s (^5), installed with "composer require %1$s", and the gd or imagick PHP extension.',
                self::PACKAGE,
            ));
        }
    }

    /** Whether the chillerlan reader can run here: the package plus ext-gd or ext-imagick. */
    public static function isAvailable(): bool
    {
        return class_exists(ChillerlanQRCode::class) && (extension_loaded('gd') || extension_loaded('imagick'));
    }

    public function decode(string $png): string
    {
        $options = new QROptions(['readerUseImagickIfAvailable' => ! extension_loaded('gd')]);

        // GD reports an unreadable image as a warning before chillerlan rejects it; the warning is
        // turned into the exception below instead of reaching the caller's error handler.
        set_error_handler(static fn (int $level, string $message): never => throw new RuntimeException($message));
        try {
            $text = (string) (new ChillerlanQRCode($options))->readFromBlob($png);
        } catch (Throwable $e) {
            throw new RuntimeException('The image holds no readable QR code: '.$e->getMessage(), 0, $e);
        } finally {
            restore_error_handler();
        }

        if ($text === '') {
            throw new RuntimeException('The image holds no readable QR code: the decoded text is empty.');
        }

        return $text;
    }
}
