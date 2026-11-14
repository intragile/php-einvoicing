<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter;

use DateTimeImmutable;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterWebhook;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookKind;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class B2BrouterWebhookTest extends TestCase
{
    private const SECRET = 'whsec_FAKESECRET000000';

    private const TIMESTAMP = 1_732_530_076;

    /**
     * @param  array<mixed>  $payload
     */
    private static function body(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private static function stateChangeBody(): string
    {
        return '{"code":"issued_invoice.state_change","triggered_at":1732530071,"data":{"account_id":78234,"invoice_id":85373,"event_id":381690,"state":"sent","notes":null}}';
    }

    private static function signature(string $body, int|string $timestamp = self::TIMESTAMP, string $secret = self::SECRET): string
    {
        return sprintf('t=%s,s=%s', $timestamp, hash_hmac('sha256', $timestamp.'.'.$body, $secret));
    }

    /**
     * @param  array<string, string|list<string>>  $extra
     * @return array<string, string|list<string>>
     */
    private static function headers(string $body, array $extra = []): array
    {
        return ['X-B2Brouter-Signature' => self::signature($body)] + $extra;
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

    public function test_valid_signature_verifies(): void
    {
        $body = self::stateChangeBody();
        // Computed independently of the class under test: HMAC-SHA256 over "<t>.<raw body>".
        $expected = hash_hmac('sha256', self::TIMESTAMP.'.'.$body, self::SECRET);
        $webhook = new B2BrouterWebhook(self::SECRET);

        self::assertTrue($webhook->verify($body, ['X-B2Brouter-Signature' => 't='.self::TIMESTAMP.',s='.$expected]));
        self::assertTrue($webhook->verify($body, ['x-b2brouter-signature' => ['t='.self::TIMESTAMP.',s='.strtoupper($expected)]]));
        self::assertFalse($webhook->verify($body, ['X-B2Brouter-Signature' => self::signature($body, self::TIMESTAMP, 'whsec_OTHERFAKE000000')]));
        self::assertFalse($webhook->verify($body, ['X-B2Brouter-Signature' => 't='.(self::TIMESTAMP + 1).',s='.$expected]), 'the timestamp is part of the signed payload');
        self::assertFalse($webhook->verify('', ['X-B2Brouter-Signature' => self::signature('')]), 'an empty body is never valid');
    }

    public function test_signature_over_reencoded_body_fails(): void
    {
        $body = "{\n  \"code\": \"received_invoice.created\",\n  \"data\": {\"invoice_id\": 85373, \"notes\": \"Szállítás \\/ 2\"}\n}";
        $reencoded = json_encode(json_decode($body, true), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $webhook = new B2BrouterWebhook(self::SECRET);

        self::assertTrue($webhook->verify($body, self::headers($body)));
        self::assertNotSame($body, $reencoded);
        self::assertFalse($webhook->verify($reencoded, self::headers($body)));
        $exception = self::capture(fn () => $webhook->parse($reencoded, self::headers($body)));
        self::assertInstanceOf(AuthenticationFailed::class, $exception);
    }

    /**
     * @return iterable<string, array{array<string, string|list<string>>}>
     */
    public static function malformedHeaders(): iterable
    {
        $body = self::stateChangeBody();
        $valid = self::signature($body);
        $hex = substr($valid, strlen('t='.self::TIMESTAMP.',s='));

        yield 'missing' => [[]];
        yield 'other header only' => [['X-Signature' => 'sha256='.$hex]];
        yield 'two headers' => [['X-B2Brouter-Signature' => $valid, 'x-b2brouter-signature' => self::signature($body, self::TIMESTAMP, 'whsec_OTHERFAKE000000')]];
        yield 'two identical values' => [['X-B2Brouter-Signature' => [$valid, $valid]]];
        yield 's before t' => [['X-B2Brouter-Signature' => sprintf('s=%s,t=%d', $hex, self::TIMESTAMP)]];
        yield 'short hex' => [['X-B2Brouter-Signature' => 't='.self::TIMESTAMP.',s='.substr($hex, 0, 63)]];
        yield 'extra field' => [['X-B2Brouter-Signature' => $valid.',v=1']];
        yield 'uppercase T=' => [['X-B2Brouter-Signature' => 'T='.self::TIMESTAMP.',s='.$hex]];
        yield 'space after comma' => [['X-B2Brouter-Signature' => 't='.self::TIMESTAMP.', s='.$hex]];
        yield 'trailing newline' => [['X-B2Brouter-Signature' => $valid."\n"]];
        yield 'empty value' => [['X-B2Brouter-Signature' => '']];
        yield 'non-numeric timestamp' => [['X-B2Brouter-Signature' => 't=abc,s='.$hex]];
        yield 'thirteen-digit timestamp' => [['X-B2Brouter-Signature' => 't=1732530076000,s='.$hex]];
        yield 'other scheme' => [['X-B2Brouter-Signature' => 'sha256='.$hex]];
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    #[DataProvider('malformedHeaders')]
    public function test_malformed_signature_headers_are_refused(array $headers): void
    {
        $webhook = new B2BrouterWebhook(self::SECRET);
        $body = self::stateChangeBody();

        self::assertFalse($webhook->verify($body, $headers));
        $exception = self::capture(fn () => $webhook->parse($body, $headers));
        self::assertInstanceOf(AuthenticationFailed::class, $exception);
        self::assertStringContainsString('X-B2Brouter-Signature', $exception->getMessage());
    }

    public function test_tolerance_is_off_by_default(): void
    {
        $body = self::stateChangeBody();
        // A timestamp from 2001: a legitimate retry may carry the signature of its first attempt.
        $headers = ['X-B2Brouter-Signature' => self::signature($body, 1_000_000_000)];
        $clock = static fn (): DateTimeImmutable => new DateTimeImmutable('@'.self::TIMESTAMP);

        self::assertTrue((new B2BrouterWebhook(self::SECRET))->verify($body, $headers));
        self::assertTrue((new B2BrouterWebhook(self::SECRET, null, $clock))->verify($body, $headers));
    }

    public function test_tolerance_refuses_old_timestamp(): void
    {
        $body = self::stateChangeBody();
        $now = static fn (): DateTimeImmutable => new DateTimeImmutable('@'.self::TIMESTAMP);
        $webhook = new B2BrouterWebhook(self::SECRET, 300, $now);

        self::assertTrue($webhook->verify($body, ['X-B2Brouter-Signature' => self::signature($body, self::TIMESTAMP - 300)]));
        self::assertTrue($webhook->verify($body, ['X-B2Brouter-Signature' => self::signature($body, self::TIMESTAMP + 300)]));
        self::assertFalse($webhook->verify($body, ['X-B2Brouter-Signature' => self::signature($body, self::TIMESTAMP - 301)]));
        self::assertFalse($webhook->verify($body, ['X-B2Brouter-Signature' => self::signature($body, self::TIMESTAMP + 301)]), 'a timestamp from the future is refused as well');
        self::assertInstanceOf(AuthenticationFailed::class, self::capture(fn () => $webhook->parse($body, ['X-B2Brouter-Signature' => self::signature($body, self::TIMESTAMP - 301)])));
    }

    public function test_tolerance_must_be_positive(): void
    {
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => new B2BrouterWebhook(self::SECRET, 0)));
        self::assertInstanceOf(InvalidArgumentException::class, self::capture(fn () => new B2BrouterWebhook(self::SECRET, -5)));
    }

    public function test_state_change_maps_kind_and_state(): void
    {
        $webhook = new B2BrouterWebhook(self::SECRET);
        $body = self::stateChangeBody();

        $event = $webhook->parse($body, self::headers($body, ['X-B2Brouter-Delivery-Id' => '184467']));

        self::assertSame(WebhookKind::DeliveryChanged, $event->kind);
        self::assertSame('issued_invoice.state_change', $event->type);
        self::assertSame('85373', $event->documentId);
        self::assertSame('78234', $event->accountId);
        self::assertSame(DeliveryState::Delivered, $event->deliveryState);
        self::assertNull($event->failure);

        foreach (['new' => DeliveryState::Pending, 'error' => DeliveryState::Failed, 'refused' => DeliveryState::Delivered, 'future_state' => DeliveryState::Unknown] as $state => $expected) {
            $body = self::body(['code' => 'issued_invoice.state_change', 'data' => ['invoice_id' => 85373, 'state' => $state]]);
            $event = $webhook->parse($body, self::headers($body));
            self::assertSame($expected, $event->deliveryState, $state);
            self::assertNull($event->failure, 'the payload carries no error code');
        }

        // Without a state the event says nothing about the delivery, so the status must be read.
        $body = self::body(['code' => 'issued_invoice.state_change', 'data' => ['invoice_id' => 85373]]);
        self::assertNull($webhook->parse($body, self::headers($body))->deliveryState);
    }

    public function test_received_maps_kind(): void
    {
        $webhook = new B2BrouterWebhook(self::SECRET);
        $body = self::body(['code' => 'received_invoice.created', 'triggered_at' => 1732530071, 'data' => ['invoice_id' => 99120, 'state' => 'new']]);

        $event = $webhook->parse($body, self::headers($body));

        self::assertSame(WebhookKind::DocumentReceived, $event->kind);
        self::assertSame('received_invoice.created', $event->type);
        self::assertSame('99120', $event->documentId);
        self::assertNull($event->accountId, 'the payload names no account');
        self::assertNull($event->deliveryState);
        self::assertNull($event->failure);
    }

    public function test_other_code_with_invoice_id_is_other(): void
    {
        $webhook = new B2BrouterWebhook(self::SECRET);
        $body = self::body(['code' => 'issued_invoice.paid', 'data' => ['invoice_id' => 85373, 'account_id' => 78234]]);

        $event = $webhook->parse($body, self::headers($body));

        self::assertSame(WebhookKind::Other, $event->kind);
        self::assertSame('issued_invoice.paid', $event->type);
        self::assertSame('85373', $event->documentId);
        self::assertNull($event->deliveryState);
    }

    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function accountIds(): iterable
    {
        yield 'positive int' => [78234, '78234'];
        yield 'digit string' => ['78234', '78234'];
        yield 'other non-empty string' => ['acc_9', 'acc_9'];
        yield 'absent' => [null, null];
        yield 'empty string' => ['', null];
        yield 'blank string' => ['  ', null];
        yield 'zero' => [0, null];
        yield 'negative' => [-4, null];
        yield 'float' => [7.5, null];
        yield 'boolean' => [true, null];
        yield 'object' => [['id' => 1], null];
    }

    #[DataProvider('accountIds')]
    public function test_account_id_is_null_unless_present(mixed $accountId, ?string $expected): void
    {
        $data = ['invoice_id' => 85373, 'state' => 'sent'];
        if ($accountId !== null) {
            $data['account_id'] = $accountId;
        }
        $body = self::body(['code' => 'issued_invoice.state_change', 'data' => $data]);

        self::assertSame($expected, (new B2BrouterWebhook(self::SECRET))->parse($body, self::headers($body))->accountId);
    }

    public function test_dedup_key_prefers_delivery_id(): void
    {
        $webhook = new B2BrouterWebhook(self::SECRET);
        $body = self::stateChangeBody();

        self::assertSame('184467', $webhook->parse($body, self::headers($body, ['x-b2brouter-delivery-id' => '184467']))->deduplicationKey);
    }

    public function test_dedup_key_falls_back_to_event_id(): void
    {
        $webhook = new B2BrouterWebhook(self::SECRET);
        $body = self::stateChangeBody();

        self::assertSame('issued_invoice.state_change:85373:381690', $webhook->parse($body, self::headers($body))->deduplicationKey);
        // A blank or repeated delivery id is no delivery id.
        self::assertSame('issued_invoice.state_change:85373:381690', $webhook->parse($body, self::headers($body, ['X-B2Brouter-Delivery-Id' => ' ']))->deduplicationKey);
        self::assertSame('issued_invoice.state_change:85373:381690', $webhook->parse($body, self::headers($body, ['X-B2Brouter-Delivery-Id' => ['1', '2']]))->deduplicationKey);
    }

    public function test_dedup_key_falls_back_to_state_without_event_id(): void
    {
        $webhook = new B2BrouterWebhook(self::SECRET);

        $sent = self::body(['code' => 'issued_invoice.state_change', 'data' => ['invoice_id' => 85373, 'state' => 'sent']]);
        $registered = self::body(['code' => 'issued_invoice.state_change', 'data' => ['invoice_id' => 85373, 'state' => 'registered']]);
        $received = self::body(['code' => 'received_invoice.created', 'data' => ['invoice_id' => 99120]]);

        self::assertSame('issued_invoice.state_change:85373:sent', $webhook->parse($sent, self::headers($sent))->deduplicationKey);
        self::assertSame('issued_invoice.state_change:85373:registered', $webhook->parse($registered, self::headers($registered))->deduplicationKey);
        self::assertSame('received_invoice.created:99120:', $webhook->parse($received, self::headers($received))->deduplicationKey, 'an empty state is allowed');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unexpectedBodies(): iterable
    {
        yield 'other code without invoice id' => [self::body(['code' => 'contact.created', 'data' => ['contact_id' => 5]])];
        yield 'state change without invoice id' => [self::body(['code' => 'issued_invoice.state_change', 'data' => ['state' => 'sent']])];
        yield 'zero invoice id' => [self::body(['code' => 'received_invoice.created', 'data' => ['invoice_id' => 0]])];
        yield 'string invoice id' => [self::body(['code' => 'received_invoice.created', 'data' => ['invoice_id' => 'abc']])];
        yield 'no data' => [self::body(['code' => 'received_invoice.created'])];
        yield 'data is a list' => [self::body(['code' => 'received_invoice.created', 'data' => [1, 2]])];
        yield 'no code' => [self::body(['data' => ['invoice_id' => 85373]])];
        yield 'empty code' => [self::body(['code' => '', 'data' => ['invoice_id' => 85373]])];
        yield 'JSON list' => ['[1,2]'];
        yield 'not JSON' => ['code=received_invoice.created'];
    }

    #[DataProvider('unexpectedBodies')]
    public function test_event_without_invoice_id_is_unexpected(string $body): void
    {
        $exception = self::capture(fn () => (new B2BrouterWebhook(self::SECRET))->parse($body, self::headers($body)));

        self::assertInstanceOf(UnexpectedResponse::class, $exception);
        self::assertStringContainsString('B2Brouter webhook notification', $exception->getMessage());
    }

    public function test_invoice_id_given_as_digits_is_accepted(): void
    {
        $body = self::body(['code' => 'received_invoice.created', 'data' => ['invoice_id' => '99120']]);

        self::assertSame('99120', (new B2BrouterWebhook(self::SECRET))->parse($body, self::headers($body))->documentId);
    }

    public function test_blank_secret_is_refused(): void
    {
        foreach (['', "  \t\n"] as $secret) {
            $exception = self::capture(fn () => new B2BrouterWebhook($secret));
            self::assertInstanceOf(InvalidArgumentException::class, $exception);
        }
        // A short secret is accepted: webhook secrets are issued by the provider, not chosen.
        $body = self::stateChangeBody();
        self::assertTrue((new B2BrouterWebhook('k'))->verify($body, ['X-B2Brouter-Signature' => self::signature($body, self::TIMESTAMP, 'k')]));
    }

    public function test_secret_is_hidden_from_dumps(): void
    {
        $webhook = new B2BrouterWebhook(self::SECRET);

        ob_start();
        var_dump($webhook);
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString(self::SECRET, $dump);
        self::assertStringNotContainsString(self::SECRET, print_r($webhook, true));
        self::assertStringNotContainsString(self::SECRET, var_export($webhook, true));
        self::assertStringNotContainsString(self::SECRET, (string) json_encode($webhook));
        self::assertStringNotContainsString(self::SECRET, self::capture(fn () => $webhook->parse('{}', []))->getMessage());
    }
}
