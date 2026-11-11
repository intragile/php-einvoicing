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
 * cac:PaymentMeans with the same means code and remittance information.
 *
 * @internal
 */
final class PaymentWriter
{
    /**
     * UBL requires a card network identifier, which EN 16931 does not model.
     */
    private const UNKNOWN_CARD_NETWORK = 'NA';

    public function __construct(private readonly UblOutput $out) {}

    /**
     * @param  ?DateTimeImmutable  $dueDate  the payment due date (BT-9) of a credit note, which UBL keeps in cac:PaymentMeans
     */
    public function payment(DOMElement $root, ?PaymentInstructions $payment, ?DateTimeImmutable $dueDate): void
    {
        if ($payment === null) {
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
            $out->text($card, 'cbc:NetworkID', self::UNKNOWN_CARD_NETWORK);
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
            $further = $this->means($root, $payment);
            $out->text($further, 'cbc:PaymentID', $payment->remittanceInformation);
            $this->account($further, $transfer);
        }
    }

    private function means(DOMElement $root, PaymentInstructions $payment): DOMElement
    {
        $means = $this->out->add($root, 'cac:PaymentMeans');
        $this->out->text($means, 'cbc:PaymentMeansCode', $payment->meansCode->value, ['name' => $payment->meansText]);

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
