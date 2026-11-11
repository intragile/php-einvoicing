<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cii;

use DOMElement;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\DirectDebit;
use IntrAgile\EInvoicing\Model\PaymentCard;
use IntrAgile\EInvoicing\Model\PaymentInstructions;

/**
 * Maps ram:SpecifiedTradeSettlementPaymentMeans to payment instructions (BG-16 with BG-17, BG-18
 * and BG-19).
 *
 * As in UBL, CII carries each payee account in its own payment means. The first one gives BT-81,
 * BT-82, the card (BG-18) and the debited account (BT-91); every further one with the same means
 * code adds its payee account as another BG-17. Anything else in a further payment means, or one
 * with a different means code, is not mapped and is reported as ignored.
 *
 * The remittance information (BT-83), the mandate reference (BT-89) and the creditor identifier
 * (BT-90) sit at settlement level in CII and are mapped only when there is a BG-16 to hold them.
 *
 * @internal
 */
final class PaymentMapper
{
    public function __construct(private readonly CiiDocument $document) {}

    public function payment(DOMElement $settlement, ?DOMElement $terms): ?PaymentInstructions
    {
        $d = $this->document;
        $means = $d->all('ram:SpecifiedTradeSettlementPaymentMeans', $settlement);
        if ($means === []) {
            return null;
        }

        $first = $means[0];
        $codeElement = $d->one('ram:TypeCode', $first);
        $code = $d->code(PaymentMeansCode::class, $codeElement);
        if ($code === null) {
            $d->reject($first, 'dropped: no valid payment means code');

            return null;
        }
        $meansText = $d->text('ram:Information', $first);

        $transfers = [$this->creditTransfer($first)];
        foreach (array_slice($means, 1) as $further) {
            $furtherCode = $d->one('ram:TypeCode', $further);
            if ($furtherCode === null || trim($furtherCode->textContent) !== $code->value) {
                continue;
            }
            $d->use($furtherCode);
            $information = $d->one('ram:Information', $further);
            if ($information !== null && $information->textContent === $meansText) {
                $d->use($information);
            }
            $transfers[] = $this->creditTransfer($further);
        }

        return new PaymentInstructions(
            meansCode: $code,
            meansText: $meansText,
            remittanceInformation: $d->text('ram:PaymentReference', $settlement),
            creditTransfers: array_values(array_filter($transfers)),
            card: $this->card($d->one('ram:ApplicableTradeSettlementFinancialCard', $first)),
            directDebit: $this->directDebit($settlement, $terms, $first),
        );
    }

    /**
     * BG-17: the payee account of one payment means, with the payee's financial institution
     * (BT-86), which CII keeps beside the account.
     */
    private function creditTransfer(DOMElement $means): ?CreditTransfer
    {
        $d = $this->document;
        $account = $d->one('ram:PayeePartyCreditorFinancialAccount', $means);
        if ($account === null) {
            return null;
        }
        $identifier = $d->text('ram:IBANID', $account) ?? $d->text('ram:ProprietaryID', $account);
        if ($identifier === null) {
            $d->reject($account, 'dropped: no payment account identifier');

            return null;
        }

        return new CreditTransfer(
            accountIdentifier: $identifier,
            accountName: $d->text('ram:AccountName', $account),
            serviceProviderIdentifier: $d->text('ram:PayeeSpecifiedCreditorFinancialInstitution/ram:BICID', $means),
        );
    }

    private function card(?DOMElement $card): ?PaymentCard
    {
        if ($card === null) {
            return null;
        }
        $d = $this->document;
        $number = $d->text('ram:ID', $card);
        if ($number === null) {
            $d->reject($card, 'dropped: no primary account number');

            return null;
        }

        return new PaymentCard(primaryAccountNumber: $number, holderName: $d->text('ram:CardholderName', $card));
    }

    private function directDebit(DOMElement $settlement, ?DOMElement $terms, DOMElement $means): ?DirectDebit
    {
        $d = $this->document;
        $mandate = $terms === null ? null : $d->text('ram:DirectDebitMandateID', $terms);
        $creditor = $d->text('ram:CreditorReferenceID', $settlement);
        $debited = $d->text('ram:PayerPartyDebtorFinancialAccount/ram:IBANID', $means);

        return $mandate === null && $debited === null && $creditor === null
            ? null
            : new DirectDebit(mandateReference: $mandate, creditorIdentifier: $creditor, debitedAccountIdentifier: $debited);
    }
}
