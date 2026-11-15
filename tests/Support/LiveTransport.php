<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\CodeList\ElectronicAddressScheme;
use IntrAgile\EInvoicing\Model\ElectronicAddress;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Syntax\Cii\CiiWriter;
use IntrAgile\EInvoicing\Syntax\Syntax;
use IntrAgile\EInvoicing\Syntax\Ubl\UblWriter;
use IntrAgile\EInvoicing\Tests\Fixtures;
use IntrAgile\EInvoicing\Transport\DeliveryState;
use IntrAgile\EInvoicing\Transport\DeliveryStatus;
use IntrAgile\EInvoicing\Transport\Http\Redactor;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

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
     * and the seller electronic address too when one is given.
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
