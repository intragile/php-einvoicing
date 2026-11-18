<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use InvalidArgumentException;

/**
 * The transaction terms of the invoice (WarunkiTransakcji).
 */
final readonly class TransactionTerms
{
    public const MAX_TRANSPORTS = 20;

    /** Contractual exchange rate (KursUmowny). */
    public ?BigDecimal $contractRate;

    /**
     * @param  list<DocumentReference>  $contracts  Contracts (Umowy).
     * @param  list<DocumentReference>  $orders  Orders (Zamowienia).
     * @param  list<string>  $batchNumbers  Goods batch numbers (NrPartiiTowaru).
     * @param  BigDecimal|numeric-string|int|null  $contractRate
     * @param  list<RawFragment>  $transports  Transport blocks (Transport), kept verbatim, at most 20.
     *
     * @throws InvalidArgumentException when only one of the contractual rate and currency is
     *                                  given, or there are more than 20 transport blocks
     */
    public function __construct(
        public array $contracts = [],
        public array $orders = [],
        public array $batchNumbers = [],
        /** Delivery terms, such as an Incoterms code (WarunkiDostawy). */
        public ?string $deliveryTerms = null,
        BigDecimal|string|int|float|null $contractRate = null,
        /** Contractual currency (WalutaUmowna). */
        public ?CurrencyCode $contractCurrency = null,
        public array $transports = [],
        /** Delivery through an intermediary entity (PodmiotPosredniczacy). */
        public bool $intermediary = false,
    ) {
        $this->contractRate = Decimal::ofNullable($contractRate, 'KursUmowny');
        if (($this->contractRate === null) !== ($contractCurrency === null)) {
            throw new InvalidArgumentException('The contractual exchange rate (KursUmowny) and currency (WalutaUmowna) go together.');
        }
        Limit::count($transports, 'Transport', self::MAX_TRANSPORTS);
    }
}
