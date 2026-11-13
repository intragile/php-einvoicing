<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Cii\CiiReader;
use IntrAgile\EInvoicing\Syntax\ReadResult;

/**
 * The CII invoice extracted from a Factur-X / ZUGFeRD hybrid PDF, with what the PDF says about it.
 *
 * The profile comes from the guideline identifier (BT-24) inside the XML, which is authoritative;
 * the XMP conformance level is reported as found, and a disagreement is a warning.
 */
final readonly class FacturXDocument
{
    /**
     * @param  string  $xml  the embedded XML, byte for byte
     * @param  string  $attachmentName  the file name it is embedded under
     * @param  FacturXProfile|null  $profile  the profile of the XML's guideline identifier, or null
     *                                        when the identifier is missing or not a known profile
     * @param  AfRelationship|null  $relationship  the file's /AFRelationship, or null when it is
     *                                             missing or not one Factur-X allows
     * @param  string|null  $xmpConformanceLevel  the XMP fx:ConformanceLevel, trimmed
     * @param  int|null  $pdfaPart  the XMP pdfaid:part
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $xml,
        public string $attachmentName,
        public ?FacturXProfile $profile,
        public ?AfRelationship $relationship,
        public ?string $xmpConformanceLevel,
        public ?int $pdfaPart,
        public array $warnings = [],
    ) {}

    /**
     * The embedded invoice read into the EN 16931 model.
     *
     * @throws UnsupportedDocument when the profile carries no invoice lines (MINIMUM, BASIC WL),
     *                             so the XML is not a complete invoice, or when the XML is not a
     *                             readable CII invoice
     * @throws UnsafeXml when the XML carries a DOCTYPE declaration
     */
    public function invoice(): ReadResult
    {
        if ($this->profile?->hasLines() === false) {
            throw new UnsupportedDocument(sprintf(
                'A %s profile document carries no invoice lines, so it cannot be read as an EN 16931 invoice; use the embedded XML directly.',
                $this->profile->value,
            ));
        }

        return (new CiiReader)->read($this->xml);
    }
}
