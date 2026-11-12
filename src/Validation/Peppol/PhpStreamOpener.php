<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Peppol;

use RuntimeException;

/**
 * Opens requests with PHP's http stream wrapper: TLS peers are verified and redirects are
 * returned to the caller instead of being followed.
 */
final class PhpStreamOpener implements StreamOpener
{
    public function open(string $url, int $timeoutSeconds): array|false
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'follow_location' => 0,
                'ignore_errors' => true,
                'timeout' => $timeoutSeconds,
                'user_agent' => 'intragile-php-einvoicing',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $error = null;
        set_error_handler(static function (int $level, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        try {
            $stream = fopen($url, 'rb', false, $context);
        } finally {
            restore_error_handler();
        }
        if ($stream === false) {
            if ($error !== null) {
                throw new RuntimeException($error);
            }

            return false;
        }

        $wrapperData = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $headers = [];
        foreach (is_array($wrapperData) ? $wrapperData : [] as $header) {
            if (is_string($header)) {
                $headers[] = $header;
            }
        }

        return [$headers, $stream];
    }
}
