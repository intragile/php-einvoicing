<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use InvalidArgumentException;

/**
 * Checks a CA bundle for the root that issues the AEAT server certificates.
 *
 * The AEAT web services present certificates chained to the FNMT-RCM "servidores seguros" root,
 * which some operating system bundles do not carry. Checking the bundle up front turns an opaque
 * TLS handshake failure into a clear configuration error.
 */
final class CaBundle
{
    public const FNMT_ROOT_CN = 'AC RAIZ FNMT-RCM SERVIDORES SEGUROS';

    private const PEM_CERTIFICATE = '/-----BEGIN CERTIFICATE-----\s.+?-----END CERTIFICATE-----/s';

    /**
     * Whether the PEM bundle at $bundlePath holds a certificate whose subject common name is the
     * FNMT-RCM secure servers root. Only the subject is compared; the bundle's owner is trusted to
     * have installed the genuine root.
     *
     * @throws InvalidArgumentException when the bundle cannot be read
     */
    public static function containsFnmtRoot(string $bundlePath): bool
    {
        $contents = is_file($bundlePath) && is_readable($bundlePath) ? @file_get_contents($bundlePath) : false;
        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('The CA bundle "%s" cannot be read.', $bundlePath));
        }

        preg_match_all(self::PEM_CERTIFICATE, $contents, $matches);
        foreach ($matches[0] as $pem) {
            $parsed = @openssl_x509_parse($pem);
            $subject = $parsed === false ? null : ($parsed['subject'] ?? null);
            if (! is_array($subject)) {
                continue;
            }
            $commonNames = (array) ($subject['CN'] ?? []);
            if (in_array(self::FNMT_ROOT_CN, $commonNames, true)) {
                self::clearOpenSslErrors();

                return true;
            }
        }
        self::clearOpenSslErrors();

        return false;
    }

    /**
     * The CA bundle the OpenSSL extension uses by default, when it exists and is readable.
     */
    public static function defaultPath(): ?string
    {
        $path = openssl_get_cert_locations()['default_cert_file'] ?? '';

        return $path !== '' && is_file($path) && is_readable($path) ? $path : null;
    }

    /**
     * Drains OpenSSL's error queue, so a skipped certificate is not reported later by an unrelated call.
     */
    private static function clearOpenSslErrors(): void
    {
        while (openssl_error_string() !== false) {
            // Discard.
        }
    }
}
