<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\Model\Support\Decimal;

/**
 * Invoice line (BG-25).
 */
final readonly class InvoiceLine
{
    /** Invoiced quantity (BT-129); negative on a corrective line. */
    public BigDecimal $quantity;

    /** Invoice line net amount (BT-131); null until the calculator fills it. */
    public ?BigDecimal $netAmount;

    /**
     * @param  BigDecimal|numeric-string|int  $quantity
     * @param  BigDecimal|numeric-string|int|null  $netAmount
     * @param  list<LineAllowance>  $allowances  Invoice line allowances (BG-27).
     * @param  list<LineCharge>  $charges  Invoice line charges (BG-28).
     */
    public function __construct(
        /** Invoice line identifier (BT-126). */
        public string $id,
        BigDecimal|string|int|float $quantity,
        /** Invoiced quantity unit of measure code (BT-130). */
        public UnitCode $unit,
        /** Price details (BG-29). */
        public Price $price,
        /** Item information (BG-31). */
        public Item $item,
        /** Line VAT information (BG-30). */
        public LineVat $vat,
        BigDecimal|string|int|float|null $netAmount = null,
        /** Invoice line note (BT-127). */
        public ?string $note = null,
        /** Invoice line object identifier (BT-128) with scheme (BT-128-1). */
        public ?Identifier $objectIdentifier = null,
        /** Referenced purchase order line reference (BT-132). */
        public ?string $orderLineReference = null,
        /** Invoice line Buyer accounting reference (BT-133). */
        public ?string $buyerAccountingReference = null,
        /** Invoice line period (BG-26). */
        public ?Period $period = null,
        public array $allowances = [],
        public array $charges = [],
        private ?Extensions $extensions = null,
    ) {
        $this->quantity = Decimal::of($quantity, 'BT-129');
        $this->netAmount = Decimal::ofNullable($netAmount, 'BT-131');
    }

    /**
     * Syntax or national data attached to this line beyond EN 16931.
     */
    public function extensions(): Extensions
    {
        return $this->extensions ?? Extensions::none();
    }

    public function withExtension(object $extension): self
    {
        return $this->copy(['extensions' => $this->extensions()->with($extension)]);
    }

    public function withExtensions(Extensions $extensions): self
    {
        return $this->copy(['extensions' => $extensions]);
    }

    /**
     * @param  BigDecimal|numeric-string|int|null  $netAmount
     */
    public function withNetAmount(BigDecimal|string|int|float|null $netAmount): self
    {
        return $this->copy(['netAmount' => Decimal::ofNullable($netAmount, 'BT-131')]);
    }

    /**
     * @param  BigDecimal|numeric-string|int  $quantity
     */
    public function withQuantity(BigDecimal|string|int|float $quantity): self
    {
        return $this->copy(['quantity' => Decimal::of($quantity, 'BT-129')]);
    }

    public function withPrice(Price $price): self
    {
        return $this->copy(['price' => $price]);
    }

    public function withVat(LineVat $vat): self
    {
        return $this->copy(['vat' => $vat]);
    }

    public function withNote(?string $note): self
    {
        return $this->copy(['note' => $note]);
    }

    public function withPeriod(?Period $period): self
    {
        return $this->copy(['period' => $period]);
    }

    public function withAllowance(LineAllowance $allowance): self
    {
        return $this->copy(['allowances' => [...$this->allowances, $allowance]]);
    }

    public function withCharge(LineCharge $charge): self
    {
        return $this->copy(['charges' => [...$this->charges, $charge]]);
    }

    /**
     * @param  array{quantity?: BigDecimal, price?: Price, vat?: LineVat, netAmount?: BigDecimal|null, note?: string|null, period?: Period|null, allowances?: list<LineAllowance>, charges?: list<LineCharge>, extensions?: Extensions}  $changes
     */
    private function copy(array $changes): self
    {
        return new self(
            id: $this->id,
            quantity: $changes['quantity'] ?? $this->quantity,
            unit: $this->unit,
            price: $changes['price'] ?? $this->price,
            item: $this->item,
            vat: $changes['vat'] ?? $this->vat,
            netAmount: array_key_exists('netAmount', $changes) ? $changes['netAmount'] : $this->netAmount,
            note: array_key_exists('note', $changes) ? $changes['note'] : $this->note,
            objectIdentifier: $this->objectIdentifier,
            orderLineReference: $this->orderLineReference,
            buyerAccountingReference: $this->buyerAccountingReference,
            period: array_key_exists('period', $changes) ? $changes['period'] : $this->period,
            allowances: $changes['allowances'] ?? $this->allowances,
            charges: $changes['charges'] ?? $this->charges,
            extensions: $changes['extensions'] ?? $this->extensions,
        );
    }
}
