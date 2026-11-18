<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Poland;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use IntrAgile\EInvoicing\Model\Support\Decimal;
use InvalidArgumentException;

/**
 * Polish FA(3) invoice extension, attached to an invoice through its extension bag. It holds the
 * FA(3) data the EN 16931 model does not carry; the defaults describe a plain VAT invoice.
 */
final readonly class PolishInvoice
{
    public const MAX_THIRD_PARTIES = 100;

    public const MAX_WAREHOUSE_DOCUMENTS = 1000;

    public const MAX_PARTIAL_ADVANCES = 31;

    public const MAX_ADVANCE_INVOICES = 100;

    public const MAX_FACTOR_BANK_ACCOUNTS = 20;

    /**
     * VAT in PLN of the buckets 1 to 4 (P_14_1W to P_14_4W), keyed by the bucket number.
     *
     * @var array<int, BigDecimal>
     */
    public array $vatInPln;

    /** Exchange rate of an advance invoice (KursWalutyZ). */
    public ?BigDecimal $exchangeRate;

    /**
     * @param  list<ThirdParty>  $thirdParties  Third parties (Podmiot3), at most 100.
     * @param  list<string>  $warehouseDocuments  Warehouse document numbers (WZ), at most 1000.
     * @param  list<PartialAdvance>  $partialAdvances  Partial advance payments (ZaliczkaCzesciowa),
     *                                                 at most 31; only on ZAL, KOR_ZAL and ROZ.
     * @param  list<AdvanceInvoiceReference>  $advanceInvoices  Settled advance invoices
     *                                                          (FakturaZaliczkowa), at most 100.
     * @param  array<int|string, BigDecimal|numeric-string|int>  $vatInPln  VAT in PLN keyed by the
     *                                                                      VatBucket value 1 to 4
     *                                                                      (P_14_xW); supplied by
     *                                                                      the issuer, never computed.
     * @param  BigDecimal|numeric-string|int|null  $exchangeRate
     * @param  list<KeyValue>  $descriptions  Additional descriptions (DodatkowyOpis).
     * @param  list<BankAccount>  $bankAccounts  Bank accounts (RachunekBankowy); when given, they
     *                                           replace the accounts derived from the credit transfers.
     * @param  list<BankAccount>  $factorBankAccounts  Factor bank accounts (RachunekBankowyFaktora),
     *                                                 at most 20.
     *
     * @throws InvalidArgumentException when a VAT in PLN key is not a bucket from 1 to 4, a list is
     *                                  longer than FA(3) allows, a correction is given with an
     *                                  explicit non-correction kind, or partial advances are
     *                                  given with an explicit kind other than ZAL, KOR_ZAL or ROZ
     */
    public function __construct(
        /** Invoice kind (RodzajFaktury); null derives it from the invoice type code (BT-3). */
        public ?InvoiceKind $kind = null,
        /** Annotations (Adnotacje). */
        public Annotations $annotations = new Annotations,
        /** FA(3) seller data (Podmiot1) the EN 16931 seller does not carry. */
        public ?PolishParty $seller = null,
        /** FA(3) buyer data (Podmiot2) the EN 16931 buyer does not carry. */
        public ?PolishParty $buyer = null,
        /** The buyer is a unit of a local government body (JST). */
        public bool $buyerIsJstUnit = false,
        /** The buyer is a member of a VAT group (GV). */
        public bool $buyerIsGvMember = false,
        public array $thirdParties = [],
        /** Place of issue (P_1M). */
        public ?string $placeOfIssue = null,
        public array $warehouseDocuments = [],
        /** Correction data of a corrective invoice. */
        public ?Correction $correction = null,
        public array $partialAdvances = [],
        public array $advanceInvoices = [],
        /** The order an advance invoice refers to (Zamowienie). */
        public ?Order $order = null,
        array $vatInPln = [],
        BigDecimal|string|int|float|null $exchangeRate = null,
        public array $descriptions = [],
        /** Charges and deductions (Rozliczenie). */
        public ?Settlement $settlement = null,
        /** Payment form (FormaPlatnosci); overrides the form derived from the payment means code (BT-81). */
        public ?PaymentForm $paymentForm = null,
        public array $bankAccounts = [],
        public array $factorBankAccounts = [],
        /** Invoice footer (Stopka). */
        public ?Footer $footer = null,
        /** Transaction terms (WarunkiTransakcji). */
        public ?TransactionTerms $transactionTerms = null,
        /** Authorised party (PodmiotUpowazniony), kept verbatim. */
        public ?RawFragment $authorizedParty = null,
        /** Attachment block (Zalacznik), kept verbatim. */
        public ?RawFragment $attachment = null,
        /** Creation timestamp (DataWytworzeniaFa); null takes the writer clock. */
        public ?DateTimeImmutable $createdAt = null,
        /** Name of the issuing system (SystemInfo); null omits it. */
        public ?string $systemInfo = null,
        /** Invoice under article 109(3d) of the VAT Act (FP). */
        public bool $article109Invoice = false,
        /** Related parties under article 32(2) of the VAT Act (TP). */
        public bool $relatedParties = false,
        /** Excise refund information for farmers (ZwrotAkcyzy). */
        public bool $exciseRefund = false,
    ) {
        $this->vatInPln = self::vatInPln($vatInPln);
        $this->exchangeRate = Decimal::ofNullable($exchangeRate, 'KursWalutyZ');

        Limit::count($thirdParties, 'Podmiot3', self::MAX_THIRD_PARTIES);
        Limit::count($warehouseDocuments, 'WZ', self::MAX_WAREHOUSE_DOCUMENTS);
        Limit::count($partialAdvances, 'ZaliczkaCzesciowa', self::MAX_PARTIAL_ADVANCES);
        Limit::count($advanceInvoices, 'FakturaZaliczkowa', self::MAX_ADVANCE_INVOICES);
        Limit::count($factorBankAccounts, 'RachunekBankowyFaktora', self::MAX_FACTOR_BANK_ACCOUNTS);

        if ($correction !== null && $kind !== null && ! $kind->isCorrection()) {
            throw new InvalidArgumentException(sprintf(
                'Correction data needs a corrective invoice kind, not %s.',
                $kind->value,
            ));
        }
        if ($partialAdvances !== [] && $kind !== null && ! in_array($kind, [InvoiceKind::Zal, InvoiceKind::KorZal, InvoiceKind::Roz], true)) {
            throw new InvalidArgumentException(sprintf(
                'Partial advance payments (ZaliczkaCzesciowa) need the kind ZAL, KOR_ZAL or ROZ, not %s.',
                $kind->value,
            ));
        }
    }

    /**
     * The VAT in PLN supplied for the bucket, or null when none was supplied.
     */
    public function vatInPlnFor(VatBucket $bucket): ?BigDecimal
    {
        return $this->vatInPln[(int) $bucket->value] ?? null;
    }

    /**
     * @param  array<int|string, BigDecimal|numeric-string|int|float>  $amounts
     * @return array<int, BigDecimal>
     */
    private static function vatInPln(array $amounts): array
    {
        $buckets = [];
        foreach ($amounts as $key => $amount) {
            $bucket = VatBucket::tryFrom((string) $key);
            $element = $bucket?->plnVatElement();
            if ($bucket === null || $element === null) {
                throw new InvalidArgumentException(sprintf(
                    'VAT in PLN exists only for the buckets 1 to 4 (P_14_1W to P_14_4W), got "%s".',
                    $key,
                ));
            }
            $buckets[(int) $bucket->value] = Decimal::of($amount, $element);
        }
        ksort($buckets);

        return $buckets;
    }
}
