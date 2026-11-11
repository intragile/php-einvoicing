<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cii;

use DOMElement;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\PaymentInstructions;

/**
 * Writes payment instructions (BG-16) as ram:SpecifiedTradeSettlementPaymentMeans; the inverse of
 * PaymentMapper.
 *
 * The first payment means carries the means code and text, the card (BG-18), the debited account
 * (BT-91) and the first payee account. Each further credit transfer (BG-17) gets its own payment
 * means with the same means code and text. The remittance information (BT-83), the creditor
 * identifier (BT-90) and the mandate reference (BT-89) sit at settlement level and are written by
 * CiiWriter.
 *
 * @internal
 */
final class PaymentWriter
{
    /** An IBAN without spaces (ISO 13616): country code, check digits, up to 30 characters. */
    private const IBAN = '/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/';

    public function __construct(private readonly CiiOutput $out) {}

    public function means(DOMElement $settlement, PaymentInstructions $payment): void
    {
        $out = $this->out;
        $transfers = $payment->creditTransfers;

        $means = $this->start($settlement, $payment);
        if ($payment->card !== null) {
            $card = $out->add($means, 'ram:ApplicableTradeSettlementFinancialCard');
            $out->text($card, 'ram:ID', $payment->card->primaryAccountNumber);
            $out->text($card, 'ram:CardholderName', $payment->card->holderName);
        }
        $out->wrapped($means, 'ram:PayerPartyDebtorFinancialAccount', 'ram:IBANID', $payment->directDebit?->debitedAccountIdentifier);
        if ($transfers !== []) {
            $this->account($means, array_shift($transfers));
        }

        foreach ($transfers as $transfer) {
            $this->account($this->start($settlement, $payment), $transfer);
        }
    }

    private function start(DOMElement $settlement, PaymentInstructions $payment): DOMElement
    {
        $means = $this->out->add($settlement, 'ram:SpecifiedTradeSettlementPaymentMeans');
        $this->out->text($means, 'ram:TypeCode', $payment->meansCode->value);
        $this->out->text($means, 'ram:Information', $payment->meansText);

        return $means;
    }

    /**
     * BT-84 as ram:IBANID when it is an IBAN, otherwise as ram:ProprietaryID; BT-86 beside it.
     */
    private function account(DOMElement $means, CreditTransfer $transfer): void
    {
        $out = $this->out;
        $account = $out->add($means, 'ram:PayeePartyCreditorFinancialAccount');
        $isIban = preg_match(self::IBAN, $transfer->accountIdentifier) === 1;
        $out->text($account, 'ram:IBANID', $isIban ? $transfer->accountIdentifier : null);
        $out->text($account, 'ram:AccountName', $transfer->accountName);
        $out->text($account, 'ram:ProprietaryID', $isIban ? null : $transfer->accountIdentifier);
        $out->wrapped($means, 'ram:PayeeSpecifiedCreditorFinancialInstitution', 'ram:BICID', $transfer->serviceProviderIdentifier);
    }
}
