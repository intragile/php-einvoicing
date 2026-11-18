<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Fa3;

use Brick\Math\BigDecimal;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\IdentifierScheme;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\Party;
use IntrAgile\EInvoicing\Poland\DocumentReference;
use IntrAgile\EInvoicing\Poland\InvoiceKind;
use IntrAgile\EInvoicing\Poland\Nip;
use IntrAgile\EInvoicing\Poland\PolishInvoice;
use IntrAgile\EInvoicing\Poland\PolishLine;
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
 * which supersedes them; the legal basis is never guessed. A contract or purchase order
 * reference (BT-12, BT-13) that differs from the contracts or orders of the transaction terms
 * is refused.
 *
 * Also refused: a seller VAT identifier (BT-31) that is not "PL" with a NIP of the schema
 * pattern, missing document totals, VAT categories and rates without an FA(3) rate code or
 * summary bucket, VAT breakdown rows whose lines map to different rate codes, decimals beyond
 * the schema (amounts 2, quantities and rates 6, unit prices and P_10 8), a half-open invoicing
 * period (BG-14), an actual delivery date (BT-72) that differs from the tax point date (BT-7),
 * a supply date (P_6) next to an invoicing period, which FA(3) offers as alternatives, and a
 * verbatim fragment that is not the element of its schema position.
 */
final class Fa3Guard
{
    private const AMOUNT = 2;

    private const QUANTITY = 6;

    private const UNIT_PRICE = 8;

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
            ...self::fragments($polish),
            ...self::dates($invoice),
            ...self::rates($invoice),
            ...self::decimals($invoice),
        ];

        if ($violations !== []) {
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
        foreach ($invoice->notes as $index => $note) {
            $refuse($note->subjectCode !== null, sprintf('BT-21 (note %d)', $index + 1), 'FA(3) has no note subject code');
        }
        $kind = self::kindOf($invoice, $polish);
        $refuse(
            $invoice->precedingInvoices !== [] && $kind?->isCorrection() !== true,
            'BG-3',
            'FA(3) references a preceding invoice only on a corrective invoice (KOR, KOR_ZAL, KOR_ROZ)',
        );
        $exempted = $polish->annotations->exemption !== null;
        foreach ($invoice->vatBreakdown as $row) {
            $label = sprintf(' (%s %s)', $row->category->value, $row->rate === null ? 'no rate' : $row->rate->__toString());
            $hint = 'FA(3) states the exemption as P_19 with its legal basis; set Annotations::$exemption of the Polish invoice extension';
            $refuse(! $exempted && $row->exemptionReason !== null, 'BT-120'.$label, $hint);
            $refuse(! $exempted && $row->exemptionReasonCode !== null, 'BT-121'.$label, $hint);
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

        $violations = [...$violations, ...self::identification($buyer, 'BT-48', 'BT-46', 'BT-47', 'BT-49')];
        if ($polish->buyer?->vatPrefix !== null) {
            $violations[] = 'Podmiot2: FA(3) has no buyer VAT prefix; the EU prefix is taken from the buyer VAT identifier (BT-48)';
        }
        if ($polish->buyer?->status !== null) {
            $violations[] = 'Podmiot2: the taxpayer status (StatusInfoPodatnika) exists only for the seller';
        }

        foreach ($polish->thirdParties as $index => $thirdParty) {
            $at = sprintf('Podmiot3[%d]', $index + 1);
            foreach (self::identification($thirdParty->party, $at.' VAT identifier', $at.' identifiers', $at.' legal registration', $at.' electronic address') as $violation) {
                $violations[] = $violation;
            }
            if ($thirdParty->internalId !== null && $thirdParty->party->vatIdentifier !== null) {
                $violations[] = $at.': FA(3) identifies a third party by its internal identifier (IDWew) or its VAT identifier, not both';
            }
            if ($thirdParty->extras?->vatPrefix !== null || $thirdParty->extras?->status !== null) {
                $violations[] = $at.': a third party has no VAT prefix or taxpayer status in FA(3)';
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
    private static function identification(Party $party, string $vatTerm, string $identifiersTerm, string $legalTerm, string $addressTerm): array
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
        $violations = [];
        $expected = ['NoweSrodkiTransportu' => $polish->annotations->newTransport];
        foreach ($expected as $name => $fragment) {
            if ($fragment !== null && $fragment->name !== $name) {
                $violations[] = sprintf('%s: the fragment must be a %s element, got %s', $name, $name, $fragment->name);
            }
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
    private static function rates(Invoice $invoice): array
    {
        $violations = [];
        $buyerCountry = $invoice->buyer->address?->country;
        foreach ($invoice->lines as $line) {
            $override = $line->extensions()->get(PolishLine::class)?->rateCode;
            try {
                Fa3LineWriter::bucket($line, $buyerCountry);
            } catch (InvalidArgumentException $e) {
                $violations[] = sprintf('%s (line "%s"): %s', $override === null ? 'BT-151' : 'P_12', $line->id, $e->getMessage());
            }
        }

        if ($invoice->totals === null) {
            $violations[] = 'BG-22: the document totals are missing; complete the invoice with TotalsCalculator first';
        }

        return [...$violations, ...Fa3Buckets::problems($invoice)];
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

        return $violations;
    }

    private static function kindOf(Invoice $invoice, PolishInvoice $polish): ?InvoiceKind
    {
        return $polish->kind ?? InvoiceKind::fromTypeCode($invoice->type);
    }

    private static function nonZero(?BigDecimal $value): bool
    {
        return $value !== null && ! $value->isZero();
    }

    private function __construct() {}
}
