<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cdar;

use DateTimeImmutable;
use DateTimeZone;
use DOMElement;
use IntrAgile\EInvoicing\Syntax\Cii\CiiDocument;
use IntrAgile\EInvoicing\Syntax\XmlOutput;

/**
 * A UN/CEFACT CrossDomainAcknowledgementAndResponse (D22B) under construction. It shares the
 * ram, udt and qdt namespaces with CII.
 *
 * Timestamps are written in format 204 (CCYYMMDDHHMMSS), which carries no time zone; they are
 * converted to French local time first, the time the French lifecycle messages are read in.
 *
 * @internal
 */
final class CdarOutput extends XmlOutput
{
    public const RSM = 'urn:un:unece:uncefact:data:standard:CrossDomainAcknowledgementAndResponse:100';

    public const TIME_ZONE = 'Europe/Paris';

    public function __construct()
    {
        parent::__construct(
            ['rsm' => self::RSM, 'ram' => CiiDocument::RAM, 'udt' => CiiDocument::UDT, 'qdt' => CiiDocument::QDT],
            self::RSM,
            'rsm:CrossDomainAcknowledgementAndResponse',
        );
    }

    /**
     * Appends a timestamp element holding a format 204 udt:DateTimeString in French local time;
     * a null value writes nothing.
     */
    public function timestamp(DOMElement $parent, string $name, ?DateTimeImmutable $at): void
    {
        if ($at !== null) {
            $local = $at->setTimezone(new DateTimeZone(self::TIME_ZONE));
            $this->text($this->add($parent, $name), 'udt:DateTimeString', $local->format('YmdHis'), ['format' => '204']);
        }
    }

    /**
     * Appends a date element holding a format 102 date string, e.g. ram:FormattedIssueDateTime
     * with qdt:DateTimeString; a null value writes nothing.
     */
    public function date(DOMElement $parent, string $name, ?DateTimeImmutable $date, string $string = 'udt:DateTimeString'): void
    {
        if ($date !== null) {
            $this->text($this->add($parent, $name), $string, $date->format('Ymd'), ['format' => '102']);
        }
    }

    /**
     * Appends an indicator element holding the given child, udt:Indicator or udt:IndicatorString.
     */
    public function indicator(DOMElement $parent, string $name, bool $value, string $child = 'udt:Indicator'): void
    {
        $this->text($this->add($parent, $name), $child, $value ? 'true' : 'false');
    }
}
