<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Support;

use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
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
     * Writes a finding of a live test to STDERR, tagged with the test's group.
     */
    public static function report(string $tag, string $line): void
    {
        fwrite(STDERR, PHP_EOL.'['.$tag.'] '.$line.PHP_EOL);
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
}
