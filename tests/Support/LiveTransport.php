<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\Exception\UnexpectedResponse;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\InboundSource;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\IncompleteTest;
use PHPUnit\Framework\SkippedTest;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Helpers shared by the opt-in live tests of the transport drivers.
 */
final class LiveTransport
{
    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    /** Status reads before polling gives up. */
    public const STATUS_ATTEMPTS = 10;

    /** Seconds between two status reads. */
    public const STATUS_WAIT_SECONDS = 6;

    /**
     * A unique invoice number for one run.
     */
    public static function invoiceNumber(): string
    {
        return 'LIVE-'.bin2hex(random_bytes(6));
    }

    /**
     * The status once it is delivered or failed, read a bounded number of times.
     *
     * @param  Closure(): DeliveryStatus  $status  reads the current status
     */
    public static function pollUntilFinal(Closure $status, int $attempts = self::STATUS_ATTEMPTS, int $waitSeconds = self::STATUS_WAIT_SECONDS): DeliveryStatus
    {
        $current = $status();
        for ($attempt = 2; $attempt <= $attempts && ! in_array($current->state, [DeliveryState::Delivered, DeliveryState::Failed], true); $attempt++) {
            sleep($waitSeconds);
            $current = $status();
        }

        return $current;
    }

    /**
     * A failure message with the start of the response, without the redacted values.
     */
    public static function excerpt(Redactor $redactor, string $what, ResponseInterface $response): string
    {
        return $redactor->redact(sprintf('%s answered HTTP %d: %s', $what, $response->getStatusCode(), substr((string) $response->getBody(), 0, 300)));
    }

    /**
     * Where report() writes; null means STDERR. Tests point it at a memory stream.
     *
     * @internal
     *
     * @var resource|null
     */
    public static $stream = null;

    /**
     * Writes a finding of a live test to STDERR, tagged with the test's group. With a redactor
     * the line is redacted first, so no configured key or account id reaches the output.
     */
    public static function report(string $tag, string $line, ?Redactor $redactor = null): void
    {
        if ($redactor !== null) {
            $line = $redactor->redact($line);
        }

        fwrite(self::$stream ?? STDERR, PHP_EOL.'['.$tag.'] '.$line.PHP_EOL);
    }

    /**
     * The XRechnung release invoice with the given number, sent from the seller participant to
     * the buyer participant, written in the given syntax.
     */
    public static function xrechnung(Syntax $syntax, string $number, ParticipantId $buyer, ParticipantId $seller): string
    {
        $invoice = self::routed(ReleaseInvoices::germanDomestic($number), $buyer, $seller);

        return match ($syntax) {
            Syntax::Ubl => (new UblWriter)->write($invoice),
            Syntax::Cii => (new CiiWriter)->write($invoice),
        };
    }

    /**
     * The Peppol BIS Billing 3.0 release invoice with the given number, sent from the seller
     * participant to the buyer participant, written as UBL.
     */
    public static function bis3(string $number, ParticipantId $buyer, ParticipantId $seller): string
    {
        return (new UblWriter)->write(self::routed(ReleaseInvoices::bis3($number), $buyer, $seller));
    }

    /**
     * Runs the body and turns any failure or exception into a test failure whose message has the
     * redacted values removed. A skip or an incomplete mark passes through unchanged.
     *
     * @param  Closure(): void  $body
     */
    public static function failRedacted(Redactor $redactor, Closure $body): void
    {
        try {
            $body();
        } catch (SkippedTest|IncompleteTest $mark) {
            throw $mark;
        } catch (AssertionFailedError $failure) {
            Assert::fail($redactor->redact($failure->getMessage()));
        } catch (Throwable $exception) {
            Assert::fail($redactor->redact($exception::class.': '.$exception->getMessage()));
        }
    }

    /**
     * The id of the inbox entry that is the sent document, read a bounded number of times: the
     * entry with the sent document id, or the entry from the sender whose invoice number (BT-1)
     * matches. Each other entry is fetched at most once; one that cannot be read (malformed,
     * unsafe or refused by the source) belongs to someone else and is skipped.
     */
    public static function awaitInbound(InboundSource $inbox, string $sentId, ParticipantId $sender, string $invoiceNumber, int $attempts, int $waitSeconds): ?string
    {
        /** @var array<string, true> $checked */
        $checked = [];
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($attempt > 1) {
                sleep($waitSeconds);
            }
            foreach ($inbox->pending() as $reference) {
                if ($reference->documentId === $sentId) {
                    return $reference->documentId;
                }
                if (isset($checked[$reference->documentId]) || ($reference->sender !== null && ! $reference->sender->equals($sender))) {
                    continue;
                }
                $checked[$reference->documentId] = true;
                try {
                    $xml = $inbox->fetch($reference->documentId)->xml;
                } catch (Throwable $exception) {
                    // Only a document that cannot be read is skipped; any other failure ends the search.
                    if ($exception instanceof UnsupportedDocument || $exception instanceof UnsafeXml || $exception instanceof UnexpectedResponse) {
                        continue;
                    }

                    throw $exception;
                }
                if (self::invoiceNumberOf($xml) === $invoiceNumber) {
                    return $reference->documentId;
                }
            }
        }

        return null;
    }

    /**
     * The invoice number (BT-1) of a UBL or CII invoice, or null when the document has none.
     */
    public static function invoiceNumberOf(string $xml): ?string
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml, LIBXML_NONET)) {
            return null;
        }
        $nodes = (new DOMXPath($dom))->query(
            '/*[local-name()="Invoice" or local-name()="CreditNote"]/*[local-name()="ID"]'
            .' | /*[local-name()="CrossIndustryInvoice"]/*[local-name()="ExchangedDocument"]/*[local-name()="ID"]',
        );
        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMElement ? trim($node->textContent) : null;
    }

    /**
     * The Peppol base example with the invoice number and the buyer electronic address replaced,
     * and the seller electronic address too when one is given. Every date in the document is
     * shifted by the same number of days so the issue date is today (UTC), because providers
     * refuse invoices dated in a closed fiscal year.
     */
    public static function baseExample(string $invoiceNumber, ParticipantId $buyer, ?ParticipantId $seller = null): string
    {
        $examples = Fixtures::peppolExamples();
        Assert::assertArrayHasKey('examples/base-example.xml', $examples);

        $dom = new DOMDocument;
        $dom->loadXML($examples['examples/base-example.xml'], LIBXML_NONET);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', self::NS_CAC);
        $xpath->registerNamespace('cbc', self::NS_CBC);

        $ids = $xpath->query('/*/cbc:ID');
        $id = $ids === false ? null : $ids->item(0);
        Assert::assertInstanceOf(DOMElement::class, $id);
        $id->textContent = $invoiceNumber;

        self::dateToday($xpath);

        $parties = ['AccountingCustomerParty' => $buyer];
        if ($seller !== null) {
            $parties['AccountingSupplierParty'] = $seller;
        }
        foreach ($parties as $party => $participant) {
            $nodes = $xpath->query(sprintf('/*/cac:%s/cac:Party/cbc:EndpointID', $party));
            $endpoint = $nodes === false ? null : $nodes->item(0);
            Assert::assertInstanceOf(DOMElement::class, $endpoint);
            $endpoint->setAttribute('schemeID', $participant->icd);
            $endpoint->textContent = $participant->value;
        }

        return (string) $dom->saveXML();
    }

    /**
     * Shifts every YYYY-MM-DD element whose name ends in "Date" by the days between the issue date
     * and today (UTC).
     */
    private static function dateToday(DOMXPath $xpath): void
    {
        $issue = $xpath->query('/*/cbc:IssueDate');
        $issueNode = $issue === false ? null : $issue->item(0);
        Assert::assertInstanceOf(DOMElement::class, $issueNode);
        $original = DateTimeImmutable::createFromFormat('!Y-m-d', trim($issueNode->textContent), new DateTimeZone('UTC'));
        Assert::assertInstanceOf(DateTimeImmutable::class, $original);
        $today = new DateTimeImmutable(gmdate('Y-m-d'), new DateTimeZone('UTC'));
        $interval = $original->diff($today);
        $days = (int) $interval->format('%R%a');

        $nodes = $xpath->query('//*[substring(local-name(), string-length(local-name()) - 3) = "Date"]');
        foreach ($nodes === false ? [] : $nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $text = trim($node->textContent);
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? DateTimeImmutable::createFromFormat('!Y-m-d', $text, new DateTimeZone('UTC')) : false;
            if ($date !== false) {
                $node->textContent = $date->modify(sprintf('%+d days', $days))->format('Y-m-d');
            }
        }
    }

    /**
     * The invoice with the company of one party replaced: its VAT identifier, its country and, when
     * the party has one, its legal registration identifier. Every other node, and the other party,
     * stay as they are.
     *
     * @param  string  $party  AccountingSupplierParty or AccountingCustomerParty
     */
    public static function withPartyCompany(string $xml, string $party, string $vatId, string $countryCode): string
    {
        Assert::assertContains($party, ['AccountingSupplierParty', 'AccountingCustomerParty']);

        $dom = new DOMDocument;
        $dom->loadXML($xml, LIBXML_NONET);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', self::NS_CAC);
        $xpath->registerNamespace('cbc', self::NS_CBC);
        $base = sprintf('/*/cac:%s/cac:Party', $party);

        $vat = $xpath->query($base.'/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID="VAT"]/cbc:CompanyID');
        $vatNode = $vat === false ? null : $vat->item(0);
        Assert::assertInstanceOf(DOMElement::class, $vatNode);
        $vatNode->textContent = $vatId;

        $country = $xpath->query($base.'/cac:PostalAddress/cac:Country/cbc:IdentificationCode');
        $countryNode = $country === false ? null : $country->item(0);
        Assert::assertInstanceOf(DOMElement::class, $countryNode);
        $countryNode->textContent = strtoupper($countryCode);

        $legal = $xpath->query($base.'/cac:PartyLegalEntity/cbc:CompanyID');
        $legalNode = $legal === false ? null : $legal->item(0);
        if ($legalNode instanceof DOMElement) {
            $legalNode->removeAttribute('schemeID');
            $legalNode->textContent = $vatId;
        }

        return (string) $dom->saveXML();
    }

    /**
     * The invoice with the seller and buyer electronic addresses set to the given participants.
     */
    private static function routed(Invoice $invoice, ParticipantId $buyer, ParticipantId $seller): Invoice
    {
        return $invoice
            ->withSeller(self::reachedAt($invoice->seller, $seller))
            ->withBuyer(self::reachedAt($invoice->buyer, $buyer));
    }

    private static function reachedAt(Party $party, ParticipantId $participant): Party
    {
        $scheme = ElectronicAddressScheme::tryFrom($participant->icd);
        Assert::assertNotNull($scheme, sprintf('The participant scheme %s is not an electronic address scheme.', $participant->icd));

        return new Party(
            name: $party->name,
            tradingName: $party->tradingName,
            identifiers: $party->identifiers,
            legalRegistration: $party->legalRegistration,
            vatIdentifier: $party->vatIdentifier,
            taxRegistration: $party->taxRegistration,
            additionalLegalInformation: $party->additionalLegalInformation,
            electronicAddress: new ElectronicAddress($participant->value, $scheme),
            address: $party->address,
            contact: $party->contact,
        );
    }
}
