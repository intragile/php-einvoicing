<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Model;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\UnitCode;

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
     * @param  list<LineAllowance>  $allowances  Invoice line allowances (BG-27).
     * @param  list<LineCharge>  $charges  Invoice line charges (BG-28).
     */
    public function __construct(
        /** Invoice line identifier (BT-126). */
        public string $id,
        BigDecimal|string|int $quantity,
        /** Invoiced quantity unit of measure code (BT-130). */
        public UnitCode $unit,
        /** Price details (BG-29). */
        public Price $price,
        /** Item information (BG-31). */
        public Item $item,
        /** Line VAT information (BG-30). */
        public LineVat $vat,
        BigDecimal|string|int|null $netAmount = null,
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
    ) {
        $this->quantity = BigDecimal::of($quantity);
        $this->netAmount = $netAmount === null ? null : BigDecimal::of($netAmount);
    }

    public function withNetAmount(BigDecimal|string|int|null $netAmount): self
    {
        return $this->copy(['netAmount' => $netAmount]);
    }

    public function withQuantity(BigDecimal|string|int $quantity): self
    {
        return $this->copy(['quantity' => $quantity]);
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
     * @param  array{quantity?: BigDecimal|string|int, price?: Price, vat?: LineVat, netAmount?: BigDecimal|string|int|null, note?: string|null, period?: Period|null, allowances?: list<LineAllowance>, charges?: list<LineCharge>}  $changes
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
        );
    }
}
