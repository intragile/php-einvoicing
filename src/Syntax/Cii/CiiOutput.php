<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cii;

use DateTimeImmutable;
use DOMElement;
use IntrAgile\EInvoicing\Syntax\XmlOutput;

/**
 * A CII CrossIndustryInvoice under construction. Dates are written in format 102 (CCYYMMDD), the
 * only format the CEN binding allows.
 *
 * @internal
 */
final class CiiOutput extends XmlOutput
{
    public function __construct()
    {
        parent::__construct(
            ['rsm' => CiiDocument::RSM, 'ram' => CiiDocument::RAM, 'udt' => CiiDocument::UDT, 'qdt' => CiiDocument::QDT],
            CiiDocument::RSM,
            'rsm:CrossIndustryInvoice',
        );
    }

    /**
     * Appends a date element holding the date string, e.g. ram:IssueDateTime with
     * udt:DateTimeString; a null date writes nothing.
     */
    public function date(DOMElement $parent, string $name, ?DateTimeImmutable $date, string $string = 'udt:DateTimeString'): void
    {
        if ($date !== null) {
            $this->text($this->add($parent, $name), $string, $date->format('Ymd'), ['format' => '102']);
        }
    }

    /**
     * Appends an indicator element, e.g. ram:ChargeIndicator with udt:Indicator.
     */
    public function indicator(DOMElement $parent, string $name, bool $value): void
    {
        $this->text($this->add($parent, $name), 'udt:Indicator', $value ? 'true' : 'false');
    }

    /**
     * Appends an element that holds a single child with text, e.g. ram:BuyerOrderReferencedDocument
     * with ram:IssuerAssignedID; a null value writes nothing.
     */
    public function wrapped(DOMElement $parent, string $name, string $child, ?string $value): void
    {
        if ($value !== null) {
            $this->text($this->add($parent, $name), $child, $value);
        }
    }
}
