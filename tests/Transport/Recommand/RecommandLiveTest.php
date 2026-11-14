<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Recommand;

use IntrAgile\EInvoicing\Tests\Support\LiveTransport;
use IntrAgile\EInvoicing\Tests\Support\StreamHttpClient;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\InboundReference;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandConfig;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Sends, queries, lists and acknowledges a document through a real Recommand playground team.
 *
 * Needs RECOMMAND_API_KEY_ID, RECOMMAND_API_SECRET and RECOMMAND_COMPANY_ID of a playground team
 * and skips without them. Before anything is sent, GET /api/v1/playgrounds/current must report a
 * playground; a production team is refused, so the test can never send a real invoice.
 *
 * The sender is the company's first Peppol identifier (GET /api/v1/companies/{id}/identifiers),
 * or RECOMMAND_SENDER_ID when set. The receiver is the sender itself, or RECOMMAND_RECIPIENT_ID
 * when set (another playground company). Each run gives the invoice a unique number, and only the
 * inbox entry carrying that number (or the sent document id) is fetched and acknowledged.
 *
 * Whether a simulated playground delivers a document to its own inbox is not documented; with
 * useTestNetwork the document travels over the Peppol test network and arrives asynchronously.
 * When the document does not show up in the inbox within about 30 seconds the inbox part is
 * marked incomplete, unless RECOMMAND_EXPECT_INBOX=1 makes it a failure.
 */
#[Group('recommand-live')]
final class RecommandLiveTest extends TestCase
{
    private const INBOX_ATTEMPTS = 10;

    private const INBOX_WAIT_SECONDS = 3;

    private RecommandConfig $config;

    private HttpClient $http;

    protected function setUp(): void
    {
        $keyId = (string) getenv('RECOMMAND_API_KEY_ID');
        $secret = (string) getenv('RECOMMAND_API_SECRET');
        $companyId = (string) getenv('RECOMMAND_COMPANY_ID');
        if ($keyId === '' || $secret === '' || $companyId === '') {
            self::markTestSkipped('Set RECOMMAND_API_KEY_ID, RECOMMAND_API_SECRET and RECOMMAND_COMPANY_ID of a Recommand playground team.');
        }

        $baseUrl = (string) getenv('RECOMMAND_BASE_URL');
        $this->config = new RecommandConfig($keyId, $secret, $companyId, $baseUrl !== '' ? $baseUrl : RecommandConfig::DEFAULT_BASE_URL);
        $factory = new Psr17Factory;
        $this->http = new HttpClient(new StreamHttpClient(30.0), $factory, $factory);
    }

    public function test_playground_send_status_pending_and_acknowledge(): void
    {
        $playground = $this->getJson('/api/v1/playgrounds/current');
        self::assertTrue(
            is_array($playground['playground'] ?? null),
            'The API key does not belong to a playground team; the live test refuses to send from a production team.',
        );

        $sender = $this->senderId();
        $recipientEnv = (string) getenv('RECOMMAND_RECIPIENT_ID');
        $recipient = $recipientEnv !== '' ? ParticipantId::parse($recipientEnv) : $sender;

        $transport = new RecommandTransport($this->config, $this->http);
        $invoiceNumber = LiveTransport::invoiceNumber();
        $document = OutboundDocument::fromXml(LiveTransport::baseExample($invoiceNumber, $recipient, $sender));

        $result = $transport->send($document);
        self::assertNotSame('', $result->documentId);
        self::assertNotSame(DeliveryState::Failed, $result->state, (string) $result->failure?->message);

        $status = $transport->status($result->documentId);
        self::assertSame($result->documentId, $status->documentId);
        self::assertNotSame(DeliveryState::Failed, $status->state, (string) $status->failure?->message);

        $pending = $transport->pending();
        if (! $recipient->equals($sender)) {
            // The document went to another company's inbox; the call itself had to succeed.
            return;
        }

        $received = self::findSent($transport, $pending, $result->documentId, $sender, $invoiceNumber);
        for ($attempt = 2; $received === null && $attempt <= self::INBOX_ATTEMPTS; $attempt++) {
            sleep(self::INBOX_WAIT_SECONDS);
            $received = self::findSent($transport, $transport->pending(), $result->documentId, $sender, $invoiceNumber);
        }
        if ($received === null) {
            $message = sprintf('Invoice %s did not reach the company\'s own inbox within %d seconds.', $invoiceNumber, self::INBOX_ATTEMPTS * self::INBOX_WAIT_SECONDS);
            if (getenv('RECOMMAND_EXPECT_INBOX') === '1') {
                self::fail($message);
            }
            self::markTestIncomplete($message.' Send and status were verified; a simulated playground may not deliver to an inbox.');
        }

        $transport->acknowledge($received);
        // Marking as read is idempotent, so a second acknowledgement must succeed as well.
        $transport->acknowledge($received);

        $remaining = array_map(static fn (InboundReference $reference): string => $reference->documentId, $transport->pending());
        self::assertNotContains($received, $remaining);
    }

    /**
     * The id of the inbox entry that is the document sent by this run, or null.
     *
     * @param  list<InboundReference>  $pending
     */
    private static function findSent(RecommandTransport $transport, array $pending, string $sentId, ParticipantId $sender, string $invoiceNumber): ?string
    {
        foreach ($pending as $reference) {
            if ($reference->documentId === $sentId) {
                return $reference->documentId;
            }
            if ($reference->sender !== null && ! $reference->sender->equals($sender)) {
                continue;
            }
            $inbound = $transport->fetch($reference->documentId);
            if (str_contains($inbound->xml, '>'.$invoiceNumber.'<')) {
                return $reference->documentId;
            }
        }

        return null;
    }

    private function senderId(): ParticipantId
    {
        $override = (string) getenv('RECOMMAND_SENDER_ID');
        if ($override !== '') {
            return ParticipantId::parse($override);
        }

        $data = $this->getJson(sprintf('/api/v1/companies/%s/identifiers', rawurlencode($this->config->companyId)));
        $first = is_array($data['identifiers'] ?? null) ? ($data['identifiers'][0] ?? null) : null;
        if (! is_array($first) || ! is_string($first['scheme'] ?? null) || ! is_string($first['identifier'] ?? null)) {
            self::fail('The company has no Peppol identifier in the expected shape; set RECOMMAND_SENDER_ID to "scheme:value".');
        }

        return ParticipantId::parse($first['scheme'].':'.$first['identifier']);
    }

    /**
     * @return array<mixed>
     */
    private function getJson(string $path): array
    {
        $response = $this->http->send('GET', $this->config->baseUrl.$path, ['Authorization' => $this->config->authorization()]);
        $body = (string) $response->getBody();
        self::assertSame(200, $response->getStatusCode(), sprintf('GET %s answered HTTP %d: %s', $path, $response->getStatusCode(), substr($body, 0, 300)));

        $data = json_decode($body, true);
        self::assertIsArray($data, sprintf('GET %s did not answer JSON.', $path));
        self::assertTrue(($data['success'] ?? null) === true, sprintf('GET %s did not report success.', $path));

        return $data;
    }
}
