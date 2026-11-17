<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\France;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\Model\Item;
use IntrAgile\EInvoicing\Model\LineVat;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\Price;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use InvalidArgumentException;

/**
 * GROUP or INFORMATION line (EXT-FR-FE-163) of an EXTENDED-CTC-FR invoice.
 *
 * The EXTENDED-CTC-FR rules sum only DETAIL lines and lines without a sub-type, so these lines are
 * held by FrenchInvoice instead of the invoice lines (BG-25) and stay out of the document totals.
 * Their amounts are informative and written as given.
 */
final readonly class InformationalLine
{
    /** Quantity (BT-129). */
    public ?BigDecimal $quantity;

    /** Line net amount (BT-131). */
    public ?BigDecimal $netAmount;

    /**
     * @param  BigDecimal|numeric-string|int|null  $quantity
     * @param  BigDecimal|numeric-string|int|null  $netAmount
     *
     * @throws InvalidArgumentException when the sub-type is DETAIL or the French data has a sub-type
     */
    public function __construct(
        /** Line identifier (BT-126). */
        public string $id,
        /** Line sub-type (EXT-FR-FE-163): GROUP or INFORMATION. */
        public LineSubType $subType,
        /** Item information (BG-31). */
        public Item $item,
        BigDecimal|string|int|float|null $quantity = null,
        /** Quantity unit of measure code (BT-130). */
        public ?UnitCode $unit = null,
        /** Price details (BG-29). */
        public ?Price $price = null,
        /** Line VAT information (BG-30). */
        public ?LineVat $vat = null,
        BigDecimal|string|int|float|null $netAmount = null,
        /** Line note (BT-127). */
        public ?string $note = null,
        /** Line period (BG-26). */
        public ?Period $period = null,
        /** Further EXTENDED-CTC-FR data of the line; its sub-type stays null. */
        public FrenchLine $french = new FrenchLine,
    ) {
        if ($subType === LineSubType::Detail) {
            throw new InvalidArgumentException(sprintf(
                'Informational line "%s" must be a GROUP or INFORMATION line (EXT-FR-FE-163); a DETAIL line is an invoice line.',
                $id,
            ));
        }
        if ($french->subType !== null) {
            throw new InvalidArgumentException(sprintf(
                'Informational line "%s" takes its sub-type (EXT-FR-FE-163) from InformationalLine::$subType, not from its French data.',
                $id,
            ));
        }
        $this->quantity = Decimal::ofNullable($quantity, 'BT-129');
        $this->netAmount = Decimal::ofNullable($netAmount, 'BT-131');
    }
}
