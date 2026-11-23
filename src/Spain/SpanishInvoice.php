<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Spain\Verifactu\AltaOperation;
use IntrAgile\EInvoicing\Spain\Verifactu\CorrectionAmounts;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxParty;

/**
 * Spanish (AEAT) data attached to an EN 16931 invoice through {@see Invoice::withExtension()}.
 *
 * Every field is optional; an invoice without an override is described as a domestic F1 and
 * the record mapper derives the rest from the EN 16931 data.
 */
final readonly class SpanishInvoice
{
    /**
     * @param  list<InvoiceId>  $substituted  invoices replaced by a substitution invoice
     * @param  list<TaxParty>  $recipients  recipients when the invoice carries no buyer VAT identifier
     * @param  array<string, BreakdownOverride>  $breakdown  keyed by {@see self::breakdownKey()}
     */
    public function __construct(
        public ?InvoiceType $type = null,
        public ?CorrectionType $correctionType = null,
        public ?CorrectionAmounts $correctionAmounts = null,
        public array $substituted = [],
        public ?string $description = null,
        public bool $simplifiedArt7273 = false,
        public bool $withoutRecipientArt61d = false,
        public ?IssuedBy $issuedBy = null,
        public ?TaxParty $thirdParty = null,
        public array $recipients = [],
        public bool $coupon = false,
        public array $breakdown = [],
        public ?EuroAmounts $euroAmounts = null,
        public ?string $externalReference = null,
        public ?string $agreementNumber = null,
        public ?string $systemAgreementId = null,
        public AltaOperation $operation = AltaOperation::Initial,
    ) {
        if ($correctionType !== null && $type !== null && ! $type->isCorrective()) {
            throw new InvalidRecord(sprintf('A correction type needs a corrective invoice type, "%s" is not one.', $type->value));
        }

        if ($thirdParty !== null && $issuedBy !== IssuedBy::ThirdParty) {
            throw new InvalidRecord('A third party can only be named when the invoice is issued by a third party (EmitidaPorTerceroODestinatario T).');
        }
    }

    /**
     * Key of a breakdown group: the VAT category code and the rate without trailing zeros
     * ("S|21", "S|10.5"), or "-" for a category without a rate ("E|-").
     */
    public static function breakdownKey(VatCategory $category, ?BigDecimal $rate): string
    {
        $text = $rate === null ? '-' : (string) $rate->strippedOfTrailingZeros();

        return $category->value.'|'.$text;
    }
}
