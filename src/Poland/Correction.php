<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use InvalidArgumentException;

/**
 * The correction data of a corrective invoice (KOR, KOR_ZAL, KOR_ROZ).
 */
final readonly class Correction
{
    public const MAX_INVOICES = 50000;

    public const MAX_BUYERS_BEFORE = 101;

    /** Amount due on the corrected advance invoice before the correction (P_15ZK). */
    public ?BigDecimal $amountBefore;

    /** Exchange rate of the corrected advance invoice (KursWalutyZK). */
    public ?BigDecimal $exchangeRateBefore;

    /**
     * @param  list<CorrectedInvoice>  $invoices  Corrected invoices (DaneFaKorygowanej), 1 to 50000.
     * @param  list<Party>  $buyersBefore  Buyer data before the correction (Podmiot2K), at most 101.
     * @param  BigDecimal|numeric-string|int|null  $amountBefore
     * @param  BigDecimal|numeric-string|int|null  $exchangeRateBefore
     *
     * @throws InvalidArgumentException when a list is empty or longer than FA(3) allows
     */
    public function __construct(
        public array $invoices,
        /** Reason for the correction (PrzyczynaKorekty). */
        public ?string $reason = null,
        /** When the correction takes effect (TypKorekty). */
        public ?CorrectionEffect $effect = null,
        /** Period a correction granting a discount covers (OkresFaKorygowanej). */
        public ?string $period = null,
        /** Correct invoice number when the number itself is corrected (NrFaKorygowany). */
        public ?string $correctedNumber = null,
        /** Seller data before the correction (Podmiot1K). */
        public ?Party $sellerBefore = null,
        public array $buyersBefore = [],
        BigDecimal|string|int|float|null $amountBefore = null,
        BigDecimal|string|int|float|null $exchangeRateBefore = null,
    ) {
        Limit::count($invoices, 'DaneFaKorygowanej', self::MAX_INVOICES, 1);
        Limit::count($buyersBefore, 'Podmiot2K', self::MAX_BUYERS_BEFORE);
        $this->amountBefore = Decimal::ofNullable($amountBefore, 'P_15ZK');
        $this->exchangeRateBefore = Decimal::ofNullable($exchangeRateBefore, 'KursWalutyZK');
    }
}
