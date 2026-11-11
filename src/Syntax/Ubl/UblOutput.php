<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\Syntax\XmlOutput;

/**
 * A UBL Invoice or CreditNote under construction. Amounts carry their currency in currencyID;
 * dates are written as Y-m-d.
 *
 * @internal
 */
final class UblOutput extends XmlOutput
{
    public function __construct(bool $creditNote)
    {
        parent::__construct(
            ['cac' => UblDocument::CAC, 'cbc' => UblDocument::CBC],
            $creditNote ? UblDocument::CREDIT_NOTE : UblDocument::INVOICE,
            $creditNote ? 'CreditNote' : 'Invoice',
        );
    }

    public function amount(DOMElement $parent, string $name, ?BigDecimal $value, CurrencyCode $currency): void
    {
        $this->decimal($parent, $name, $value, ['currencyID' => $currency->value]);
    }

    public function date(DOMElement $parent, string $name, ?DateTimeImmutable $date): void
    {
        if ($date !== null) {
            $this->text($parent, $name, $date->format('Y-m-d'));
        }
    }

    /**
     * Appends cac:TaxScheme with the given identifier; UBL requires the element in every tax category.
     */
    public function taxScheme(DOMElement $parent, string $identifier): void
    {
        $this->text($this->add($parent, 'cac:TaxScheme'), 'cbc:ID', $identifier);
    }
}
