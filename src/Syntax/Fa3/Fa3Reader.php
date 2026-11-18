<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use DateTimeImmutable;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\AllowanceReasonCode;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Delivery;
use IntrAgile\EInvoicing\Model\DocumentTotals;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Note;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Model\PaymentInstructions;
use IntrAgile\EInvoicing\Model\Period;
use IntrAgile\EInvoicing\Model\PrecedingInvoiceReference;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use IntrAgile\EInvoicing\Poland\InvoiceKind;
use IntrAgile\EInvoicing\Poland\KeyValue;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\VatBucket;
use IntrAgile\EInvoicing\Syntax\ReadResult;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use InvalidArgumentException;

/**
 * Reads a Polish structured invoice FA(3), schema version 1-0E, into the EN 16931 model with
 * the Polish data in the PolishInvoice and PolishLine extensions; the inverse of Fa3Writer.
 *
 * Mapping highlights:
 * - The invoice type code (BT-3) follows the kind: VAT, ROZ and UPR are 380, the corrective kinds
 *   384, ZAL 386; the exact kind (RodzajFaktury) is always kept in the extension.
 * - The seller VAT identifier (BT-31) is "PL" and the NIP. P_6 is the actual delivery date
 *   (BT-72), OkresFa the invoicing period (BG-14). The first WZ is the despatch advice reference
 *   (BT-16), the others are the extension's warehouse documents.
 * - Each P_13_x bucket is one VAT breakdown row (BT-116, BT-117 from P_14_x). Its category and
 *   rate are those of the rate code its lines carry; lines of two codes (23 and 22) give
 *   category S without a rate. The zero, exempt and reverse charge rows have rate 0. The
 *   one-stop-shop row (P_13_5) is category S at the OSS rate (P_12_XII) of its rows, without a
 *   rate when they differ.
 * - An invoice without FaWiersz rows gets one synthetic bucket line per P_13 bucket.
 * - Totals: BT-106 sums the line net amounts, BT-109 the P_13 buckets, BT-110 P_14_1 to P_14_5;
 *   BT-112 is P_15 and BT-115 the Rozliczenie balance (DoZaplaty, or DoRozliczenia negated),
 *   else P_15. On a settlement invoice (ROZ), P_15 is the amount due (BT-115) and BT-112 is the
 *   sum of the buckets; the difference is the paid amount (BT-113). On an invoice in a foreign
 *   currency, BT-6 is PLN and BT-111 the sum of P_14_1W to P_14_4W, kept per bucket as well.
 * - On a corrective invoice, every corrected invoice (DaneFaKorygowanej) is a preceding invoice
 *   reference (BG-3); the correction data is kept in the extension when it carries more than
 *   those references (a KSeF number, the reason, the type, the parties before, ...).
 * - Additional descriptions (DodatkowyOpis) under the fixed keys of Fa3Format::DESCRIPTION_KEYS
 *   are read back to their EN 16931 terms; any other pair, a repeated single-value key, or a
 *   value its term cannot hold stays an extension description.
 * - The contract (BT-12) and purchase order (BT-13) references are the first numbers of
 *   WarunkiTransakcji Umowy and Zamowienia.
 * - The transport blocks, the new means of transport details, the authorised party and the
 *   attachment are kept verbatim.
 *
 * Elements that are not mapped are listed in ReadResult::$ignored; only a document without an
 * invoice number, issue date, currency or seller NIP is unsupported.
 */
final class Fa3Reader
{
    /** Single-value header terms carried as fixed-key descriptions. */
    private const HEADER_TERMS = ['BT-10', 'BT-11', 'BT-14', 'BT-15', 'BT-17', 'BT-19', 'BT-20', 'BT-28', 'BT-41', 'BT-45', 'BT-56', 'BT-83'];

    /** Single-value line terms carried as fixed-key descriptions. */
    private const LINE_TERMS = ['BT-127', 'BT-154', 'BT-128', 'BT-132', 'BT-133', 'BT-156', 'BT-159', 'BT-130'];

    /** Line allowance terms carried as fixed-key descriptions; read for a row with P_10. */
    private const ALLOWANCE_TERMS = ['BT-139', 'BT-137', 'BT-138', 'BT-140'];

    /**
     * @throws UnsafeXml when the document carries a DOCTYPE declaration
     * @throws UnsupportedDocument when it is not an FA(3) invoice or lacks the invoice number,
     *                             issue date, currency or seller NIP
     */
    public function read(string $xml): ReadResult
    {
        $d = new Fa3Document(SafeXml::load($xml));
        $root = $d->root;
        $parties = new Fa3PartyMapper($d);
        $blocks = new Fa3BlockMapper($d, $parties);
        $lineMapper = new Fa3LineMapper($d);

        $fa = $d->one('fa:Fa') ?? throw new UnsupportedDocument('Missing required invoice data (fa:Fa) in /fa:Faktura.');
        $sellerElement = $d->one('fa:Podmiot1') ?? throw new UnsupportedDocument('Missing required seller (Podmiot1) with its NIP in /fa:Faktura.');
        $currency = $d->requiredCode(CurrencyCode::class, $d->one('fa:KodWaluty', $fa), null, 'BT-5', 'fa:KodWaluty', $fa);
        $number = $d->requiredText('fa:P_2', $fa, 'BT-1');
        $issueDate = $d->requiredDate('fa:P_1', $fa, 'BT-2');
        $kind = $d->code(InvoiceKind::class, $d->one('fa:RodzajFaktury', $fa)) ?? InvoiceKind::Vat;

        try {
            [$createdAt, $systemInfo] = $this->header($d);
            $rows = $d->all('fa:FaWiersz', $fa);
            [$header, $notes, $lineTerms, $descriptions] = $this->descriptions($d, $fa, $rows);

            [$seller, $sellerExtras] = $parties->seller($sellerElement, $header['BT-28'] ?? null, $header['BT-41'] ?? null);
            $buyerElement = $d->one('fa:Podmiot2');
            [$buyer, $buyerExtras] = $buyerElement === null
                ? [new Party(name: ''), null]
                : $parties->buyer($buyerElement, $header['BT-45'] ?? null, $header['BT-56'] ?? null);
            $buyerCountry = $buyer->address?->country;
            $thirdParties = array_map(fn (DOMElement $party) => $parties->thirdParty($party), $d->all('fa:Podmiot3'));
            $authorized = $d->one('fa:PodmiotUpowazniony');

            $annotations = $blocks->annotations($d->one('fa:Adnotacje', $fa));
            $warehouse = array_values(array_filter(
                array_map(fn (DOMElement $wz): ?string => $d->text('.', $wz), $d->all('fa:WZ', $fa)),
                static fn (?string $wz): bool => $wz !== null,
            ));

            $buckets = $this->buckets($d, $fa, $currency);
            $onlyBucket = count($buckets) === 1 ? $buckets[0][0] : null;
            $lines = [];
            foreach ($rows as $index => $row) {
                $lines[] = $lineMapper->line($row, $lineTerms[$index] ?? [], $buyerCountry, $annotations->margin !== null, $onlyBucket);
            }
            if ($lines === []) {
                $position = 0;
                foreach ($buckets as [$bucket, $net]) {
                    $lines[] = Fa3LineMapper::bucketLine((string) ++$position, $bucket, $net, $buyerCountry);
                }
            }

            $correction = $kind->isCorrection() ? $blocks->correction($fa) : null;
            [$settlement, $balance] = $blocks->settlement($fa);
            [$dueDate, $payment, $paymentForm, $bankAccounts, $factorAccounts] = $blocks->payment($fa);
            if ($payment !== null && isset($header['BT-83'])) {
                $payment = new PaymentInstructions($payment->meansCode, $payment->meansText, $header['BT-83'], $payment->creditTransfers);
            } elseif (isset($header['BT-83'])) {
                $descriptions[] = new KeyValue(null, Fa3Format::DESCRIPTION_KEYS['BT-83'], $header['BT-83']);
            }
            [$terms, $contractReference, $orderReference] = $blocks->transactionTerms($fa);

            $vatInPln = [];
            foreach ($buckets as [$bucket, , , $pln]) {
                if ($pln !== null) {
                    $vatInPln[(int) $bucket->value] = $pln;
                }
            }

            $polish = new PolishInvoice(
                kind: $kind,
                annotations: $annotations,
                seller: $sellerExtras,
                buyer: $buyerExtras,
                buyerIsJstUnit: $buyerElement !== null && $d->flag('fa:JST', $buyerElement),
                buyerIsGvMember: $buyerElement !== null && $d->flag('fa:GV', $buyerElement),
                thirdParties: $thirdParties,
                placeOfIssue: $d->text('fa:P_1M', $fa),
                warehouseDocuments: array_slice($warehouse, 1),
                correction: $correction !== null && Fa3BlockMapper::beyondReferences($correction) ? $correction : null,
                partialAdvances: in_array($kind, [InvoiceKind::Zal, InvoiceKind::KorZal, InvoiceKind::Roz], true) ? $blocks->partialAdvances($fa) : [],
                advanceInvoices: $blocks->advanceInvoices($fa),
                order: $blocks->order($fa),
                vatInPln: $currency === CurrencyCode::PLN ? [] : $vatInPln,
                exchangeRate: $d->decimal('fa:KursWalutyZ', $fa),
                descriptions: $descriptions,
                settlement: $settlement,
                paymentForm: $paymentForm,
                bankAccounts: $bankAccounts,
                factorBankAccounts: $factorAccounts,
                footer: $blocks->footer($d->one('fa:Stopka')),
                transactionTerms: $terms,
                authorizedParty: $authorized === null ? null : $d->fragment($authorized),
                attachment: ($attachment = $d->one('fa:Zalacznik')) === null ? null : $d->fragment($attachment),
                createdAt: $createdAt,
                systemInfo: $systemInfo,
                article109Invoice: $d->flag('fa:FP', $fa),
                relatedParties: $d->flag('fa:TP', $fa),
                exciseRefund: $d->flag('fa:ZwrotAkcyzy', $fa),
            );

            $invoice = new Invoice(
                number: $number,
                issueDate: $issueDate,
                type: $kind->typeCode(),
                currency: $currency,
                seller: $seller,
                buyer: $buyer,
                lines: $lines,
                taxCurrency: $currency === CurrencyCode::PLN ? null : CurrencyCode::PLN,
                dueDate: $dueDate,
                buyerReference: $header['BT-10'] ?? null,
                projectReference: $header['BT-11'] ?? null,
                contractReference: $contractReference,
                purchaseOrderReference: $orderReference,
                salesOrderReference: $header['BT-14'] ?? null,
                receivingAdviceReference: $header['BT-15'] ?? null,
                despatchAdviceReference: $warehouse[0] ?? null,
                tenderOrLotReference: $header['BT-17'] ?? null,
                buyerAccountingReference: $header['BT-19'] ?? null,
                paymentTerms: $header['BT-20'] ?? null,
                notes: array_map(static fn (string $note): Note => new Note($note), $notes),
                precedingInvoices: $correction === null ? [] : array_map(
                    static fn ($corrected): PrecedingInvoiceReference => new PrecedingInvoiceReference($corrected->number, $corrected->issueDate),
                    $correction->invoices,
                ),
                delivery: $this->delivery($d, $fa),
                payment: $payment,
                totals: $this->totals($d, $fa, $kind, $lines, $buckets, $balance, $vatInPln, $currency),
                vatBreakdown: $this->vatBreakdown($lines, $buckets, $buyerCountry),
            );
        } catch (InvalidArgumentException $e) {
            throw new UnsupportedDocument($e->getMessage(), 0, $e);
        }

        return new ReadResult(invoice: $invoice->withExtension($polish), ignored: $d->ignored());
    }

    /**
     * @return array{?DateTimeImmutable, ?string}
     */
    private function header(Fa3Document $d): array
    {
        $header = $d->one('fa:Naglowek');
        if ($header === null) {
            return [null, null];
        }
        $d->token('fa:KodFormularza', $header);
        $d->token('fa:WariantFormularza', $header);

        return [$d->dateTime('fa:DataWytworzeniaFa', $header), $d->text('fa:SystemInfo', $header)];
    }

    /**
     * The additional descriptions split into header terms, notes (BT-22), line terms per row
     * index and the remaining pairs, in document order.
     *
     * @param  list<DOMElement>  $rows
     * @return array{array<string, string>, list<string>, array<int, array<string, string>>, list<KeyValue>}
     */
    private function descriptions(Fa3Document $d, DOMElement $fa, array $rows): array
    {
        $terms = array_flip(Fa3Format::DESCRIPTION_KEYS);
        $rowIndex = [];
        $allowanceRows = [];
        $labelRows = [];
        foreach ($rows as $index => $row) {
            $position = trim($d->one('fa:NrWierszaFa', $row)->textContent ?? '');
            $rowIndex[$position] ??= $index;
            $allowanceRows[$index] = $d->has('fa:P_10', $row);
            $unit = $d->one('fa:P_8A', $row);
            $labelRows[$index] = $unit !== null && UnitCode::tryFrom(trim($unit->textContent)) === null;
        }

        $header = [];
        $notes = [];
        $lines = [];
        $descriptions = [];
        foreach ($d->all('fa:DodatkowyOpis', $fa) as $element) {
            $lineNumber = $d->token('fa:NrWiersza', $element);
            $key = $d->text('fa:Klucz', $element);
            $value = $d->text('fa:Wartosc', $element);
            if ($key === null || $value === null) {
                continue;
            }
            if ($lineNumber !== null && preg_match('/\A[1-9]\d*\z/', $lineNumber) !== 1) {
                $d->reject($element, sprintf('line number "%s" is not a positive integer', $lineNumber));

                continue;
            }
            $term = $terms[$key] ?? null;
            $index = $lineNumber === null ? null : ($rowIndex[$lineNumber] ?? null);

            if ($term !== null && $lineNumber === null && $term === 'BT-22') {
                $notes[] = $value;

                continue;
            }
            if ($term !== null && $lineNumber === null && in_array($term, self::HEADER_TERMS, true) && ! isset($header[$term])) {
                $header[$term] = $value;

                continue;
            }
            if ($term !== null && $index !== null && ! isset($lines[$index][$term]) && self::lineTermFits($term, $value, $allowanceRows[$index], $labelRows[$index])) {
                $lines[$index][$term] = $value;

                continue;
            }
            $descriptions[] = new KeyValue($lineNumber === null ? null : (int) $lineNumber, $key, $value);
        }

        return [$header, $notes, $lines, $descriptions];
    }

    /**
     * Whether a line term can take the value: a country code for BT-159, a unit code other than
     * C62 on a row whose P_8A is a unit label for BT-130, a number for BT-137 and BT-138, an
     * allowance reason code for BT-140; allowance terms only on a row with P_10.
     */
    private static function lineTermFits(string $term, string $value, bool $hasAllowance, bool $hasUnitLabel): bool
    {
        if (in_array($term, self::LINE_TERMS, true)) {
            return match ($term) {
                'BT-159' => CountryCode::tryFrom($value) !== null,
                'BT-130' => $hasUnitLabel && ! in_array(UnitCode::tryFrom($value), [null, UnitCode::C62], true),
                default => true,
            };
        }
        if (! in_array($term, self::ALLOWANCE_TERMS, true) || ! $hasAllowance) {
            return false;
        }

        return match ($term) {
            'BT-137', 'BT-138' => self::isNumber($value),
            'BT-140' => AllowanceReasonCode::tryFrom($value) !== null,
            default => true,
        };
    }

    private static function isNumber(string $value): bool
    {
        try {
            BigDecimal::of($value);

            return true;
        } catch (MathException) {
            return false;
        }
    }

    /**
     * The P_13 buckets present: bucket, net (P_13_x), VAT (P_14_x) and VAT in PLN (P_14_xW). VAT
     * in PLN on a PLN invoice, or for a bucket without amounts, is reported, not read.
     *
     * @return list<array{VatBucket, BigDecimal, ?BigDecimal, ?BigDecimal}>
     */
    private function buckets(Fa3Document $d, DOMElement $fa, CurrencyCode $currency): array
    {
        $buckets = [];
        foreach (VatBucket::cases() as $bucket) {
            $net = $d->decimal('fa:'.$bucket->netElement(), $fa);
            $vatElement = $bucket->vatElement();
            $vat = $vatElement === null ? null : $d->decimal('fa:'.$vatElement, $fa);
            $plnName = $bucket->plnVatElement();
            $plnElement = $plnName === null ? null : $d->one('fa:'.$plnName, $fa);
            $present = $net !== null || $vat !== null;
            $pln = null;
            if ($plnElement !== null && $currency === CurrencyCode::PLN) {
                $d->reject($plnElement, 'VAT in PLN on a PLN invoice');
            } elseif ($plnElement !== null && ! $present) {
                $d->reject($plnElement, sprintf('VAT in PLN without %s', $bucket->netElement()));
            } elseif ($plnElement !== null) {
                $pln = $d->decimalOf($plnElement);
            }
            if ($present) {
                $buckets[] = [$bucket, $net ?? BigDecimal::zero(), $vat, $pln];
            }
        }

        return $buckets;
    }

    /**
     * One VAT breakdown row per bucket, with the category and rate of the rate code its lines
     * carry.
     *
     * @param  list<InvoiceLine>  $lines
     * @param  list<array{VatBucket, BigDecimal, ?BigDecimal, ?BigDecimal}>  $buckets
     * @return list<VatBreakdown>
     */
    private function vatBreakdown(array $lines, array $buckets, ?CountryCode $buyerCountry): array
    {
        $codes = [];
        $ossRates = [];
        foreach ($lines as $line) {
            try {
                $bucket = Fa3LineWriter::bucket($line, $buyerCountry);
                $code = Fa3LineWriter::rateCode($line, $buyerCountry);
            } catch (InvalidArgumentException) {
                continue;
            }
            $ossRate = $line->extensions()->get(PolishLine::class)?->ossRate;
            if ($bucket === VatBucket::Oss && $ossRate !== null) {
                $known = array_filter($ossRates, static fn (BigDecimal $rate): bool => $rate->isEqualTo($ossRate));
                if ($known === []) {
                    $ossRates[] = $ossRate;
                }

                continue;
            }
            $codes[$bucket->value][$code->value ?? ''] = $code;
        }

        $rows = [];
        foreach ($buckets as [$bucket, $net, $vat]) {
            $found = $codes[$bucket->value] ?? [];
            if ($bucket === VatBucket::Oss) {
                $category = VatCategory::StandardRate;
                $rate = count($ossRates) === 1 ? reset($ossRates) : null;
            } elseif (count($found) > 1) {
                $category = VatCategory::StandardRate;
                $rate = null;
            } else {
                $code = $found === [] ? Fa3LineMapper::defaultCode($bucket) : reset($found);
                [$category, $rate] = $code === null ? [Fa3LineMapper::codelessCategory($bucket), null] : $code->toModel();
            }
            $rows[] = new VatBreakdown($net, $vat ?? BigDecimal::zero(), $category, $rate);
        }

        return $rows;
    }

    /**
     * @param  list<InvoiceLine>  $lines
     * @param  list<array{VatBucket, BigDecimal, ?BigDecimal, ?BigDecimal}>  $buckets
     * @param  array<int, BigDecimal>  $vatInPln
     */
    private function totals(
        Fa3Document $d,
        DOMElement $fa,
        InvoiceKind $kind,
        array $lines,
        array $buckets,
        ?BigDecimal $balance,
        array $vatInPln,
        CurrencyCode $currency,
    ): DocumentTotals {
        $lineNet = BigDecimal::zero();
        foreach ($lines as $line) {
            $lineNet = $lineNet->plus($line->netAmount ?? BigDecimal::zero());
        }
        $net = BigDecimal::zero();
        $vat = BigDecimal::zero();
        foreach ($buckets as [, $bucketNet, $bucketVat]) {
            $net = $net->plus($bucketNet);
            $vat = $vat->plus($bucketVat ?? BigDecimal::zero());
        }
        $p15 = $d->decimal('fa:P_15', $fa);
        $paid = null;
        if ($kind === InvoiceKind::Roz) {
            $total = $net->plus($vat);
            $due = $p15 ?? $total;
            $paid = $total->minus($due);
            $paid = $paid->isZero() ? null : $paid;
        } else {
            $total = $p15 ?? $net->plus($vat);
            $due = $balance ?? $total;
        }
        $plnVat = null;
        if ($currency !== CurrencyCode::PLN && $vatInPln !== []) {
            $plnVat = array_reduce($vatInPln, static fn (BigDecimal $sum, BigDecimal $amount): BigDecimal => $sum->plus($amount), BigDecimal::zero());
        }

        return new DocumentTotals(
            sumOfLineNetAmounts: $lineNet,
            totalWithoutVat: $net,
            totalWithVat: $total,
            amountDue: $due,
            totalVat: $vat,
            totalVatInAccountingCurrency: $plnVat,
            paidAmount: $paid,
        );
    }

    private function delivery(Fa3Document $d, DOMElement $fa): ?Delivery
    {
        $date = $d->date('fa:P_6', $fa);
        $period = $d->one('fa:OkresFa', $fa);
        $start = $period === null ? null : $d->date('fa:P_6_Od', $period);
        $end = $period === null ? null : $d->date('fa:P_6_Do', $period);
        if ($date === null && $start === null && $end === null) {
            return null;
        }

        return new Delivery(
            actualDeliveryDate: $date,
            invoicingPeriod: $start === null && $end === null ? null : new Period($start, $end),
        );
    }
}
