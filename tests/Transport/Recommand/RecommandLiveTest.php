<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Recommand;

use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\StreamHttpClient;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
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
 * or RECOMMAND_SENDER_ID when set. The receiver is the sender itself, so the document also lands
 * in the company's own inbox, or RECOMMAND_RECIPIENT_ID when set (another playground company).
 */
#[Group('recommand-live')]
final class RecommandLiveTest extends TestCase
{
    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

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
        $document = OutboundDocument::fromXml(self::baseExample($sender, $recipient));

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

        self::assertNotSame([], $pending, 'The document sent to the company itself did not reach its inbox.');
        $received = $pending[0];
        $inbound = $transport->fetch($received->documentId);
        self::assertStringContainsString('Invoice', $inbound->xml);

        $transport->acknowledge($received->documentId);
        // Marking as read is idempotent, so a second acknowledgement must succeed as well.
        $transport->acknowledge($received->documentId);

        $remaining = array_map(static fn ($reference): string => $reference->documentId, $transport->pending());
        self::assertNotContains($received->documentId, $remaining);
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

    /**
     * The Peppol base example with the seller and buyer electronic addresses replaced.
     */
    private static function baseExample(ParticipantId $seller, ParticipantId $buyer): string
    {
        $examples = Fixtures::peppolExamples();
        self::assertArrayHasKey('examples/base-example.xml', $examples);

        $dom = new DOMDocument;
        $dom->loadXML($examples['examples/base-example.xml'], LIBXML_NONET);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', self::NS_CAC);
        $xpath->registerNamespace('cbc', self::NS_CBC);

        foreach (['AccountingSupplierParty' => $seller, 'AccountingCustomerParty' => $buyer] as $party => $id) {
            $nodes = $xpath->query(sprintf('/*/cac:%s/cac:Party/cbc:EndpointID', $party));
            $endpoint = $nodes === false ? null : $nodes->item(0);
            self::assertInstanceOf(DOMElement::class, $endpoint);
            $endpoint->setAttribute('schemeID', $id->icd);
            $endpoint->textContent = $id->value;
        }

        return (string) $dom->saveXML();
    }
}
