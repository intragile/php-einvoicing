<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

use DateTimeImmutable;
use DOMElement;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\PaymentInstructions;

/**
 * Writes payment instructions (BG-16) as cac:PaymentMeans; the inverse of PaymentMapper.
 *
 * The first cac:PaymentMeans carries the means code, the remittance information, the card, the
 * mandate and the first payee account. Each further credit transfer (BG-17) gets its own
 * cac:PaymentMeans with the same means code and remittance information, without the payment means
 * text (BT-82), which may occur only once (UBL-SR-46).
 *
 * A credit note keeps its due date (BT-9) in cac:PaymentMeans; without payment instructions it is
 * written in a cac:PaymentMeans that holds only means code 1 and the due date.
 *
 * @internal
 */
final class PaymentWriter
{
    /**
     * Means code ("Instrument not defined", UNTDID 4461) of the cac:PaymentMeans that carries
     * only the due date (BT-9) of a credit note without payment instructions, because UBL makes
     * cbc:PaymentMeansCode mandatory. PaymentMapper reads it back as BT-9 alone.
     */
    public const DUE_DATE_ONLY_MEANS_CODE = '1';

    public function __construct(private readonly UblOutput $out) {}

    /**
     * @param  ?DateTimeImmutable  $dueDate  the payment due date (BT-9) of a credit note, which UBL keeps in cac:PaymentMeans
     */
    public function payment(DOMElement $root, ?PaymentInstructions $payment, ?DateTimeImmutable $dueDate): void
    {
        if ($payment === null) {
            if ($dueDate !== null) {
                $means = $this->out->add($root, 'cac:PaymentMeans');
                $this->out->text($means, 'cbc:PaymentMeansCode', self::DUE_DATE_ONLY_MEANS_CODE);
                $this->out->date($means, 'cbc:PaymentDueDate', $dueDate);
            }

            return;
        }
        $out = $this->out;
        $transfers = $payment->creditTransfers;

        $means = $this->means($root, $payment);
        $out->date($means, 'cbc:PaymentDueDate', $dueDate);
        $out->text($means, 'cbc:PaymentID', $payment->remittanceInformation);

        if ($payment->card !== null) {
            $card = $out->add($means, 'cac:CardAccount');
            $out->text($card, 'cbc:PrimaryAccountNumberID', $payment->card->primaryAccountNumber);
            $out->text($card, 'cbc:NetworkID', Placeholder::CARD_NETWORK);
            $out->text($card, 'cbc:HolderName', $payment->card->holderName);
        }

        if ($transfers !== []) {
            $this->account($means, array_shift($transfers));
        }

        $debit = $payment->directDebit;
        if ($debit !== null && ($debit->mandateReference !== null || $debit->debitedAccountIdentifier !== null)) {
            $mandate = $out->add($means, 'cac:PaymentMandate');
            $out->text($mandate, 'cbc:ID', $debit->mandateReference);
            if ($debit->debitedAccountIdentifier !== null) {
                $out->text($out->add($mandate, 'cac:PayerFinancialAccount'), 'cbc:ID', $debit->debitedAccountIdentifier);
            }
        }

        foreach ($transfers as $transfer) {
            $further = $this->means($root, $payment, withText: false);
            $out->text($further, 'cbc:PaymentID', $payment->remittanceInformation);
            $this->account($further, $transfer);
        }
    }

    private function means(DOMElement $root, PaymentInstructions $payment, bool $withText = true): DOMElement
    {
        $means = $this->out->add($root, 'cac:PaymentMeans');
        $this->out->text($means, 'cbc:PaymentMeansCode', $payment->meansCode->value, ['name' => $withText ? $payment->meansText : null]);

        return $means;
    }

    private function account(DOMElement $means, CreditTransfer $transfer): void
    {
        $out = $this->out;
        $account = $out->add($means, 'cac:PayeeFinancialAccount');
        $out->text($account, 'cbc:ID', $transfer->accountIdentifier);
        $out->text($account, 'cbc:Name', $transfer->accountName);
        if ($transfer->serviceProviderIdentifier !== null) {
            $out->text($out->add($account, 'cac:FinancialInstitutionBranch'), 'cbc:ID', $transfer->serviceProviderIdentifier);
        }
    }
}
