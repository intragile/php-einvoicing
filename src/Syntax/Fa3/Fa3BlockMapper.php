<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\PaymentMeansCode;
use IntrAgile\EInvoicing\Model\CreditTransfer;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Poland\AdvanceInvoiceReference;
use IntrAgile\EInvoicing\Poland\Annotations;
use IntrAgile\EInvoicing\Poland\BankAccount;
use IntrAgile\EInvoicing\Poland\CorrectedInvoice;
use IntrAgile\EInvoicing\Poland\Correction;
use IntrAgile\EInvoicing\Poland\CorrectionEffect;
use IntrAgile\EInvoicing\Poland\DocumentReference;
use IntrAgile\EInvoicing\Poland\Exemption;
use IntrAgile\EInvoicing\Poland\ExemptionBasis;
use IntrAgile\EInvoicing\Poland\Footer;
use IntrAgile\EInvoicing\Poland\Gtu;
use IntrAgile\EInvoicing\Poland\KsefNumber;
use IntrAgile\EInvoicing\Poland\MarginScheme;
use IntrAgile\EInvoicing\Poland\Order;
use IntrAgile\EInvoicing\Poland\OrderLine;
use IntrAgile\EInvoicing\Poland\PartialAdvance;
use IntrAgile\EInvoicing\Poland\PaymentForm;
use IntrAgile\EInvoicing\Poland\Procedure;
use IntrAgile\EInvoicing\Poland\Registry;
use IntrAgile\EInvoicing\Poland\Settlement;
use IntrAgile\EInvoicing\Poland\SettlementEntry;
use IntrAgile\EInvoicing\Poland\TransactionTerms;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use InvalidArgumentException;

/**
 * Reads the FA(3) blocks of the annotations, the kind-specific and the optional invoice data:
 * Adnotacje, the correction block, ZaliczkaCzesciowa, FakturaZaliczkowa, Rozliczenie, Platnosc,
 * WarunkiTransakcji, Zamowienie and Stopka; the inverse of Fa3BlockWriter and the payment,
 * annotation and footer parts of Fa3Writer.
 *
 * A KSeF number the KSeF number rules refuse is reported with its entry, which is left out, so a
 * reference is never re-written as one issued outside KSeF.
 *
 * @internal
 */
final class Fa3BlockMapper
{
    public function __construct(private readonly Fa3Document $d, private readonly Fa3PartyMapper $parties) {}

    public function annotations(?DOMElement $element): Annotations
    {
        if ($element === null) {
            return new Annotations;
        }
        $d = $this->d;
        $exemption = null;
        $zwolnienie = $d->one('fa:Zwolnienie', $element);
        if ($zwolnienie !== null && $d->flag('fa:P_19', $zwolnienie)) {
            foreach (ExemptionBasis::cases() as $basis) {
                $text = $d->text('fa:'.$basis->value, $zwolnienie);
                if ($text !== null) {
                    $exemption = new Exemption($basis, $text);
                    break;
                }
            }
        } elseif ($zwolnienie !== null) {
            $d->flag('fa:P_19N', $zwolnienie);
        }

        $newTransport = null;
        $transport = $d->one('fa:NoweSrodkiTransportu', $element);
        if ($transport !== null && $d->has('fa:P_22N', $transport) && $transport->childElementCount === 1) {
            $d->flag('fa:P_22N', $transport);
        } elseif ($transport !== null) {
            $newTransport = $d->fragment($transport);
        }

        $margin = null;
        $pMarzy = $d->one('fa:PMarzy', $element);
        if ($pMarzy !== null && $d->flag('fa:P_PMarzy', $pMarzy)) {
            foreach (MarginScheme::cases() as $scheme) {
                if ($d->flag('fa:'.$scheme->value, $pMarzy)) {
                    $margin = $scheme;
                    break;
                }
            }
        } elseif ($pMarzy !== null) {
            $d->flag('fa:P_PMarzyN', $pMarzy);
        }

        return new Annotations(
            cashMethod: $d->flag('fa:P_16', $element),
            selfBilling: $d->flag('fa:P_17', $element),
            reverseCharge: $d->flag('fa:P_18', $element),
            splitPayment: $d->flag('fa:P_18A', $element),
            exemption: $exemption,
            newTransport: $newTransport,
            triangular: $d->flag('fa:P_23', $element),
            margin: $margin,
        );
    }

    /**
     * The correction block (PrzyczynaKorekty to KursWalutyZK) of a corrective invoice; null when
     * it lists no readable corrected invoice.
     */
    public function correction(DOMElement $fa): ?Correction
    {
        $d = $this->d;
        $invoices = [];
        foreach ($d->all('fa:DaneFaKorygowanej', $fa) as $element) {
            $corrected = $this->correctedInvoice($element);
            if ($corrected !== null) {
                $invoices[] = $corrected;
            }
        }
        if ($invoices === []) {
            return null;
        }

        return new Correction(
            invoices: $invoices,
            reason: $d->text('fa:PrzyczynaKorekty', $fa),
            effect: $d->intCode(CorrectionEffect::class, $d->one('fa:TypKorekty', $fa)),
            period: $d->text('fa:OkresFaKorygowanej', $fa),
            correctedNumber: $d->text('fa:NrFaKorygowany', $fa),
            sellerBefore: ($seller = $d->one('fa:Podmiot1K', $fa)) === null ? null : $this->parties->sellerBefore($seller),
            buyersBefore: array_map(fn (DOMElement $buyer) => $this->parties->buyerBefore($buyer), $d->all('fa:Podmiot2K', $fa)),
            amountBefore: $d->decimal('fa:P_15ZK', $fa),
            exchangeRateBefore: $d->decimal('fa:KursWalutyZK', $fa),
        );
    }

    /**
     * Whether the correction carries more than the preceding invoice references (BG-3) rebuild:
     * a KSeF number or any other correction data.
     */
    public static function beyondReferences(Correction $correction): bool
    {
        foreach ($correction->invoices as $invoice) {
            if ($invoice->ksefNumber !== null) {
                return true;
            }
        }

        return $correction->reason !== null || $correction->effect !== null || $correction->period !== null
            || $correction->correctedNumber !== null || $correction->sellerBefore !== null || $correction->buyersBefore !== []
            || $correction->amountBefore !== null || $correction->exchangeRateBefore !== null;
    }

    /**
     * @return list<PartialAdvance>
     */
    public function partialAdvances(DOMElement $fa): array
    {
        $advances = [];
        foreach ($this->d->all('fa:ZaliczkaCzesciowa', $fa) as $element) {
            $date = $this->d->date('fa:P_6Z', $element);
            $amount = $this->d->decimal('fa:P_15Z', $element);
            if ($date !== null && $amount !== null) {
                $advances[] = new PartialAdvance($date, $amount, $this->d->decimal('fa:KursWalutyZW', $element));
            }
        }

        return $advances;
    }

    /**
     * @return list<AdvanceInvoiceReference>
     */
    public function advanceInvoices(DOMElement $fa): array
    {
        $references = [];
        foreach ($this->d->all('fa:FakturaZaliczkowa', $fa) as $element) {
            $ksef = $this->d->one('fa:NrKSeFFaZaliczkowej', $element);
            if ($ksef !== null) {
                $number = $this->ksefNumber($ksef);
                if ($number !== null) {
                    $references[] = new AdvanceInvoiceReference(ksefNumber: $number);
                }

                continue;
            }
            $number = $this->d->text('fa:NrFaZaliczkowej', $element);
            if ($number !== null) {
                $this->d->flag('fa:NrKSeFZN', $element);
                $references[] = new AdvanceInvoiceReference(number: $number);
            }
        }

        return $references;
    }

    /**
     * Rozliczenie as charges and deductions, with the balance (DoZaplaty, or DoRozliczenia
     * negated) or null when neither is written. A written sum that differs from its entries is
     * reported, since the writer recomputes it.
     *
     * @return array{?Settlement, ?BigDecimal}
     */
    public function settlement(DOMElement $fa): array
    {
        $element = $this->d->one('fa:Rozliczenie', $fa);
        if ($element === null) {
            return [null, null];
        }
        $settlement = new Settlement(
            charges: $this->entries($element, 'fa:Obciazenia'),
            deductions: $this->entries($element, 'fa:Odliczenia'),
        );
        $this->sum($element, 'fa:SumaObciazen', $settlement->totalCharges());
        $this->sum($element, 'fa:SumaOdliczen', $settlement->totalDeductions());
        $balance = $this->d->decimal('fa:DoZaplaty', $element) ?? $this->d->decimal('fa:DoRozliczenia', $element)?->negated();

        return [$settlement, $balance];
    }

    /**
     * Platnosc: the due date (BT-9), the EN 16931 payment instructions, the Polish payment form
     * when it has no payment means code of its own, and the Polish bank accounts.
     *
     * The payment instructions exist when a form (FormaPlatnosci) or another payment
     * (PlatnoscInna) is written; their credit transfers (BG-17) are the bank accounts. The bank
     * accounts are kept in the Polish data as well when one carries a bank name or the own-bank
     * marking, or when there are no payment instructions to hold them.
     *
     * @return array{?DateTimeImmutable, ?PaymentInstructions, ?PaymentForm, list<BankAccount>, list<BankAccount>}
     */
    public function payment(DOMElement $fa): array
    {
        $d = $this->d;
        $element = $d->one('fa:Platnosc', $fa);
        if ($element === null) {
            return [null, null, null, [], []];
        }
        $term = $d->one('fa:TerminPlatnosci', $element);
        $dueDate = $term === null ? null : $d->date('fa:Termin', $term);
        $accounts = array_map(fn (DOMElement $account): BankAccount => $this->bankAccount($account), $d->all('fa:RachunekBankowy', $element));
        $factorAccounts = array_map(fn (DOMElement $account): BankAccount => $this->bankAccount($account), $d->all('fa:RachunekBankowyFaktora', $element));

        $form = $d->intCode(PaymentForm::class, $d->one('fa:FormaPlatnosci', $element));
        $meansCode = null;
        $meansText = null;
        if ($form !== null) {
            $meansCode = $form->meansCode();
        } elseif ($d->flag('fa:PlatnoscInna', $element)) {
            $description = $d->text('fa:OpisPlatnosci', $element);
            $meansCode = $description === null ? null : PaymentMeansCode::tryFrom($description);
            if ($meansCode === null || PaymentForm::fromMeansCode($meansCode) !== null) {
                $meansCode = PaymentMeansCode::C_1;
                $meansText = $description;
            }
        }

        $payment = $meansCode === null ? null : new PaymentInstructions(
            meansCode: $meansCode,
            meansText: $meansText,
            creditTransfers: array_map(
                static fn (BankAccount $account): CreditTransfer => new CreditTransfer($account->number, $account->description, $account->swift),
                $accounts,
            ),
        );
        $detailed = array_filter($accounts, static fn (BankAccount $account): bool => $account->bankName !== null || $account->ownBankAccount !== null);
        $polishAccounts = $payment === null || $detailed !== [] ? $accounts : [];
        $polishForm = $form !== null && PaymentForm::fromMeansCode($form->meansCode()) !== $form ? $form : null;

        return [$dueDate, $payment, $polishForm, $polishAccounts, $factorAccounts];
    }

    /**
     * WarunkiTransakcji, with the contract (BT-12) and purchase order (BT-13) references: the
     * number of the first Umowy and Zamowienia entry. A single undated entry is carried by the
     * reference alone, as the writer writes it; any other list is kept in the transaction terms.
     *
     * @return array{?TransactionTerms, ?string, ?string}
     */
    public function transactionTerms(DOMElement $fa): array
    {
        $d = $this->d;
        $element = $d->one('fa:WarunkiTransakcji', $fa);
        if ($element === null) {
            return [null, null, null];
        }
        $contracts = $this->documents($element, 'fa:Umowy', 'fa:DataUmowy', 'fa:NrUmowy');
        $orders = $this->documents($element, 'fa:Zamowienia', 'fa:DataZamowienia', 'fa:NrZamowienia');
        $contractReference = self::firstNumber($contracts);
        $orderReference = self::firstNumber($orders);
        $rate = $d->decimal('fa:KursUmowny', $element);
        $currency = $d->code(CurrencyCode::class, $d->one('fa:WalutaUmowna', $element));
        if (($rate === null) !== ($currency === null)) {
            foreach (['fa:KursUmowny', 'fa:WalutaUmowna'] as $name) {
                $lone = $d->one($name, $element);
                if ($lone !== null) {
                    $d->reject($lone, 'the contractual exchange rate and currency go together');
                }
            }
            $rate = null;
            $currency = null;
        }

        $terms = new TransactionTerms(
            contracts: self::simple($contracts) ? [] : $contracts,
            orders: self::simple($orders) ? [] : $orders,
            batchNumbers: array_values(array_filter(array_map(
                fn (DOMElement $batch): ?string => $this->text($batch),
                $d->all('fa:NrPartiiTowaru', $element),
            ), static fn (?string $batch): bool => $batch !== null)),
            deliveryTerms: $d->text('fa:WarunkiDostawy', $element),
            contractRate: $rate,
            contractCurrency: $currency,
            transports: array_map(fn (DOMElement $transport) => $d->fragment($transport), $d->all('fa:Transport', $element)),
            intermediary: $d->flag('fa:PodmiotPosredniczacy', $element),
        );

        return [$terms == new TransactionTerms ? null : $terms, $contractReference, $orderReference];
    }

    public function order(DOMElement $fa): ?Order
    {
        $d = $this->d;
        $element = $d->one('fa:Zamowienie', $fa);
        if ($element === null) {
            return null;
        }
        $value = $d->decimal('fa:WartoscZamowienia', $element);
        $lines = [];
        foreach ($d->all('fa:ZamowienieWiersz', $element) as $row) {
            $d->token('fa:NrWierszaZam', $row);
            $procedureElement = $d->one('fa:ProceduraZ', $row);
            $procedure = $d->code(Procedure::class, $procedureElement);
            if ($procedure !== null && ! $procedure->allowedInOrder() && $procedureElement !== null) {
                $d->reject($procedureElement, sprintf('procedure %s is not allowed on an order line', $procedure->value));
                $procedure = null;
            }
            $lines[] = new OrderLine(
                uuid: $d->text('fa:UU_IDZ', $row),
                name: $d->text('fa:P_7Z', $row),
                index: $d->text('fa:IndeksZ', $row),
                gtin: $d->text('fa:GTINZ', $row),
                pkwiu: $d->text('fa:PKWiUZ', $row),
                cn: $d->text('fa:CNZ', $row),
                pkob: $d->text('fa:PKOBZ', $row),
                unitLabel: $d->text('fa:P_8AZ', $row),
                quantity: $d->decimal('fa:P_8BZ', $row),
                unitNetPrice: $d->decimal('fa:P_9AZ', $row),
                netAmount: $d->decimal('fa:P_11NettoZ', $row),
                vatAmount: $d->decimal('fa:P_11VatZ', $row),
                rateCode: $d->code(VatRateCode::class, $d->one('fa:P_12Z', $row)),
                ossRate: $d->decimal('fa:P_12Z_XII', $row),
                annex15: $d->flag('fa:P_12Z_Zal_15', $row),
                gtu: $d->code(Gtu::class, $d->one('fa:GTUZ', $row)),
                procedure: $procedure,
                excise: $d->decimal('fa:KwotaAkcyzyZ', $row),
                stateBefore: $d->flag('fa:StanPrzedZ', $row),
            );
        }
        if ($value === null || $lines === []) {
            return null;
        }

        return new Order($value, $lines);
    }

    public function footer(?DOMElement $element): ?Footer
    {
        if ($element === null) {
            return null;
        }
        $d = $this->d;
        $notes = [];
        foreach ($d->all('fa:Informacje', $element) as $information) {
            $note = $d->text('fa:StopkaFaktury', $information);
            if ($note !== null) {
                $notes[] = $note;
            }
        }
        $registries = array_map(fn (DOMElement $registry): Registry => new Registry(
            fullName: $d->text('fa:PelnaNazwa', $registry),
            krs: $d->text('fa:KRS', $registry),
            regon: $d->text('fa:REGON', $registry),
            bdo: $d->text('fa:BDO', $registry),
        ), $d->all('fa:Rejestry', $element));

        return $notes === [] && $registries === [] ? null : new Footer($notes, $registries);
    }

    private function correctedInvoice(DOMElement $element): ?CorrectedInvoice
    {
        $d = $this->d;
        $ksef = null;
        if ($d->has('fa:NrKSeFFaKorygowanej', $element)) {
            $numberElement = $d->one('fa:NrKSeFFaKorygowanej', $element);
            $ksef = $numberElement === null ? null : $this->ksefNumber($numberElement);
            if ($ksef === null) {
                return null;
            }
            $d->flag('fa:NrKSeF', $element);
        } else {
            $d->flag('fa:NrKSeFN', $element);
        }
        $date = $d->date('fa:DataWystFaKorygowanej', $element);
        $number = $d->text('fa:NrFaKorygowanej', $element);

        return $date === null || $number === null ? null : new CorrectedInvoice($number, $date, $ksef);
    }

    private function ksefNumber(DOMElement $element): ?KsefNumber
    {
        try {
            $number = KsefNumber::parse(trim($element->textContent));
        } catch (InvalidArgumentException $e) {
            $parent = $element->parentNode;
            $this->d->reject($parent instanceof DOMElement ? $parent : $element, 'entry left out: '.$e->getMessage());

            return null;
        }
        $this->d->use($element);

        return $number;
    }

    private function bankAccount(DOMElement $element): BankAccount
    {
        $d = $this->d;
        $own = $d->token('fa:RachunekWlasnyBanku', $element);

        return new BankAccount(
            number: $d->text('fa:NrRB', $element) ?? '',
            swift: $d->text('fa:SWIFT', $element),
            ownBankAccount: $own === null ? null : (int) $own,
            bankName: $d->text('fa:NazwaBanku', $element),
            description: $d->text('fa:OpisRachunku', $element),
        );
    }

    /**
     * @return list<SettlementEntry>
     */
    private function entries(DOMElement $element, string $name): array
    {
        $entries = [];
        foreach ($this->d->all($name, $element) as $entry) {
            $amount = $this->d->decimal('fa:Kwota', $entry);
            $reason = $this->d->text('fa:Powod', $entry);
            if ($amount !== null && $reason !== null) {
                $entries[] = new SettlementEntry($amount, $reason);
            }
        }

        return $entries;
    }

    private function sum(DOMElement $element, string $name, BigDecimal $computed): void
    {
        $sum = $this->d->one($name, $element);
        if ($sum === null) {
            return;
        }
        $value = $this->d->peekDecimal($sum);
        if ($value !== null && ! $value->isEqualTo($computed)) {
            $this->d->reject($sum, sprintf('sum %s differs from the total of its entries %s, which is used', $value, $computed));

            return;
        }
        $this->d->decimalOf($sum);
    }

    /**
     * @return list<DocumentReference>
     */
    private function documents(DOMElement $parent, string $name, string $dateName, string $numberName): array
    {
        $documents = [];
        foreach ($this->d->all($name, $parent) as $element) {
            $date = $this->d->date($dateName, $element);
            $number = $this->d->text($numberName, $element);
            if ($date !== null || $number !== null) {
                $documents[] = new DocumentReference($date, $number);
            }
        }

        return $documents;
    }

    /**
     * @param  list<DocumentReference>  $documents
     */
    private static function firstNumber(array $documents): ?string
    {
        foreach ($documents as $document) {
            if ($document->number !== null) {
                return $document->number;
            }
        }

        return null;
    }

    /**
     * Whether the list is one entry with a number and no date, which the reference carries alone.
     *
     * @param  list<DocumentReference>  $documents
     */
    private static function simple(array $documents): bool
    {
        return count($documents) === 1 && $documents[0]->date === null && $documents[0]->number !== null;
    }

    private function text(DOMElement $element): ?string
    {
        $this->d->use($element);

        return $element->textContent === '' ? null : $element->textContent;
    }
}
