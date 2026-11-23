<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\CodeList\CurrencyCode;
use IntrAgile\EInvoicing\CodeList\InvoiceTypeCode;
use IntrAgile\EInvoicing\CodeList\VatCategory;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Model\VatBreakdown;
use IntrAgile\EInvoicing\Spain\BreakdownOverride;
use IntrAgile\EInvoicing\Spain\CorrectionType;
use IntrAgile\EInvoicing\Spain\EuroAmounts;
use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\ForeignIdType;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\IssuedBy;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\OperationQualification;
use IntrAgile\EInvoicing\Spain\RegimeKey;
use IntrAgile\EInvoicing\Spain\SpanishInvoice;
use IntrAgile\EInvoicing\Spain\TaxType;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\MappingRefused;
use InvalidArgumentException;

/**
 * Maps an EN 16931 invoice to the content of a VERI*FACTU RegistroAlta.
 *
 * The default describes a domestic F1 invoice under the general VAT regime; everything the EN
 * 16931 model cannot express (corrective type and kind, exemption article, not-subject cause,
 * recipients without a VAT identifier, EUR amounts of a foreign-currency invoice) comes from the
 * {@see SpanishInvoice} extension. The mapper never guesses: every missing or contradictory input
 * is collected and reported in one {@see MappingRefused}.
 *
 * Sign convention: a credit note corrected by difference (TipoRectificativa I) carries negated
 * base, quota, equivalence surcharge and totals; every other record carries the amounts as given.
 */
final class RecordMapper
{
    private const DESCRIPTION_MAX = 500;

    private const NAME_MAX = 120;

    private const BREAKDOWN_MAX = 12;

    private const MACRODATO_THRESHOLD = '100000000.00';

    /**
     * @throws MappingRefused listing every problem found
     */
    public function map(Invoice $invoice): RegistrationDraft
    {
        $spanish = $invoice->extensions()->get(SpanishInvoice::class);
        $spanish = $spanish instanceof SpanishInvoice ? $spanish : new SpanishInvoice;

        $problems = [];

        $issuer = $this->issuer($invoice, $problems);
        $id = $issuer === null ? null : $this->invoiceId($issuer, $invoice->number, $invoice->issueDate, 'NumSerieFactura (BT-1)', $problems);

        $issuerName = trim($invoice->seller->name);
        if ($issuerName === '' || mb_strlen($issuerName) > self::NAME_MAX) {
            $problems[] = sprintf('NombreRazonEmisor (BT-27): the seller name must be 1 to %d characters after trimming.', self::NAME_MAX);
        }

        $type = $this->type($invoice, $spanish, $problems);
        $this->checkCorrection($type, $spanish, $problems);
        $corrected = $issuer !== null && $type?->isCorrective() === true ? $this->corrected($invoice, $issuer, $problems) : [];

        if ($spanish->substituted !== [] && $type !== null && $type !== InvoiceType::F3) {
            $problems[] = sprintf('FacturasSustituidas: only a substitution invoice (F3) lists substituted invoices, the type is %s.', $type->value);
        }

        $this->checkFlags($type, $spanish, $problems);

        $description = $this->description($invoice, $spanish, $problems);
        $recipients = $this->recipients($invoice, $spanish, $type, $issuer, $problems);

        $negate = $invoice->type->isCreditNote() && $spanish->correctionType === CorrectionType::Difference;
        $euro = $this->euroAmounts($invoice, $spanish, $problems);
        $breakdown = $this->breakdown($invoice, $spanish, $euro, $negate, $problems);
        [$totalTax, $total] = $this->totals($invoice, $euro, $negate, $problems);

        if ($problems !== [] || $id === null || $type === null || $total === null || $totalTax === null) {
            throw new MappingRefused($problems);
        }

        try {
            return new RegistrationDraft(
                id: $id,
                issuerName: $issuerName,
                type: $type,
                correctionType: $spanish->correctionType,
                corrected: $corrected,
                substituted: $spanish->substituted,
                correctionAmounts: $spanish->correctionAmounts,
                operationDate: $this->operationDate($invoice),
                description: $description,
                simplifiedArt7273: $spanish->simplifiedArt7273,
                withoutRecipientArt61d: $spanish->withoutRecipientArt61d,
                macrodato: $total->abs()->isGreaterThanOrEqualTo(self::MACRODATO_THRESHOLD),
                issuedBy: $spanish->issuedBy,
                thirdParty: $spanish->thirdParty,
                recipients: $recipients,
                coupon: $spanish->coupon,
                breakdown: $breakdown,
                totalTax: $totalTax,
                total: $total,
                externalReference: $spanish->externalReference,
                agreementNumber: $spanish->agreementNumber,
                systemAgreementId: $spanish->systemAgreementId,
                operation: $spanish->operation,
            );
        } catch (InvalidRecord $e) {
            throw new MappingRefused([$e->getMessage()]);
        }
    }

    /**
     * IDEmisorFactura: the seller VAT identifier (BT-31) without its ES prefix, else the seller
     * tax registration (BT-32).
     *
     * @param  list<string>  $problems
     */
    private function issuer(Invoice $invoice, array &$problems): ?Nif
    {
        $seller = $invoice->seller;
        [$term, $given] = $seller->vatIdentifier !== null
            ? ['BT-31', $seller->vatIdentifier]
            : ['BT-32', $seller->taxRegistration?->identifier];

        if ($given === null) {
            $problems[] = 'IDEmisorFactura (BT-31): the seller has neither a VAT identifier nor a tax registration.';

            return null;
        }

        try {
            return Nif::parse(Nif::normalize($given));
        } catch (InvalidArgumentException $e) {
            $problems[] = sprintf('IDEmisorFactura (%s): %s', $term, $e->getMessage());

            return null;
        }
    }

    /**
     * @param  list<string>  $problems
     */
    private function invoiceId(Nif $issuer, string $number, DateTimeImmutable $date, string $field, array &$problems): ?InvoiceId
    {
        try {
            return new InvoiceId($issuer, $number, $date);
        } catch (InvalidRecord $e) {
            $problems[] = $field.': '.$e->getMessage();

            return null;
        }
    }

    /**
     * TipoFactura: the override, else F1; a credit note or a corrected invoice needs an explicit
     * corrective type, because the legal basis of a correction is never guessed.
     *
     * @param  list<string>  $problems
     */
    private function type(Invoice $invoice, SpanishInvoice $spanish, array &$problems): ?InvoiceType
    {
        $correcting = $invoice->type->isCreditNote() || $invoice->type === InvoiceTypeCode::C_384;

        if ($spanish->type === null) {
            if ($correcting) {
                $problems[] = sprintf('TipoFactura: a %s invoice (BT-3) needs an explicit corrective type R1 to R5 in SpanishInvoice::$type.', $invoice->type->value);

                return null;
            }

            return InvoiceType::F1;
        }

        if ($correcting && ! $spanish->type->isCorrective()) {
            $problems[] = sprintf('TipoFactura: a %s invoice (BT-3) must have a corrective type R1 to R5, %s given.', $invoice->type->value, $spanish->type->value);
        }

        return $spanish->type;
    }

    /**
     * TipoRectificativa and ImporteRectificacion.
     *
     * @param  list<string>  $problems
     */
    private function checkCorrection(?InvoiceType $type, SpanishInvoice $spanish, array &$problems): void
    {
        if ($spanish->correctionType === null) {
            if ($type === null || $type->isCorrective()) {
                $problems[] = 'TipoRectificativa: a corrective invoice needs SpanishInvoice::$correctionType (S substitution or I difference).';
            }
        } elseif ($type !== null && ! $type->isCorrective()) {
            $problems[] = sprintf('TipoRectificativa: only a corrective invoice (R1 to R5) carries a correction type, the type is %s.', $type->value);
        }

        if ($spanish->correctionType === CorrectionType::Substitution && $spanish->correctionAmounts === null) {
            $problems[] = 'ImporteRectificacion: a correction by substitution (S) needs SpanishInvoice::$correctionAmounts.';
        }

        if ($spanish->correctionAmounts !== null && $spanish->correctionType !== CorrectionType::Substitution) {
            $problems[] = 'ImporteRectificacion: correction amounts are written only for a correction by substitution (S).';
        }
    }

    /**
     * FacturaSimplificadaArt7273, FacturaSinIdentifDestinatarioArt61d and Tercero against the
     * invoice type and each other (Validaciones §3.1.3.8, §3.1.3.9, §3.1.3.11).
     *
     * @param  list<string>  $problems
     */
    private function checkFlags(?InvoiceType $type, SpanishInvoice $spanish, array &$problems): void
    {
        $simplified = $type === InvoiceType::F2 || $type === InvoiceType::R5;

        if ($spanish->simplifiedArt7273 && $simplified) {
            $problems[] = sprintf('FacturaSimplificadaArt7273: only an F1, F3 or R1 to R4 invoice is issued under art. 7.2 and 7.3, the type is %s (AEAT error 1183).', $type->value);
        }

        if ($spanish->withoutRecipientArt61d && $type !== null && ! $simplified) {
            $problems[] = sprintf('FacturaSinIdentifDestinatarioArt61d: only a simplified invoice (F2, R5) is issued without an identified recipient, the type is %s (AEAT error 1185).', $type->value);
        }

        if ($spanish->withoutRecipientArt61d && $spanish->recipients !== []) {
            $problems[] = 'FacturaSinIdentifDestinatarioArt61d: an invoice issued without an identified recipient cannot list SpanishInvoice::$recipients.';
        }

        if ($spanish->issuedBy === IssuedBy::ThirdParty && $spanish->thirdParty === null) {
            $problems[] = 'Tercero: an invoice issued by a third party (EmitidaPorTerceroODestinatario T) needs SpanishInvoice::$thirdParty (AEAT error 1186).';
        }
    }

    /**
     * FacturasRectificadas: one entry per preceding invoice reference (BG-3).
     *
     * @param  list<string>  $problems
     * @return list<InvoiceId>
     */
    private function corrected(Invoice $invoice, Nif $issuer, array &$problems): array
    {
        $corrected = [];

        foreach ($invoice->precedingInvoices as $reference) {
            if ($reference->issueDate === null) {
                $problems[] = sprintf('FacturasRectificadas (BT-26): the preceding invoice "%s" has no issue date.', $reference->number);

                continue;
            }

            $id = $this->invoiceId($issuer, $reference->number, $reference->issueDate, 'FacturasRectificadas (BT-25)', $problems);
            if ($id !== null) {
                $corrected[] = $id;
            }
        }

        return $corrected;
    }

    /**
     * DescripcionOperacion: the override, else the first note (BT-22) when it fits, else
     * "Factura <BT-1>".
     *
     * @param  list<string>  $problems
     */
    private function description(Invoice $invoice, SpanishInvoice $spanish, array &$problems): string
    {
        $fallback = 'Factura '.trim($invoice->number);

        if ($spanish->description !== null) {
            $description = trim($spanish->description);

            if ($description === '' || mb_strlen($description) > self::DESCRIPTION_MAX) {
                $problems[] = sprintf('DescripcionOperacion: SpanishInvoice::$description must be 1 to %d characters after trimming.', self::DESCRIPTION_MAX);

                return $fallback;
            }

            return $description;
        }

        $note = isset($invoice->notes[0]) ? trim($invoice->notes[0]->text) : '';

        return $note !== '' && mb_strlen($note) <= self::DESCRIPTION_MAX ? $note : $fallback;
    }

    /**
     * Destinatarios: the buyer VAT identifier (BT-48), else SpanishInvoice::$recipients; none for
     * F2 and R5 invoices, which may also be issued without an identified recipient (art. 6.1.d).
     * No recipient may carry the issuer's NIF (AEAT error 1193).
     *
     * @param  list<string>  $problems
     * @return list<TaxParty>
     */
    private function recipients(Invoice $invoice, SpanishInvoice $spanish, ?InvoiceType $type, ?Nif $issuer, array &$problems): array
    {
        if ($type === InvoiceType::F2 || $type === InvoiceType::R5) {
            return [];
        }

        $vat = $invoice->buyer->vatIdentifier === null ? '' : trim($invoice->buyer->vatIdentifier);

        if ($vat === '') {
            if ($spanish->recipients === []) {
                $problems[] = 'Destinatarios (BT-48): the buyer has no VAT identifier; give SpanishInvoice::$recipients '
                    .'or use a simplified type (F2, R5).';
            }

            return $this->withoutIssuer($spanish->recipients, $issuer, 'Destinatarios', $problems);
        }

        if ($spanish->recipients !== []) {
            $problems[] = 'Destinatarios: the buyer VAT identifier (BT-48) and SpanishInvoice::$recipients cannot both name the recipient.';

            return [];
        }

        $recipient = $this->buyerRecipient($invoice->buyer->name, $vat, $problems);

        return $recipient === null ? [] : $this->withoutIssuer([$recipient], $issuer, 'Destinatarios (BT-48)', $problems);
    }

    /**
     * @param  list<TaxParty>  $recipients
     * @param  list<string>  $problems
     * @return list<TaxParty>
     */
    private function withoutIssuer(array $recipients, ?Nif $issuer, string $field, array &$problems): array
    {
        if ($issuer === null) {
            return $recipients;
        }

        foreach ($recipients as $recipient) {
            if ($recipient->nif?->value === $issuer->value) {
                $problems[] = sprintf('%s: the recipient NIF %s is the issuer NIF (AEAT error 1193).', $field, $issuer->value);

                return [];
            }
        }

        return $recipients;
    }

    /**
     * @param  list<string>  $problems
     */
    private function buyerRecipient(string $name, string $vat, array &$problems): ?TaxParty
    {
        $prefix = strtoupper(substr($vat, 0, 2));

        try {
            if ($prefix === 'ES') {
                return new TaxParty($name, Nif::parse(Nif::normalize($vat)), null);
            }

            $country = CountryCode::tryFrom($prefix === 'EL' ? 'GR' : $prefix);
            if ($country === null) {
                $problems[] = sprintf('Destinatarios (BT-48): "%s" does not start with a country code.', $vat);

                return null;
            }

            return new TaxParty($name, null, new ForeignId(ForeignIdType::VatNumber, $vat, $country));
        } catch (InvalidArgumentException $e) {
            $problems[] = sprintf('Destinatarios (BT-48): %s', $e->getMessage());

            return null;
        }
    }

    /**
     * The EUR amounts of a foreign-currency invoice; null for an EUR invoice.
     *
     * @param  list<string>  $problems
     */
    private function euroAmounts(Invoice $invoice, SpanishInvoice $spanish, array &$problems): ?EuroAmounts
    {
        if ($invoice->currency === CurrencyCode::EUR) {
            if ($spanish->euroAmounts !== null) {
                $problems[] = 'EuroAmounts: the invoice is already in EUR (BT-5); remove SpanishInvoice::$euroAmounts.';
            }

            return null;
        }

        return $spanish->euroAmounts;
    }

    /**
     * Desglose: one line per VAT breakdown group (BG-23).
     *
     * @param  list<string>  $problems
     * @return list<TaxBreakdownLine>
     */
    private function breakdown(Invoice $invoice, SpanishInvoice $spanish, ?EuroAmounts $euro, bool $negate, array &$problems): array
    {
        if ($invoice->totals === null && $invoice->vatBreakdown === []) {
            return [];
        }

        if ($invoice->vatBreakdown === []) {
            $problems[] = 'Desglose (BG-23): the invoice has no VAT breakdown; complete the invoice with TotalsCalculator first.';

            return [];
        }

        if (count($invoice->vatBreakdown) > self::BREAKDOWN_MAX) {
            $problems[] = sprintf('Desglose (BG-23): VERI*FACTU admits at most %d breakdown lines, the invoice has %d VAT groups.', self::BREAKDOWN_MAX, count($invoice->vatBreakdown));

            return [];
        }

        $lines = [];
        $keys = [];

        foreach ($invoice->vatBreakdown as $group) {
            $key = SpanishInvoice::breakdownKey($group->category, $group->rate);
            $keys[$key] = true;

            $line = $this->breakdownLine($group, $key, $spanish->breakdown[$key] ?? null, $euro, $problems);
            if ($line !== null) {
                $lines[] = $negate ? $line->negated() : $line;
            }
        }

        foreach (array_keys($spanish->breakdown) as $key) {
            if (! isset($keys[$key])) {
                $problems[] = sprintf('SpanishInvoice::$breakdown (%s): no VAT breakdown group of the invoice has this key.', $key);
            }
        }

        foreach (array_keys($euro === null ? [] : $euro->breakdown) as $key) {
            if (! isset($keys[$key])) {
                $problems[] = sprintf('EuroAmounts (%s): no VAT breakdown group of the invoice has this key.', $key);
            }
        }

        return $lines;
    }

    /**
     * One DetalleDesglose: the default for the VAT category, replaced field by field by the override.
     *
     * @param  list<string>  $problems
     */
    private function breakdownLine(VatBreakdown $group, string $key, ?BreakdownOverride $override, ?EuroAmounts $euro, array &$problems): ?TaxBreakdownLine
    {
        $default = $this->defaultFor($group->category);
        if ($default === null) {
            $problems[] = sprintf('Desglose (%s): VAT category %s has no VERI*FACTU equivalent.', $key, $group->category->value);

            return null;
        }

        [$tax, $regime, $qualification, $exemption] = $default;

        if ($override?->qualification !== null) {
            [$qualification, $exemption] = [$override->qualification, null];
        } elseif ($override?->exemption !== null) {
            [$qualification, $exemption] = [null, $override->exemption];
        }

        $tax = $override->tax ?? $tax;
        // IVA and IGIC refuse E2 and E3 under regime 01 (Validaciones §3.1.3.15.5, error 1199);
        // those exemptions default to the export regime 02 unless the override names a regime.
        if (in_array($exemption, [ExemptOperation::E2, ExemptOperation::E3], true)
            && in_array($tax, [TaxType::Iva, TaxType::Igic], true)) {
            $regime = RegimeKey::Key02;
        }

        if ($qualification === null && $exemption === null) {
            $problems[] = $group->category === VatCategory::NotSubjectToVat
                ? sprintf('CalificacionOperacion (%s): a not-subject group needs the cause (N1 or N2) in SpanishInvoice::$breakdown.', $key)
                : sprintf('OperacionExenta (%s): an exempt group needs the exemption cause (E1 to E8) in SpanishInvoice::$breakdown.', $key);

            return null;
        }

        [$base, $quota] = $this->groupAmounts($group, $key, $euro, $problems);
        if ($base === null) {
            return null;
        }

        $rate = null;
        if ($qualification === OperationQualification::S1) {
            if ($group->rate === null) {
                $problems[] = sprintf('TipoImpositivo (%s): a taxable group needs a VAT rate (BT-119).', $key);

                return null;
            }
            $rate = TaxRate::of($group->rate);
        } elseif ($qualification === OperationQualification::S2) {
            // Reverse charge: rate and quota are written as zero (Validaciones, error 1198).
            [$rate, $quota] = [TaxRate::of('0'), Amount::of('0')];
        } else {
            // Exempt and not-subject lines carry neither a rate nor a quota.
            $quota = null;
        }

        if ($qualification === OperationQualification::S1 && $quota === null) {
            $problems[] = sprintf('EuroAmounts (%s): a taxable group needs its EUR quota.', $key);

            return null;
        }

        return new TaxBreakdownLine(
            $tax,
            $override->regime ?? $regime,
            $qualification,
            $exemption,
            $rate,
            $base,
            $override?->costBase,
            $quota,
            $override?->equivalenceRate,
            $override?->equivalenceQuota,
        );
    }

    /**
     * Default Impuesto, ClaveRegimen and CalificacionOperacion or OperacionExenta per VAT category;
     * exempt (E) and not-subject (O) groups have no default cause. Null for a category without an
     * equivalent.
     *
     * @return array{TaxType, RegimeKey, ?OperationQualification, ?ExemptOperation}|null
     */
    private function defaultFor(VatCategory $category): ?array
    {
        return match ($category) {
            VatCategory::StandardRate, VatCategory::ZeroRated => [TaxType::Iva, RegimeKey::Key01, OperationQualification::S1, null],
            VatCategory::ReverseCharge => [TaxType::Iva, RegimeKey::Key01, OperationQualification::S2, null],
            // Regime 01 does not admit E2 (Validaciones, error 1199); exports use regime 02.
            VatCategory::ExportOutsideEu => [TaxType::Iva, RegimeKey::Key02, null, ExemptOperation::E2],
            VatCategory::IntraCommunitySupply => [TaxType::Iva, RegimeKey::Key01, null, ExemptOperation::E5],
            VatCategory::CanaryIslands => [TaxType::Igic, RegimeKey::Key01, OperationQualification::S1, null],
            VatCategory::CeutaAndMelilla => [TaxType::Ipsi, RegimeKey::Key01, OperationQualification::S1, null],
            VatCategory::Exempt, VatCategory::NotSubjectToVat => [TaxType::Iva, RegimeKey::Key01, null, null],
            VatCategory::TransferredVat => null,
        };
    }

    /**
     * Base and quota of a group: BT-116 and BT-117, or the EUR amounts of a foreign-currency invoice.
     *
     * @param  list<string>  $problems
     * @return array{?Amount, ?Amount}
     */
    private function groupAmounts(VatBreakdown $group, string $key, ?EuroAmounts $euro, array &$problems): array
    {
        if ($euro !== null) {
            $entry = $euro->breakdown[$key] ?? null;
            if ($entry === null) {
                $problems[] = sprintf('Desglose (%s): SpanishInvoice::$euroAmounts has no EUR amounts for this VAT group.', $key);

                return [null, null];
            }

            return [$entry['base'], $entry['quota']];
        }

        return [
            $this->amount($group->taxableAmount, sprintf('BaseImponibleOimporteNoSujeto (%s)', $key), $problems),
            $this->amount($group->taxAmount, sprintf('CuotaRepercutida (%s)', $key), $problems),
        ];
    }

    /**
     * CuotaTotal (BT-110) and ImporteTotal (BT-112), or the EUR totals of a foreign-currency invoice.
     *
     * @param  list<string>  $problems
     * @return array{?Amount, ?Amount}
     */
    private function totals(Invoice $invoice, ?EuroAmounts $euro, bool $negate, array &$problems): array
    {
        $totals = $invoice->totals;

        if ($totals === null) {
            $problems[] = 'BG-22: the invoice has no document totals; complete the invoice with TotalsCalculator first.';

            return [null, null];
        }

        if ($invoice->currency !== CurrencyCode::EUR) {
            if ($euro === null) {
                $problems[] = sprintf(
                    'BT-5: VERI*FACTU records are in EUR; a %s invoice needs SpanishInvoice::$euroAmounts.',
                    $invoice->currency->value,
                );

                return [null, null];
            }

            [$totalTax, $total] = [$euro->totalTax, $euro->total];
        } else {
            $totalTax = $this->amount($totals->totalVat ?? BigDecimal::zero(), 'CuotaTotal (BT-110)', $problems);
            $total = $this->amount($totals->totalWithVat, 'ImporteTotal (BT-112)', $problems);
        }

        if ($negate) {
            return [$totalTax?->negated(), $total?->negated()];
        }

        return [$totalTax, $total];
    }

    /**
     * @param  list<string>  $problems
     */
    private function amount(BigDecimal $value, string $field, array &$problems): ?Amount
    {
        try {
            return Amount::of($value);
        } catch (InvalidRecord $e) {
            $problems[] = $field.': '.$e->getMessage();

            return null;
        }
    }

    /**
     * FechaOperacion: the actual delivery date (BT-72), else the end of the invoicing period
     * (BG-14), written only when it differs from the issue date.
     */
    private function operationDate(Invoice $invoice): ?DateTimeImmutable
    {
        $delivery = $invoice->delivery;
        $date = $delivery === null ? null : ($delivery->actualDeliveryDate ?? $delivery->invoicingPeriod?->end);

        if ($date === null || $date->format('Y-m-d') === $invoice->issueDate->format('Y-m-d')) {
            return null;
        }

        return $date;
    }
}
