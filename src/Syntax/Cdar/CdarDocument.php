<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cdar;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMNode;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Syntax\Cii\CiiDocument;
use IntrAgile\EInvoicing\Syntax\XmlDocument;

/**
 * A loaded UN/CEFACT CrossDomainAcknowledgementAndResponse (D22B).
 *
 * Date strings come in two formats: 204 (CCYYMMDDHHMMSS) for timestamps, which carry no time zone
 * and are read in French local time, and 102 (CCYYMMDD) for dates. The format attribute is
 * required, and each element must hold the format its business term calls for.
 *
 * @internal
 */
final class CdarDocument extends XmlDocument
{
    public const TIMESTAMP_FORMAT = '204';

    public const DATE_FORMAT = '102';

    private const PREFIXES = [
        CdarOutput::RSM => 'rsm',
        CiiDocument::RAM => 'ram',
        CiiDocument::UDT => 'udt',
        CiiDocument::QDT => 'qdt',
    ];

    private const PATTERNS = [self::TIMESTAMP_FORMAT => '!YmdHis', self::DATE_FORMAT => '!Ymd'];

    public function __construct(DOMDocument $document)
    {
        parent::__construct($document, self::PREFIXES);
        $root = $this->root;

        if ($root->namespaceURI !== CdarOutput::RSM || $root->localName !== 'CrossDomainAcknowledgementAndResponse') {
            throw new UnsupportedDocument(sprintf(
                'Unsupported root element {%s}%s; expected a CDAR CrossDomainAcknowledgementAndResponse.',
                (string) $root->namespaceURI,
                (string) $root->localName,
            ));
        }
    }

    /**
     * A format 204 timestamp in French local time; one in another format or invalid is reported
     * and read as null.
     */
    public function timestamp(string $expression, DOMNode $context): ?DateTimeImmutable
    {
        return $this->formatted($expression, $context, self::TIMESTAMP_FORMAT);
    }

    /**
     * A format 204 timestamp in French local time that the model cannot do without.
     */
    public function requiredTimestamp(string $expression, DOMNode $context, string $term): DateTimeImmutable
    {
        $element = $this->one($expression, $context) ?? throw $this->missing($expression, $context, $term);
        $value = $this->parse($element, self::TIMESTAMP_FORMAT)
            ?? throw new UnsupportedDocument(sprintf('%s for %s at %s.', ucfirst($this->invalid($element, 'timestamp')), $term, $this->path($element)));
        $this->use($element);

        return $value;
    }

    /**
     * A format 102 date; one in another format or invalid is reported and read as null.
     */
    public function day(string $expression, DOMNode $context): ?DateTimeImmutable
    {
        return $this->formatted($expression, $context, self::DATE_FORMAT);
    }

    protected function parseDate(DOMElement $element): ?DateTimeImmutable
    {
        $format = $this->attribute($element, 'format');

        return $format === null ? null : $this->parse($element, $format);
    }

    protected function invalidDate(DOMElement $element): string
    {
        return $this->invalid($element, 'date');
    }

    private function formatted(string $expression, DOMNode $context, string $format): ?DateTimeImmutable
    {
        $element = $this->one($expression, $context);
        if ($element === null) {
            return null;
        }
        $value = $this->parse($element, $format);
        if ($value === null) {
            $this->reject($element, $this->invalid($element, $format === self::TIMESTAMP_FORMAT ? 'timestamp' : 'date'));

            return null;
        }
        $this->use($element);

        return $value;
    }

    /**
     * Parses the element when it is in the given format; timestamps are read in French local time.
     */
    private function parse(DOMElement $element, string $format): ?DateTimeImmutable
    {
        $pattern = self::PATTERNS[$format] ?? null;
        if ($pattern === null || $this->attribute($element, 'format') !== $format) {
            return null;
        }
        $value = trim($element->textContent);
        $zone = $format === self::TIMESTAMP_FORMAT ? new DateTimeZone(CdarOutput::TIME_ZONE) : null;
        $parsed = DateTimeImmutable::createFromFormat($pattern, $value, $zone);

        return $parsed !== false && $parsed->format(substr($pattern, 1)) === $value ? $parsed : null;
    }

    private function invalid(DOMElement $element, string $kind): string
    {
        $format = $this->attribute($element, 'format');

        return sprintf("invalid %s '%s'", $kind, trim($element->textContent))
            .($format === null ? ' without format' : " in format '{$format}'");
    }
}
