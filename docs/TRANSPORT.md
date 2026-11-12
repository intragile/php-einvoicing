# Peppol transport

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)

This page explains how to send and receive invoices over Peppol with this package: sending
through an access point (Recommand is the first driver), looking up participants in the SML and
their SMP, searching the Peppol Directory, receiving documents by polling or webhooks, the
errors and what to do about each, the SBDH envelope, and what is verified and what is not. The
invoice format and its validation are described in [PEPPOL.md](PEPPOL.md).

> **Live transmission through Recommand is not yet verified.** The Recommand driver is tested
> offline against fixtures written by hand from Recommand's published API description; see
> [What is verified](#what-is-verified).

## Quick start

```php
use IntrAgile\EInvoicing\Transport\OutboundDocument;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandConfig;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandTransport;
use IntrAgile\EInvoicing\Transport\ValidatingTransport;
use IntrAgile\EInvoicing\Validation\Validator;

$config = new RecommandConfig($apiKeyId, $secret, $companyId);
$recommand = new RecommandTransport($config, $http);  // $http: see "HTTP client" below

$transport = new ValidatingTransport(
    $recommand,
    Validator::peppol(__DIR__.'/var/peppol-rules'),   // installed with einvoicing-rules
);

$result = $transport->send(OutboundDocument::fromXml($ublXml));

$result->documentId;   // the access point's id; keep it for status() and de-duplication
$result->state;        // DeliveryState::Pending, Delivered, Failed or Unknown
```

`OutboundDocument::fromXml()` reads the routing metadata from the invoice itself: the sender
from the seller's electronic address (BT-34, `cbc:EndpointID`), the receiver from the buyer's
(BT-49), the process from `cbc:ProfileID` and the sender's country (C1) from the seller's
country. `OutboundDocument::fromInvoice($invoice)` writes a model invoice as UBL first. Later,
`$transport->status($result->documentId)` returns a `DeliveryStatus` with the current state and,
for a failed delivery, a `DeliveryFailure` with a `FailureCategory`.

**Always wrap the driver in `ValidatingTransport` with `Validator::peppol()`.** It validates
every document before it leaves and throws `InvalidDocument` (with the full report) instead of
sending. It also catches documents that are not Peppol BIS Billing 3.0 at all: the Peppol
document type identifier is derived from the root element (UBL `Invoice` or `CreditNote`), not
from `cbc:CustomizationID`, so without validation an XRechnung or other national UBL invoice
would be sent under the BIS 3.0 document type. The Peppol rules refuse such a document.

`OutboundDocument::fromXml()` refuses, with `UnsupportedDocument`:

- **UN/CEFACT CII invoices.** This version sends UBL only; write the invoice as UBL.
- **Self-billing invoices** (a `CustomizationID` containing `selfbilling`). Self-billing swaps
  the envelope roles and uses its own document type, which this version does not support.
- An invoice without a seller or buyer electronic address, and an `EndpointID` whose `schemeID`
  has surrounding whitespace (the official envelope rule compares it as written, so the envelope
  would fail).

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
- **Do not follow redirects**, at least for SMP lookups. `SmpClient` handles the SMP's own
  `Redirect` element (one hop) itself; an HTTP 3xx followed silently by the client would bypass
  the URL checks the lookup applies (https only, no user info, query string or fragment).
- The `Authorization` header value is kept out of this package's exception messages; a PSR-18
  client's own exception, chained as the previous exception, is passed through unchanged, so check
  what your client puts in its messages before logging the whole chain.

## Errors

Every transport error extends `TransportException`, whose `isRetryable()` says whether repeating
the same call later may succeed **and cannot cause a duplicate**.

| Exception | `isRetryable()` | When | What to do |
|---|---|---|---|
| `InvalidDocument` | false | `ValidatingTransport` found a fatal finding or a dynamic error; nothing was sent | Fix the invoice; `$e->report` is the `ValidationReport` |
| `DocumentRejected` | false | The access point refused the document (Recommand HTTP 400 on send) | Fix the document; `$e->errors` holds the provider's details |
| `RecipientUnreachable` | false | The receiver is not registered or does not accept this document type (Recommand HTTP 422) | `$e->category` is a `FailureCategory`; use another channel or ask the receiver |
| `AuthenticationFailed` | false | Wrong credentials or missing permission (HTTP 401/403); a webhook with a bad signature | Fix the configuration; for a webhook, refuse the request |
| `RateLimited` | true | Too many requests (HTTP 429) | Wait `$e->retryAfterSeconds` (when given), then retry |
| `TransportUnavailable` | true | A GET got no answer, or the provider answered 500, 502 or 503 | Retry later with backoff |
| `OutcomeUnknown` | false | A send (or another state-changing request) got no answer, or the gateway timed out (HTTP 504) | **Do not resend blindly**; see below |
| `UnexpectedResponse` | false | An unexpected status, a malformed or oversized body | Log `$e->status` and `$e->bodyExcerpt`; investigate |

`fetch()` can also throw `UnsafeXml` and `UnsupportedDocument` (the library's own exceptions)
for a received document with a DOCTYPE or broken XML; `InvalidArgumentException` signals an
empty document id or a malformed value.

### OutcomeUnknown

PSR-18 cannot tell a request that never left from one that was sent and not answered: both
arrive as the same client exception. For a send, the access point may therefore have accepted
the invoice. Resending at once risks delivering it twice. Instead:

1. Mark the invoice as "outcome unknown" in your system, not as failed.
2. Check the access point's document list (for Recommand, the documents of the company in the
   dashboard or the API) for a document to the same receiver with the same invoice number.
3. Resend only when it is not there.

An HTTP 504 from the Recommand gateway on `send()` is treated the same way. `acknowledge()` also
throws `OutcomeUnknown` when it gets no answer, but marking as read is idempotent, so calling it
again with the same id is safe.

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
    new SmpClient(new HttpClient($psr18, $factory, $factory, 2_000_000)),
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
- SMP answers are parsed with `SafeXml` (DOCTYPE refused), capped at 2 MB, and at most one SMP
  `Redirect` is followed.
- **The SMP signature is not verified.** The endpoint data is only as trustworthy as the TLS
  connection to the SMP. Your access point verifies the receiver's certificate when it sends;
  use the lookup to decide whether to send over Peppol, not to pin certificates.
- **`DnsGetRecordResolver` is a fallback, not a production resolver.** PHP's `dns_get_record()`
  has no timeout of its own (the system resolver's applies), and some platforms report a
  resolver failure (SERVFAIL) like a missing record (NXDOMAIN), which would turn `Unknown` into
  `NotRegistered`. For production, implement `NaptrResolver` (one method,
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

```php
use IntrAgile\EInvoicing\Transport\Exception\AuthenticationFailed;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Recommand\RecommandWebhook;

$webhook = new RecommandWebhook($webhookSecret);   // response() stands for your framework's response

try {
    // The raw request body, never re-encoded JSON; $headers as name => value or list of values.
    $event = $webhook->parse($rawBody, $headers);
} catch (AuthenticationFailed) {
    return response('', 401);                       // missing or wrong X-Signature
} catch (UnexpectedResponse) {
    return response('', 204);                       // an event without a document id: ignore it
}

if (! $alreadySeen($event->idempotencyKey)) {      // X-Idempotency-Key: deliveries can repeat
    // $event->type, $event->documentId, $event->deliveryState, $event->failure
    // A notification carries no document: fetch($event->documentId) for "document.received".
}

return response('', 204);
```

- The signature is an HMAC-SHA256 of the raw body (`X-Signature: sha256=<hex>`), compared in
  constant time. Pass the body exactly as received; a decoded and re-encoded body no longer
  matches.
- Only document events are parsed. A signed event without a `documentId` (for example a company
  verification event) throws `UnexpectedResponse`; answer it with 2xx anyway, or the sender will
  keep retrying it.
- `verify($rawBody, $signatureHeader)` returns the signature check alone as a boolean.

## The SBDH envelope

On the Peppol network every document travels inside a Standard Business Document: the Peppol
Business Message Envelope (SBDH) followed by the document. Recommand builds the envelope itself,
so `RecommandTransport` sends the bare invoice. For an access point that expects the whole
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
The reader matches elements by namespace, parses with `SafeXml` and refuses an envelope that
lacks mandatory header data.

### Envelope validation

`Validator::peppolEnvelope($dir)` runs the official envelope rules (`BR-SBDH-1` to `BR-SBDH-4`)
on a whole Standard Business Document; see [Envelope (SBDH) rules](PEPPOL.md#envelope-sbdh-rules).
Two limits matter here:

- The rules address **UBL paths only**. An enveloped CII invoice passes without any envelope
  check, because no official rule matches it; this package does not send CII anyway.
- The rules check the sender and receiver ids only, not the invoice. Validate the invoice with
  `Validator::peppol()` as well.

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

## Recommand

- Create an API key in the Recommand dashboard; its id (`key_…`) and secret (`secret_…`) and the
  sending company's id go into `RecommandConfig`. The secret is kept out of `var_export()`,
  `print_r()`, `var_dump()`, `json_encode()` and exception messages.
- **Playground teams.** A Recommand playground is a separate team whose API key uses the same
  base URL. By default nothing it sends leaves Recommand: transmissions are simulated and the
  returned `peppolMessageId` and `envelopeId` are `null`. A playground created with
  `useTestNetwork: true` is the only way to send over the real Peppol **test** network through
  Recommand. Recipient lookups in a playground that is not on the test network still query the
  **production** SML and SMP, and the Directory search is always production.
- Recommand returns the whole inbox in one `pending()` call, so acknowledge processed documents
  to keep it short.

## What is verified

| Area | How | Status |
|---|---|---|
| Identifiers, `OutboundDocument`, SBDH writer and reader, `HttpClient`, `FakeTransport`, `ValidatingTransport` | Offline unit tests | Run on every test run |
| Envelope rules | `Validator::peppolEnvelope()` against the official envelope Schematron compiled with the ISO skeleton and run by SaxonC-HE, on the 14 official envelope tests and 10 envelopes written by `EnvelopeWriter` (oracle group) | Same verdicts on all 24 |
| SML and SMP lookup | Offline tests with scripted DNS answers and SMP fixtures; live test group `network` against the production SML and public SMPs (a known participant, its endpoints, a random unregistered id) | Live checks passed on 2026-11-12 |
| Peppol Directory | Offline tests with recorded answers; one manual live search and lookup against the production Directory (no live test in the suite) | Manual check passed on 2026-11-12 |
| Recommand driver | Offline tests with JSON fixtures **hand-written from Recommand's published API description** (OpenAPI document and source), not recorded from the live service | **Live transmission not yet verified** |

A live test group, `recommand-live`, sends the Peppol base example through a Recommand
playground team and then calls `status()`, `pending()`, `fetch()` and `acknowledge()`. It
refuses to run against a production team. It needs `RECOMMAND_API_KEY_ID`,
`RECOMMAND_API_SECRET` and `RECOMMAND_COMPANY_ID` and is skipped without them:

```bash
RECOMMAND_API_KEY_ID=key_… RECOMMAND_API_SECRET=secret_… RECOMMAND_COMPANY_ID=c_… \
    vendor/bin/phpunit --group recommand-live
```

Neither live group runs by default; `vendor/bin/phpunit --group network` runs the unauthenticated
SML and SMP lookups (and the rule download from GitHub).

## Known limitations

- **No SMP signature verification**: the XMLDSig signature of SMP answers is not checked.
- **No CII sending**: `OutboundDocument` accepts UBL Invoice and CreditNote only.
- **No self-billing**: self-billing documents are refused.
- **The document type follows the root element**, so non-Peppol customizations must be stopped
  by `ValidatingTransport` with `Validator::peppol()`.
- **`dns_get_record()`** has no timeout and may report SERVFAIL as NXDOMAIN; inject your own
  `NaptrResolver` in production.
- **Only the first-ranked NAPTR record** is tried.
- **Directory rate limit** (2 requests per second) is enforced per `DirectoryClient` instance.
- **One access point driver** (Recommand); the `Transport` and `InboundSource` interfaces are
  the extension point for others.

---

Developed and maintained by [IntrAgile Kft.](https://intragile.eu)
