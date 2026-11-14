<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Webhook;

use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;

/**
 * Verifies and parses the webhook notifications of one provider.
 *
 * Pass the request body exactly as received: a body that was decoded and encoded again no longer
 * matches its signature. Header names are matched case-insensitively; a signature header that
 * appears more than once is refused.
 */
interface WebhookParser
{
    /**
     * The event of a signed notification.
     *
     * @param  array<string, string|list<string>>  $headers
     *
     * @throws AuthenticationFailed when the signature is missing or wrong
     * @throws UnexpectedResponse when a signed body is not a notification this parser understands
     */
    public function parse(string $rawBody, array $headers): WebhookEvent;

    /**
     * Whether the headers carry a valid signature of the raw body. Never throws for malformed
     * input; the comparison takes constant time.
     *
     * @param  array<string, string|list<string>>  $headers
     */
    public function verify(string $rawBody, array $headers): bool;
}
