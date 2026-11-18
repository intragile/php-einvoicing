<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\IdentifierScheme;
use IntrAgile\EInvoicing\CodeList\UnitCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Contact;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\InvoiceLine;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Poland\DocumentReference;
use IntrAgile\EInvoicing\Poland\InvoiceKind;
use IntrAgile\EInvoicing\Poland\Nip;
use IntrAgile\EInvoicing\Poland\PaymentForm;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
use IntrAgile\EInvoicing\Poland\PolishParty;
use IntrAgile\EInvoicing\Poland\ThirdParty;
use IntrAgile\EInvoicing\Poland\ThirdPartyRole;
use IntrAgile\EInvoicing\Poland\VatBucket;
use IntrAgile\EInvoicing\Poland\VatRateCode;
use InvalidArgumentException;

/**
 * Checks an invoice before it is written as FA(3) and refuses, in one exception, every model
 * value FA(3) cannot carry. Each violation reads "<term or element>: <reason>".
 *
 * Refused because FA(3) has no element for them: the payee (BG-10), the tax representative
 * (BG-11), the deliver-to party name, location and address (BT-70, BT-71, BG-15), document
 * allowances and charges (BG-20, BG-21), additional supporting documents (BG-24), line charges
 * (BG-28), the invoiced object identifier (BT-18), party identifiers (BT-29, BT-46), the seller
 * legal registration (BT-30), the buyer legal registration next to a VAT identifier (BT-47),
 * the seller tax registration and additional legal information (BT-32, BT-33), electronic
 * addresses (BT-34, BT-49), a VAT accounting currency other than PLN (BT-6), the tax point date
 * code (BT-8), the paid amount (BT-113, except on a settlement invoice) and the rounding amount
 * (BT-114), and payment card and direct debit data (BG-18, BG-19). The text terms FA(3) carries
 * as additional descriptions are not refused.
 *
 * Refused as well: note subject codes (BT-21), country subdivisions (BT-39, BT-54 and those of
 * third-party and correspondence addresses), preceding invoice references (BG-3) on an invoice
 * that is not a correction, line periods (BG-26), item price discounts and gross prices (BT-147,
 * BT-148), item classifications (BT-158) and item attributes (BG-32). VAT exemption reasons
 * (BT-120, BT-121) are accepted only with the exemption annotation (Annotations::$exemption),
 * which supersedes them; the legal basis is never guessed. Also refused: identifier schemes FA(3)
 * has no field for (BT-128-1, BT-47-1 and the third-party legal registration scheme), a price
 * base quantity unit (BT-150) other than the line unit, the payment means text (BT-82) when a
 * payment form is written, the tax registration and additional legal information of the buyer
 * or a third party, and the trading name and contact name of a third party. A contract or
 * purchase order reference (BT-12, BT-13) that differs from the contracts or orders of the
 * transaction terms is refused.
 *
 * Also refused: a seller VAT identifier (BT-31) that is not "PL" with a NIP of the schema
 * pattern, missing document totals, VAT categories and rates without an FA(3) rate code or
 * summary bucket, a written line whose summary bucket (P_13_x) no VAT breakdown row writes, VAT
 * breakdown rows whose lines map to different rate codes, decimals beyond
 * the schema (amounts 2, quantities and rates 6, unit prices and P_10 8), a half-open invoicing
 * period (BG-14), an actual delivery date (BT-72) that differs from the tax point date (BT-7),
 * a supply date (P_6) next to an invoicing period, which FA(3) offers as alternatives, a
 * verbatim fragment that is not the element of its schema position, and lists longer than the
 * schema allows (WZ, RachunekBankowy, Podmiot3, DaneKontaktowe, Umowy, Zamowienia,
 * NrPartiiTowaru).
 *
 * Kind-specific data is checked against the effective kind (the extension's, else the one
 * derived from BT-3): correction data only on KOR, KOR_ZAL and KOR_ROZ; state-before rows only on
 * corrections; partial advances only on ZAL, KOR_ZAL and ROZ; settled advance invoices only on
 * ROZ and KOR_ROZ; the order and the advance exchange rate (KursWalutyZ) only on ZAL and KOR_ZAL,
 * and an advance invoice (ZAL) needs the order; state-before order lines only on KOR_ZAL; bucket
 * lines only on ZAL, KOR_ZAL and KOR; a margin scheme line needs the margin annotation. On a
 * corrective invoice the preceding invoice references (BG-3) need an issue date (BT-26) when they
 * stand in for the correction data, and must name a corrected invoice when correction data is
 * given. The seller and buyers before the correction are refused like the parties, and the
 * exchange rate before the correction needs the amount before it (P_15ZK).
 *
 * An invoice in a foreign currency needs the VAT in PLN of each bucket 1 to 4 it uses, and none
 * for other buckets; VAT in PLN and the advance exchange rate are refused on an invoice in PLN.
 * The VAT total in accounting currency (BT-111) must equal the sum of the VAT in PLN, or on an
 * invoice in PLN the VAT total.
 */
final class Fa3Guard
{
    private const AMOUNT = 2;

    private const QUANTITY = 6;

    private const UNIT_PRICE = 8;

    private const MAX_BANK_ACCOUNTS = 100;

    private const MAX_TERMS_DOCUMENTS = 100;

    private const MAX_BATCH_NUMBERS = 1000;

    /**
     * @throws InvalidArgumentException listing every violation
     */
    public static function check(Invoice $invoice): void
    {
        $polish = $invoice->extensions()->get(PolishInvoice::class) ?? new PolishInvoice;
        $violations = [
            ...self::unmapped($invoice, $polish),
            ...self::parties($invoice, $polish),
            ...self::kind($invoice, $polish),
            ...self::kindSpecific($invoice, $polish),
            ...self::bucketLines($invoice),
            ...self::precedingInvoices($invoice, $polish),
            ...self::correctionParties($polish),
            ...self::currency($invoice, $polish),
            ...self::limits($invoice, $polish),
            ...self::fragments($polish),
            ...self::dates($invoice),
            ...self::rates($invoice, $polish),
            ...self::decimals($invoice),
        ];

        if ($violations !== []) {
            $violations = array_map(static fn (string $violation): string => rtrim($violation, '.'), $violations);
            throw new InvalidArgumentException('The invoice cannot be written as FA(3): '.implode('; ', $violations));
        }
    }

    /**
     * @return list<string>
     */
    private static function unmapped(Invoice $invoice, PolishInvoice $polish): array
    {
        $none = 'FA(3) has no element for it';
        $violations = [];
        $refuse = static function (bool $present, string $term, string $reason = '') use (&$violations, $none): void {
            if ($present) {
                $violations[] = $term.': '.($reason === '' ? $none : $reason);
            }
        };

        $refuse($invoice->payee !== null, 'BG-10', 'FA(3) has no element for the payee');
        $refuse($invoice->taxRepresentative !== null, 'BG-11', 'FA(3) has no element for the seller tax representative');
        $refuse($invoice->delivery?->partyName !== null, 'BT-70');
        $refuse($invoice->delivery?->locationIdentifier !== null, 'BT-71');
        $refuse($invoice->delivery?->address !== null, 'BG-15', 'FA(3) has no element for the deliver-to address');
        $refuse($invoice->allowances !== [], 'BG-20', 'FA(3) has no document level allowances; apply them to the lines');
        $refuse($invoice->charges !== [], 'BG-21', 'FA(3) has no document level charges; invoice them as lines');
        $refuse($invoice->additionalDocuments !== [], 'BG-24');
        $refuse($invoice->invoicedObjectIdentifier !== null, 'BT-18');
        $refuse($invoice->taxCurrency !== null && $invoice->taxCurrency !== CurrencyCode::PLN, 'BT-6', 'the VAT accounting currency can only be PLN');
        $refuse($invoice->taxPointDateCode !== null, 'BT-8');
        $refuse(self::nonZero($invoice->totals?->paidAmount) && self::kindOf($invoice, $polish) !== InvoiceKind::Roz, 'BT-113');
        $refuse(self::nonZero($invoice->totals?->roundingAmount), 'BT-114');
        $refuse($invoice->payment?->card !== null, 'BG-18', 'FA(3) carries the payment form only, not card data');
        $refuse($invoice->payment?->directDebit !== null, 'BG-19', 'FA(3) has no direct debit');
        $refuse(
            $invoice->payment?->meansText !== null && ($polish->paymentForm ?? PaymentForm::fromMeansCode($invoice->payment->meansCode)) !== null,
            'BT-82',
            'FA(3) writes the payment means text (OpisPlatnosci) only for a payment without an FA(3) payment form (PlatnoscInna)',
        );
        foreach ($invoice->notes as $index => $note) {
            $refuse($note->subjectCode !== null, sprintf('BT-21 (note %d)', $index + 1), 'FA(3) has no note subject code');
        }
        $exempted = $polish->annotations->exemption !== null;
        foreach ($invoice->vatBreakdown as $row) {
            $label = substr(Fa3Buckets::label($row), strlen('BG-23 '));
            $hint = 'FA(3) states the exemption as P_19 with its legal basis; set Annotations::$exemption of the Polish invoice extension';
            $refuse(! $exempted && $row->exemptionReason !== null, 'BT-120 '.$label, $hint);
            $refuse(! $exempted && $row->exemptionReasonCode !== null, 'BT-121 '.$label, $hint);
        }
        foreach ([[$invoice->seller->address, 'BT-39'], [$invoice->buyer->address, 'BT-54'], [$polish->seller?->correspondenceAddress, 'Podmiot1/AdresKoresp subdivision'], [$polish->buyer?->correspondenceAddress, 'Podmiot2/AdresKoresp subdivision']] as [$address, $term]) {
            $refuse($address?->subdivision !== null, $term, 'FA(3) addresses have no country subdivision');
        }
        foreach ($polish->thirdParties as $index => $thirdParty) {
            foreach ([$thirdParty->party->address, $thirdParty->extras?->correspondenceAddress] as $address) {
                $refuse($address?->subdivision !== null, sprintf('Podmiot3[%d] subdivision', $index + 1), 'FA(3) addresses have no country subdivision');
            }
        }
        foreach (self::referenceConflicts($invoice, $polish) as $violation) {
            $violations[] = $violation;
        }

        foreach ($invoice->lines as $line) {
            $refuse($line->charges !== [], sprintf('BG-28 (line "%s")', $line->id), 'FA(3) has no line charges; include them in the net amount');
            $scheme = $line->item->standardIdentifier?->scheme;
            $refuse(
                $line->item->standardIdentifier !== null && $scheme !== IdentifierScheme::C_0160->value,
                sprintf('BT-157 (line "%s")', $line->id),
                sprintf('only a GTIN (scheme 0160) has an FA(3) element, got scheme "%s"', $scheme ?? ''),
            );
            $at = sprintf(' (line "%s")', $line->id);
            $refuse($line->period !== null, 'BG-26'.$at);
            $refuse($line->price->discount !== null, 'BT-147'.$at, 'FA(3) states the net unit price only (P_9A)');
            $refuse($line->price->grossPrice !== null, 'BT-148'.$at, 'FA(3) states the net unit price only (P_9A)');
            $refuse($line->item->classifications !== [], 'BT-158'.$at, 'FA(3) carries classifications only as the Polish PKWiU, CN and PKOB codes of the line extension');
            $refuse($line->item->attributes !== [], 'BG-32'.$at);
            $refuse($line->objectIdentifier?->scheme !== null, 'BT-128-1'.$at, 'FA(3) has no scheme field; the identifier value is carried as an additional description');
            $refuse(
                $line->price->baseQuantityUnit !== null && $line->price->baseQuantityUnit !== $line->unit,
                'BT-150'.$at,
                'FA(3) has one unit per line (P_8A); the price base quantity unit must be the line unit (BT-130)',
            );
            $base = $line->price->baseQuantity;
            $refuse(
                $base !== null && ! $base->isEqualTo(1),
                sprintf('BT-149 (line "%s")', $line->id),
                'FA(3) states the unit price per one unit (P_9A); a base quantity other than 1 cannot be carried',
            );
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private static function parties(Invoice $invoice, PolishInvoice $polish): array
    {
        $violations = [];
        $seller = $invoice->seller;
        $buyer = $invoice->buyer;

        $nip = Fa3PartyWriter::nip($seller->vatIdentifier);
        if ($nip === null || ! Nip::isWellFormed($nip)) {
            $violations[] = sprintf(
                'BT-31: FA(3) needs the seller VAT identifier as "PL" followed by a 10 digit NIP, got %s',
                $seller->vatIdentifier === null ? 'none' : '"'.$seller->vatIdentifier.'"',
            );
        }
        if ($seller->address === null) {
            $violations[] = 'BG-5: FA(3) requires the seller address (Podmiot1/Adres)';
        }
        foreach (['BT-29' => $seller->identifiers !== [], 'BT-30' => $seller->legalRegistration !== null, 'BT-32' => $seller->taxRegistration !== null, 'BT-33' => $seller->additionalLegalInformation !== null, 'BT-34' => $seller->electronicAddress !== null] as $term => $present) {
            if ($present) {
                $violations[] = $term.': FA(3) has no element for it';
            }
        }
        if ($polish->seller?->vatPrefix !== null && $polish->seller->vatPrefix !== CountryCode::PL) {
            $violations[] = 'PrefiksPodatnika: the seller VAT prefix is fixed to PL';
        }
        if ($polish->seller?->customerNumber !== null || $polish->seller?->buyerId !== null) {
            $violations[] = 'Podmiot1: the seller has no customer number (NrKlienta) or buyer identifier (IDNabywcy)';
        }

        $violations = [...$violations, ...self::identification($buyer, 'Podmiot2', 'BT-48', 'BT-46', 'BT-47', 'BT-47-1', 'BT-49')];
        if ($polish->buyer?->vatPrefix !== null) {
            $violations[] = 'Podmiot2: FA(3) has no buyer VAT prefix; the EU prefix is taken from the buyer VAT identifier (BT-48)';
        }
        if ($polish->buyer?->gln !== null && $buyer->address === null) {
            $violations[] = 'Podmiot2 GLN: FA(3) writes the GLN in the address (Adres/GLN), and the buyer has no address';
        }
        foreach ([['JST', $polish->buyerIsJstUnit, ThirdPartyRole::JstRecipient, 'a subordinate unit of a local government'], ['GV', $polish->buyerIsGvMember, ThirdPartyRole::GvMemberRecipient, 'a VAT group member']] as [$flag, $set, $role, $what]) {
            $found = array_filter($polish->thirdParties, static fn (ThirdParty $party): bool => $party->role === $role);
            if ($set && $found === []) {
                $violations[] = sprintf('%s: a buyer marked as %s needs that unit as a third party (Podmiot3) in role %d', $flag, $what, $role->value);
            }
        }
        if ($polish->buyer?->status !== null) {
            $violations[] = 'Podmiot2: the taxpayer status (StatusInfoPodatnika) exists only for the seller';
        }

        foreach ($polish->thirdParties as $index => $thirdParty) {
            $at = sprintf('Podmiot3[%d]', $index + 1);
            foreach (self::identification($thirdParty->party, $at, $at.' VAT identifier', $at.' identifiers', $at.' legal registration', $at.' legal registration scheme', $at.' electronic address') as $violation) {
                $violations[] = $violation;
            }
            if ($thirdParty->party->tradingName !== null) {
                $violations[] = $at.' trading name: FA(3) has no element for it';
            }
            if ($thirdParty->party->contact?->name !== null) {
                $violations[] = $at.' contact name: FA(3) carries only the e-mail address and phone number (DaneKontaktowe)';
            }
            if ($thirdParty->internalId !== null && $thirdParty->party->vatIdentifier !== null) {
                $violations[] = $at.': FA(3) identifies a third party by its internal identifier (IDWew) or its VAT identifier, not both';
            }
            if ($thirdParty->internalId !== null && $thirdParty->party->legalRegistration !== null) {
                $violations[] = $at.': FA(3) identifies a third party by its internal identifier (IDWew) or its legal registration as tax number (NrID), not both';
            }
            if ($thirdParty->extras?->gln !== null && $thirdParty->party->address === null) {
                $violations[] = $at.' GLN: FA(3) writes the GLN in the address (Adres/GLN), and the party has no address';
            }
            if ($thirdParty->extras?->vatPrefix !== null || $thirdParty->extras?->status !== null) {
                $violations[] = $at.': a third party has no VAT prefix or taxpayer status in FA(3)';
            }
        }

        $contacts = [['BT-42', 'BT-43', $seller->contact], ['BT-57', 'BT-58', $buyer->contact]];
        foreach ($polish->thirdParties as $index => $thirdParty) {
            $at = sprintf('Podmiot3[%d] contact', $index + 1);
            $contacts[] = [$at.' phone', $at.' e-mail', $thirdParty->party->contact];
        }
        foreach ($contacts as [$phoneTerm, $emailTerm, $contact]) {
            if ($contact?->phone === '') {
                $violations[] = $phoneTerm.': FA(3) has no empty phone number (DaneKontaktowe/Telefon); leave it null';
            }
            if ($contact?->email === '') {
                $violations[] = $emailTerm.': FA(3) has no empty e-mail address (DaneKontaktowe/Email); leave it null';
            }
        }

        foreach ([[$seller, 'BG-5'], [$buyer, 'BG-8']] as [$party, $term]) {
            if ($party->address !== null && Fa3PartyWriter::addressLine1($party->address) === null) {
                $violations[] = $term.': FA(3) requires at least one address line (AdresL1)';
            }
        }

        return $violations;
    }

    /**
     * Refusals for a buyer or third party, identified per Fa3PartyWriter.
     *
     * @return list<string>
     */
    private static function identification(Party $party, string $label, string $vatTerm, string $identifiersTerm, string $legalTerm, string $schemeTerm, string $addressTerm): array
    {
        $violations = [];
        $nip = Fa3PartyWriter::nip($party->vatIdentifier);
        if ($nip !== null && ! Nip::isWellFormed($nip)) {
            $violations[] = sprintf('%s: a Polish VAT identifier must be "PL" followed by a 10 digit NIP, got "%s"', $vatTerm, $party->vatIdentifier);
        }
        $euPrefix = Fa3PartyWriter::euPrefix($party->vatIdentifier);
        if ($euPrefix !== null && preg_match('/\A(\d|[A-Z]|\+|\*){1,12}\z/', substr((string) $party->vatIdentifier, 2)) !== 1) {
            $violations[] = sprintf('%s: the EU VAT number after the prefix must be 1 to 12 digits or capital letters (NrVatUE), got "%s"', $vatTerm, $party->vatIdentifier);
        }
        if ($party->identifiers !== []) {
            $violations[] = $identifiersTerm.': FA(3) has no element for it';
        }
        if ($party->legalRegistration !== null && $party->vatIdentifier !== null) {
            $violations[] = $legalTerm.': FA(3) carries it only as the tax number (NrID) of a party without a VAT identifier';
        }
        if ($party->legalRegistration?->scheme !== null) {
            $violations[] = $schemeTerm.': FA(3) has no scheme for the tax number (NrID)';
        }
        if ($party->taxRegistration !== null) {
            $violations[] = $label.' tax registration: FA(3) has no element for it';
        }
        if ($party->additionalLegalInformation !== null) {
            $violations[] = $label.' additional legal information: FA(3) has no element for it';
        }
        if ($party->electronicAddress !== null) {
            $violations[] = $addressTerm.': FA(3) has no element for it';
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private static function kind(Invoice $invoice, PolishInvoice $polish): array
    {
        if (self::kindOf($invoice, $polish) !== null) {
            return [];
        }

        return [sprintf(
            'BT-3: invoice type code %s implies no FA(3) invoice kind; set the kind of the Polish invoice extension',
            $invoice->type->value,
        )];
    }

    /**
     * BT-12 and BT-13 share WarunkiTransakcji with the transaction terms of the extension; when
     * the terms list contracts or orders, the EN reference must be one of their numbers.
     *
     * @return list<string>
     */
    private static function referenceConflicts(Invoice $invoice, PolishInvoice $polish): array
    {
        $terms = $polish->transactionTerms;
        if ($terms === null) {
            return [];
        }
        $violations = [];
        foreach ([['BT-12', $invoice->contractReference, $terms->contracts, 'Umowy'], ['BT-13', $invoice->purchaseOrderReference, $terms->orders, 'Zamowienia']] as [$term, $reference, $documents, $element]) {
            $numbers = array_map(static fn (DocumentReference $document): ?string => $document->number, $documents);
            if ($reference !== null && $documents !== [] && ! in_array($reference, $numbers, true)) {
                $violations[] = sprintf(
                    '%s: "%s" conflicts with the %s of the transaction terms (%s); give the reference in one place',
                    $term,
                    $reference,
                    $element,
                    implode(', ', array_map(static fn (?string $number): string => '"'.($number ?? '').'"', $numbers)),
                );
            }
        }

        return $violations;
    }

    /**
     * Each verbatim fragment must be the element of its schema position.
     *
     * @return list<string>
     */
    private static function fragments(PolishInvoice $polish): array
    {
        $expected = [
            ['NoweSrodkiTransportu', $polish->annotations->newTransport],
            ['PodmiotUpowazniony', $polish->authorizedParty],
            ['Zalacznik', $polish->attachment],
        ];
        foreach ($polish->transactionTerms->transports ?? [] as $transport) {
            $expected[] = ['Transport', $transport];
        }
        $violations = [];
        foreach ($expected as [$name, $fragment]) {
            if ($fragment !== null && $fragment->name !== $name) {
                $violations[] = sprintf('%s: the fragment must be a %s element, got %s', $name, $name, $fragment->name);
            }
        }

        return $violations;
    }

    /**
     * Data FA(3) carries only on some invoice kinds, checked against the effective kind (the
     * extension's, else the one derived from BT-3).
     *
     * @return list<string>
     */
    private static function kindSpecific(Invoice $invoice, PolishInvoice $polish): array
    {
        $kind = self::kindOf($invoice, $polish);
        if ($kind === null) {
            return [];
        }
        $violations = [];
        $refuse = static function (bool $present, string $term, string $reason) use (&$violations, $kind): void {
            if ($present) {
                $violations[] = sprintf('%s: %s, not %s', $term, $reason, $kind->value);
            }
        };
        $in = static fn (InvoiceKind ...$kinds): bool => in_array($kind, $kinds, true);

        $refuse($polish->correction !== null && ! $kind->isCorrection(), 'DaneFaKorygowanej', 'correction data is written only on a corrective invoice (KOR, KOR_ZAL, KOR_ROZ)');
        $refuse($polish->partialAdvances !== [] && ! $in(InvoiceKind::Zal, InvoiceKind::KorZal, InvoiceKind::Roz), 'ZaliczkaCzesciowa', 'partial advance payments are written only on ZAL, KOR_ZAL and ROZ');
        $refuse($polish->advanceInvoices !== [] && ! $in(InvoiceKind::Roz, InvoiceKind::KorRoz), 'FakturaZaliczkowa', 'settled advance invoices are written only on a settlement invoice or its correction (ROZ, KOR_ROZ)');
        $refuse($polish->order !== null && ! $in(InvoiceKind::Zal, InvoiceKind::KorZal), 'Zamowienie', 'the order is written only on an advance invoice or its correction (ZAL, KOR_ZAL)');
        $refuse($polish->exchangeRate !== null && ! $in(InvoiceKind::Zal, InvoiceKind::KorZal), 'KursWalutyZ', 'the exchange rate of an advance invoice is written only on ZAL and KOR_ZAL');
        if ($kind->isCorrection() && Fa3BlockWriter::correction($invoice, $polish, $kind) === null) {
            $violations[] = sprintf('DaneFaKorygowanej: a corrective invoice (%s) needs the corrected invoice: give the preceding invoice reference (BG-3) with its issue date, or the correction data of the Polish invoice extension', $kind->value);
        }
        if ($kind === InvoiceKind::Zal && $polish->order === null) {
            $violations[] = 'Zamowienie: an advance invoice (ZAL) needs the order or contract it refers to (art. 106f ust. 1 pkt 4 of the VAT Act)';
        }
        foreach ($polish->order->lines ?? [] as $index => $line) {
            $refuse($line->stateBefore && $kind !== InvoiceKind::KorZal, sprintf('StanPrzedZ (order line %d)', $index + 1), 'a state-before order line is written only on a correction of an advance invoice (KOR_ZAL)');
        }
        foreach ($invoice->lines as $line) {
            $polishLine = $line->extensions()->get(PolishLine::class);
            $at = sprintf(' (line "%s")', $line->id);
            $refuse($polishLine?->stateBefore === true && ! $kind->isCorrection(), 'StanPrzed'.$at, 'a state-before row is written only on a corrective invoice (KOR, KOR_ZAL, KOR_ROZ)');
            $refuse($polishLine?->bucketLine === true && ! $in(InvoiceKind::Zal, InvoiceKind::KorZal, InvoiceKind::Kor), 'FaWiersz'.$at, 'a bucket line stands for an invoice without FaWiersz rows, which only ZAL, KOR_ZAL and KOR may be');
            if ($polishLine?->margin === true && $polish->annotations->margin === null) {
                $violations[] = sprintf('PMarzy: line "%s" is a margin scheme line, which needs the margin scheme annotation (Annotations::$margin)', $line->id);
            }
        }

        return $violations;
    }

    /**
     * Preceding invoice references (BG-3) are carried by the correction block: on a corrective
     * invoice without correction data they become the corrected invoices, otherwise each must
     * name one of the corrected invoices.
     *
     * @return list<string>
     */
    private static function precedingInvoices(Invoice $invoice, PolishInvoice $polish): array
    {
        $kind = self::kindOf($invoice, $polish);
        if ($invoice->precedingInvoices === [] || $kind === null) {
            return [];
        }
        if (! $kind->isCorrection()) {
            return ['BG-3: FA(3) references a preceding invoice only on a corrective invoice (KOR, KOR_ZAL, KOR_ROZ)'];
        }
        $violations = [];
        foreach ($invoice->precedingInvoices as $reference) {
            if ($polish->correction === null && $reference->issueDate === null) {
                $violations[] = sprintf('BT-26 (BG-3 "%s"): FA(3) needs the issue date of the corrected invoice (DataWystFaKorygowanej)', $reference->number);
            }
            if ($polish->correction !== null && ! Fa3BlockWriter::references($reference, $polish->correction)) {
                $violations[] = sprintf(
                    'BG-3: "%s"%s conflicts with the corrected invoices of the correction data (DaneFaKorygowanej); give the reference in one place',
                    $reference->number,
                    $reference->issueDate === null ? '' : ' of '.$reference->issueDate->format('Y-m-d'),
                );
            }
        }

        return $violations;
    }

    /**
     * The seller and buyers before the correction (Podmiot1K, Podmiot2K) and the amount before it.
     *
     * @return list<string>
     */
    private static function correctionParties(PolishInvoice $polish): array
    {
        $correction = $polish->correction;
        if ($correction === null) {
            return [];
        }
        $violations = [];
        if ($correction->exchangeRateBefore !== null && $correction->amountBefore === null) {
            $violations[] = 'KursWalutyZK: FA(3) writes the exchange rate before the correction only with the amount before it (P_15ZK)';
        }
        $seller = $correction->sellerBefore;
        if ($seller !== null) {
            $nip = Fa3PartyWriter::nip($seller->vatIdentifier);
            if ($nip === null || ! Nip::isWellFormed($nip)) {
                $violations[] = 'Podmiot1K: FA(3) needs the seller VAT identifier before the correction as "PL" followed by a 10 digit NIP';
            }
            if ($seller->address === null || Fa3PartyWriter::addressLine1($seller->address) === null) {
                $violations[] = 'Podmiot1K: FA(3) requires the seller address before the correction with at least one address line (Adres/AdresL1)';
            }
            foreach (['identifiers' => $seller->identifiers !== [], 'legal registration' => $seller->legalRegistration !== null, 'tax registration' => $seller->taxRegistration !== null, 'additional legal information' => $seller->additionalLegalInformation !== null] as $field => $present) {
                if ($present) {
                    $violations[] = sprintf('Podmiot1K %s: FA(3) has no element for it', $field);
                }
            }
            $violations = [...$violations, ...self::unmappedPartyDetails($seller, 'Podmiot1K')];
        }
        foreach ($correction->buyersBefore as $index => $buyer) {
            $at = sprintf('Podmiot2K[%d]', $index + 1);
            $violations = [
                ...$violations,
                ...self::identification($buyer, $at, $at.' VAT identifier', $at.' identifiers', $at.' legal registration', $at.' legal registration scheme', $at.' electronic address'),
                ...self::unmappedPartyDetails($buyer, $at),
            ];
            if ($buyer->address !== null && Fa3PartyWriter::addressLine1($buyer->address) === null) {
                $violations[] = $at.': FA(3) requires at least one address line (AdresL1)';
            }
        }

        return $violations;
    }

    /**
     * Party details a party before the correction has no element for.
     *
     * @return list<string>
     */
    private static function unmappedPartyDetails(Party $party, string $label): array
    {
        $violations = [];
        if ($party->tradingName !== null) {
            $violations[] = $label.' trading name: FA(3) has no element for it';
        }
        if ($party->contact !== null) {
            $violations[] = $label.' contact: FA(3) has no contact data for a party before the correction';
        }
        if ($party->address?->subdivision !== null) {
            $violations[] = $label.' subdivision: FA(3) addresses have no country subdivision';
        }
        if ($label === 'Podmiot1K' && $party->electronicAddress !== null) {
            $violations[] = $label.' electronic address: FA(3) has no element for it';
        }

        return $violations;
    }

    /**
     * VAT in PLN (P_14_1W to P_14_4W) is supplied for each bucket 1 to 4 of an invoice in a
     * foreign currency, and only there; the VAT total in accounting currency (BT-111) must agree.
     *
     * @return list<string>
     */
    private static function currency(Invoice $invoice, PolishInvoice $polish): array
    {
        $violations = [];
        $foreign = $invoice->currency !== CurrencyCode::PLN;
        if (! $foreign) {
            foreach (array_keys($polish->vatInPln) as $key) {
                $violations[] = sprintf('P_14_%dW: VAT in PLN is written only on an invoice in a foreign currency, this one is in PLN', $key);
            }
            if ($polish->exchangeRate !== null) {
                $violations[] = 'KursWalutyZ: the exchange rate is written only on an invoice in a foreign currency, this one is in PLN';
            }
            $accounting = $invoice->totals?->totalVatInAccountingCurrency;
            $total = $invoice->totals->totalVat ?? BigDecimal::zero();
            if ($accounting !== null && ! $accounting->isEqualTo($total)) {
                $violations[] = sprintf('BT-111: %s differs from the VAT total %s of an invoice in PLN', $accounting, $total);
            }

            return $violations;
        }

        try {
            $buckets = array_map(static fn (array $entry): VatBucket => $entry[0], Fa3Buckets::of($invoice));
        } catch (InvalidArgumentException) {
            return [];
        }
        foreach ($buckets as $bucket) {
            if ($bucket->plnVatElement() !== null && $polish->vatInPlnFor($bucket) === null) {
                $violations[] = sprintf(
                    '%s: an invoice in %s needs the VAT in PLN of bucket %s (PolishInvoice::$vatInPln); it is supplied by the issuer, never computed',
                    $bucket->plnVatElement(),
                    $invoice->currency->value,
                    $bucket->value,
                );
            }
        }
        foreach (array_keys($polish->vatInPln) as $key) {
            if (! in_array(VatBucket::from((string) $key), $buckets, true)) {
                $violations[] = sprintf('P_14_%dW: VAT in PLN is supplied for bucket %d, which the invoice does not use (no P_13_%d)', $key, $key, $key);
            }
        }
        $accounting = $invoice->totals?->totalVatInAccountingCurrency;
        $supplied = array_reduce($polish->vatInPln, static fn (BigDecimal $sum, BigDecimal $amount): BigDecimal => $sum->plus($amount), BigDecimal::zero());
        if ($accounting !== null && ! $accounting->isEqualTo($supplied)) {
            $violations[] = sprintf('BT-111: %s differs from the sum %s of the VAT in PLN (P_14_xW); FA(3) carries the VAT in PLN per bucket only', $accounting, $supplied);
        }

        return $violations;
    }

    /**
     * Occurrence limits of the schema that the model does not enforce on its own.
     *
     * @return list<string>
     */
    private static function limits(Invoice $invoice, PolishInvoice $polish): array
    {
        $violations = [];
        $limit = static function (int $count, string $element, int $max) use (&$violations): void {
            if ($count > $max) {
                $violations[] = sprintf('%s: occurs %d times; FA(3) allows at most %d', $element, $count, $max);
            }
        };

        $limit(count(Fa3Writer::warehouseDocuments($invoice, $polish)), 'WZ', PolishInvoice::MAX_WAREHOUSE_DOCUMENTS);
        $limit(count(Fa3Writer::bankAccounts($invoice, $polish)), 'RachunekBankowy', self::MAX_BANK_ACCOUNTS);
        $limit(count($polish->thirdParties), 'Podmiot3', PolishInvoice::MAX_THIRD_PARTIES);
        $contacts = static fn (?Contact $contact, ?PolishParty $extras): int => $extras !== null && $extras->contacts !== []
            ? count($extras->contacts)
            : ($contact?->email !== null || $contact?->phone !== null ? 1 : 0);
        $limit($contacts($invoice->seller->contact, $polish->seller), 'Podmiot1/DaneKontaktowe', PolishParty::MAX_CONTACTS);
        $limit($contacts($invoice->buyer->contact, $polish->buyer), 'Podmiot2/DaneKontaktowe', PolishParty::MAX_CONTACTS);
        foreach ($polish->thirdParties as $index => $thirdParty) {
            $limit($contacts($thirdParty->party->contact, $thirdParty->extras), sprintf('Podmiot3[%d]/DaneKontaktowe', $index + 1), PolishParty::MAX_CONTACTS);
        }
        $terms = $polish->transactionTerms;
        if ($terms !== null) {
            $limit(count($terms->contracts), 'WarunkiTransakcji/Umowy', self::MAX_TERMS_DOCUMENTS);
            $limit(count($terms->orders), 'WarunkiTransakcji/Zamowienia', self::MAX_TERMS_DOCUMENTS);
            $limit(count($terms->batchNumbers), 'WarunkiTransakcji/NrPartiiTowaru', self::MAX_BATCH_NUMBERS);
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private static function dates(Invoice $invoice): array
    {
        $violations = [];
        $delivery = $invoice->delivery?->actualDeliveryDate;
        $taxPoint = $invoice->taxPointDate;
        if ($delivery !== null && $taxPoint !== null && $delivery->format('Y-m-d') !== $taxPoint->format('Y-m-d')) {
            $violations[] = sprintf(
                'BT-72: FA(3) has one supply date (P_6); the actual delivery date %s differs from the tax point date (BT-7) %s',
                $delivery->format('Y-m-d'),
                $taxPoint->format('Y-m-d'),
            );
        }

        $period = $invoice->delivery?->invoicingPeriod;
        if ($period !== null && ($period->start === null) !== ($period->end === null)) {
            $violations[] = 'BG-14: FA(3) needs both the start (BT-73) and the end (BT-74) of the invoicing period (OkresFa)';
        }
        if ($period?->start !== null && $period->end !== null && Fa3Writer::supplyDate($invoice) !== null) {
            $violations[] = 'BG-14: FA(3) carries either a supply date (P_6) or an invoicing period (OkresFa), not both';
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private static function rates(Invoice $invoice, PolishInvoice $polish): array
    {
        $violations = [];
        $buyerCountry = $invoice->buyer->address?->country;
        $written = Fa3Buckets::written($invoice);
        foreach ($invoice->lines as $line) {
            $override = $line->extensions()->get(PolishLine::class)?->rateCode;
            try {
                $bucket = Fa3LineWriter::bucket($line, $buyerCountry);
            } catch (InvalidArgumentException $e) {
                $violations[] = sprintf('%s (line "%s"): %s', $override === null ? 'BT-151' : 'P_12', $line->id, $e->getMessage());

                continue;
            }
            foreach (self::ossProblems($line) as $problem) {
                $violations[] = sprintf('P_12_XII (line "%s"): %s', $line->id, $problem);
            }
            $polishLine = $line->extensions()->get(PolishLine::class);
            if (! $polish->annotations->reverseCharge && Fa3LineWriter::isWritten($line) && $polishLine?->rateCodeOmitted !== true
                && Fa3LineWriter::rateCode($line, $buyerCountry) === VatRateCode::ReverseCharge) {
                $violations[] = sprintf('P_18: line "%s" is a domestic reverse-charge row (P_12 = oo), which needs the reverse-charge annotation (Annotations::$reverseCharge); it is never set silently', $line->id);
            }
            if ($override !== null && ! self::overrideFits($override, $line->vat->category, $line->vat->rate, $buyerCountry)) {
                [$category, $rate] = $override->toModel();
                $violations[] = sprintf(
                    'P_12 (line "%s"): the rate code override "%s" stands for VAT category %s%s, but the line is %s%s, and an override only refines the code of the line category and rate (23 or 22, 8 or 7, oo or np II)',
                    $line->id,
                    $override->value,
                    $category->value,
                    $rate === null ? '' : ' '.$rate,
                    $line->vat->category->value,
                    $line->vat->rate === null ? '' : ' '.$line->vat->rate,
                );
            }
            if (Fa3LineWriter::isWritten($line) && ! in_array($bucket, $written, true) && $polishLine?->rateCodeOmitted === true) {
                $violations[] = sprintf(
                    'BT-151 (line "%s"): the row was read without P_12 and the invoice summary buckets do not tell which one it feeds, so its bucket is unknown and it cannot be written back; give the line its VAT category and rate',
                    $line->id,
                );
            } elseif (Fa3LineWriter::isWritten($line) && ! in_array($bucket, $written, true)) {
                $violations[] = sprintf(
                    '%s (line "%s"): the line feeds %s, which no VAT breakdown row (BG-23) writes; every row needs its summary bucket',
                    $override === null ? 'BT-151' : 'P_12',
                    $line->id,
                    $bucket->netElement(),
                );
            }
        }

        if ($invoice->totals === null) {
            $violations[] = 'BG-22: the document totals are missing; complete the invoice with TotalsCalculator first';
        }

        return [...$violations, ...Fa3Buckets::problems($invoice)];
    }

    /**
     * Why a one-stop-shop line (PolishLine::$ossRate without a rate code override) cannot be
     * written: it feeds P_13_5 without a rate code, so it is not a margin scheme line and is VAT
     * category S at its OSS rate. A line with an override is written with both elements.
     *
     * @return list<string>
     */
    private static function ossProblems(InvoiceLine $line): array
    {
        $polish = $line->extensions()->get(PolishLine::class);
        $oss = $polish?->ossRate;
        if ($oss === null) {
            return [];
        }
        $problems = [];
        if ($polish->rateCode !== null) {
            // Written with both P_12 and P_12_XII in the bucket of the rate code.
            return [];
        }
        if ($polish->margin) {
            $problems[] = 'a margin scheme line has no one-stop-shop rate';
        }
        $rate = $line->vat->rate;
        if ($line->vat->category !== VatCategory::StandardRate || $rate === null || ! $rate->isEqualTo($oss)) {
            $problems[] = sprintf(
                'a one-stop-shop line is VAT category S at its OSS rate %s, got %s%s',
                $oss,
                $line->vat->category->value,
                $rate === null ? ' without a rate' : ' '.$rate,
            );
        }

        return $problems;
    }

    /**
     * Whether a rate code override stands for the line's VAT category: the same category, and for
     * the standard rate a code of the same summary bucket as the line rate (23 or 22, 8 or 7).
     */
    private static function overrideFits(VatRateCode $override, VatCategory $category, ?BigDecimal $rate, ?CountryCode $buyerCountry): bool
    {
        if ($override->toModel()[0] !== $category) {
            return false;
        }
        if ($category !== VatCategory::StandardRate) {
            return true;
        }
        try {
            $derived = VatRateCode::fromModel($category, $rate, $buyerCountry);
        } catch (InvalidArgumentException) {
            return false;
        }

        return $override->bucket() !== null && $derived->bucket() === $override->bucket();
    }

    /**
     * A bucket line stands for one P_13 amount and is never written as a row (FaWiersz), so it
     * carries nothing beyond its identifier, quantity 1, price, net amount and VAT; its item name
     * is the bucket's net amount element or empty.
     *
     * @return list<string>
     */
    private static function bucketLines(Invoice $invoice): array
    {
        $buyerCountry = $invoice->buyer->address?->country;
        $violations = [];
        foreach ($invoice->lines as $line) {
            $polish = $line->extensions()->get(PolishLine::class);
            if ($polish?->bucketLine !== true) {
                continue;
            }
            try {
                $element = Fa3LineWriter::bucket($line, $buyerCountry)->netElement();
            } catch (InvalidArgumentException) {
                $element = null;
            }
            $item = $line->item;
            $present = [
                'BT-129' => ! $line->quantity->isEqualTo(1),
                'BT-130' => $line->unit !== UnitCode::C62,
                'BT-153' => $item->name !== '' && $item->name !== $element,
                'BT-154' => $item->description !== null,
                'BT-155' => $item->sellersIdentifier !== null,
                'BT-156' => $item->buyersIdentifier !== null,
                'BT-157' => $item->standardIdentifier !== null,
                'BT-159' => $item->originCountry !== null,
                'BT-127' => $line->note !== null,
                'BT-128' => $line->objectIdentifier !== null,
                'BT-132' => $line->orderLineReference !== null,
                'BT-133' => $line->buyerAccountingReference !== null,
                'BG-27' => $line->allowances !== [],
                'P_8A' => $polish->unitLabel !== null,
                'P_6A' => $polish->supplyDate !== null,
                'P_9B' => $polish->grossUnitPrice !== null,
                'P_11A' => $polish->grossAmount !== null,
                'P_11Vat' => $polish->vatAmount !== null,
                'P_12_Zal_15' => $polish->annex15,
                'KwotaAkcyzy' => $polish->excise !== null,
                'GTU' => $polish->gtu !== null,
                'Procedura' => $polish->procedure !== null,
                'KursWaluty' => $polish->exchangeRate !== null,
                'PKWiU' => $polish->pkwiu !== null,
                'CN' => $polish->cn !== null,
                'PKOB' => $polish->pkob !== null,
                'P_12_XII' => $polish->ossRate !== null,
            ];
            foreach ($present as $term => $set) {
                if ($set) {
                    $violations[] = sprintf(
                        '%s (bucket line "%s"): a bucket line stands for a summary amount (P_13_x) and is not written as a row (FaWiersz), so it carries only its identifier, quantity 1, price, net amount and VAT%s',
                        $term,
                        $line->id,
                        $term === 'BT-153' ? sprintf('; its item name is %s or empty', $element ?? 'the bucket element name') : '',
                    );
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private static function decimals(Invoice $invoice): array
    {
        $violations = [];
        $check = static function (string $term, ?BigDecimal $value, int $max) use (&$violations): void {
            if ($value !== null && $value->getScale() > $max) {
                $violations[] = sprintf('%s: %s has more than %d decimals', $term, (string) $value, $max);
            }
        };

        $check('BT-112', $invoice->totals?->totalWithVat, self::AMOUNT);
        $check('BT-115', $invoice->totals?->amountDue, self::AMOUNT);
        foreach ($invoice->vatBreakdown as $row) {
            $check('BT-116', $row->taxableAmount, self::AMOUNT);
            $check('BT-117', $row->taxAmount, self::AMOUNT);
        }
        foreach ($invoice->lines as $line) {
            $at = sprintf(' (line "%s")', $line->id);
            $polish = $line->extensions()->get(PolishLine::class);
            $check('BT-129'.$at, $line->quantity, self::QUANTITY);
            $check('BT-146'.$at, $line->price->netPrice, self::UNIT_PRICE);
            $check('BT-136'.$at, Fa3LineWriter::allowanceTotal($line), self::UNIT_PRICE);
            $check('BT-131'.$at, $line->netAmount, self::AMOUNT);
            $check('P_9B'.$at, $polish?->grossUnitPrice, self::UNIT_PRICE);
            $check('P_11A'.$at, $polish?->grossAmount, self::AMOUNT);
            $check('P_11Vat'.$at, $polish?->vatAmount, self::AMOUNT);
            $check('P_12_XII'.$at, $polish?->ossRate, self::QUANTITY);
            $check('KwotaAkcyzy'.$at, $polish?->excise, self::AMOUNT);
            $check('KursWaluty'.$at, $polish?->exchangeRate, self::QUANTITY);
        }

        return [...$violations, ...self::extensionDecimals($invoice->extensions()->get(PolishInvoice::class) ?? new PolishInvoice)];
    }

    /**
     * @return list<string>
     */
    private static function extensionDecimals(PolishInvoice $polish): array
    {
        $violations = [];
        $check = static function (string $term, ?BigDecimal $value, int $max) use (&$violations): void {
            if ($value !== null && $value->getScale() > $max) {
                $violations[] = sprintf('%s: %s has more than %d decimals', $term, (string) $value, $max);
            }
        };

        foreach ($polish->vatInPln as $key => $amount) {
            $check(sprintf('P_14_%dW', $key), $amount, self::AMOUNT);
        }
        $check('KursWalutyZ', $polish->exchangeRate, self::QUANTITY);
        foreach ($polish->thirdParties as $index => $thirdParty) {
            $check(sprintf('Podmiot3[%d] Udzial', $index + 1), $thirdParty->share, self::QUANTITY);
        }
        foreach ($polish->partialAdvances as $index => $advance) {
            $at = sprintf(' (ZaliczkaCzesciowa %d)', $index + 1);
            $check('P_15Z'.$at, $advance->amount, self::AMOUNT);
            $check('KursWalutyZW'.$at, $advance->exchangeRate, self::QUANTITY);
        }
        $check('P_15ZK', $polish->correction?->amountBefore, self::AMOUNT);
        $check('KursWalutyZK', $polish->correction?->exchangeRateBefore, self::QUANTITY);
        foreach ([...$polish->settlement->charges ?? [], ...$polish->settlement->deductions ?? []] as $entry) {
            $check(sprintf('Rozliczenie Kwota ("%s")', $entry->reason), $entry->amount, self::AMOUNT);
        }
        $check('KursUmowny', $polish->transactionTerms?->contractRate, self::QUANTITY);
        $check('WartoscZamowienia', $polish->order?->value, self::AMOUNT);
        foreach ($polish->order->lines ?? [] as $index => $line) {
            $at = sprintf(' (order line %d)', $index + 1);
            $check('P_8BZ'.$at, $line->quantity, self::QUANTITY);
            $check('P_9AZ'.$at, $line->unitNetPrice, self::UNIT_PRICE);
            $check('P_11NettoZ'.$at, $line->netAmount, self::AMOUNT);
            $check('P_11VatZ'.$at, $line->vatAmount, self::AMOUNT);
            $check('P_12Z_XII'.$at, $line->ossRate, self::QUANTITY);
            $check('KwotaAkcyzyZ'.$at, $line->excise, self::AMOUNT);
        }

        return $violations;
    }

    /**
     * The effective invoice kind: the extension's kind, else the kind implied by the invoice type
     * code (BT-3); null when neither gives one.
     */
    public static function kindOf(Invoice $invoice, PolishInvoice $polish): ?InvoiceKind
    {
        return $polish->kind ?? InvoiceKind::fromTypeCode($invoice->type);
    }

    private static function nonZero(?BigDecimal $value): bool
    {
        return $value !== null && ! $value->isZero();
    }

    private function __construct() {}
}
