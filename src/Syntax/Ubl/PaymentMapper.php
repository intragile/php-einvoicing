<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Ubl;

use DOMElement;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\DirectDebit;
use IntrAgile\EInvoicing\Model\PaymentCard;
use IntrAgile\EInvoicing\Model\PaymentInstructions;

/**
 * Maps cac:PaymentMeans to payment instructions (BG-16 with BG-17, BG-18 and BG-19).
 *
 * EN 16931 has one BG-16 but up to n credit transfers (BG-17), and UBL carries each payee
 * account in its own cac:PaymentMeans. The first cac:PaymentMeans therefore gives BT-81 to BT-83,
 * the card and the mandate; every further cac:PaymentMeans with the same means code adds its
 * payee account as another BG-17. Anything else in a further cac:PaymentMeans, or one with a
 * different means code, is not mapped and is reported as ignored.
 *
 * On a credit note, a single cac:PaymentMeans holding only means code 1 and cbc:PaymentDueDate
 * carries the due date (BT-9) alone and gives no BG-16; see PaymentWriter.
 *
 * The bank assigned creditor identifier (BT-90) is the first seller or payee party identifier
 * with scheme "SEPA".
 *
 * @internal
 */
final class PaymentMapper
{
    public function __construct(private readonly UblDocument $document) {}

    public function payment(): ?PaymentInstructions
    {
        $d = $this->document;
        $means = $d->all('cac:PaymentMeans');
        if ($means === []) {
            return null;
        }

        $first = $means[0];
        if (count($means) === 1 && $this->isDueDateOnly($first)) {
            return null;
        }
        $codeElement = $d->one('cbc:PaymentMeansCode', $first);
        $code = $d->code(PaymentMeansCode::class, $codeElement);
        if ($codeElement === null || $code === null) {
            $d->reject($first, 'dropped: no valid payment means code');

            return null;
        }
        $remittance = $d->text('cbc:PaymentID', $first);

        $transfers = [];
        $firstAccount = $d->one('cac:PayeeFinancialAccount', $first);
        if ($firstAccount !== null) {
            $transfers[] = $this->creditTransfer($firstAccount);
        }

        foreach (array_slice($means, 1) as $further) {
            $furtherCode = $d->one('cbc:PaymentMeansCode', $further);
            if ($furtherCode === null || trim($furtherCode->textContent) !== $code->value) {
                continue;
            }
            $d->use($furtherCode);
            $paymentId = $d->one('cbc:PaymentID', $further);
            if ($paymentId !== null && $paymentId->textContent === $remittance) {
                $d->use($paymentId);
            }
            foreach ($d->all('cac:PayeeFinancialAccount', $further) as $account) {
                $transfers[] = $this->creditTransfer($account);
            }
        }

        return new PaymentInstructions(
            meansCode: $code,
            meansText: $d->attribute($codeElement, 'name'),
            remittanceInformation: $remittance,
            creditTransfers: array_values(array_filter($transfers)),
            card: $this->card($d->one('cac:CardAccount', $first)),
            directDebit: $this->directDebit($first),
        );
    }

    /**
     * Whether this is the credit note placeholder that carries only the due date (BT-9): means
     * code 1 without a name, followed by cbc:PaymentDueDate and nothing else. UblReader maps the
     * due date; the means code is consumed here and yields no BG-16.
     */
    private function isDueDateOnly(DOMElement $means): bool
    {
        $d = $this->document;
        if (! $d->isCreditNote) {
            return false;
        }
        $children = $d->all('*', $means);
        if (count($children) !== 2
            || $children[0]->namespaceURI !== UblDocument::CBC || $children[0]->localName !== 'PaymentMeansCode'
            || $children[1]->namespaceURI !== UblDocument::CBC || $children[1]->localName !== 'PaymentDueDate'
            || trim($children[0]->textContent) !== PaymentWriter::DUE_DATE_ONLY_MEANS_CODE
            || $d->attribute($children[0], 'name') !== null) {
            return false;
        }
        $d->use($children[0]);

        return true;
    }

    private function creditTransfer(DOMElement $account): ?CreditTransfer
    {
        $d = $this->document;
        $identifier = $d->text('cbc:ID', $account);
        if ($identifier === null) {
            $d->reject($account, 'dropped: no payment account identifier');

            return null;
        }

        return new CreditTransfer(
            accountIdentifier: $identifier,
            accountName: $d->text('cbc:Name', $account),
            serviceProviderIdentifier: $d->text('cac:FinancialInstitutionBranch/cbc:ID', $account),
        );
    }

    private function card(?DOMElement $card): ?PaymentCard
    {
        if ($card === null) {
            return null;
        }
        $d = $this->document;
        $number = $d->text('cbc:PrimaryAccountNumberID', $card);
        if ($number === null) {
            $d->reject($card, 'dropped: no primary account number');

            return null;
        }

        return new PaymentCard(primaryAccountNumber: $number, holderName: $d->text('cbc:HolderName', $card));
    }

    private function directDebit(DOMElement $means): ?DirectDebit
    {
        $d = $this->document;
        $mandate = $d->text('cac:PaymentMandate/cbc:ID', $means);
        $debited = $d->text('cac:PaymentMandate/cac:PayerFinancialAccount/cbc:ID', $means);
        $creditor = $this->creditorIdentifier();

        return $mandate === null && $debited === null && $creditor === null
            ? null
            : new DirectDebit(mandateReference: $mandate, creditorIdentifier: $creditor, debitedAccountIdentifier: $debited);
    }

    private function creditorIdentifier(): ?string
    {
        $d = $this->document;
        $candidates = $d->all('cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID | cac:PayeeParty/cac:PartyIdentification/cbc:ID');
        foreach ($candidates as $id) {
            if (PartyMapper::isSepa($d, $id) && $id->textContent !== '') {
                $d->use($id);

                return $id->textContent;
            }
        }

        return null;
    }
}
