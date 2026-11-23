<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

use chillerlan\QRCode\QRCode as ChillerlanQRCode;
use chillerlan\QRCode\QROptions;
use Closure;
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

    /** The levels the @ operator leaves in error_reporting(); any other bit means not silenced. */
    private const UNSILENCEABLE_LEVELS = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

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
        // turned into the exception below instead of reaching the caller's error handler. The
        // caller's handler is read first so that every other error is passed on to it.
        $previous = set_error_handler(static fn (): bool => false);
        restore_error_handler();
        set_error_handler(self::errorHandler($previous));
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

    /**
     * An error handler that throws a RuntimeException for a warning or notice, and passes every
     * other error, deprecations and warnings silenced with the @ operator included, to the
     * previous handler, or to PHP's own handler when there is none.
     *
     * Silencing is read from error_reporting() the way the @ operator leaves it: only levels that
     * cannot be silenced remain. A plain lower error_reporting() is not taken as silencing,
     * because error handlers such as PHPUnit's lower it while they handle every error themselves.
     *
     * @param  callable|null  $previous  the handler that was active before
     * @return Closure(int, string, string=, int=): bool
     */
    private static function errorHandler(?callable $previous): Closure
    {
        return static function (int $level, string $message, string $file = '', int $line = 0) use ($previous): bool {
            $silenced = (error_reporting() & ~self::UNSILENCEABLE_LEVELS) === 0;
            if (($level & (E_WARNING | E_NOTICE)) !== 0 && ! $silenced) {
                throw new RuntimeException($message);
            }

            return $previous !== null && $previous($level, $message, $file, $line) !== false;
        };
    }
}
