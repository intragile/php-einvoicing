<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use DOMElement;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Poland\Annotations;
use IntrAgile\EInvoicing\Poland\BankAccount;
use IntrAgile\EInvoicing\Poland\InvoiceKind;
use IntrAgile\EInvoicing\Poland\KeyValue;
use IntrAgile\EInvoicing\Poland\PaymentForm;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Syntax\WriterGuard;
use InvalidArgumentException;

/**
 * Writes an invoice as a Polish structured invoice FA(3), schema version 1-0E (KSeF).
 *
 * The Polish data EN 16931 does not carry comes from the PolishInvoice and PolishLine
 * extensions; an invoice without them is written as a plain VAT invoice with every annotation
 * negative. Elements follow the schema sequence: Naglowek, Podmiot1, Podmiot2, Podmiot3, Fa,
 * Stopka.
 *
 * Mapping highlights:
 * - DataWytworzeniaFa is the extension's creation time, else the writer clock, in UTC.
 * - The seller NIP is the seller VAT identifier (BT-31) without its "PL" prefix.
 * - P_6 is the actual delivery date (BT-72), else the tax point date (BT-7), written only when
 *   it differs from the issue date (BT-2); otherwise the invoicing period (BG-14) is OkresFa.
 * - The summary buckets P_13_x and P_14_x sum the VAT breakdown rows (BT-116, BT-117) by the
 *   rate code of their lines; P_15 is the invoice total with VAT (BT-112).
 * - EN 16931 text terms without an FA(3) element are written as additional descriptions
 *   (DodatkowyOpis) with the fixed keys of Fa3Format::DESCRIPTION_KEYS, after the extension's
 *   own descriptions.
 * - The contract reference (BT-12) and the purchase order reference (BT-13) are written in
 *   WarunkiTransakcji as Umowy/NrUmowy and Zamowienia/NrZamowienia.
 * - VAT exemption reasons (BT-120, BT-121) are superseded by the exemption annotation
 *   (Annotations::$exemption, P_19 with its legal basis) and not written; without that
 *   annotation the guard refuses them.
 * - The payment form is the extension's override, else derived from the payment means code
 *   (BT-81); a code without a form is written as PlatnoscInna with the means text (BT-82), else
 *   the code. Bank accounts are the extension's, else the credit transfers (BG-17).
 *
 * Values FA(3) cannot carry are refused before anything is written, see Fa3Guard.
 */
final class Fa3Writer
{
    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $now;

    /**
     * @param  (Closure(): DateTimeImmutable)|null  $now  clock for DataWytworzeniaFa; defaults to the system time
     */
    public function __construct(?Closure $now = null)
    {
        $this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable;
    }

    /**
     * @throws InvalidArgumentException when the invoice holds text XML cannot carry or values
     *                                  FA(3) cannot carry (see Fa3Guard)
     */
    public function write(Invoice $invoice): string
    {
        WriterGuard::xmlText($invoice);
        Fa3Guard::check($invoice);

        $polish = $invoice->extensions()->get(PolishInvoice::class) ?? new PolishInvoice;
        $output = new Fa3Output;

        $this->header($output, $polish);
        Fa3PartyWriter::seller($output, $invoice, $polish);
        Fa3PartyWriter::buyer($output, $invoice, $polish);
        foreach ($polish->thirdParties as $thirdParty) {
            Fa3PartyWriter::thirdParty($output, $thirdParty);
        }
        $this->invoice($output, $invoice, $polish);
        $this->footer($output, $polish);

        return $output->xml();
    }

    /**
     * The supply date written as P_6: the actual delivery date (BT-72), else the tax point date
     * (BT-7), when it falls on another day than the issue date (BT-2); otherwise null.
     */
    public static function supplyDate(Invoice $invoice): ?DateTimeImmutable
    {
        $date = $invoice->delivery->actualDeliveryDate ?? $invoice->taxPointDate;

        return $date !== null && $date->format('Y-m-d') !== $invoice->issueDate->format('Y-m-d') ? $date : null;
    }

    private function header(Fa3Output $output, PolishInvoice $polish): void
    {
        $header = $output->add($output->root, 'Naglowek');
        $output->text($header, 'KodFormularza', Fa3Format::FORM_CODE, [
            'kodSystemowy' => Fa3Format::SYSTEM_CODE,
            'wersjaSchemy' => Fa3Format::SCHEMA_VERSION,
        ]);
        $output->text($header, 'WariantFormularza', Fa3Format::FORM_VARIANT);
        $createdAt = ($polish->createdAt ?? ($this->now)())->setTimezone(new DateTimeZone('UTC'));
        $output->text($header, 'DataWytworzeniaFa', $createdAt->format('Y-m-d\TH:i:s\Z'));
        $output->text($header, 'SystemInfo', $polish->systemInfo);
    }

    private function invoice(Fa3Output $output, Invoice $invoice, PolishInvoice $polish): void
    {
        $fa = $output->add($output->root, 'Fa');

        $output->text($fa, 'KodWaluty', $invoice->currency->value);
        $output->date($fa, 'P_1', $invoice->issueDate);
        $output->text($fa, 'P_1M', $polish->placeOfIssue);
        $output->text($fa, 'P_2', $invoice->number);
        foreach ($this->warehouseDocuments($invoice, $polish) as $document) {
            $output->text($fa, 'WZ', $document);
        }
        $this->supply($output, $fa, $invoice);
        $this->buckets($output, $fa, $invoice);
        $this->annotations($output, $fa, $polish->annotations);
        $output->text($fa, 'RodzajFaktury', self::kind($invoice, $polish)->value);
        if ($polish->article109Invoice) {
            $output->marker($fa, 'FP');
        }
        if ($polish->relatedParties) {
            $output->marker($fa, 'TP');
        }
        foreach ($this->descriptions($invoice, $polish) as $description) {
            $element = $output->add($fa, 'DodatkowyOpis');
            $output->text($element, 'NrWiersza', $description->lineNumber === null ? null : (string) $description->lineNumber);
            $output->text($element, 'Klucz', $description->key);
            $output->text($element, 'Wartosc', $description->value);
        }
        if ($polish->exciseRefund) {
            $output->marker($fa, 'ZwrotAkcyzy');
        }
        $buyerCountry = $invoice->buyer->address?->country;
        foreach ($this->writtenLines($invoice) as $position => $line) {
            Fa3LineWriter::write($output, $fa, $line, $position, $buyerCountry);
        }
        $this->payment($output, $fa, $invoice, $polish);
        $this->transactionTerms($output, $fa, $invoice);
    }

    /**
     * WarunkiTransakcji with the contract reference (BT-12) as Umowy/NrUmowy and the purchase
     * order reference (BT-13) as Zamowienia/NrZamowienia.
     */
    private function transactionTerms(Fa3Output $output, DOMElement $fa, Invoice $invoice): void
    {
        if ($invoice->contractReference === null && $invoice->purchaseOrderReference === null) {
            return;
        }
        $element = $output->add($fa, 'WarunkiTransakcji');
        if ($invoice->contractReference !== null) {
            $output->text($output->add($element, 'Umowy'), 'NrUmowy', $invoice->contractReference);
        }
        if ($invoice->purchaseOrderReference !== null) {
            $output->text($output->add($element, 'Zamowienia'), 'NrZamowienia', $invoice->purchaseOrderReference);
        }
    }

    /**
     * The despatch advice reference (BT-16) first, then the extension's warehouse documents.
     *
     * @return list<string>
     */
    private function warehouseDocuments(Invoice $invoice, PolishInvoice $polish): array
    {
        $despatch = $invoice->despatchAdviceReference;
        if ($despatch === null) {
            return $polish->warehouseDocuments;
        }

        return [$despatch, ...array_values(array_filter($polish->warehouseDocuments, static fn (string $document): bool => $document !== $despatch))];
    }

    private function supply(Fa3Output $output, DOMElement $fa, Invoice $invoice): void
    {
        $date = self::supplyDate($invoice);
        if ($date !== null) {
            $output->date($fa, 'P_6', $date);

            return;
        }
        $period = $invoice->delivery?->invoicingPeriod;
        if ($period?->start !== null && $period->end !== null) {
            $element = $output->add($fa, 'OkresFa');
            $output->date($element, 'P_6_Od', $period->start);
            $output->date($element, 'P_6_Do', $period->end);
        }
    }

    private function buckets(Fa3Output $output, DOMElement $fa, Invoice $invoice): void
    {
        foreach (Fa3Buckets::of($invoice) as [$bucket, $net, $vat]) {
            $output->decimal($fa, $bucket->netElement(), $net);
            $vatElement = $bucket->vatElement();
            if ($vatElement !== null) {
                $output->decimal($fa, $vatElement, $vat);
            }
        }
        $output->decimal($fa, 'P_15', $invoice->totals?->totalWithVat);
    }

    private function annotations(Fa3Output $output, DOMElement $fa, Annotations $annotations): void
    {
        $element = $output->add($fa, 'Adnotacje');
        $output->yesNo($element, 'P_16', $annotations->cashMethod);
        $output->yesNo($element, 'P_17', $annotations->selfBilling);
        $output->yesNo($element, 'P_18', $annotations->reverseCharge);
        $output->yesNo($element, 'P_18A', $annotations->splitPayment);

        $exemption = $output->add($element, 'Zwolnienie');
        if ($annotations->exemption !== null) {
            $output->marker($exemption, 'P_19');
            $output->text($exemption, $annotations->exemption->basis->value, $annotations->exemption->text);
        } else {
            $output->marker($exemption, 'P_19N');
        }

        if ($annotations->newTransport !== null) {
            $output->fragment($element, $annotations->newTransport);
        } else {
            $output->marker($output->add($element, 'NoweSrodkiTransportu'), 'P_22N');
        }

        $output->yesNo($element, 'P_23', $annotations->triangular);

        $margin = $output->add($element, 'PMarzy');
        if ($annotations->margin !== null) {
            $output->marker($margin, 'P_PMarzy');
            $output->marker($margin, $annotations->margin->value);
        } else {
            $output->marker($margin, 'P_PMarzyN');
        }
    }

    /**
     * The extension's descriptions, then the EN 16931 text terms under their fixed keys.
     *
     * @return list<KeyValue>
     */
    private function descriptions(Invoice $invoice, PolishInvoice $polish): array
    {
        $keys = Fa3Format::DESCRIPTION_KEYS;
        $descriptions = $polish->descriptions;
        $add = static function (string $term, ?string $value, ?int $line = null) use (&$descriptions, $keys): void {
            if ($value !== null) {
                $descriptions[] = new KeyValue($line, $keys[$term], $value);
            }
        };

        $add('BT-10', $invoice->buyerReference);
        $add('BT-11', $invoice->projectReference);
        $add('BT-14', $invoice->salesOrderReference);
        $add('BT-15', $invoice->receivingAdviceReference);
        $add('BT-17', $invoice->tenderOrLotReference);
        $add('BT-19', $invoice->buyerAccountingReference);
        $add('BT-20', $invoice->paymentTerms);
        foreach ($invoice->notes as $note) {
            $add('BT-22', $note->text);
        }
        $add('BT-28', $invoice->seller->tradingName);
        $add('BT-41', $invoice->seller->contact?->name);
        $add('BT-45', $invoice->buyer->tradingName);
        $add('BT-56', $invoice->buyer->contact?->name);
        $add('BT-83', $invoice->payment?->remittanceInformation);
        foreach ($this->writtenLines($invoice) as $position => $line) {
            $add('BT-127', $line->note, $position);
            $add('BT-154', $line->item->description, $position);
            $add('BT-128', $line->objectIdentifier?->value, $position);
            $add('BT-132', $line->orderLineReference, $position);
            $add('BT-133', $line->buyerAccountingReference, $position);
            $add('BT-156', $line->item->buyersIdentifier, $position);
            $add('BT-159', $line->item->originCountry?->value, $position);
            foreach ($line->allowances as $allowance) {
                $add('BT-139', $allowance->reason, $position);
            }
        }

        return $descriptions;
    }

    private function payment(Fa3Output $output, DOMElement $fa, Invoice $invoice, PolishInvoice $polish): void
    {
        $payment = $invoice->payment;
        $form = $polish->paymentForm ?? ($payment === null ? null : PaymentForm::fromMeansCode($payment->meansCode));
        $accounts = $polish->bankAccounts !== [] ? $polish->bankAccounts : array_map(
            static fn (CreditTransfer $transfer): BankAccount => new BankAccount(
                number: $transfer->accountIdentifier,
                swift: $transfer->serviceProviderIdentifier,
                description: $transfer->accountName,
            ),
            $payment->creditTransfers ?? [],
        );
        if ($invoice->dueDate === null && $payment === null && $form === null && $accounts === [] && $polish->factorBankAccounts === []) {
            return;
        }

        $element = $output->add($fa, 'Platnosc');
        if ($invoice->dueDate !== null) {
            $output->date($output->add($element, 'TerminPlatnosci'), 'Termin', $invoice->dueDate);
        }
        if ($form !== null) {
            $output->text($element, 'FormaPlatnosci', (string) $form->value);
        } elseif ($payment !== null) {
            $output->marker($element, 'PlatnoscInna');
            $output->text($element, 'OpisPlatnosci', $payment->meansText ?? $payment->meansCode->value);
        }
        foreach ($accounts as $account) {
            $this->bankAccount($output, $element, 'RachunekBankowy', $account);
        }
        foreach ($polish->factorBankAccounts as $account) {
            $this->bankAccount($output, $element, 'RachunekBankowyFaktora', $account);
        }
    }

    private function bankAccount(Fa3Output $output, DOMElement $parent, string $name, BankAccount $account): void
    {
        $element = $output->add($parent, $name);
        $output->text($element, 'NrRB', $account->number);
        $output->text($element, 'SWIFT', $account->swift);
        $output->text($element, 'RachunekWlasnyBanku', $account->ownBankAccount === null ? null : (string) $account->ownBankAccount);
        $output->text($element, 'NazwaBanku', $account->bankName);
        $output->text($element, 'OpisRachunku', $account->description);
    }

    private function footer(Fa3Output $output, PolishInvoice $polish): void
    {
        $footer = $polish->footer;
        if ($footer === null || ($footer->notes === [] && $footer->registries === [])) {
            return;
        }
        $element = $output->add($output->root, 'Stopka');
        foreach ($footer->notes as $note) {
            $output->text($output->add($element, 'Informacje'), 'StopkaFaktury', $note);
        }
        foreach ($footer->registries as $registry) {
            $entry = $output->add($element, 'Rejestry');
            $output->text($entry, 'PelnaNazwa', $registry->fullName);
            $output->text($entry, 'KRS', $registry->krs);
            $output->text($entry, 'REGON', $registry->regon);
            $output->text($entry, 'BDO', $registry->bdo);
        }
    }

    /**
     * The lines written as FaWiersz, keyed by their line number (NrWierszaFa) from 1.
     *
     * @return array<int, InvoiceLine>
     */
    private function writtenLines(Invoice $invoice): array
    {
        $lines = [];
        $position = 0;
        foreach ($invoice->lines as $line) {
            if (Fa3LineWriter::isWritten($line)) {
                $lines[++$position] = $line;
            }
        }

        return $lines;
    }

    /**
     * @throws InvalidArgumentException when the type code implies no kind (refused by the guard)
     */
    private static function kind(Invoice $invoice, PolishInvoice $polish): InvoiceKind
    {
        return $polish->kind ?? InvoiceKind::fromTypeCode($invoice->type)
            ?? throw new InvalidArgumentException(sprintf('Invoice type code %s implies no FA(3) invoice kind.', $invoice->type->value));
    }
}
