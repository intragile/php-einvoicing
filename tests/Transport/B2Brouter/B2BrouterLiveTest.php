<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter;

use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Tests\Support\StreamHttpClient;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterTransport;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\DocumentNumber;
use IntrAgile\EInvoicing\Transport\FailureCategory;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\InboundReference;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Sends, queries, lists, fetches and acknowledges invoices through a real B2Brouter sandbox.
 *
 * Needs B2BROUTER_API_KEY and B2BROUTER_ACCOUNT_ID of a sandbox account, optionally
 * B2BROUTER_BASE_URL, and skips without them. B2Brouter routes a request to its sandbox by the key
 * alone, so a key that does not start with "test_" is refused before any request is made; the test
 * can never send a real invoice.
 *
 * The sandbox simulates the Peppol network with pre-created test recipients: 0088:9508397101047
 * and 0088:9506215594996 end delivered (the second is refused afterwards, a business response),
 * 0088:9500047420799 fails with PEPPOL_NO_RECEIVER. Inbound documents are not simulated, so the
 * inbox is seeded by importing a document as a received invoice. Each run gives every invoice a
 * unique number. The seller is the one in the Peppol base example.
 *
 * Two findings are printed rather than asserted: the document type codes that mention XRechnung,
 * and whether the delivered ("legal") document of a sent invoice equals the XML that was sent.
 * Output never contains the API key or the account id.
 */
#[Group('b2brouter-live')]
final class B2BrouterLiveTest extends TestCase
{
    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    private const DELIVERED_RECIPIENT = '0088:9508397101047';

    private const REFUSED_RECIPIENT = '0088:9506215594996';

    private const UNKNOWN_RECIPIENT = '0088:9500047420799';

    private const STATUS_ATTEMPTS = 10;

    private const STATUS_WAIT_SECONDS = 6;

    private B2BrouterConfig $config;

    private HttpClient $http;

    private Redactor $redactor;

    /**
     * Why the configuration must not be used by the live test, or null when it is a sandbox key.
     * The message names neither the key nor the account.
     */
    public static function productionKeyRefusal(B2BrouterConfig $config): ?string
    {
        return $config->isSandboxKey()
            ? null
            : 'B2BROUTER_API_KEY does not start with "test_"; the live test runs only against the B2Brouter sandbox and refuses a production or staging key.';
    }

    protected function setUp(): void
    {
        $apiKey = (string) getenv('B2BROUTER_API_KEY');
        $accountId = (string) getenv('B2BROUTER_ACCOUNT_ID');
        if ($apiKey === '' || $accountId === '') {
            self::markTestSkipped('Set B2BROUTER_API_KEY and B2BROUTER_ACCOUNT_ID of a B2Brouter sandbox account.');
        }

        $baseUrl = (string) getenv('B2BROUTER_BASE_URL');
        $this->config = new B2BrouterConfig($apiKey, $accountId, $baseUrl !== '' ? $baseUrl : B2BrouterConfig::DEFAULT_BASE_URL);
        $refusal = self::productionKeyRefusal($this->config);
        if ($refusal !== null) {
            self::fail($refusal);
        }

        // The account id is kept out of output as well as the key.
        $secrets = [...$this->config->secrets(), (string) $this->config->accountId];
        $this->redactor = new Redactor($secrets);
        $factory = new Psr17Factory;
        $this->http = (new HttpClient(new StreamHttpClient(30.0), $factory, $factory))->withSecrets($secrets);
    }

    public function test_sandbox_recipients_reach_their_simulated_outcomes(): void
    {
        $transport = new B2BrouterTransport($this->config, $this->http);
        $xml = [];
        $ids = [];
        foreach ([self::DELIVERED_RECIPIENT, self::REFUSED_RECIPIENT, self::UNKNOWN_RECIPIENT] as $recipient) {
            $xml[$recipient] = self::baseExample(ParticipantId::parse($recipient), self::invoiceNumber());
            $result = $transport->send(OutboundDocument::fromXml($xml[$recipient]));
            self::assertNotSame('', $result->documentId);
            $ids[$recipient] = $result->documentId;
        }

        $delivered = $this->finalStatus($transport, $ids[self::DELIVERED_RECIPIENT]);
        self::assertSame(DeliveryState::Delivered, $delivered->state, (string) $delivered->failure?->message);

        $refused = $this->finalStatus($transport, $ids[self::REFUSED_RECIPIENT]);
        self::assertSame(DeliveryState::Delivered, $refused->state, 'a refusal is a business response to a delivered invoice');

        $unknown = $this->finalStatus($transport, $ids[self::UNKNOWN_RECIPIENT]);
        self::assertSame(DeliveryState::Failed, $unknown->state);
        self::assertSame(FailureCategory::RecipientNotFound, $unknown->failure?->category);
        self::assertSame('PEPPOL_NO_RECEIVER', $unknown->failure->providerCode);

        // Evidence for whether the delivered document can be compared with the sent one.
        $legal = $this->request('GET', sprintf('/invoices/%s/as/legal', rawurlencode($ids[self::DELIVERED_RECIPIENT])), ['Accept' => 'application/xml, */*;q=0.1']);
        self::report(sprintf(
            'GET /invoices/{id}/as/legal answered HTTP %d; the body is %s the sent XML.',
            $legal->getStatusCode(),
            (string) $legal->getBody() === $xml[self::DELIVERED_RECIPIENT] ? 'byte-identical to' : 'NOT byte-identical to',
        ));
    }

    public function test_seeded_inbox_pending_fetch_and_acknowledge(): void
    {
        $transport = new B2BrouterTransport($this->config, $this->http);
        $invoiceNumber = self::invoiceNumber();

        // The sandbox does not simulate inbound traffic; importing with issued=false creates a
        // received invoice in the account.
        $import = $this->request(
            'POST',
            sprintf('/accounts/%d/invoices/import?issued=false', $this->config->accountId),
            ['Content-Type' => 'application/octet-stream'],
            self::baseExample(ParticipantId::parse(self::DELIVERED_RECIPIENT), $invoiceNumber),
        );
        self::assertSame(201, $import->getStatusCode(), $this->excerpt('The import of the received invoice', $import));
        $imported = json_decode((string) $import->getBody(), true);
        $id = is_array($imported) && is_array($imported['invoice'] ?? null) ? ($imported['invoice']['id'] ?? null) : null;
        self::assertIsInt($id, $this->excerpt('The import answer has no invoice id', $import));
        $id = (string) $id;

        self::assertContains($id, self::ids($transport->pending()));

        $inbound = $transport->fetch($id);
        self::assertSame($invoiceNumber, DocumentNumber::of($inbound->xml));

        $transport->acknowledge($id);
        self::assertNotContains($id, self::ids($transport->pending()));
    }

    public function test_report_xrechnung_document_type_codes(): void
    {
        $response = $this->request('GET', '/document_types');
        self::assertSame(200, $response->getStatusCode(), $this->excerpt('GET /document_types', $response));
        $data = json_decode((string) $response->getBody(), true);
        $types = is_array($data) && is_array($data['document_types'] ?? null) ? $data['document_types'] : [];

        $codes = [];
        foreach ($types as $type) {
            $code = is_array($type) ? ($type['code'] ?? null) : null;
            if (is_string($code) && stripos($code, 'xrechnung') !== false) {
                $codes[] = $code;
            }
        }
        self::report('Document type codes containing "xrechnung": '.($codes === [] ? '(none)' : implode(', ', $codes)));
        $this->addToAssertionCount(1);
    }

    /**
     * The status of an invoice once it is delivered or failed, polled a bounded number of times.
     */
    private function finalStatus(B2BrouterTransport $transport, string $invoiceId): DeliveryStatus
    {
        $status = $transport->status($invoiceId);
        for ($attempt = 2; $attempt <= self::STATUS_ATTEMPTS && ! in_array($status->state, [DeliveryState::Delivered, DeliveryState::Failed], true); $attempt++) {
            sleep(self::STATUS_WAIT_SECONDS);
            $status = $transport->status($invoiceId);
        }

        return $status;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(string $method, string $path, array $headers = [], ?string $body = null): ResponseInterface
    {
        return $this->http->send($method, $this->config->baseUrl.$path, $headers + $this->config->headers(), $body);
    }

    /**
     * A failure message with the start of the response, without the key or the account id.
     */
    private function excerpt(string $what, ResponseInterface $response): string
    {
        return $this->redactor->redact(sprintf('%s answered HTTP %d: %s', $what, $response->getStatusCode(), substr((string) $response->getBody(), 0, 300)));
    }

    /**
     * @param  list<InboundReference>  $references
     * @return list<string>
     */
    private static function ids(array $references): array
    {
        return array_map(static fn (InboundReference $reference): string => $reference->documentId, $references);
    }

    private static function invoiceNumber(): string
    {
        return 'LIVE-'.bin2hex(random_bytes(6));
    }

    private static function report(string $line): void
    {
        fwrite(STDERR, PHP_EOL.'[b2brouter-live] '.$line.PHP_EOL);
    }

    /**
     * The Peppol base example with the invoice number and the buyer electronic address replaced.
     */
    private static function baseExample(ParticipantId $buyer, string $invoiceNumber): string
    {
        $examples = Fixtures::peppolExamples();
        self::assertArrayHasKey('examples/base-example.xml', $examples);

        $dom = new DOMDocument;
        $dom->loadXML($examples['examples/base-example.xml'], LIBXML_NONET);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', self::NS_CAC);
        $xpath->registerNamespace('cbc', self::NS_CBC);

        $ids = $xpath->query('/*/cbc:ID');
        $id = $ids === false ? null : $ids->item(0);
        self::assertInstanceOf(DOMElement::class, $id);
        $id->textContent = $invoiceNumber;

        $nodes = $xpath->query('/*/cac:AccountingCustomerParty/cac:Party/cbc:EndpointID');
        $endpoint = $nodes === false ? null : $nodes->item(0);
        self::assertInstanceOf(DOMElement::class, $endpoint);
        $endpoint->setAttribute('schemeID', $buyer->icd);
        $endpoint->textContent = $buyer->value;

        return (string) $dom->saveXML();
    }
}
