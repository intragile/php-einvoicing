<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Flux10;

use DateTimeImmutable;
use DateTimeZone;
use DOMElement;
use IntrAgile\EInvoicing\Syntax\XmlOutput;
use RuntimeException;

/**
 * A Flux 10 e-reporting transmission under construction. Flux 10 documents have no namespace:
 * every element, the root included, is unqualified.
 *
 * Dates are written as CCYYMMDD; the creation timestamp as CCYYMMDDHHMMSS in French local time,
 * since the format carries no time zone.
 *
 * @internal
 */
final class Flux10Output extends XmlOutput
{
    public const TIME_ZONE = 'Europe/Paris';

    public function __construct()
    {
        parent::__construct([], null, 'Report');
    }

    /**
     * Appends an empty unqualified element.
     */
    public function add(DOMElement $parent, string $name): DOMElement
    {
        $document = $parent->ownerDocument ?? throw new RuntimeException('The element belongs to no document.');
        $element = $document->createElement($name);
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Appends a CCYYMMDD date; a null value writes nothing.
     */
    public function date(DOMElement $parent, string $name, ?DateTimeImmutable $date): void
    {
        if ($date !== null) {
            $this->text($parent, $name, $date->format('Ymd'));
        }
    }

    /**
     * Appends a CCYYMMDDHHMMSS timestamp in French local time.
     */
    public function timestamp(DOMElement $parent, string $name, DateTimeImmutable $at): void
    {
        $this->text($parent, $name, $at->setTimezone(new DateTimeZone(self::TIME_ZONE))->format('YmdHis'));
    }
}
