<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\Recommand;

use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Tests\Support\LiveTransport;
use IntrAgile\EInvoicing\Tests\Support\StreamHttpClient;
use IntrAgile\EInvoicing\Tests\Validation\Peppol\InstalledRules;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\InboundReference;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\XRechnungPeppol;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandConfig;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandTransport;
use IntrAgile\EInvoicing\Transport\ValidatingTransport;
use IntrAgile\EInvoicing\Validation\PeppolPayloadValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Sends, queries, lists and acknowledges documents through a real Recommand playground team.
 *
 * Needs RECOMMAND_API_KEY_ID, RECOMMAND_API_SECRET and RECOMMAND_COMPANY_ID of a playground team
 * and skips without them. Before anything is sent, GET /api/v1/playgrounds/current must report a
 * playground; a production team is refused, so the test can never send a real invoice.
 *
 * The sender is the company's first Peppol identifier (GET /api/v1/companies/{id}/identifiers),
 * or RECOMMAND_SENDER_ID when set. The receiver is the sender itself, or RECOMMAND_RECIPIENT_ID
 * when set. Each run gives the invoice a unique number, and only the inbox entry carrying that
 * number (or the sent document id) is fetched and acknowledged.
 *
 * test_test_network_send_receive needs a playground created with useTestNetwork, so the documents
 * travel over the Peppol test network. The receiving company is RECOMMAND_RECIPIENT_COMPANY_ID
 * when set (another company of the same team that owns RECOMMAND_RECIPIENT_ID), otherwise
 * RECOMMAND_COMPANY_ID; its inbox is polled and its document types must include the XRechnung
 * ones. RECOMMAND_SETUP_DOCTYPES=1 lets the test register missing XRechnung document types;
 * without it a missing type fails the test with the list of registered types.
 */
#[Group('recommand-live')]
final class RecommandLiveTest extends TestCase
{
    private const INBOX_ATTEMPTS = 10;

    private const INBOX_WAIT_SECONDS = 3;

    /** Status reads on the test network before giving up. */
    private const NETWORK_STATUS_ATTEMPTS = 10;

    private const NETWORK_STATUS_WAIT_SECONDS = 30;

    /** Inbox reads on the test network before giving up. */
    private const NETWORK_INBOX_ATTEMPTS = 20;

    private const NETWORK_INBOX_WAIT_SECONDS = 15;

    private const REFUSAL = 'The API key does not belong to a playground team; the live test refuses to send from a production team.';

    private const NO_TEST_NETWORK = 'The playground does not use the Peppol test network; create one with useTestNetwork: true.';

    private const TAG = 'recommand-live';

    private RecommandConfig $config;

    private RecommandConfig $receiverConfig;

    private HttpClient $http;

    private Redactor $redactor;

    /**
     * Null when GET /api/v1/playgrounds/current reports a playground team, otherwise why the live
     * test refuses to send. It makes only that one GET request.
     */
    public static function playgroundRefusal(HttpClient $http, RecommandConfig $config): ?string
    {
        $playground = self::playground($http, $config);

        return is_string($playground) ? $playground : null;
    }

    /**
     * Null when GET /api/v1/playgrounds/current reports a playground team on the Peppol test
     * network, otherwise why the test-network case refuses to send. It makes only that one GET
     * request.
     */
    public static function networkRefusal(HttpClient $http, RecommandConfig $config): ?string
    {
        $playground = self::playground($http, $config);
        if (is_string($playground)) {
            return $playground;
        }

        return ($playground['useTestNetwork'] ?? null) === true ? null : self::NO_TEST_NETWORK;
    }

    /**
     * The redactor for every value a Recommand live test keeps out of its output: the API key id,
     * the secret, the Basic pair and the sending and receiving company ids.
     */
    public static function redactorFor(RecommandConfig $config, RecommandConfig $receiverConfig): Redactor
    {
        return $config->redactor()->with([$config->companyId, $receiverConfig->companyId]);
    }

    /**
     * The playground object of GET /api/v1/playgrounds/current, or why the live test refuses.
     *
     * @return array<mixed>|string
     */
    private static function playground(HttpClient $http, RecommandConfig $config): array|string
    {
        $response = $http->send('GET', $config->baseUrl.'/api/v1/playgrounds/current', [
            'Authorization' => $config->authorization(),
            'Accept' => 'application/json',
        ]);
        if ($response->getStatusCode() !== 200) {
            return LiveTransport::excerpt($config->redactor(), 'GET /api/v1/playgrounds/current', $response);
        }

        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) && ($data['success'] ?? null) === true && is_array($data['playground'] ?? null)
            ? $data['playground']
            : self::REFUSAL;
    }

    protected function setUp(): void
    {
        $keyId = (string) getenv('RECOMMAND_API_KEY_ID');
        $secret = (string) getenv('RECOMMAND_API_SECRET');
        $companyId = (string) getenv('RECOMMAND_COMPANY_ID');
        if ($keyId === '' || $secret === '' || $companyId === '') {
            self::markTestSkipped('Set RECOMMAND_API_KEY_ID, RECOMMAND_API_SECRET and RECOMMAND_COMPANY_ID of a Recommand playground team.');
        }

        $baseUrl = (string) getenv('RECOMMAND_BASE_URL');
        $baseUrl = $baseUrl !== '' ? $baseUrl : RecommandConfig::DEFAULT_BASE_URL;
        $receiverCompanyId = (string) getenv('RECOMMAND_RECIPIENT_COMPANY_ID');
        $this->config = new RecommandConfig($keyId, $secret, $companyId, $baseUrl);
        $this->receiverConfig = new RecommandConfig($keyId, $secret, $receiverCompanyId !== '' ? $receiverCompanyId : $companyId, $baseUrl);
        // Company ids are kept out of output as well as the credentials.
        $this->redactor = self::redactorFor($this->config, $this->receiverConfig);
        $factory = new Psr17Factory;
        $this->http = (new HttpClient(new StreamHttpClient(30.0), $factory, $factory))
            ->withSecrets([$keyId, $secret, $this->config->companyId, $this->receiverConfig->companyId]);
    }

    public function test_playground_send_status_pending_and_acknowledge(): void
    {
        $refusal = self::playgroundRefusal($this->http, $this->config);
        if ($refusal !== null) {
            self::fail($refusal);
        }

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
     * @return iterable<string, array{string}>
     */
    public static function networkCases(): iterable
    {
        yield 'bis3-ubl' => ['bis3-ubl'];
        yield 'xrechnung-ubl' => ['xrechnung-ubl'];
        yield 'xrechnung-cii' => ['xrechnung-cii'];
    }

    #[DataProvider('networkCases')]
    public function test_test_network_send_receive(string $case): void
    {
        $refusal = self::networkRefusal($this->http, $this->config);
        if ($refusal !== null) {
            self::fail($refusal);
        }

        // Exception messages may carry a company id in a URL, so every failure is redacted.
        LiveTransport::failRedacted($this->redactor, fn () => $this->sendAndReceive($case));
    }

    /**
     * One test-network case after the guard: send, delivery, receipt, fetch and acknowledgement.
     */
    private function sendAndReceive(string $case): void
    {
        if ($case !== 'bis3-ubl') {
            $this->ensureXRechnungDocumentTypes();
        }

        $sender = $this->senderId();
        $recipientEnv = (string) getenv('RECOMMAND_RECIPIENT_ID');
        $recipient = $recipientEnv !== '' ? ParticipantId::parse($recipientEnv) : $sender;
        $invoiceNumber = LiveTransport::invoiceNumber();
        $xml = match ($case) {
            'bis3-ubl' => LiveTransport::bis3($invoiceNumber, $recipient, $sender),
            'xrechnung-ubl' => LiveTransport::xrechnung(Syntax::Ubl, $invoiceNumber, $recipient, $sender),
            default => LiveTransport::xrechnung(Syntax::Cii, $invoiceNumber, $recipient, $sender),
        };

        $outbound = new RecommandTransport($this->config, $this->http);
        $transport = new ValidatingTransport($outbound, PeppolPayloadValidator::create(InstalledRules::dir()));
        $result = $transport->send(OutboundDocument::fromXml($xml));
        self::assertNotSame('', $result->documentId);

        $status = LiveTransport::pollUntilFinal(
            static fn (): DeliveryStatus => $transport->status($result->documentId),
            self::NETWORK_STATUS_ATTEMPTS,
            self::NETWORK_STATUS_WAIT_SECONDS,
        );
        LiveTransport::report(self::TAG, sprintf(
            '%s state=%s messageId=%s',
            $case,
            $status->state->name,
            $result->peppolMessageId ?? 'none',
        ), $this->redactor);
        self::assertSame(DeliveryState::Delivered, $status->state, $this->redactor->redact((string) $status->failure?->message));

        $inbox = new RecommandTransport($this->receiverConfig, $this->http);
        $received = LiveTransport::awaitInbound($inbox, $result->documentId, $sender, $invoiceNumber, self::NETWORK_INBOX_ATTEMPTS, self::NETWORK_INBOX_WAIT_SECONDS);
        LiveTransport::report(self::TAG, sprintf('%s received=%s', $case, $received === null ? 'no' : 'yes'), $this->redactor);
        self::assertNotNull($received, sprintf(
            'Invoice %s did not reach the receiving inbox within %d seconds.',
            $invoiceNumber,
            self::NETWORK_INBOX_ATTEMPTS * self::NETWORK_INBOX_WAIT_SECONDS,
        ));

        $fetched = $inbox->fetch($received);
        self::assertSame($invoiceNumber, LiveTransport::invoiceNumberOf($fetched->xml));

        $inbox->acknowledge($received);
        LiveTransport::report(self::TAG, $case.' acknowledged=yes', $this->redactor);
    }

    /**
     * Checks that the receiving company accepts both XRechnung document types, registering the
     * missing ones when RECOMMAND_SETUP_DOCTYPES=1.
     */
    private function ensureXRechnungDocumentTypes(): void
    {
        $path = sprintf('/api/v1/companies/%s/document-types', rawurlencode($this->receiverConfig->companyId));
        $registered = [];
        $types = $this->getJson($path)['documentTypes'] ?? null;
        foreach (is_array($types) ? $types : [] as $type) {
            if (is_array($type) && is_string($type['docTypeId'] ?? null)) {
                $registered[] = self::bareDocumentType($type['docTypeId']);
            }
        }

        $missing = array_values(array_diff([XRechnungPeppol::UBL_INVOICE, XRechnungPeppol::CII_INVOICE], $registered));
        if ($missing === []) {
            return;
        }
        if (getenv('RECOMMAND_SETUP_DOCTYPES') !== '1') {
            self::fail(sprintf(
                "The receiving company lacks the XRechnung document types:\n  %s\nRegistered:\n  %s\nSet RECOMMAND_SETUP_DOCTYPES=1 to register them.",
                implode("\n  ", $missing),
                $registered === [] ? '(none)' : implode("\n  ", $registered),
            ));
        }

        foreach ($missing as $docTypeId) {
            $response = $this->http->send('POST', $this->config->baseUrl.$path, [
                'Authorization' => $this->config->authorization(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ], (string) json_encode(['docTypeId' => $docTypeId, 'processId' => XRechnungPeppol::PROCESS], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            self::assertSame(200, $response->getStatusCode(), LiveTransport::excerpt($this->redactor, 'POST '.$path, $response));
        }
        LiveTransport::report(self::TAG, 'registered document types: '.implode(', ', $missing), $this->redactor);
    }

    /**
     * The document type identifier without a leading "busdox-docid-qns::" scheme.
     */
    private static function bareDocumentType(string $id): string
    {
        $prefix = DocumentTypeId::SCHEME.'::';

        return str_starts_with($id, $prefix) ? substr($id, strlen($prefix)) : $id;
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
        self::assertSame(200, $response->getStatusCode(), LiveTransport::excerpt($this->redactor, 'GET '.$path, $response));

        $data = json_decode($body, true);
        self::assertIsArray($data, $this->redactor->redact(sprintf('GET %s did not answer JSON.', $path)));
        self::assertTrue(($data['success'] ?? null) === true, $this->redactor->redact(sprintf('GET %s did not report success.', $path)));

        return $data;
    }
}
