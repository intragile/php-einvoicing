<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\EInvoiceBe;

use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\EInvoiceBe\CanonicalJson;
use IntrAgile\EInvoicing\Transport\EInvoiceBe\EInvoiceBeWebhook;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookKind;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class EInvoiceBeWebhookTest extends TestCase
{
    /**
     * The secret and signature of the e-invoice.be test vector, published on
     * https://docs.e-invoice.be/essentials/webhooks.md ("Test vector"; research note
     * RESEARCH-PEPPOL-DRIVERS.md §2.5) for the example event in CanonicalJsonTest. The six-character
     * secret is the provider's published value and the one exception to the fake-key fixture rule.
     */
    private const VECTOR_SECRET = 'secret';

    private const VECTOR_SIGNATURE = 'sha256=a0b509e3466347b9c227767ff860769c15bf2d6c975a5ae706813aeb22a8694e';

    private const SECRET = 'whsec_FAKESECRET000000';

    private const DOCUMENT_ID = 'doc-1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d';

    /**
     * A compact notification body, keys unsorted, as the API sends it.
     *
     * @param  array<string, mixed>  $override
     */
    private static function body(string $type, array $override = []): string
    {
        return json_encode($override + [
            'id' => 'evt-FAKE'.bin2hex(random_bytes(8)),
            'tenant_id' => 'ten-FAKETENANT01',
            'created_at' => 1762780249,
            'type' => $type,
            'data' => ['document_id' => self::DOCUMENT_ID],
            'text' => "\u{26A1}\u{FE0F} New webhook event: ".$type,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, string>
     */
    private static function headers(string $body, string $secret = self::SECRET): array
    {
        return ['X-Signature' => 'sha256='.hash_hmac('sha256', CanonicalJson::fromRaw($body), $secret)];
    }

    private static function capture(callable $callable): Throwable
    {
        try {
            $callable();
        } catch (Throwable $exception) {
            return $exception;
        }
        self::fail('No exception was thrown.');
    }

    public function test_documented_vector_verifies(): void
    {
        $webhook = new EInvoiceBeWebhook(self::VECTOR_SECRET);

        foreach ([CanonicalJsonTest::DOCUMENTED_EVENT, CanonicalJsonTest::documentedEventAsSent()] as $body) {
            self::assertTrue($webhook->verify($body, ['X-Signature' => self::VECTOR_SIGNATURE]));
            self::assertFalse($webhook->verify($body, ['x-signature' => [strtoupper(self::VECTOR_SIGNATURE)]]), 'the scheme is "sha256=" exactly');
            self::assertTrue($webhook->verify($body, ['x-signature' => ['sha256='.strtoupper(substr(self::VECTOR_SIGNATURE, 7))]]), 'hexadecimal digits in either case');

            $event = $webhook->parse($body, ['X-Signature' => self::VECTOR_SIGNATURE]);
            self::assertSame(WebhookKind::DeliveryChanged, $event->kind);
            self::assertSame('document.sent', $event->type);
            self::assertSame(self::DOCUMENT_ID, $event->documentId);
            self::assertSame('ten-k3m9x2p7q4w8z5n1', $event->accountId);
            self::assertSame('document.sent:'.self::DOCUMENT_ID, $event->deduplicationKey);
            self::assertSame(DeliveryState::Delivered, $event->deliveryState);
            self::assertNull($event->failure);
        }

        self::assertFalse((new EInvoiceBeWebhook('whsec_FAKEWRONG00000'))->verify(CanonicalJsonTest::DOCUMENTED_EVENT, ['X-Signature' => self::VECTOR_SIGNATURE]));
    }

    public function test_raw_body_hmac_does_not_verify(): void
    {
        $webhook = new EInvoiceBeWebhook(self::VECTOR_SECRET);

        foreach ([CanonicalJsonTest::DOCUMENTED_EVENT, CanonicalJsonTest::documentedEventAsSent()] as $body) {
            $headers = ['X-Signature' => 'sha256='.hash_hmac('sha256', $body, self::VECTOR_SECRET)];

            self::assertFalse($webhook->verify($body, $headers));
            self::assertInstanceOf(AuthenticationFailed::class, self::capture(fn () => $webhook->parse($body, $headers)));
        }
    }

    public function test_reordered_and_reencoded_body_verifies(): void
    {
        // The signature covers the canonical form, so any encoding of the same event verifies.
        $webhook = new EInvoiceBeWebhook(self::VECTOR_SECRET);
        $decoded = json_decode(CanonicalJsonTest::DOCUMENTED_EVENT, true, 512, JSON_THROW_ON_ERROR);
        $reversed = json_encode(array_reverse($decoded), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        self::assertTrue($webhook->verify($reversed, ['X-Signature' => self::VECTOR_SIGNATURE]));

        $changed = str_replace('ten-k3m9x2p7q4w8z5n1', 'ten-k3m9x2p7q4w8z5n2', $reversed);
        self::assertFalse($webhook->verify($changed, ['X-Signature' => self::VECTOR_SIGNATURE]));
    }

    /**
     * @return iterable<string, array{string, WebhookKind, ?DeliveryState}>
     */
    public static function kinds(): iterable
    {
        yield 'received' => ['document.received', WebhookKind::DocumentReceived, null];
        yield 'sent' => ['document.sent', WebhookKind::DeliveryChanged, DeliveryState::Delivered];
        yield 'send failed: read the status' => ['document.sent.failed', WebhookKind::DeliveryChanged, null];
        yield 'receive failed' => ['document.received.failed', WebhookKind::Other, null];
        yield 'reserved type' => ['peppol.sent', WebhookKind::Other, null];
    }

    #[DataProvider('kinds')]
    public function test_kind_mapping(string $type, WebhookKind $kind, ?DeliveryState $state): void
    {
        $body = self::body($type);

        $event = (new EInvoiceBeWebhook(self::SECRET))->parse($body, self::headers($body));

        self::assertSame($kind, $event->kind);
        self::assertSame($type, $event->type);
        self::assertSame(self::DOCUMENT_ID, $event->documentId);
        self::assertSame('ten-FAKETENANT01', $event->accountId);
        self::assertSame($state, $event->deliveryState);
        self::assertNull($event->failure, 'the notification carries no failure reason');
    }

    public function test_dedup_key_is_type_and_document(): void
    {
        $webhook = new EInvoiceBeWebhook(self::SECRET);
        // Each attempt of one delivery has its own event id and creation time.
        $first = self::body('document.sent', ['id' => 'evt-FAKEFIRST', 'created_at' => 1762780249]);
        $retry = self::body('document.sent', ['id' => 'evt-FAKERETRY', 'created_at' => 1762780260]);
        $failed = self::body('document.sent.failed', ['id' => 'evt-FAKEFIRST']);

        $key = $webhook->parse($first, self::headers($first))->deduplicationKey;

        self::assertSame('document.sent:'.self::DOCUMENT_ID, $key);
        self::assertSame($key, $webhook->parse($retry, self::headers($retry))->deduplicationKey);
        self::assertSame('document.sent.failed:'.self::DOCUMENT_ID, $webhook->parse($failed, self::headers($failed))->deduplicationKey);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function missingTenants(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['  '];
        yield 'null' => [null];
        yield 'number' => [17];
        yield 'object' => [['id' => 'ten-x']];
    }

    #[DataProvider('missingTenants')]
    public function test_account_id_is_null_without_tenant(mixed $tenant): void
    {
        $body = self::body('document.received', ['tenant_id' => $tenant]);

        self::assertNull((new EInvoiceBeWebhook(self::SECRET))->parse($body, self::headers($body))->accountId);

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        unset($decoded['tenant_id']);
        $without = json_encode($decoded, JSON_THROW_ON_ERROR);
        self::assertNull((new EInvoiceBeWebhook(self::SECRET))->parse($without, self::headers($without))->accountId);
    }

    /**
     * @return iterable<string, array{array<string, string|list<string>>}>
     */
    public static function malformedHeaders(): iterable
    {
        $body = self::body('document.sent', ['id' => 'evt-FAKEFIXED']);
        $valid = self::headers($body)['X-Signature'];
        $hex = substr($valid, 7);

        yield 'missing' => [[]];
        yield 'other header only' => [['X-Signature-256' => $valid]];
        yield 'two headers' => [['X-Signature' => $valid, 'x-signature' => 'sha256='.str_repeat('0', 64)]];
        yield 'two identical values' => [['X-Signature' => [$valid, $valid]]];
        yield 'uppercase scheme' => [['X-Signature' => 'SHA256='.$hex]];
        yield 'other scheme' => [['X-Signature' => 'sha1='.$hex]];
        yield 'no scheme' => [['X-Signature' => $hex]];
        yield 'short hex' => [['X-Signature' => 'sha256='.substr($hex, 0, 63)]];
        yield 'long hex' => [['X-Signature' => $valid.'0']];
        yield 'trailing newline' => [['X-Signature' => $valid."\n"]];
        yield 'leading space' => [['X-Signature' => ' '.$valid]];
        yield 'empty value' => [['X-Signature' => '']];
        yield 'B2Brouter form' => [['X-Signature' => 't=1762780249,s='.$hex]];
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    #[DataProvider('malformedHeaders')]
    public function test_malformed_signature_headers_are_refused(array $headers): void
    {
        $webhook = new EInvoiceBeWebhook(self::SECRET);
        $body = self::body('document.sent', ['id' => 'evt-FAKEFIXED']);
        self::assertTrue($webhook->verify($body, self::headers($body)), 'the body itself verifies');

        self::assertFalse($webhook->verify($body, $headers));
        $exception = self::capture(fn () => $webhook->parse($body, $headers));
        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertStringContainsString('X-Signature', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function uncanonicalBodies(): iterable
    {
        yield 'float' => ['{"type":"document.sent","data":{"document_id":"doc-1"},"amount":1.5}'];
        yield 'out-of-range integer' => ['{"type":"document.sent","data":{"document_id":"doc-1"},"n":99999999999999999999}'];
        yield 'invalid JSON' => ['{"type":"document.sent",'];
        yield 'empty' => [''];
    }

    #[DataProvider('uncanonicalBodies')]
    public function test_body_without_canonical_form_fails_closed(string $body): void
    {
        $webhook = new EInvoiceBeWebhook(self::SECRET);
        // Whatever was signed, this body cannot be checked against it.
        $headers = ['X-Signature' => 'sha256='.hash_hmac('sha256', $body, self::SECRET)];

        self::assertFalse($webhook->verify($body, $headers));
        $exception = self::capture(fn () => $webhook->parse($body, $headers));
        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertStringContainsString('canonical', $exception->getMessage());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unusableBodies(): iterable
    {
        yield 'list' => ['[{"type":"document.sent"}]', 'is not a JSON object'];
        yield 'no type' => ['{"data":{"document_id":"doc-1"}}', 'has no event type'];
        yield 'blank type' => ['{"type":" ","data":{"document_id":"doc-1"}}', 'has no event type'];
        yield 'no data' => ['{"type":"document.sent"}', 'has no document id'];
        yield 'data is a list' => ['{"type":"document.sent","data":["doc-1"]}', 'has no document id'];
        yield 'no document id' => ['{"type":"document.sent","data":{}}', 'has no document id'];
        yield 'document id is a number' => ['{"type":"document.sent","data":{"document_id":7}}', 'has no document id'];
        yield 'blank document id' => ['{"type":"document.sent","data":{"document_id":"  "}}', 'has no document id'];
    }

    #[DataProvider('unusableBodies')]
    public function test_signed_body_without_document_is_unexpected(string $body, string $message): void
    {
        $exception = self::capture(fn () => (new EInvoiceBeWebhook(self::SECRET))->parse($body, self::headers($body)));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('e-invoice.be webhook notification '.$message, $exception->getMessage());
    }

    public function test_secret_must_not_be_empty(): void
    {
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => new EInvoiceBeWebhook('')));
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => new EInvoiceBeWebhook(" \t\n")));
    }

    public function test_dump_hides_secret(): void
    {
        $webhook = new EInvoiceBeWebhook(self::SECRET);

        ob_start();
        var_dump($webhook);
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString(self::SECRET, $dump);
        self::assertStringNotContainsString(self::SECRET, print_r($webhook, true));
        self::assertStringNotContainsString(self::SECRET, var_export($webhook, true));
        self::assertStringNotContainsString(self::SECRET, (string) json_encode($webhook));
    }
}
