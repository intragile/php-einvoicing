# Peppol transport

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

This page explains how to send and receive invoices over Peppol with this package: sending through
an access point (Recommand, B2Brouter and e-invoice.be are the available drivers; Storecove is
planned for 1.0.0), looking up participants in the SML and their SMP, searching the Peppol
Directory, receiving documents by polling or webhooks, the errors and what to do about each, the
SBDH envelope, and what is verified and what is not. The invoice format and its validation are
described in [PEPPOL.md](PEPPOL.md).

> **No driver is live-tested yet.** The Recommand, B2Brouter and e-invoice.be drivers are tested
> offline against fixtures written by hand from each provider's published API description; see
> [What is verified](#what-is-verified).

## Quick start

```php
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandConfig;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandTransport;
use IntrAgile\EInvoicing\Transport\ValidatingTransport;
use IntrAgile\EInvoicing\Validation\PeppolPayloadValidator;

$config = new RecommandConfig($apiKeyId, $secret, $companyId);
$recommand = new RecommandTransport($config, $http);  // $http: see "HTTP client" below

$transport = new ValidatingTransport(
    $recommand,
    PeppolPayloadValidator::create(__DIR__.'/var/peppol-rules'),   // installed with einvoicing-rules
);

$result = $transport->send(OutboundDocument::fromXml($ublXml));

$result->documentId;   // the access point's id; keep it for status() and de-duplication
$result->state;        // DeliveryState::Pending, Delivered, Failed or Unknown
```

`OutboundDocument::fromXml()` reads the routing metadata from the invoice itself: the sender
from the seller's electronic address (BT-34; UBL `cbc:EndpointID`, CII
`ram:SellerTradeParty/ram:URIUniversalCommunication/ram:URIID`), the receiver from the buyer's
(BT-49), the process from the business process type (BT-23; UBL `cbc:ProfileID`, CII
`ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID`; Peppol billing when absent) and
the sender's country (C1) from the seller's country (BT-40).
Later, `$transport->status($result->documentId)` returns a `DeliveryStatus` with the current
state and, for a failed delivery, a `DeliveryFailure` with a `FailureCategory`.

`OutboundDocument::fromInvoice($invoice)` writes a model invoice as UBL first and reads the
routing metadata from that XML; nothing goes over the network:

<!-- sample: run -->
```php
<?php

declare(strict_types=1);

use IntrAgile\EInvoicing\Calculation\TotalsCalculator;
use IntrAgile\EInvoicing\CodeList\{CountryCode, CurrencyCode, ElectronicAddressScheme, InvoiceTypeCode, UnitCode, VatCategory};
use IntrAgile\EInvoicing\Model\{ElectronicAddress, Invoice, InvoiceLine, Item, LineVat, Party, PostalAddress, Price, ProcessControl};
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;

$invoice = TotalsCalculator::complete(new Invoice(
    number: 'INV-2026-0042',
    issueDate: new DateTimeImmutable('2026-11-14'),
    type: InvoiceTypeCode::C_380,
    currency: CurrencyCode::EUR,
    seller: new Party(
        'Example Seller BV',
        vatIdentifier: 'BE0123456749',
        electronicAddress: new ElectronicAddress('0123456749', ElectronicAddressScheme::C_0208),   // BT-34: the sender
        address: new PostalAddress(CountryCode::BE, city: 'Brussels', postCode: '1000'),
    ),
    buyer: new Party(
        'Example Buyer NV',
        electronicAddress: new ElectronicAddress('0987654394', ElectronicAddressScheme::C_0208),   // BT-49: the receiver
        address: new PostalAddress(CountryCode::BE, city: 'Antwerp', postCode: '2000'),
    ),
    lines: [
        new InvoiceLine('1', '3', UnitCode::C62, new Price('100.00'), new Item('Widget'), new LineVat(VatCategory::StandardRate, '21')),
    ],
    buyerReference: 'PO-1234',
    processControl: new ProcessControl(PeppolBis3::CUSTOMIZATION, PeppolBis3::PROCESS),   // BT-24, BT-23
));

$document = OutboundDocument::fromInvoice($invoice);

$document->sender->toString();     // "0208:0123456749"
$document->receiver->toString();   // "0208:0987654394"
$document->documentType->value;    // PeppolBis3::INVOICE
$document->countryC1;              // "BE"
```

`fromInvoice()` needs the BIS specification identifier (BT-24) or an XRechnung one on the
invoice, as in [What can be sent](#what-can-be-sent); an invoice without process control is
written with `urn:cen.eu:en16931:2017` and refused. `fromInvoice($invoice, syntax: Syntax::Cii)`
writes CII, which is allowed only for an invoice with `XRechnung::processControl()`.

### What can be sent

The Peppol document type is chosen by the exact pair of root element and specification
identifier (BT-24, UBL `cbc:CustomizationID`, CII
`ram:GuidelineSpecifiedDocumentContextParameter/ram:ID`), compared as written, without trimming:

| Document | BT-24 | Peppol document type |
|---|---|---|
| UBL Invoice, UBL CreditNote | `urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0` | `PeppolBis3::INVOICE`, `PeppolBis3::CREDIT_NOTE` |
| UBL Invoice, UBL CreditNote, CII CrossIndustryInvoice | `XRechnung::CIUS` (`…#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0`) | `XRechnungPeppol::UBL_INVOICE`, `UBL_CREDIT_NOTE`, `CII_INVOICE` |
| UBL Invoice, CII CrossIndustryInvoice | `XRechnung::EXTENSION` | `XRechnungPeppol::UBL_INVOICE_EXTENSION`, `CII_INVOICE_EXTENSION` |

Everything else is refused with `UnsupportedDocument`, and the message lists the sendable
identifiers. That includes a plain EN 16931 invoice (`urn:cen.eu:en16931:2017`, which the writers
use for an invoice without process control), older XRechnung versions, the XRechnung CVD (it has
no Peppol document type), the XRechnung UBL Credit Note Extension (deprecated in Peppol Code
Lists v9.7), a CII document with the BIS identifier and an identifier with surrounding
whitespace. An XRechnung document must use the process
`urn:fdc:peppol.eu:2017:poacc:billing:01:1.0` (BT-23), the only one its document types are
registered for; another process is refused and named in the message. See
[What can be sent](PEPPOL.md#what-can-be-sent) for the background.

**The receiver must be registered for the document type.** An XRechnung document travels under
its own Peppol document type, not the BIS one. A receiver whose SMP lists only BIS Billing 3.0
cannot receive it: the access point then reports `recipient_not_found` or a similar error. Check
the receiver's SMP entry with `ParticipantLookup` (see
[Participant lookup](#participant-lookup-sml-and-smp)) before you send.

**Always wrap the driver in `ValidatingTransport` with `PeppolPayloadValidator`.** It validates
every document before it leaves with the rules of the specification it declares: a BIS document
with the Peppol rules (`Validator::peppol()`), an XRechnung document with the KoSIT XRechnung
rules (`XRechnungValidator`), and throws `InvalidDocument` (with the full report) instead of
sending. Validating XRechnung needs `composer require intragile/einvoicing-rules-xrechnung`;
without it an XRechnung document throws `RulesNotInstalled`, which names that command, and BIS
documents are still validated. `ValidatingTransport` takes any `DocumentValidator`, so
`Validator::peppol()` alone still works when you send BIS only.

`OutboundDocument::fromXml()` also refuses, with `UnsupportedDocument`:

- **Self-billing invoices and credit notes** (a specification identifier containing
  `selfbilling`). Self-billing swaps the envelope roles and uses its own document type, which
  this version does not support.
- An invoice without a seller or buyer electronic address, and an electronic address whose
  `schemeID` has surrounding whitespace (the official envelope rule compares it as written, so
  the envelope would fail).

A DOCTYPE throws `UnsafeXml`; a malformed identifier or country throws `InvalidArgumentException`.

## HTTP client

The package depends only on the PSR interfaces (PSR-7, PSR-17, PSR-18). Pass any PSR-18 client
and PSR-17 factories to `HttpClient`; it reads response bodies up to a size cap (20 MB by
default) and turns client failures into the transport exceptions below.

Guzzle (`composer require guzzlehttp/guzzle`):

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use IntrAgile\EInvoicing\Transport\Http\HttpClient;

$factory = new HttpFactory;
$http = new HttpClient(
    new Client(['connect_timeout' => 10, 'timeout' => 60, 'allow_redirects' => false, 'http_errors' => false]),
    $factory,
    $factory,
);
```

Symfony (`composer require symfony/http-client nyholm/psr7`):

```php
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;
use Symfony\Component\HttpClient\Psr18Client;

$client = new Psr18Client(SymfonyHttpClient::create(['timeout' => 60, 'max_redirects' => 0]));
$http = new HttpClient($client, $client, $client);
```

These two snippets show the intended wiring; the test suite runs with its own PSR-18 test
clients, not with Guzzle or Symfony.

- **Set timeouts.** A request without a timeout can hang the worker indefinitely.
- **Configure your PSR-18 client not to follow redirects.** `SmpClient` handles the SMP's own
  `Redirect` element (one hop) itself; an HTTP 3xx followed silently by the client would bypass
  the URL checks the lookup applies (https only, no user info, query string or fragment), and a
  redirected send could reach a host you did not configure.
- **Redaction.** Exception messages and body excerpts of this package have whole occurrences of
  the credentials replaced by `[redacted]`: the `Authorization` header value, the URL's user info
  and query string, and every secret passed as the fifth constructor argument
  (`new HttpClient($client, $factory, $factory, $maxBytes, [$apiKey])`), each raw, URL-encoded
  and Base64-encoded. A body is redacted before its 500-byte excerpt is cut, so a secret across
  the cut is replaced whole. Secrets shorter than eight characters are ignored. Text that echoes
  only part of a secret, or encodes it some other way, is not recognised. The built-in drivers
  add their own credentials by themselves (`RecommandTransport` its API key id and secret,
  `B2BrouterTransport` and `EInvoiceBeTransport` their API key).
- A PSR-18 client's own exception, chained as the previous exception, is passed through
  unchanged, so check what your client puts in its messages before logging the whole chain.
- `send()` takes an optional `$maxBytes` that lowers the size cap for one response; a body over
  the cap is an `UnexpectedResponse` whatever its status, and it is not retryable, because the
  same request would get the same body again.

## Errors

Every transport error extends `TransportException`, whose `isRetryable()` says whether repeating
the same call later may succeed **and cannot cause a duplicate**.

| Exception | `isRetryable()` | When | What to do |
|---|---|---|---|
| `InvalidDocument` | false | `ValidatingTransport` found a fatal finding or a dynamic error; nothing was sent | Fix the invoice; `$e->report` is the `ValidationReport` |
| `DocumentRejected` | false | The access point refused the document (Recommand HTTP 400 on send; B2Brouter 400, 406, 409 or 422; e-invoice.be 415 or 422 on upload, 400, 406, 409 or 422 on send) | Fix the document; `$e->errors` holds the provider's details |
| `RecipientUnreachable` | false | The receiver is not registered or does not accept this document type (Recommand HTTP 422) | `$e->category` is a `FailureCategory`; use another channel or ask the receiver |
| `AuthenticationFailed` | false | Wrong credentials or missing permission (HTTP 401/403); a webhook with a bad signature | Fix the configuration; for a webhook, refuse the request |
| `RateLimited` | true | Too many requests (HTTP 429) | Wait `$e->retryAfterSeconds` (when given), then retry |
| `TransportUnavailable` | true | A GET got no answer (or its body stalled), the provider answered 503, a GET answered another 5xx (the Peppol Directory: 500, 502, 503 or 504; any other 5xx from it is `UnexpectedResponse`) | Retry later with backoff |
| `OutcomeUnknown` | false | A send (or another state-changing request) got no answer or a body that stalled; a send answered any 5xx except 503; a send answered 200 with a body that is not JSON, does not report success or has no document id | **Do not resend blindly**; see below |
| `UnexpectedResponse` | false | An unexpected status, or a malformed or oversized body. On a send, a malformed 200 is `OutcomeUnknown`, but a body over the size cap is this exception whatever the status | Log `$e->status` and `$e->bodyExcerpt`; investigate. On a send over the size cap, also handle it like `OutcomeUnknown` |

`fetch()` can also throw `UnsafeXml` and `UnsupportedDocument` (the library's own exceptions)
for a received document with a DOCTYPE, broken XML or an incomplete or inconsistent SBDH
envelope; `InvalidArgumentException` signals an empty or whitespace-only document id or a
malformed value.

### OutcomeUnknown

PSR-18 cannot tell a request that never left from one that was sent and not answered: both
arrive as the same client exception. For a send, the access point may therefore have accepted
the invoice. Resending at once risks delivering it twice. Instead:

1. Mark the invoice as "outcome unknown" in your system, not as failed.
2. Check the access point's document list for a document to the same receiver with the same
   invoice number. The exception message says where to look:
   - Recommand: the documents of the company in the dashboard or the API;
   - B2Brouter: the invoices of the account, by invoice number. Its API documentation says an
     invoice number that already exists in the account is refused (HTTP 422 `parameter_taken`,
     reported as `DocumentRejected` with the category `duplicate`), so a resend of an imported
     invoice should fail rather than deliver it twice;
   - e-invoice.be: when the upload got no answer, the drafts, by invoice number (send an existing
     draft rather than uploading again); when the upload succeeded and the send call got no
     answer, the message names the draft id: read its state and send it again only when it is
     `DRAFT` or `FAILED`.
3. Resend only when it is not there.

`send()` treats these answers the same way: every 5xx status except 503 (a server error may come
after the document was stored, and a proxy error such as 502 or 524 usually means the request
reached the application before the connection broke), and a 200 whose body is not JSON, does not
report success or has no document id (the original `UnexpectedResponse` is chained as
`getPrevious()`). Only 503 and 429 on `send()` are retryable. A response to `send()` over the size
cap is an `UnexpectedResponse` whatever its status; it is not retryable either, and the document
may have been accepted, so handle it like `OutcomeUnknown`. `acknowledge()` also throws
`OutcomeUnknown` when it gets no answer, but marking as read is idempotent, so calling it again
with the same id is safe.

## Participant lookup (SML and SMP)

```php
use IntrAgile\EInvoicing\Transport\Http\HttpClient;
use IntrAgile\EInvoicing\Transport\Lookup\DnsGetRecordResolver;
use IntrAgile\EInvoicing\Transport\Lookup\LookupStatus;
use IntrAgile\EInvoicing\Transport\Lookup\ParticipantLookup;
use IntrAgile\EInvoicing\Transport\Lookup\SmlLocator;
use IntrAgile\EInvoicing\Transport\Lookup\SmlZone;
use IntrAgile\EInvoicing\Transport\Lookup\SmpClient;
use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use IntrAgile\EInvoicing\Transport\Peppol\PeppolBis3;
use IntrAgile\EInvoicing\Transport\Peppol\ProcessId;

$lookup = new ParticipantLookup(
    new SmlLocator(new DnsGetRecordResolver, SmlZone::Production),  // SmlZone::Test for the test network
    new SmpClient(new HttpClient($psr18, $factory, $factory)),  // the SMP cap (2 MiB) applies per request
);

$result = $lookup->lookup(ParticipantId::parse('0208:0123456789'), new DocumentTypeId(PeppolBis3::INVOICE));

match ($result->status) {
    LookupStatus::Registered => $result->supports(new DocumentTypeId(PeppolBis3::INVOICE), new ProcessId(PeppolBis3::PROCESS)),
    LookupStatus::NotRegistered => false,        // the SML or SMP does not know the participant
    LookupStatus::Unknown => throw new RuntimeException((string) $result->reason),  // try again later
};
```

- `ParticipantId::parse()` accepts `0208:0123456789`, `iso6523-actorid-upis::0208:0123456789`
  and surrounding whitespace. `equals()` ignores letter case; `toString()` keeps the value as
  written, which is what the envelope and the access point need.
- **`Unknown` is never `NotRegistered`.** A DNS failure, an unusable SMP URL, an SMP 5xx or
  timeout and a malformed or hostile SMP answer all give `Unknown` with the reason, so that an
  outage does not make you fall back to e-mail for a participant that is in fact on Peppol.
- `lookup()` without a document type reads only the SMP ServiceGroup; with one, it reads the
  endpoints of that type. `supports()` checks that an endpoint for the document type, process and
  transport profile (AS4 by default) is active now.
- SMP answers are parsed with `SafeXml` (DOCTYPE refused) and capped at 2 MiB while they are
  read, so an oversized answer is not downloaded to the end. At most one SMP `Redirect` is
  followed. The `DocumentIdentifier` of an endpoint answer must repeat the requested document
  type: the scheme in any case (Peppol identifier schemes are case-insensitive) and the value
  exactly, case-sensitive; an answer for another type gives `Unknown`.
- Only the first-ranked NAPTR record of the SML answer is used; the others are not tried.
- **The SMP signature is not verified.** The endpoint data is only as trustworthy as the TLS
  connection to the SMP. Your access point verifies the receiver's certificate when it sends;
  use the lookup to decide whether to send over Peppol, not to pin certificates.
- **`DnsGetRecordResolver` is a fallback, not a production resolver.** PHP's `dns_get_record()`
  has no timeout of its own (the system resolver's applies), and a DNS SERVFAIL can look like
  NXDOMAIN: some platforms report a resolver failure like a missing record, which would turn
  `Unknown` into `NotRegistered`. For production, implement `NaptrResolver` (one method,
  `naptr(string $host): ?array`, returning `null` on failure and `[]` when there is no record)
  with a resolver that has a timeout and tells the two apart, for example a DNS-over-HTTPS client.

## Peppol Directory

```php
use IntrAgile\EInvoicing\Transport\Directory\DirectoryClient;
use IntrAgile\EInvoicing\Transport\Directory\DirectoryQuery;

$directory = new DirectoryClient($http);  // DirectoryEnvironment::Test as the second argument for the test directory

$page = $directory->search(new DirectoryQuery(name: 'Acme', country: 'BE'));
foreach ($page->entries as $entry) {
    echo $entry->participant->toString(), ' ', implode(' / ', $entry->names), "\n";
}

$entry = $directory->find(ParticipantId::parse('0208:0123456789'));  // null when not listed
```

- The Directory is informative only: its data is copied from SMP business cards and may be stale
  or incomplete. Use it to find a participant id; decide whether and where to send with
  `ParticipantLookup`.
- The Directory allows two requests per second. Each `DirectoryClient` keeps its own requests at
  least 500 ms apart, but **instances do not coordinate**: create one per process and share it. A
  429 answer throws `RateLimited`.
- A query needs at least one criterion; a name of two characters or fewer as the only criterion
  is refused (the Directory ignores it), and only the first 1000 results can be paged through.
- 500, 502, 503 and 504 answers throw `TransportUnavailable` (retryable); any other error status
  throws `UnexpectedResponse`.

## Receiving documents

### Polling

```php
use IntrAgile\EInvoicing\Syntax\Ubl\UblReader;

// Poll on the driver itself: ValidatingTransport wraps sending only.
foreach ($recommand->pending() as $reference) {      // RecommandTransport implements InboundSource
    $inbound = $recommand->fetch($reference->documentId);

    $invoice = (new UblReader)->read($inbound->xml)->invoice;
    $inbound->envelope?->sender;                       // SBDH metadata, when it arrived in one

    // Store the invoice first, then acknowledge: an acknowledged document leaves the inbox.
    $recommand->acknowledge($reference->documentId);
}
```

`fetch()` parses the document with `SafeXml`, caps it at the HTTP client's size limit (20 MB by
default) and, when it still arrives wrapped in an SBDH, returns the bare invoice with the
envelope metadata in `$inbound->envelope`. Acknowledge only after the invoice is stored; if the
acknowledgement fails, the document is fetched again on the next poll, so de-duplicate on the
document id.

### Webhooks

Every built-in driver has a webhook parser that implements `Transport\Webhook\WebhookParser`
(`RecommandWebhook`, `B2BrouterWebhook`, `EInvoiceBeWebhook`) and returns the same
provider-neutral `WebhookEvent`:

```php
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandWebhook;
use IntrAgile\EInvoicing\Transport\Webhook\WebhookKind;

$webhook = new RecommandWebhook($webhookSecret);   // response() stands for your framework's response

try {
    // The raw request body, never re-encoded JSON; $headers as name => value or list of values.
    $event = $webhook->parse($rawBody, $headers);
} catch (AuthenticationFailed) {
    return response('', 401);                       // missing, repeated or wrong signature
} catch (UnexpectedResponse) {
    return response('', 204);                       // signed, but not a document event: ignore it
}

if (! $alreadySeen($event->deduplicationKey)) {    // deliveries can repeat
    match ($event->kind) {
        WebhookKind::DocumentReceived => $queueFetch($event->documentId),   // fetch() it; the event carries no document
        WebhookKind::DeliveryChanged => $event->deliveryState === null
            ? $queueStatusCheck($event->documentId)                         // the event did not say how: read status()
            : $record($event->documentId, $event->deliveryState, $event->failure),
        WebhookKind::Other => null,
    };
}

return response('', 204);
```

- `$event->kind` says what the event is about (`DocumentReceived`, `DeliveryChanged` or `Other`);
  `$event->type` keeps the provider's event name as sent.
- `$event->deliveryState` is `null` when the provider did not say where delivery stands; read
  `status()` then. Events arrive more than once and not always in order, so never let an event
  move a document out of a final state.
- `$event->accountId` is the provider account the event names (Recommand company, B2Brouter
  account, e-invoice.be tenant), or `null` when it names none. Compare it with your own account
  when one endpoint could receive events of several accounts.
- `$event->deduplicationKey` is the same on every retry of one delivery; how each provider builds
  it is described below.
- **Header names are matched case-insensitively, and a signature header that appears more than
  once is refused**, even when the copies are identical.
- `verify($rawBody, $headers)` returns the signature check alone as a boolean and never throws.
- Answer a notification you ignore with 2xx, or the provider keeps retrying it.
- **Webhooks are not live-tested.** That needs a public HTTPS endpoint. The signatures are
  verified offline against each provider's documented scheme; for e-invoice.be also against its
  published test vector.

Recommand:

- The signature is an HMAC-SHA256 of the raw body (`X-Signature: sha256=<hex>`), compared in
  constant time. Pass the body exactly as received; a decoded and re-encoded body no longer
  matches.
- `document.received` is `DocumentReceived`; `document.sent` and
  `document.delivery_status_changed` are `DeliveryChanged`; any other type is `Other`. A correctly
  signed notification throws `UnexpectedResponse` when its body is not valid JSON, is not a JSON
  object, or has no non-empty `eventType` or `documentId` (for example a company verification
  event). Log `$e->bodyExcerpt` if you want to see what was ignored.
- The deduplication key is the `X-Idempotency-Key` header, or `"{type}:{documentId}:{state}"`
  (the delivery state value, empty when the event reports none) when the header is missing,
  blank or repeated, so that two different transitions of one document never share a key.
- A status event whose `deliveryStatus` is `null` gives `DeliveryState::Unknown`. This differs
  from `send()` and `status()`, where a missing or `null` `deliveryStatus` gives
  `DeliveryState::Pending`: the document was accepted and its delivery is not yet known. An event
  of a non-Peppol channel (for example e-mail) without a `deliveryStatus` carries no state.

B2Brouter and e-invoice.be: see [B2Brouter webhooks](#b2brouter-webhooks) and
[e-invoice.be webhooks](#e-invoicebe-webhooks).

## The SBDH envelope

On the Peppol network every document travels inside a Standard Business Document: the Peppol
Business Message Envelope (SBDH) followed by the document. Recommand, B2Brouter and e-invoice.be
build the envelope themselves, so their drivers send the bare invoice. For an access point that expects the whole
Standard Business Document, or for archiving, write and read it yourself:

```php
use IntrAgile\EInvoicing\Transport\Sbdh\EnvelopeReader;
use IntrAgile\EInvoicing\Transport\Sbdh\EnvelopeWriter;

$sbd = (new EnvelopeWriter)->wrap(OutboundDocument::fromXml($ublXml));  // random UUID, current time

[$envelope, $invoiceXml] = (new EnvelopeReader)->unwrap($sbd);
$envelope->sender;              // ParticipantId
$envelope->instanceIdentifier;  // the envelope's unique id
EnvelopeReader::isEnvelope($xml);
```

The writer copies the participant ids exactly as they appear in the invoice, because the
official rules compare them case-sensitively, and writes the header elements in schema order.
The reader matches elements by namespace, parses with `SafeXml` and throws
`UnsupportedDocument` for an envelope that lacks mandatory header data, has its header anywhere
but first or more than once, has a `COUNTRY_C1` that is not two upper-case letters, a
`CreationDateAndTime` that does not exist (such as `2026-02-30`), or a `Standard` and `Type`
that do not name the payload root. It tolerates a participant `Identifier` without the
`Authority` attribute and one that repeats the `iso6523-actorid-upis::` prefix.

### Envelope validation

`Validator::peppolEnvelope($dir)` runs the official envelope rules (`BR-SBDH-1` to `BR-SBDH-4`)
on a whole Standard Business Document; see [Envelope (SBDH) rules](PEPPOL.md#envelope-sbdh-rules).
Two limits matter here:

- The rules address **UBL paths only**. An enveloped CII invoice (an XRechnung CII document)
  passes without any envelope check, because no official rule matches it. Its envelope is checked
  structurally only: `EnvelopeWriter` takes the sender and receiver from the CII electronic
  addresses and checks that `Standard` and `Type` name the payload root.
- The rules check the sender and receiver ids only, not the invoice. Validate the invoice as
  well, with `PeppolPayloadValidator`: it applies the BIS rules to a BIS invoice and the KoSIT
  XRechnung rules to an XRechnung invoice.

The envelope rules are compiled by `einvoicing-rules install peppol` together with the invoice
rules, so a rules folder now holds **five** files. A folder installed by an earlier version holds
four: `check` exits 1 on it and `peppolEnvelope()` throws `RulesNotInstalled`; run `install`
again. `install` replaces the files only after all five have compiled, and if moving them into
place fails it restores the previous files.

## Testing your code

`FakeTransport` is an in-memory `Transport` and `InboundSource` for your own tests:

```php
use IntrAgile\EInvoicing\Transport\Exception\OutcomeUnknown;
use IntrAgile\EInvoicing\Transport\FakeTransport;
use IntrAgile\EInvoicing\Transport\OutboundDocument;

$fake = new FakeTransport;
$fake->willReturn(new OutcomeUnknown('no answer'));   // queue answers or exceptions
// ... run the code under test ...
$fake->assertSent(fn (OutboundDocument $d): bool => $d->receiver->toString() === '0208:0123456789');
```

Unqueued sends get delivered results with the ids `fake-1`, `fake-2`, …; a queued `SendResult`
with an empty document id gets `fake-q-1`, `fake-q-2`, …, and a generated id never repeats one
already returned. `fetch()` and `acknowledge()` of an unknown inbound id throw
`UnexpectedResponse`, as a real driver does.

## Recommand

- Create an API key in the Recommand dashboard; its id (`key_…`) and secret (`secret_…`) and the
  sending company's id go into `RecommandConfig`. The secret is kept out of `var_export()`,
  `print_r()`, `var_dump()`, `json_encode()` and exception messages. `RecommandConfig` refuses an
  API key id or secret shorter than eight characters (without quoting it) and trims the company
  id.
- An inbox entry's `createdAt` must be an ISO 8601 time with a time zone
  (`2026-11-12T08:02:00+00:00` or `2026-11-12T07:45:10.000Z`); anything else is an
  `UnexpectedResponse`.
- **Playground teams.** A Recommand playground is a separate team whose API key uses the same
  base URL. By default nothing it sends leaves Recommand: transmissions are simulated and the
  returned `peppolMessageId` and `envelopeId` are `null`. A playground created with
  `useTestNetwork: true` is the only way to send over the real Peppol **test** network through
  Recommand. Recipient lookups in a playground that is not on the test network still query the
  **production** SML and SMP, and the Directory search is always production.
- Recommand returns the whole inbox in one `pending()` call, so acknowledge processed documents
  to keep it short.

## B2Brouter

```php
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterConfig;
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterTransport;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\ValidatingTransport;
use IntrAgile\EInvoicing\Validation\PeppolPayloadValidator;

$config = new B2BrouterConfig($apiKey, $accountId);   // the account id as an int or as digits
$b2brouter = new B2BrouterTransport($config, $http);

$transport = new ValidatingTransport($b2brouter, PeppolPayloadValidator::create(__DIR__.'/var/peppol-rules'));
$result = $transport->send(OutboundDocument::fromXml($ublXml));
$result->documentId;   // the B2Brouter invoice id (digits)
```

**Configuration.** `B2BrouterConfig($apiKey, $accountId, $baseUrl, $documentTypeCodes)` takes the
API key (at least eight characters, kept out of dumps and messages), the B2Brouter account that
sends and receives (a positive integer), the base URL (default `https://api.b2brouter.net`) and
extra document type codes (below). Every request carries the key in `X-B2B-API-Key` and pins
`X-B2B-API-Version: 2026-06-26`, the API version the driver was built against, so an account
setting cannot change the response shapes. B2Brouter routes a request to its **sandbox by the key
alone**: a key that starts with `test_` goes to the sandbox, on the same base URL.
`$config->isSandboxKey()` tells which one you have.

**What can be sent.** B2Brouter takes the XML and a B2Brouter document type code, which decides
the format of the document it transmits. The driver maps the Peppol document type:

| Peppol document type | B2Brouter code |
|---|---|
| `PeppolBis3::INVOICE` | `xml.ubl.invoice.bis3` |
| `PeppolBis3::CREDIT_NOTE` | `xml.ubl.credit_note.bis3` |
| any other, for example XRechnung | only with a code you configure |

Any other document type is refused with `UnsupportedDocument` before a request is made, unless
`$documentTypeCodes` maps it (`[XRechnungPeppol::UBL_INVOICE => '<code>']`; codes are 3 to 100
characters of `a-z`, `0-9`, `.` and `_`). B2Brouter documents no code for XRechnung 3.0 yet, only
the old `xml.ubl.invoice.xrechnung.v22`; the codes of your account are listed by its
`GET /document_types` call. A send imports the XML into the account and queues it for sending in
one request (`POST /accounts/{account}/invoices/import`, with `send_after_import`, `issued`, the
transport `peppol` and the code).

**Who builds the SBDH.** B2Brouter. The driver sends the bare invoice.

**The delivered document is not byte-for-byte your XML.** B2Brouter renders the transmitted
document from its own invoice model, in the format of the document type code, so the bytes that
reach the receiver may differ from the XML you passed in (and validated). B2Brouter keeps the
document as delivered as the invoice's "legal" document (`GET /invoices/{id}/as/legal`); the
driver does not read it.

**Status.** `status()` reads `GET /invoices/{id}`. `changedAt` is the invoice's
`state_updated_at`. An id that belongs to a received invoice is an `UnexpectedResponse` (B2Brouter
answers it with a redirect, which is never followed).

| B2Brouter state | `DeliveryState` | Failure |
|---|---|---|
| `new`, `sending` | `Pending` | |
| `sent`, `registered`, `read`, `accepted`, `refused`, `received`, `allegedly_paid`, `paid`, `closed` | `Delivered` | |
| `error` | `Failed` | `recipient_not_found` for the error code `PEPPOL_NO_RECEIVER`, else `transport`; with B2Brouter's `error_message` and `error_code` |
| `invalid` | `Failed` | `validation` |
| any other state (for example `annulled`, `expired`) or none | `Unknown` | |

`refused`, `accepted` and `paid` are business responses of the receiver. They come after
delivery to the receiver's access point and do not change the delivery state, as for Recommand.

**Inbound.** `pending()` lists the account's unacknowledged received invoices, at most ten pages
of 100 per call; acknowledge processed ones to reach those behind them. The list reports no Peppol
participant identifiers, so `sender` and `receiver` are `null`; `documentType` is B2Brouter's
document type code, not a Peppol identifier, and `receivedAt` is the invoice's `created_at`.
`fetch()` reads the document exactly as B2Brouter stored it (`GET /invoices/{id}/as/original`),
removes an SBDH when there is one, and follows one redirect only when it stays on the base URL's
host (the API key is sent again); any other redirect is an `UnexpectedResponse`. Its reference holds only the id and the time of the fetch.
`acknowledge()` calls `POST /invoices/{id}/ack`, which removes the invoice from `pending()`; it is
idempotent, so it is safe to repeat after an `OutcomeUnknown`.

**Sandbox.** A B2Brouter sandbox is free and self-service. It **simulates** the Peppol network:
nothing reaches a real access point, and the SMP is never queried. Pre-created test recipients
(GLN, scheme `0088`) give fixed outcomes: `0088:9508397101047` ends `registered`,
`0088:9506215594996` ends `refused` (a business response, still `Delivered`), and
`0088:9500047420799` fails with `PEPPOL_NO_RECEIVER`; the sandbox (observed 2026-11-15) reports it only in the `errors` list, without an error code, and the driver maps that message to `recipient_not_found`. Received documents are not simulated;
importing a document as a received invoice seeds the inbox.

### B2Brouter webhooks

```php
use IntrAgile\EInvoicing\Transport\B2Brouter\B2BrouterWebhook;

$webhook = new B2BrouterWebhook($webhookSecret);         // no timestamp check (the default)
$webhook = new B2BrouterWebhook($webhookSecret, 300);    // refuse a signed time more than 300 s from now
```

- The secret is the web hook's signing secret (`signing_secret` in B2Brouter's web hook object;
  B2Brouter's guide calls it `signature_key`).
- B2Brouter signs with `X-B2Brouter-Signature: t=<unix time>,s=<hex>`, an HMAC-SHA256 of
  `"<t>.<raw body>"`. The header must appear exactly once and match that form exactly; pass the
  body exactly as received.
- `issued_invoice.state_change` is `DeliveryChanged` with the state of `data.state` as in the
  table above (`null` when the notification has no state). The failure is always `null`, because
  the notification carries no error code: read `status()` for it. `received_invoice.created` is
  `DocumentReceived`; any other event with an invoice id is `Other`. A signed notification without
  `data.invoice_id` throws `UnexpectedResponse`. The account id is `data.account_id`.
- The deduplication key is the `X-B2Brouter-Delivery-Id` header, the same on every attempt of one
  delivery. Without it, the key is `"<code>:<invoice id>:<data.event_id>"`, or
  `"<code>:<invoice id>:<data.state>"` when there is no event id either.
- **The `X-B2Brouter-Delivery-Id` header is not covered by the signature**, and the signed
  timestamp is **not checked by default**. Someone who captured a signed notification can
  therefore send it again later, with a new delivery id, and it is accepted as a new delivery. It
  can only repeat a state B2Brouter really reported for that invoice; never let it move a document
  out of a final state. The tolerance is off because B2Brouter retries a failed delivery for about
  a week, treats any 4xx answer other than 429 as final, and does not say whether a retry is signed
  again: with a tolerance, a legitimate late retry would be refused for good. To check the
  timestamp anyway, pass the tolerance in seconds as the second constructor argument (in the
  Laravel bridge, `webhook_tolerance`); a value well above your longest outage keeps late retries,
  if B2Brouter signs them again.

**Rate limits.** 1,000 requests per minute in production, 600 in the sandbox and on staging.
A 429 answer throws `RateLimited`; B2Brouter documents no `Retry-After` header, so
`retryAfterSeconds` is usually `null` and you choose the wait. The driver does not throttle.

**Not verified yet** (these are checked when the driver is first run against the sandbox):

- that a send is delivered to the buyer's electronic address (`cbc:EndpointID`);
- whether the delivered "legal" document equals the XML sent;
- which document type codes exist for XRechnung 3.0 UBL and CII;
- whether a received document's original carries an SBDH (the driver unwraps one either way);
- whether B2Brouter checks that the seller's tax id matches the account;
- whether webhook retries are signed again;
- whether a 429 answer carries `Retry-After`, and the full list of Peppol delivery error codes;
- that the received-invoice list carries no Peppol participant identifiers.

## e-invoice.be

```php
use IntrAgile\EInvoicing\Transport\EInvoiceBe\EInvoiceBeConfig;
use IntrAgile\EInvoicing\Transport\EInvoiceBe\EInvoiceBeTransport;
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\ValidatingTransport;
use IntrAgile\EInvoicing\Validation\PeppolPayloadValidator;

$config = new EInvoiceBeConfig($apiKey);   // inboundLookbackDays: 7, inboundMaxPages: 10
$einvoicebe = new EInvoiceBeTransport($config, $http);

$transport = new ValidatingTransport($einvoicebe, PeppolPayloadValidator::create(__DIR__.'/var/peppol-rules'));
$result = $transport->send(OutboundDocument::fromXml($ublXml));
$result->documentId;   // the e-invoice.be document id, "doc-…"
```

**Configuration.** `EInvoiceBeConfig($apiKey, $baseUrl, $inboundLookbackDays, $inboundMaxPages)`
takes the API key (at least eight characters, sent as a bearer token and kept out of dumps and
messages), the base URL (default `https://api.e-invoice.be`) and the two inbound limits below.
e-invoice.be uses **one host for test and production**: the company the key belongs to decides
whether a send reaches the Peppol network.

**What can be sent.** Peppol BIS Billing 3.0 UBL only: `PeppolBis3::INVOICE` and
`PeppolBis3::CREDIT_NOTE`, where the document's declared type and its XML must both resolve to
one of them. XRechnung and CII are refused with `UnsupportedDocument` before any request;
self-billing is out of scope for 1.0. A send takes two requests: the UBL is uploaded as a draft
(`POST /api/documents/ubl`), then the draft is sent (`POST /api/documents/{id}/send`) with the
sender's and receiver's scheme and id from the document. The draft id is the document id. A
refused send leaves the draft in the account. HTTP 409 on the send (the sender is not one of the
company's Peppol identifiers) is a `DocumentRejected`; 405 (the draft is not `DRAFT` or `FAILED`)
is an `UnexpectedResponse`.

**Who builds the SBDH.** e-invoice.be. The driver sends the bare invoice.

**Byte-for-byte, except the EndpointIDs.** When it transmits the document, e-invoice.be writes the
final sender and receiver into the `cbc:EndpointID` elements of the UBL; the rest of the document
is sent unchanged.

**Status.** `status()` reads `GET /api/documents/{id}`.

| e-invoice.be state | `DeliveryState` | Failure |
|---|---|---|
| `DRAFT`, `TRANSIT` | `Pending` | |
| `SENT` | `Delivered` | |
| `FAILED` | `Failed` | `transport`, provider code `send_failed`; `changedAt` is the newest `send_failed` time of the timeline |
| any other state, or none | `Unknown` | |

e-invoice.be does not record the technical reason of a failed transmission, so the failure only
says that it failed. A `FAILED` document can be sent again. When the timeline cannot be read, the
state is still `Failed`, without a message.

**Inbound without a remote acknowledgement.** e-invoice.be has no call to mark a received document
as processed and no unread filter, so:

- `pending()` lists the inbox newest first, back to `inboundLookbackDays` (default 7, 1 to 365),
  reading at most `inboundMaxPages` pages of 100 (default 10, 1 to 100). It lists a processed
  document again on every call until the document falls out of the look-back, so **de-duplicate
  on the document id**. A document that falls out of the look-back before you fetched it is not
  listed any more: poll more often than the look-back, or use webhooks.
- The inbox reports no Peppol participants or document type, so `sender`, `receiver` and
  `documentType` are `null`; `receivedAt` is the inbox entry's time.
- `fetch()` reads the document's UBL record, then downloads the document from its signed URL
  **without the API key**, over https only. The signed URL is a credential and is kept out of
  every message. When the record carries a SHA-256 file hash, the download must match it. An SBDH
  is removed when present. The reference carries the sender and receiver of the record.
- `acknowledge()` checks the id and records nothing remotely.

**Sandbox.** A sandbox company is free and needs no VAT or contact verification. It **simulates**
the Peppol network: a send becomes an e-mail with the UBL to the company's contact address, and
then the document is `SENT`. e-invoice.be documents that a sandbox company without a contact
e-mail address answers the send with HTTP 400 (a `DocumentRejected`) **and still moves the document
to `SENT`**, so give it a contact address. Received documents can only be simulated with
"Simulate inbound" in the e-invoice.be app; a send to yourself never reaches your inbox. No API
field tells a sandbox company from a production one.

### e-invoice.be webhooks

```php
use IntrAgile\EInvoicing\Transport\EInvoiceBe\EInvoiceBeWebhook;

$webhook = new EInvoiceBeWebhook($webhookSecret);   // the "secret" of the webhook create response
```

- e-invoice.be signs with `X-Signature: sha256=<hex>`, an HMAC-SHA256 **not of the raw body but of
  a canonical JSON string** of the event: the output of Python's
  `json.dumps(payload, sort_keys=True)`. The parser decodes the body and rebuilds that string
  (`EInvoiceBe\CanonicalJson`). **Do not compute the HMAC of the raw body yourself, and do not
  re-encode the body before you pass it**: pass it exactly as received. A body whose canonical form
  cannot be rebuilt, such as one with a number that has a fraction or an exponent, or an integer
  outside the PHP range, is refused with `AuthenticationFailed` (it fails closed); the documented
  events carry no such number.
- `document.received` is `DocumentReceived`. `document.sent` is `DeliveryChanged` with
  `Delivered`. `document.sent.failed` is `DeliveryChanged` **without a state**: read `status()`,
  which also gives the failure. `document.received.failed` and any other type are `Other`. A
  signed notification without `data.document_id` throws `UnexpectedResponse`. The account id is
  `tenant_id`.
- The deduplication key is `"<type>:<document id>"`, because the event id changes on every
  attempt. **This merges distinct events of the same type for one document**: a second
  `document.sent.failed` after a resend has the same key as the first. It follows from
  e-invoice.be's event model (an event carries no state and no stable id), so de-duplicate by
  reading the document state rather than by trusting the key alone.
- e-invoice.be makes at most three attempts (2xx within 10 seconds counts as success) and does not
  keep the order of events.

**Rate limits.** e-invoice.be limits only some calls, among them the UBL upload. A 429 answer
throws `RateLimited`, whose `retryAfterSeconds` is the `Retry-After` header; e-invoice.be documents
it as the length of its rate window rather than the time until the limit resets. The driver does
not throttle.

**Not verified yet** (these are checked when the driver is first run against the sandbox):

- how long a signed download URL stays valid, which matters when the fetch is queued;
- whether the UBL record of a received CII document serves it unchanged;
- that document ids always have the form `doc-` followed by letters and digits;
- that the record's file hash is a SHA-256 (a hash of another shape is ignored);
- where e-invoice.be takes the envelope's `COUNTRY_C1` from;
- whether `GET /api/me/` tells a sandbox company apart.

## What is verified

| Area | How | Status |
|---|---|---|
| Identifiers, `OutboundDocument`, SBDH writer and reader, `HttpClient`, `FakeTransport`, `ValidatingTransport` | Offline unit tests | Run on every test run |
| Envelope rules | `Validator::peppolEnvelope()` against the official envelope Schematron compiled with the ISO skeleton and run by SaxonC-HE, on the 14 official envelope tests and 10 envelopes written by `EnvelopeWriter` (oracle group) | Same verdicts on all 24 |
| SML and SMP lookup | Offline tests with scripted DNS answers and SMP fixtures; live test group `network` against the production SML and public SMPs (a known participant, its endpoints, a random unregistered id) | Live checks passed on 2026-11-12 |
| Peppol Directory | Offline tests with recorded answers; one manual live search and lookup against the production Directory (no live test in the suite) | Manual check passed on 2026-11-12 |
| Recommand driver | Offline tests with JSON fixtures **hand-written from Recommand's published API description** (OpenAPI document and source), not recorded from the live service | **Live transmission not yet verified** |
| B2Brouter driver | Offline contract tests with JSON fixtures hand-written from B2Brouter's public API reference (OpenAPI 2026-06-26) and developer guides | **Not live-tested** |
| e-invoice.be driver | Offline contract tests with JSON fixtures hand-written from e-invoice.be's public API reference (OpenAPI 1.1.0) and guides | **Not live-tested** |
| Webhook signatures | Offline tests against each provider's documented signing scheme; e-invoice.be also against its published test vector | **Not live-tested** (needs a public HTTPS endpoint) |

A live test group, `recommand-live`, sends the Peppol base example through a Recommand
playground team and then calls `status()`, `pending()`, `fetch()` and `acknowledge()`. It
refuses to run against a production team. It needs `RECOMMAND_API_KEY_ID`,
`RECOMMAND_API_SECRET` and `RECOMMAND_COMPANY_ID` and is skipped without them:

```bash
RECOMMAND_API_KEY_ID=key_… RECOMMAND_API_SECRET=secret_… RECOMMAND_COMPANY_ID=c_… \
    vendor/bin/phpunit --group recommand-live
```

Two more live groups run against the providers' sandboxes. Both prove the provider's API contract
against its **simulation**, not delivery over Peppol:

- `b2brouter-live` sends to the three sandbox test recipients, queries their status, seeds the
  inbox with an imported received invoice, fetches and acknowledges it, and prints the XRechnung
  document type codes and whether the delivered document equals the sent XML. It needs
  `B2BROUTER_API_KEY` and `B2BROUTER_ACCOUNT_ID` (optionally `B2BROUTER_BASE_URL`) and refuses a key
  that does not start with `test_` before any request.
- `einvoicebe-live` sends to the company's own Peppol id, queries the status, and lists, fetches
  and acknowledges the inbox. It needs `EINVOICEBE_API_KEY` and `EINVOICEBE_SANDBOX_CONFIRMED=1`
  (your statement that the key belongs to a sandbox company, since no API field tells); without
  the confirmation it fails before any request. For the inbox case, simulate a received document
  in the e-invoice.be app and set `EINVOICEBE_EXPECT_INBOUND_NUMBER` to its invoice number.

```bash
B2BROUTER_API_KEY=test_… B2BROUTER_ACCOUNT_ID=… vendor/bin/phpunit --group b2brouter-live
EINVOICEBE_API_KEY=… EINVOICEBE_SANDBOX_CONFIRMED=1 vendor/bin/phpunit --group einvoicebe-live
```

No live group runs by default; `vendor/bin/phpunit --group network` runs the unauthenticated
SML and SMP lookups (and the rule download from GitHub).

## Known limitations

- **No SMP signature verification**: the XMLDSig signature of SMP answers is not checked.
- **CII only for XRechnung**: BIS Billing 3.0 is sent as UBL; CII goes out only as XRechnung
  3.0 or XRechnung 3.0 Extension. See [What can be sent](#what-can-be-sent).
- **No self-billing**: self-billing documents are refused.
- **No XRechnung CVD and no XRechnung UBL Credit Note Extension**: neither has an active Peppol
  document type.
- **`dns_get_record()`** has no timeout and may report SERVFAIL as NXDOMAIN; inject your own
  `NaptrResolver` in production.
- **Only the first-ranked NAPTR record** is tried.
- **Directory rate limit** (2 requests per second) is enforced per `DirectoryClient` instance.
- **Three access point drivers today**: Recommand, B2Brouter and e-invoice.be; Storecove is
  planned for 1.0.0. The `Transport`, `InboundSource` and `WebhookParser` interfaces are the
  extension point for others.
- **e-invoice.be sends BIS Billing 3.0 UBL only**; B2Brouter sends XRechnung only with a document
  type code you configure.
- **B2Brouter renders the delivered document itself**, so it may differ from the XML you validated.
- **e-invoice.be cannot acknowledge** received documents; they are listed again until they fall
  out of the look-back.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)
