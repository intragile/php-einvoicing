<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Pdf;

use Closure;
use IntrAgile\EInvoicing\Pdf\Exception\UnreadablePdf;
use IntrAgile\EInvoicing\Pdf\Exception\UnsupportedPdfFeature;

/**
 * Stream filters (ISO 32000-1 §7.4): FlateDecode with the PNG and TIFF predictors. Inflation
 * runs in chunks and stops as soon as the output passes the caller's byte limit, so a
 * decompression bomb is refused before it is expanded.
 *
 * @internal
 */
final class Filters
{
    private const int CHUNK = 4096;

    /**
     * Applies the stream's /Filter chain. $resolve follows indirect references in /Filter and
     * /DecodeParms.
     *
     * @param  Closure(mixed): mixed  $resolve
     */
    public static function decode(PdfStream $stream, Closure $resolve, int $maxBytes): string
    {
        $dictionary = $stream->dictionary;
        $filters = $resolve($dictionary->get('Filter'));
        if ($filters === null) {
            return $stream->raw;
        }
        $filters = is_array($filters) ? $filters : [$filters];
        $parameters = $resolve($dictionary->get('DecodeParms'));
        $parameters = is_array($parameters) ? $parameters : [$parameters];
        $data = $stream->raw;
        foreach ($filters as $i => $filter) {
            $filter = $resolve($filter);
            if (! $filter instanceof PdfName) {
                throw UnreadablePdf::malformed('A stream /Filter entry is not a name.');
            }
            if ($filter->name !== 'FlateDecode' && $filter->name !== 'Fl') {
                throw new UnsupportedPdfFeature('The PDF stream filter /'.$filter->name.' is not supported.');
            }
            $parms = $resolve($parameters[$i] ?? null);
            $data = self::predict(self::inflate($data, $maxBytes), $parms instanceof PdfDictionary ? $parms : null);
        }

        return $data;
    }

    private static function inflate(string $data, int $maxBytes): string
    {
        try {
            return self::inflateWith(ZLIB_ENCODING_DEFLATE, $data, $maxBytes);
        } catch (UnreadablePdf $e) {
            if ($e->getCode() !== UnreadablePdf::MALFORMED) {
                throw $e;
            }

            // Some writers emit raw deflate data without the zlib header.
            return self::inflateWith(ZLIB_ENCODING_RAW, $data, $maxBytes);
        }
    }

    private static function inflateWith(int $encoding, string $data, int $maxBytes): string
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw UnreadablePdf::malformed('Corrupt FlateDecode data: '.$message);
        });
        try {
            $context = inflate_init($encoding);
            if ($context === false) {
                throw UnreadablePdf::malformed('Cannot initialise FlateDecode.');
            }
            $out = '';
            $length = strlen($data);
            for ($pos = 0; $pos < $length; $pos += self::CHUNK) {
                $chunk = inflate_add($context, substr($data, $pos, self::CHUNK), ZLIB_SYNC_FLUSH);
                if ($chunk === false) {
                    throw UnreadablePdf::malformed('Corrupt FlateDecode data.');
                }
                $out .= $chunk;
                if (strlen($out) > $maxBytes) {
                    throw UnreadablePdf::refused(sprintf(
                        'Decoded stream data exceeds the limit of %d bytes (%d MiB per stream, %d MiB per document).',
                        $maxBytes,
                        PdfParser::MAX_STREAM_BYTES >> 20,
                        PdfParser::MAX_TOTAL_DECODED_BYTES >> 20,
                    ));
                }
                if (inflate_get_status($context) === ZLIB_STREAM_END) {
                    break;
                }
            }

            // A truncated stream yields what could be inflated, as common readers do.
            return $out;
        } finally {
            restore_error_handler();
        }
    }

    private static function predict(string $data, ?PdfDictionary $parms): string
    {
        $predictor = $parms?->get('Predictor') ?? 1;
        if ($predictor === 1) {
            return $data;
        }
        $colors = self::intParam($parms, 'Colors', 1, 1, 32);
        $bits = self::intParam($parms, 'BitsPerComponent', 8, 1, 16);
        $columns = self::intParam($parms, 'Columns', 1, 1, 1 << 24);
        if (! in_array($bits, [1, 2, 4, 8, 16], true)) {
            throw UnreadablePdf::malformed('Invalid /BitsPerComponent '.$bits.'.');
        }
        $rowLength = intdiv($colors * $bits * $columns + 7, 8);
        $pixelBytes = max(1, intdiv($colors * $bits + 7, 8));

        if ($predictor === 2) {
            return self::tiff($data, $rowLength, $colors, $bits);
        }
        if (is_int($predictor) && $predictor >= 10 && $predictor <= 15) {
            return self::png($data, $rowLength, $pixelBytes);
        }

        throw new UnsupportedPdfFeature('The PDF stream predictor '.(is_scalar($predictor) ? $predictor : '?').' is not supported.');
    }

    private static function intParam(?PdfDictionary $parms, string $key, int $default, int $min, int $max): int
    {
        $value = $parms?->get($key) ?? $default;
        if (! is_int($value) || $value < $min || $value > $max) {
            throw UnreadablePdf::malformed(sprintf('Invalid /%s in /DecodeParms.', $key));
        }

        return $value;
    }

    /** Reverses the PNG row filters (RFC 2083 §6), each row prefixed with its filter type. */
    private static function png(string $data, int $rowLength, int $pixelBytes): string
    {
        $out = '';
        $previous = str_repeat("\0", $rowLength);
        $length = strlen($data);
        for ($p = 0; $p < $length; $p += $rowLength + 1) {
            $type = ord($data[$p]);
            $row = substr($data, $p + 1, $rowLength);
            $available = strlen($row);
            $row = str_pad($row, $rowLength, "\0");
            switch ($type) {
                case 0:
                    break;
                case 1:
                    for ($i = $pixelBytes; $i < $rowLength; $i++) {
                        $row[$i] = chr((ord($row[$i]) + ord($row[$i - $pixelBytes])) & 0xFF);
                    }
                    break;
                case 2:
                    for ($i = 0; $i < $rowLength; $i++) {
                        $row[$i] = chr((ord($row[$i]) + ord($previous[$i])) & 0xFF);
                    }
                    break;
                case 3:
                    for ($i = 0; $i < $rowLength; $i++) {
                        $left = $i >= $pixelBytes ? ord($row[$i - $pixelBytes]) : 0;
                        $row[$i] = chr((ord($row[$i]) + (($left + ord($previous[$i])) >> 1)) & 0xFF);
                    }
                    break;
                case 4:
                    for ($i = 0; $i < $rowLength; $i++) {
                        $a = $i >= $pixelBytes ? ord($row[$i - $pixelBytes]) : 0;
                        $b = ord($previous[$i]);
                        $c = $i >= $pixelBytes ? ord($previous[$i - $pixelBytes]) : 0;
                        $estimate = $a + $b - $c;
                        $pa = abs($estimate - $a);
                        $pb = abs($estimate - $b);
                        $pc = abs($estimate - $c);
                        $predicted = $pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c);
                        $row[$i] = chr((ord($row[$i]) + $predicted) & 0xFF);
                    }
                    break;
                default:
                    throw UnreadablePdf::malformed('Invalid PNG predictor row type '.$type.'.');
            }
            $out .= substr($row, 0, $available);
            $previous = $row;
        }

        return $out;
    }

    /** Reverses TIFF Predictor 2 (horizontal differencing) for 8- and 16-bit components. */
    private static function tiff(string $data, int $rowLength, int $colors, int $bits): string
    {
        if ($bits !== 8 && $bits !== 16) {
            throw new UnsupportedPdfFeature('The TIFF predictor with '.$bits.' bits per component is not supported.');
        }
        $out = '';
        $length = strlen($data);
        $sampleBytes = $bits >> 3;
        $step = $colors * $sampleBytes;
        for ($p = 0; $p < $length; $p += $rowLength) {
            $row = substr($data, $p, $rowLength);
            $n = strlen($row) - strlen($row) % $sampleBytes;
            for ($i = $step; $i < $n; $i += $sampleBytes) {
                if ($sampleBytes === 1) {
                    $row[$i] = chr((ord($row[$i]) + ord($row[$i - $step])) & 0xFF);
                } else {
                    $sum = ((ord($row[$i]) << 8 | ord($row[$i + 1])) + (ord($row[$i - $step]) << 8 | ord($row[$i - $step + 1]))) & 0xFFFF;
                    $row[$i] = chr($sum >> 8);
                    $row[$i + 1] = chr($sum & 0xFF);
                }
            }
            $out .= $row;
        }

        return $out;
    }
}
