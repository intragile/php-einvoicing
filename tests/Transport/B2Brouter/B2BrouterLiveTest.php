<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Transport\B2Brouter;

use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PostalAddress;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use IntrAgile\EInvoicing\Tests\Support\LiveTransport;
use IntrAgile\EInvoicing\Tests\Support\ReleaseInvoices;
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
use Throwable;

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
 * unique number. The parties are the Peppol base example's, except that the sandbox account's
 * company replaces the seller (sent invoices) or the buyer (seeded inbox), because B2Brouter
 * requires its account company to be a party of every invoice.
 *
 * Two findings are printed rather than asserted: the document type codes that mention XRechnung,
 * and whether the delivered ("legal") document of a sent invoice equals the XML that was sent.
 * Output never contains the API key, the account id or the account's tax identifier.
 */
#[Group('b2brouter-live')]
final class B2BrouterLiveTest extends TestCase
{
    private const DELIVERED_RECIPIENT = '0088:9508397101047';

    private const REFUSED_RECIPIENT = '0088:9506215594996';

    private const UNKNOWN_RECIPIENT = '0088:9500047420799';

    /** B2Brouter validates the buyer's identifiers; this is a synthetic, checksum-valid Swedish VAT number. */
    private const BUYER_VAT = 'SE556000000101';

    private B2BrouterConfig $config;

    private HttpClient $http;

    private Redactor $redactor;

    /** Tax identifier of the sandbox account's company, with its country prefix. */
    private string $companyTin;

    /** Lowercase ISO 3166-1 alpha-2 country of the sandbox account's company. */
    private string $companyCountry;

    /** The account company as a Peppol participant, or null when the account has no TIN scheme. */
    private ?ParticipantId $companyParticipant = null;

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

    /**
     * Every value the live test keeps out of its output: the API key, the account id and, once it
     * is known, the account company's tax identifier.
     *
     * @return list<string>
     */
    public static function secretsFor(B2BrouterConfig $config, ?string $companyTin = null): array
    {
        $secrets = [...$config->secrets(), (string) $config->accountId];
        if ($companyTin !== null && $companyTin !== '') {
            $secrets[] = $companyTin;
        }

        return $secrets;
    }

    /**
     * The redactor for the values of secretsFor().
     */
    public static function redactorFor(B2BrouterConfig $config, ?string $companyTin = null): Redactor
    {
        return new Redactor(self::secretsFor($config, $companyTin));
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
        $factory = new Psr17Factory;
        $this->redactor = self::redactorFor($this->config);
        $this->http = (new HttpClient(new StreamHttpClient(30.0), $factory, $factory))->withSecrets(self::secretsFor($this->config));

        // B2Brouter requires the account's own company to be a party of every invoice.
        $response = $this->request('GET', sprintf('/accounts/%d', $this->config->accountId));
        $data = json_decode((string) $response->getBody(), true);
        $account = is_array($data) && is_array($data['account'] ?? null) ? $data['account'] : [];
        $tin = $account['tin_value'] ?? null;
        $country = $account['country'] ?? null;
        $known = is_string($tin) && $tin !== '' ? $tin : null;

        // The tax identifier is kept out of output as well as the key and the account id.
        if ($known !== null) {
            $this->redactor = self::redactorFor($this->config, $known);
            $this->http = $this->http->withSecrets(self::secretsFor($this->config, $known));
        }
        self::assertSame(200, $response->getStatusCode(), LiveTransport::excerpt($this->redactor, 'GET /accounts/{account}', $response));
        $missing = array_keys(array_filter(
            ['tin_value' => $known, 'country' => is_string($country) && $country !== '' ? $country : null],
            static fn (?string $value): bool => $value === null,
        ));
        if ($missing !== [] || $known === null || ! is_string($country)) {
            self::fail('GET /accounts/{account} lacks '.implode(' and ', $missing).'.');
        }
        $this->companyTin = $known;
        $this->companyCountry = $country;

        $scheme = $account['tin_scheme'] ?? null;
        $this->companyParticipant = null;
        if (is_string($scheme) && $scheme !== '') {
            try {
                $this->companyParticipant = ParticipantId::parse($scheme.':'.$known);
            } catch (Throwable $exception) {
                self::fail($this->redactor->redact('The account tin_scheme and tin_value are not a Peppol participant id: '.$exception->getMessage()));
            }
        }
    }

    public function test_sandbox_recipients_reach_their_simulated_outcomes(): void
    {
        $transport = new B2BrouterTransport($this->config, $this->http);
        $xml = [];
        $ids = [];
        foreach ([self::DELIVERED_RECIPIENT, self::REFUSED_RECIPIENT, self::UNKNOWN_RECIPIENT] as $recipient) {
            $xml[$recipient] = LiveTransport::withPartyCompany(
                LiveTransport::withPartyCompany(
                    LiveTransport::baseExample(LiveTransport::invoiceNumber(), ParticipantId::parse($recipient), $this->companyParticipant),
                    'AccountingSupplierParty',
                    $this->companyTin,
                    $this->companyCountry,
                ),
                'AccountingCustomerParty',
                self::BUYER_VAT,
                'se',
            );
            $result = $transport->send(OutboundDocument::fromXml($xml[$recipient]));
            self::assertNotSame('', $result->documentId);
            $ids[$recipient] = $result->documentId;
        }

        $delivered = LiveTransport::pollUntilFinal(static fn (): DeliveryStatus => $transport->status($ids[self::DELIVERED_RECIPIENT]));
        self::assertSame(DeliveryState::Delivered, $delivered->state, (string) $delivered->failure?->message);

        $refused = LiveTransport::pollUntilFinal(static fn (): DeliveryStatus => $transport->status($ids[self::REFUSED_RECIPIENT]));
        self::assertSame(DeliveryState::Delivered, $refused->state, 'a refusal is a business response to a delivered invoice');

        $unknown = LiveTransport::pollUntilFinal(static fn (): DeliveryStatus => $transport->status($ids[self::UNKNOWN_RECIPIENT]));
        self::assertSame(DeliveryState::Failed, $unknown->state);
        self::assertSame(FailureCategory::RecipientNotFound, $unknown->failure?->category);
        // The documented code is PEPPOL_NO_RECEIVER; the sandbox may report the reason in the message only.
        self::assertContains($unknown->failure->providerCode, ['PEPPOL_NO_RECEIVER', null]);
        if ($unknown->failure->providerCode === null) {
            self::assertStringContainsString('not registered to receive', (string) $unknown->failure->message);
        }

        // Evidence for whether the delivered document can be compared with the sent one.
        $legal = $this->request('GET', sprintf('/invoices/%s/as/legal', rawurlencode($ids[self::DELIVERED_RECIPIENT])), ['Accept' => 'application/xml, */*;q=0.1']);
        LiveTransport::report('b2brouter-live', sprintf(
            'GET /invoices/{id}/as/legal answered HTTP %d; the body is %s the sent XML.',
            $legal->getStatusCode(),
            (string) $legal->getBody() === $xml[self::DELIVERED_RECIPIENT] ? 'byte-identical to' : 'NOT byte-identical to',
        ), $this->redactor);
    }

    public function test_seeded_inbox_pending_fetch_and_acknowledge(): void
    {
        $transport = new B2BrouterTransport($this->config, $this->http);
        $invoiceNumber = LiveTransport::invoiceNumber();

        // The sandbox does not simulate inbound traffic; importing with issued=false creates a
        // received invoice in the account.
        $import = $this->request(
            'POST',
            sprintf('/accounts/%d/invoices/import?issued=false', $this->config->accountId),
            ['Content-Type' => 'application/octet-stream'],
            LiveTransport::withPartyCompany(
                LiveTransport::baseExample($invoiceNumber, $this->companyParticipant ?? ParticipantId::parse(self::DELIVERED_RECIPIENT)),
                'AccountingCustomerParty',
                $this->companyTin,
                $this->companyCountry,
            ),
        );
        self::assertSame(201, $import->getStatusCode(), LiveTransport::excerpt($this->redactor, 'The import of the received invoice', $import));
        $imported = json_decode((string) $import->getBody(), true);
        $id = is_array($imported) && is_array($imported['invoice'] ?? null) ? ($imported['invoice']['id'] ?? null) : null;
        self::assertIsInt($id, LiveTransport::excerpt($this->redactor, 'The import answer has no invoice id', $import));
        $id = (string) $id;

        self::assertContains($id, self::ids($transport->pending()));

        $inbound = $transport->fetch($id);
        self::assertSame($invoiceNumber, DocumentNumber::of($inbound->xml));

        $transport->acknowledge($id);
        self::assertNotContains($id, self::ids($transport->pending()));
    }

    /**
     * Every XRechnung 3.0 document type (UBL and CII invoice, both Extensions, UBL credit note)
     * goes out with its default document type code, without any configured map, and reaches the
     * delivered state.
     */
    public function test_xrechnung_sent_with_default_document_type_codes(): void
    {
        if ($this->companyParticipant === null) {
            self::markTestSkipped('The sandbox account company has no TIN scheme, so it cannot be the Peppol sender.');
        }
        $transport = new B2BrouterTransport($this->config, $this->http);
        $recipient = ParticipantId::parse(self::DELIVERED_RECIPIENT);

        $documents = [
            'UBL' => (new UblWriter)->write($this->fromAccountCompany(ReleaseInvoices::germanDomestic(LiveTransport::invoiceNumber()), $recipient)),
            'CII' => (new CiiWriter)->write($this->fromAccountCompany(ReleaseInvoices::germanDomestic(LiveTransport::invoiceNumber()), $recipient)),
            'UBL Extension' => (new UblWriter)->write($this->fromAccountCompany(
                ReleaseInvoices::germanDomestic(LiveTransport::invoiceNumber())->withProcessControl(XRechnung::processControl(XRechnung::EXTENSION)),
                $recipient,
            )),
            'CII Extension' => (new CiiWriter)->write($this->fromAccountCompany(
                ReleaseInvoices::germanDomestic(LiveTransport::invoiceNumber())->withProcessControl(XRechnung::processControl(XRechnung::EXTENSION)),
                $recipient,
            )),
            'UBL credit note' => (new UblWriter)->write($this->fromAccountCompany(ReleaseInvoices::germanCreditNote(LiveTransport::invoiceNumber()), $recipient)),
        ];
        $ids = [];
        foreach ($documents as $label => $xml) {
            $result = $transport->send(OutboundDocument::fromXml($xml));
            self::assertNotSame('', $result->documentId, $label);
            $ids[$label] = $result->documentId;
        }
        foreach ($ids as $label => $id) {
            $status = LiveTransport::pollUntilFinal(static fn (): DeliveryStatus => $transport->status($id));
            self::assertSame(DeliveryState::Delivered, $status->state, $label.': '.$this->redactor->redact((string) $status->failure?->message));
            LiveTransport::report('b2brouter-live', 'XRechnung '.$label.' sent with the default document type code: delivered.', $this->redactor);
        }
    }

    public function test_report_xrechnung_document_type_codes(): void
    {
        $response = $this->request('GET', '/document_types');
        self::assertSame(200, $response->getStatusCode(), LiveTransport::excerpt($this->redactor, 'GET /document_types', $response));
        $data = json_decode((string) $response->getBody(), true);
        $types = is_array($data) && is_array($data['document_types'] ?? null) ? $data['document_types'] : [];

        $codes = [];
        foreach ($types as $type) {
            $code = is_array($type) ? ($type['code'] ?? null) : null;
            if (is_string($code) && stripos($code, 'xrechnung') !== false) {
                $codes[] = $code;
            }
        }
        LiveTransport::report('b2brouter-live', 'Document type codes containing "xrechnung": '.($codes === [] ? '(none)' : implode(', ', $codes)), $this->redactor);
        $this->addToAssertionCount(1);
    }

    /**
     * The invoice with the sandbox account company as the seller, reached at its own Peppol
     * participant, and a buyer with a checksum-valid VAT number reached at the given recipient.
     * B2Brouter requires its account company to be a party of every invoice it sends.
     */
    private function fromAccountCompany(Invoice $invoice, ParticipantId $recipient): Invoice
    {
        $participant = $this->companyParticipant;
        self::assertNotNull($participant);

        return $invoice
            ->withSeller(self::replaced(
                $invoice->seller,
                $this->companyTin,
                CountryCode::from(strtoupper($this->companyCountry)),
                $participant,
            ))
            ->withBuyer(self::replaced($invoice->buyer, self::BUYER_VAT, CountryCode::SE, $recipient));
    }

    private static function replaced(Party $party, string $vatId, CountryCode $country, ParticipantId $participant): Party
    {
        $scheme = ElectronicAddressScheme::tryFrom($participant->icd);
        self::assertNotNull($scheme, sprintf('The participant scheme %s is not an electronic address scheme.', $participant->icd));
        $address = $party->address;

        return new Party(
            name: $party->name,
            tradingName: $party->tradingName,
            identifiers: [],
            legalRegistration: null,
            vatIdentifier: $vatId,
            taxRegistration: null,
            additionalLegalInformation: $party->additionalLegalInformation,
            electronicAddress: new ElectronicAddress($participant->value, $scheme),
            address: $address === null ? new PostalAddress($country) : new PostalAddress(
                $country,
                $address->line1,
                $address->line2,
                $address->line3,
                $address->city,
                $address->postCode,
                $address->subdivision,
            ),
            contact: $party->contact,
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(string $method, string $path, array $headers = [], ?string $body = null): ResponseInterface
    {
        return $this->http->send($method, $this->config->baseUrl.$path, $headers + $this->config->headers(), $body);
    }

    /**
     * @param  list<InboundReference>  $references
     * @return list<string>
     */
    private static function ids(array $references): array
    {
        return array_map(static fn (InboundReference $reference): string => $reference->documentId, $references);
    }
}
