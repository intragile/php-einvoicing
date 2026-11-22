<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Qr;

use DateTimeImmutable;
use DOMElement;
use DOMXPath;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Fa3\Fa3Format;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use InvalidArgumentException;

/**
 * The seller NIP (Podmiot1/DaneIdentyfikacyjne/NIP) and the issue date (Fa/P_1) of an FA(3)
 * invoice, as the verification links need them.
 *
 * @internal
 */
final readonly class Fa3InvoiceFacts
{
    private function __construct(
        public string $sellerNip,
        public DateTimeImmutable $issueDate,
    ) {}

    /**
     * @throws InvalidArgumentException when either value is missing or the issue date is ill-formed
     * @throws UnsafeXml when the document carries a DOCTYPE declaration or declares entities
     * @throws UnsupportedDocument when the document is empty or not well-formed XML
     */
    public static function read(string $invoiceXml): self
    {
        $xpath = new DOMXPath(SafeXml::load($invoiceXml));
        $xpath->registerNamespace('fa', Fa3Format::NS);

        $nip = self::single($xpath, '/fa:Faktura/fa:Podmiot1/fa:DaneIdentyfikacyjne/fa:NIP', 'Podmiot1/DaneIdentyfikacyjne/NIP');
        $p1 = self::single($xpath, '/fa:Faktura/fa:Fa/fa:P_1', 'Fa/P_1');

        $issueDate = DateTimeImmutable::createFromFormat('!Y-m-d', $p1);
        if ($issueDate === false || $issueDate->format('Y-m-d') !== $p1) {
            throw new InvalidArgumentException(sprintf('Invalid issue date Fa/P_1 "%s": expected YYYY-MM-DD.', $p1));
        }

        return new self($nip, $issueDate);
    }

    private static function single(DOMXPath $xpath, string $query, string $label): string
    {
        $nodes = $xpath->query($query);
        $node = $nodes === false || $nodes->length !== 1 ? null : $nodes->item(0);
        if (! $node instanceof DOMElement) {
            throw new InvalidArgumentException(sprintf('The FA(3) invoice must contain exactly one %s.', $label));
        }

        return trim($node->textContent);
    }
}
