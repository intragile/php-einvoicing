<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\EInvoiceBe;

use IntrAgile\EInvoicing\Tests\Support\LiveTransport;
use IntrAgile\EInvoicing\Tests\Support\RequestCounter;
use IntrAgile\EInvoicing\Tests\Support\StreamHttpClient;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\DocumentNumber;
use IntrAgile\EInvoicing\Transport\EInvoiceBe\EInvoiceBeConfig;
use IntrAgile\EInvoicing\Transport\EInvoiceBe\EInvoiceBeTransport;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\InboundDocument;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Sends, queries, lists, fetches and acknowledges documents through a real e-invoice.be sandbox
 * company.
 *
 * Needs EINVOICEBE_API_KEY of a sandbox company and skips without it. e-invoice.be uses one host
 * for test and production and no API field tells a sandbox company from a production one, so the
 * test also needs EINVOICEBE_SANDBOX_CONFIRMED=1, the operator's statement that the key belongs to
 * a sandbox company; it fails without it before any request is made. As a second safeguard, the
 * sender and the receiver of the sent invoice are both the company's own first Peppol id from
 * GET /api/me/, so even a production key would only address the company itself.
 *
 * A sandbox send is not transmitted over Peppol: e-invoice.be e-mails the UBL to the company's
 * contact address and then marks the document SENT. Inbound documents are only simulated with
 * "Simulate inbound" in the e-invoice.be app; set EINVOICEBE_EXPECT_INBOUND_NUMBER to the invoice
 * number (BT-1) of the simulated document, or the inbox case is marked incomplete.
 *
 * One finding is printed rather than asserted: the field names of GET /api/me/ and the company's
 * plan, to record whether a sandbox company can be recognised. Output never contains the API key
 * or the company's Peppol id.
 */
#[Group('einvoicebe-live')]
final class EInvoiceBeLiveTest extends TestCase
{
    private const CONFIRMATION_VARIABLE = 'EINVOICEBE_SANDBOX_CONFIRMED';

    /** Pending documents fetched, newest first, while looking for the simulated one. */
    private const INBOX_FETCH_LIMIT = 20;

    private EInvoiceBeConfig $config;

    private HttpClient $http;

    private Redactor $redactor;

    /** Counts the requests sent through $http. */
    private RequestCounter $counter;

    /** The company's own Peppol id, the sender and the receiver of every sent document. */
    private ParticipantId $company;

    /** @var array<mixed> */
    private array $me;

    /**
     * Why the live test must not run, or null when the operator confirmed that the key belongs to
     * a sandbox company by setting EINVOICEBE_SANDBOX_CONFIRMED to exactly "1".
     *
     * @param  string|false  $confirmation  the value of the variable, false when it is not set
     */
    public static function sandboxConfirmationRefusal(string|false $confirmation): ?string
    {
        return $confirmation === '1'
            ? null
            : 'Set EINVOICEBE_SANDBOX_CONFIRMED=1 to confirm that EINVOICEBE_API_KEY belongs to an e-invoice.be sandbox company. e-invoice.be uses one host for test and production and reports no sandbox flag, so the live test refuses to run without this confirmation.';
    }

    /**
     * The company's first Peppol id in a GET /api/me/ answer, or null when it lists none that is a
     * valid participant identifier.
     *
     * @param  array<mixed>  $me
     */
    public static function companyParticipant(array $me): ?ParticipantId
    {
        $ids = $me['peppol_ids'] ?? null;
        $first = is_array($ids) && array_is_list($ids) && $ids !== [] ? $ids[0] : null;
        if (! is_string($first)) {
            return null;
        }
        try {
            return ParticipantId::parse($first);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Why a document must not be sent by the live test, or null when its sender and its receiver
     * are both the company's own Peppol id. The message names no participant.
     */
    public static function selfAddressRefusal(ParticipantId $sender, ParticipantId $receiver, ParticipantId $company): ?string
    {
        return $sender->equals($company) && $receiver->equals($company)
            ? null
            : 'The live test sends only to the company itself: the sender and the receiver must both be the first Peppol id of GET /api/me/.';
    }

    /**
     * Every value the live test keeps out of its output: the API key and, once known, the
     * company's Peppol id.
     *
     * @return list<string>
     */
    public static function secretsFor(EInvoiceBeConfig $config, ?ParticipantId $company = null): array
    {
        return $company === null
            ? $config->secrets()
            : [...$config->secrets(), $company->value, $company->icd.':'.$company->value];
    }

    /**
     * The redactor for the values of secretsFor().
     */
    public static function redactorFor(EInvoiceBeConfig $config, ?ParticipantId $company = null): Redactor
    {
        return $config->redactor()->with(self::secretsFor($config, $company));
    }

    protected function setUp(): void
    {
        $apiKey = (string) getenv('EINVOICEBE_API_KEY');
        if ($apiKey === '') {
            self::markTestSkipped('Set EINVOICEBE_API_KEY of an e-invoice.be sandbox company and EINVOICEBE_SANDBOX_CONFIRMED=1.');
        }
        $refusal = self::sandboxConfirmationRefusal(getenv(self::CONFIRMATION_VARIABLE));
        if ($refusal !== null) {
            self::fail($refusal);
        }

        $this->config = new EInvoiceBeConfig($apiKey);
        $factory = new Psr17Factory;
        $this->counter = new RequestCounter(new StreamHttpClient(30.0));
        $this->http = (new HttpClient($this->counter, $factory, $factory))->withSecrets($this->config->secrets());
        $this->redactor = self::redactorFor($this->config);

        $response = $this->request('GET', '/api/me/');
        self::assertSame(200, $response->getStatusCode(), LiveTransport::excerpt($this->redactor, 'GET /api/me/', $response));
        $me = json_decode((string) $response->getBody(), true);
        self::assertIsArray($me, LiveTransport::excerpt($this->redactor, 'GET /api/me/ is not a JSON object', $response));
        $this->me = $me;

        $company = self::companyParticipant($me);
        if ($company === null) {
            self::fail('GET /api/me/ lists no valid Peppol id for the company; add one in the e-invoice.be app.');
        }
        $this->company = $company;

        // The company's Peppol id is kept out of output as well as the key.
        $secrets = self::secretsFor($this->config, $company);
        $this->redactor = self::redactorFor($this->config, $company);
        $this->http = $this->http->withSecrets($secrets);
    }

    public function test_send_to_self_is_delivered(): void
    {
        $transport = new EInvoiceBeTransport($this->config, $this->http);
        $document = OutboundDocument::fromXml(LiveTransport::baseExample(LiveTransport::invoiceNumber(), $this->company, $this->company));
        $refusal = self::selfAddressRefusal($document->sender, $document->receiver, $this->company);
        if ($refusal !== null) {
            self::fail($refusal);
        }

        $result = $transport->send($document);
        self::assertNotSame('', $result->documentId);

        $status = LiveTransport::pollUntilFinal(static fn (): DeliveryStatus => $transport->status($result->documentId));
        self::assertSame(DeliveryState::Delivered, $status->state, $this->redactor->redact((string) $status->failure?->message));
    }

    public function test_simulated_inbound_pending_fetch_and_acknowledge(): void
    {
        $expected = (string) getenv('EINVOICEBE_EXPECT_INBOUND_NUMBER');
        if ($expected === '') {
            self::markTestIncomplete('Seed the sandbox inbox with "Simulate inbound" in the e-invoice.be app and set EINVOICEBE_EXPECT_INBOUND_NUMBER to the invoice number (BT-1) of the simulated document.');
        }
        $transport = new EInvoiceBeTransport($this->config, $this->http);

        $references = $transport->pending();
        self::assertNotSame([], $references, 'The inbox lists no document within the look-back.');

        $found = null;
        foreach (array_slice($references, 0, self::INBOX_FETCH_LIMIT) as $reference) {
            $document = $transport->fetch($reference->documentId);
            if (DocumentNumber::of($document->xml) === $expected) {
                $found = $document;
                break;
            }
        }
        self::assertInstanceOf(InboundDocument::class, $found, sprintf(
            'None of the %d newest pending documents has the invoice number "%s".',
            min(count($references), self::INBOX_FETCH_LIMIT),
            $expected,
        ));

        $before = $this->counter->count();
        $transport->acknowledge($found->reference->documentId);
        self::assertSame($before, $this->counter->count(), 'acknowledge() makes no request');
    }

    public function test_report_company_fields(): void
    {
        $fields = array_map(strval(...), array_keys($this->me));
        sort($fields);
        $plan = $this->me['plan'] ?? null;

        LiveTransport::report('einvoicebe-live', 'GET /api/me/ fields: '.implode(', ', $fields), $this->redactor);
        LiveTransport::report('einvoicebe-live', 'plan: '.(is_string($plan) && preg_match('/\A[A-Za-z0-9_-]{1,40}\z/', $plan) === 1 ? $plan : '(not a short plain string)'), $this->redactor);
        $this->addToAssertionCount(1);
    }

    private function request(string $method, string $path): ResponseInterface
    {
        return $this->http->send($method, $this->config->baseUrl.$path, $this->config->headers());
    }
}
