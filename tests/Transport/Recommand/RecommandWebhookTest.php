<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Recommand;

use IntrAgile\EInvoicing\Tests\Support\CapturesThrowable;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandWebhook;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookKind;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecommandWebhookTest extends TestCase
{
    use CapturesThrowable;

    private const SECRET = 'whsec_01topsecretvalue';

    /**
     * @return iterable<string, array{string, ?string, bool}>
     */
    public static function signatureCases(): iterable
    {
        $raw = self::read('webhook-received.json');
        $valid = hash_hmac('sha256', $raw, self::SECRET);
        $reencoded = json_encode(json_decode($raw), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        yield 'valid lowercase hex' => [$raw, 'sha256='.$valid, true];
        yield 'valid uppercase hex' => [$raw, 'sha256='.strtoupper($valid), true];
        yield 'missing header' => [$raw, null, false];
        yield 'empty header' => [$raw, '', false];
        yield 'wrong secret' => [$raw, 'sha256='.hash_hmac('sha256', $raw, 'whsec_other'), false];
        yield 'sha1 prefix' => [$raw, 'sha1='.$valid, false];
        yield 'uppercase prefix' => [$raw, 'SHA256='.$valid, false];
        yield 'prefix only' => [$raw, 'sha256=', false];
        yield 'no prefix' => [$raw, $valid, false];
        yield 'surrounding whitespace' => [$raw, ' sha256='.$valid.' ', false];
        yield 'non-hex tail' => [$raw, 'sha256='.substr($valid, 0, 62).'zz', false];
        yield 'truncated hex' => [$raw, 'sha256='.substr($valid, 0, 32), false];
        yield 'signature over re-encoded JSON' => [$raw, 'sha256='.hash_hmac('sha256', $reencoded, self::SECRET), false];
        yield 'body re-encoded, signature over raw' => [$reencoded, 'sha256='.$valid, false];
        yield 'empty body with valid-looking header' => ['', 'sha256='.hash_hmac('sha256', '', self::SECRET), false];
    }

    #[DataProvider('signatureCases')]
    public function test_webhook_signature_cases(string $rawBody, ?string $header, bool $expected): void
    {
        $headers = $header === null ? [] : ['X-Signature' => $header];

        self::assertSame($expected, (new RecommandWebhook(self::SECRET))->verify($rawBody, $headers));
    }

    public function test_webhook_is_a_parser(): void
    {
        self::assertInstanceOf(WebhookParser::class, new RecommandWebhook(self::SECRET));
    }

    public function test_parse_returns_neutral_event(): void
    {
        $raw = self::read('webhook-received.json');

        $event = (new RecommandWebhook(self::SECRET))->parse($raw, [
            'X-Signature' => self::sign($raw),
            'X-Idempotency-Key' => 'idem_01abc',
        ]);

        self::assertSame(WebhookKind::DocumentReceived, $event->kind);
        self::assertSame('document.received', $event->type);
        self::assertSame('doc_01inboundfirst', $event->documentId);
        self::assertSame('c_01example', $event->accountId);
        self::assertSame('idem_01abc', $event->deduplicationKey);
    }

    /**
     * @return iterable<string, array{array<string, string|list<string>>}>
     */
    public static function unusableIdempotencyHeaders(): iterable
    {
        yield 'missing' => [[]];
        yield 'blank' => [['X-Idempotency-Key' => ' ']];
        yield 'repeated' => [['X-Idempotency-Key' => ['idem_01abc', 'idem_01abc']]];
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    #[DataProvider('unusableIdempotencyHeaders')]
    public function test_deduplication_key_falls_back_to_type_and_document_id(array $headers): void
    {
        $raw = self::read('webhook-received.json');

        $event = (new RecommandWebhook(self::SECRET))->parse($raw, ['X-Signature' => self::sign($raw)] + $headers);

        self::assertSame('document.received:doc_01inboundfirst:', $event->deduplicationKey);
    }

    public function test_company_and_idempotency_key_are_trimmed(): void
    {
        $raw = '{"eventType":"document.sent","documentId":"doc_01","teamId":"t","companyId":" c_01example\t","deliveryStatus":"delivered"}';

        $event = (new RecommandWebhook(self::SECRET))->parse($raw, ['X-Signature' => self::sign($raw), 'X-Idempotency-Key' => " idem_01abc\n"]);

        self::assertSame('c_01example', $event->accountId);
        self::assertSame('idem_01abc', $event->deduplicationKey);

        $blank = '{"eventType":"document.sent","documentId":"doc_01","teamId":"t","companyId":"  ","deliveryStatus":"delivered"}';
        self::assertNull((new RecommandWebhook(self::SECRET))->parse($blank, ['X-Signature' => self::sign($blank)])->accountId);
    }

    public function test_fallback_key_differs_per_delivery_state(): void
    {
        $webhook = new RecommandWebhook(self::SECRET);
        $delivered = '{"eventType":"document.delivery_status_changed","documentId":"doc_01","deliveryStatus":"delivered"}';
        $failed = '{"eventType":"document.delivery_status_changed","documentId":"doc_01","deliveryStatus":"failed"}';

        $first = $webhook->parse($delivered, ['X-Signature' => self::sign($delivered)]);
        $second = $webhook->parse($failed, ['X-Signature' => self::sign($failed)]);

        self::assertSame('document.delivery_status_changed:doc_01:delivered', $first->deduplicationKey);
        self::assertSame('document.delivery_status_changed:doc_01:failed', $second->deduplicationKey);
    }

    public function test_two_identical_signature_headers_are_refused(): void
    {
        $raw = self::read('webhook-received.json');
        $headers = ['X-Signature' => [self::sign($raw), self::sign($raw)]];

        $webhook = new RecommandWebhook(self::SECRET);

        self::assertFalse($webhook->verify($raw, $headers));
        self::assertInstanceOf(AuthenticationFailed::class, self::capture(fn () => $webhook->parse($raw, $headers)));
    }

    public function test_received_event_is_parsed(): void
    {
        $raw = self::read('webhook-received.json');

        $event = (new RecommandWebhook(self::SECRET))->parse($raw, ['X-Signature' => self::sign($raw)]);

        self::assertSame('document.received', $event->type);
        self::assertSame('doc_01inboundfirst', $event->documentId);
        self::assertSame('c_01example', $event->accountId);
        self::assertNull($event->deliveryState);
        self::assertNull($event->failure);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function idempotencyHeaderNames(): iterable
    {
        yield 'canonical' => ['X-Idempotency-Key'];
        yield 'lowercase' => ['x-idempotency-key'];
        yield 'uppercase' => ['X-IDEMPOTENCY-KEY'];
    }

    #[DataProvider('idempotencyHeaderNames')]
    public function test_idempotency_key_is_exposed(string $name): void
    {
        $raw = self::read('webhook-received.json');

        $event = (new RecommandWebhook(self::SECRET))->parse($raw, [
            'x-SIGNATURE' => [self::sign($raw)],
            $name => 'idem_01abc',
        ]);

        self::assertSame('idem_01abc', $event->deduplicationKey);
    }

    public function test_status_event_maps_delivery_state(): void
    {
        $raw = self::read('webhook-status.json');

        $event = (new RecommandWebhook(self::SECRET))->parse($raw, ['X-Signature' => self::sign($raw)]);

        self::assertSame(WebhookKind::DeliveryChanged, $event->kind);
        self::assertSame('document.delivery_status_changed', $event->type);
        self::assertSame('doc_01failed', $event->documentId);
        self::assertSame(DeliveryState::Failed, $event->deliveryState);
        self::assertNotNull($event->failure);
        self::assertSame(FailureCategory::RecipientNotFound, $event->failure->category);
        self::assertSame('The receiver is not registered on the Peppol network.', $event->failure->message);
        self::assertSame('SMP_NOT_FOUND', $event->failure->providerCode);
    }

    public function test_sent_event_maps_delivery_state(): void
    {
        $raw = '{"eventType":"document.sent","documentId":"doc_01","teamId":"t","companyId":"c","deliveryStatus":"delivered"}';

        $event = (new RecommandWebhook(self::SECRET))->parse($raw, ['X-Signature' => self::sign($raw)]);

        self::assertSame(WebhookKind::DeliveryChanged, $event->kind);
        self::assertSame(DeliveryState::Delivered, $event->deliveryState);
        self::assertNull($event->failure);
    }

    public function test_failure_of_a_non_peppol_delivery_is_ignored(): void
    {
        $data = json_decode(self::read('webhook-status.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        $data['channel'] = 'email';
        $data['deliveryStatus'] = 'delivered';
        $raw = json_encode($data, JSON_THROW_ON_ERROR);

        $event = (new RecommandWebhook(self::SECRET))->parse($raw, ['X-Signature' => self::sign($raw)]);

        self::assertSame(DeliveryState::Delivered, $event->deliveryState);
        self::assertNull($event->failure);
    }

    public function test_null_delivery_status_is_unknown(): void
    {
        $data = json_decode(self::read('webhook-status.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        $data['deliveryStatus'] = null;
        $raw = json_encode($data, JSON_THROW_ON_ERROR);

        $event = (new RecommandWebhook(self::SECRET))->parse($raw, ['X-Signature' => self::sign($raw)]);

        self::assertSame(DeliveryState::Unknown, $event->deliveryState);
    }

    public function test_unknown_type_is_other(): void
    {
        $raw = '{"eventType":"document.teleported","documentId":"doc_01","deliveryStatus":"somewhere"}';

        $event = (new RecommandWebhook(self::SECRET))->parse($raw, ['X-Signature' => self::sign($raw)]);

        self::assertSame(WebhookKind::Other, $event->kind);
        self::assertSame('document.teleported', $event->type);
        self::assertSame('doc_01', $event->documentId);
        self::assertNull($event->accountId);
        self::assertSame(DeliveryState::Unknown, $event->deliveryState);
    }

    /**
     * @return iterable<string, array{array<string, string|list<string>>}>
     */
    public static function unverifiedHeaders(): iterable
    {
        yield 'no signature header' => [['X-Idempotency-Key' => 'idem']];
        yield 'wrong signature' => [['X-Signature' => 'sha256='.str_repeat('0', 64)]];
        yield 'two signature values' => [['X-Signature' => ['sha256='.str_repeat('0', 64), 'sha256='.str_repeat('1', 64)]]];
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    #[DataProvider('unverifiedHeaders')]
    public function test_parse_requires_a_verified_signature(array $headers): void
    {
        $exception = self::capture(fn () => (new RecommandWebhook(self::SECRET))->parse(self::read('webhook-received.json'), $headers));

        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertStringNotContainsString(self::SECRET, $exception->getMessage());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedBodies(): iterable
    {
        yield 'not JSON' => ['not json'];
        yield 'JSON scalar' => ['"document.received"'];
        yield 'JSON array' => ['["document.received"]'];
        yield 'no event type' => ['{"documentId":"doc_01"}'];
        yield 'empty event type' => ['{"eventType":"","documentId":"doc_01"}'];
        yield 'no document id' => ['{"eventType":"document.received"}'];
        yield 'empty document id' => ['{"eventType":"document.received","documentId":""}'];
    }

    #[DataProvider('malformedBodies')]
    public function test_malformed_signed_body_is_unexpected(string $raw): void
    {
        $exception = self::capture(fn () => (new RecommandWebhook(self::SECRET))->parse($raw, ['X-Signature' => self::sign($raw)]));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
    }

    public function test_empty_secret_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RecommandWebhook('  ');
    }

    public function test_secret_stays_out_of_dumps(): void
    {
        $webhook = new RecommandWebhook(self::SECRET);

        self::assertStringNotContainsString(self::SECRET, var_export($webhook, true));
        self::assertStringNotContainsString(self::SECRET, print_r($webhook, true));
        ob_start();
        var_dump($webhook);
        self::assertStringNotContainsString(self::SECRET, (string) ob_get_clean());
        self::assertStringNotContainsString(self::SECRET, (string) json_encode($webhook));
    }

    private static function sign(string $raw): string
    {
        return 'sha256='.hash_hmac('sha256', $raw, self::SECRET);
    }

    private static function read(string $name): string
    {
        $contents = file_get_contents(__DIR__.'/../../fixtures/recommand/'.$name);
        self::assertIsString($contents);

        return $contents;
    }
}
