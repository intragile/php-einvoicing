<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use Brick\Math\BigDecimal;
use DOMElement;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Poland\AdvanceInvoiceReference;
use IntrAgile\EInvoicing\Poland\CorrectedInvoice;
use IntrAgile\EInvoicing\Poland\Correction;
use IntrAgile\EInvoicing\Poland\DocumentReference;
use IntrAgile\EInvoicing\Poland\InvoiceKind;
use IntrAgile\EInvoicing\Poland\Order;
use IntrAgile\EInvoicing\Poland\PartialAdvance;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\Settlement;
use IntrAgile\EInvoicing\Poland\SettlementEntry;

/**
 * Writes the FA(3) blocks of the kind-specific and optional invoice data: the correction block,
 * partial advance payments (ZaliczkaCzesciowa), settled advance invoices (FakturaZaliczkowa),
 * charges and deductions (Rozliczenie), the transaction terms (WarunkiTransakcji) and the order
 * of an advance invoice (Zamowienie).
 *
 * The caller runs Fa3Guard first; the blocks are written as given, in schema order.
 *
 * @internal
 */
final class Fa3BlockWriter
{
    /**
     * The correction data written as the correction block: the extension's correction, else, on a
     * corrective invoice, the preceding invoice references (BG-3) as corrected invoices issued
     * outside KSeF (NrKSeFN = 1); null when there is none.
     */
    public static function correction(Invoice $invoice, PolishInvoice $polish, InvoiceKind $kind): ?Correction
    {
        if ($polish->correction !== null || ! $kind->isCorrection()) {
            return $polish->correction;
        }
        $invoices = [];
        foreach ($invoice->precedingInvoices as $reference) {
            if ($reference->issueDate !== null) {
                $invoices[] = new CorrectedInvoice($reference->number, $reference->issueDate);
            }
        }

        return $invoices === [] ? null : new Correction($invoices);
    }

    /**
     * Whether the preceding invoice reference (BG-3) names one of the corrected invoices, with the
     * same issue date when BT-26 is given.
     */
    public static function references(PrecedingInvoiceReference $reference, Correction $correction): bool
    {
        foreach ($correction->invoices as $corrected) {
            if ($corrected->number === $reference->number
                && ($reference->issueDate === null || $reference->issueDate->format('Y-m-d') === $corrected->issueDate->format('Y-m-d'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * PrzyczynaKorekty to KursWalutyZK, written right after RodzajFaktury.
     */
    public static function correctionBlock(Fa3Output $output, DOMElement $fa, Correction $correction): void
    {
        $output->text($fa, 'PrzyczynaKorekty', $correction->reason);
        $output->text($fa, 'TypKorekty', $correction->effect === null ? null : (string) $correction->effect->value);
        foreach ($correction->invoices as $corrected) {
            $element = $output->add($fa, 'DaneFaKorygowanej');
            $output->date($element, 'DataWystFaKorygowanej', $corrected->issueDate);
            $output->text($element, 'NrFaKorygowanej', $corrected->number);
            if ($corrected->ksefNumber !== null) {
                $output->marker($element, 'NrKSeF');
                $output->text($element, 'NrKSeFFaKorygowanej', $corrected->ksefNumber->value);
            } else {
                $output->marker($element, 'NrKSeFN');
            }
        }
        $output->text($fa, 'OkresFaKorygowanej', $correction->period);
        $output->text($fa, 'NrFaKorygowany', $correction->correctedNumber);
        if ($correction->sellerBefore !== null) {
            Fa3PartyWriter::sellerBefore($output, $fa, $correction->sellerBefore);
        }
        foreach ($correction->buyersBefore as $buyer) {
            Fa3PartyWriter::buyerBefore($output, $fa, $buyer);
        }
        $output->decimal($fa, 'P_15ZK', $correction->amountBefore);
        $output->decimal($fa, 'KursWalutyZK', $correction->exchangeRateBefore);
    }

    /**
     * @param  list<PartialAdvance>  $advances
     */
    public static function partialAdvances(Fa3Output $output, DOMElement $fa, array $advances): void
    {
        foreach ($advances as $advance) {
            $element = $output->add($fa, 'ZaliczkaCzesciowa');
            $output->date($element, 'P_6Z', $advance->receivedOn);
            $output->decimal($element, 'P_15Z', $advance->amount);
            $output->decimal($element, 'KursWalutyZW', $advance->exchangeRate);
        }
    }

    /**
     * @param  list<AdvanceInvoiceReference>  $references
     */
    public static function advanceInvoices(Fa3Output $output, DOMElement $fa, array $references): void
    {
        foreach ($references as $reference) {
            $element = $output->add($fa, 'FakturaZaliczkowa');
            if ($reference->ksefNumber !== null) {
                $output->text($element, 'NrKSeFFaZaliczkowej', $reference->ksefNumber->value);
            } else {
                $output->marker($element, 'NrKSeFZN');
                $output->text($element, 'NrFaZaliczkowej', $reference->number);
            }
        }
    }

    /**
     * Rozliczenie: the charges and their sum, the deductions and their sum, then the balance
     * P_15 + charges - deductions as DoZaplaty when not negative, else its absolute value as
     * DoRozliczenia.
     */
    public static function settlement(Fa3Output $output, DOMElement $fa, Settlement $settlement, BigDecimal $p15): void
    {
        $element = $output->add($fa, 'Rozliczenie');
        self::entries($output, $element, 'Obciazenia', $settlement->charges);
        if ($settlement->charges !== []) {
            $output->decimal($element, 'SumaObciazen', $settlement->totalCharges());
        }
        self::entries($output, $element, 'Odliczenia', $settlement->deductions);
        if ($settlement->deductions !== []) {
            $output->decimal($element, 'SumaOdliczen', $settlement->totalDeductions());
        }
        $balance = $settlement->balance($p15);
        if ($balance->isNegative()) {
            $output->decimal($element, 'DoRozliczenia', $balance->negated());
        } else {
            $output->decimal($element, 'DoZaplaty', $balance);
        }
    }

    /**
     * WarunkiTransakcji from the extension's transaction terms, with the contract reference
     * (BT-12) and the purchase order reference (BT-13) as Umowy and Zamowienia when the terms
     * list none; a reference equal to a listed number is carried by that entry.
     */
    public static function transactionTerms(Fa3Output $output, DOMElement $fa, Invoice $invoice, PolishInvoice $polish): void
    {
        $terms = $polish->transactionTerms;
        $contracts = $terms === null || $terms->contracts === []
            ? ($invoice->contractReference === null ? [] : [new DocumentReference(number: $invoice->contractReference)])
            : $terms->contracts;
        $orders = $terms === null || $terms->orders === []
            ? ($invoice->purchaseOrderReference === null ? [] : [new DocumentReference(number: $invoice->purchaseOrderReference)])
            : $terms->orders;
        $empty = $contracts === [] && $orders === []
            && ($terms === null || ($terms->batchNumbers === [] && $terms->deliveryTerms === null && $terms->contractRate === null && $terms->transports === [] && ! $terms->intermediary));
        if ($empty) {
            return;
        }

        $element = $output->add($fa, 'WarunkiTransakcji');
        self::documents($output, $element, 'Umowy', 'DataUmowy', 'NrUmowy', $contracts);
        self::documents($output, $element, 'Zamowienia', 'DataZamowienia', 'NrZamowienia', $orders);
        if ($terms === null) {
            return;
        }
        foreach ($terms->batchNumbers as $batch) {
            $output->text($element, 'NrPartiiTowaru', $batch);
        }
        $output->text($element, 'WarunkiDostawy', $terms->deliveryTerms);
        $output->decimal($element, 'KursUmowny', $terms->contractRate);
        $output->text($element, 'WalutaUmowna', $terms->contractCurrency?->value);
        foreach ($terms->transports as $transport) {
            $output->fragment($element, $transport);
        }
        if ($terms->intermediary) {
            $output->marker($element, 'PodmiotPosredniczacy');
        }
    }

    /**
     * Zamowienie with its lines numbered 1 to n (NrWierszaZam).
     */
    public static function order(Fa3Output $output, DOMElement $fa, Order $order): void
    {
        $element = $output->add($fa, 'Zamowienie');
        $output->decimal($element, 'WartoscZamowienia', $order->value);
        foreach ($order->lines as $index => $line) {
            $row = $output->add($element, 'ZamowienieWiersz');
            $output->text($row, 'NrWierszaZam', (string) ($index + 1));
            $output->text($row, 'UU_IDZ', $line->uuid);
            $output->text($row, 'P_7Z', $line->name);
            $output->text($row, 'IndeksZ', $line->index);
            $output->text($row, 'GTINZ', $line->gtin);
            $output->text($row, 'PKWiUZ', $line->pkwiu);
            $output->text($row, 'CNZ', $line->cn);
            $output->text($row, 'PKOBZ', $line->pkob);
            $output->text($row, 'P_8AZ', $line->unitLabel);
            $output->decimal($row, 'P_8BZ', $line->quantity);
            $output->decimal($row, 'P_9AZ', $line->unitNetPrice);
            $output->decimal($row, 'P_11NettoZ', $line->netAmount);
            $output->decimal($row, 'P_11VatZ', $line->vatAmount);
            $output->text($row, 'P_12Z', $line->rateCode?->value);
            $output->decimal($row, 'P_12Z_XII', $line->ossRate);
            if ($line->annex15) {
                $output->marker($row, 'P_12Z_Zal_15');
            }
            $output->text($row, 'GTUZ', $line->gtu?->value);
            $output->text($row, 'ProceduraZ', $line->procedure?->value);
            $output->decimal($row, 'KwotaAkcyzyZ', $line->excise);
            if ($line->stateBefore) {
                $output->marker($row, 'StanPrzedZ');
            }
        }
    }

    /**
     * @param  list<SettlementEntry>  $entries
     */
    private static function entries(Fa3Output $output, DOMElement $parent, string $name, array $entries): void
    {
        foreach ($entries as $entry) {
            $element = $output->add($parent, $name);
            $output->decimal($element, 'Kwota', $entry->amount);
            $output->text($element, 'Powod', $entry->reason);
        }
    }

    /**
     * @param  list<DocumentReference>  $documents
     */
    private static function documents(Fa3Output $output, DOMElement $parent, string $name, string $dateName, string $numberName, array $documents): void
    {
        foreach ($documents as $document) {
            $element = $output->add($parent, $name);
            $output->date($element, $dateName, $document->date);
            $output->text($element, $numberName, $document->number);
        }
    }

    private function __construct() {}
}
