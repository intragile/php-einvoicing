<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

use Brick\Math\BigDecimal;
use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Model\Invoice;
use IntrAgile\EInvoicing\Spain\CorrectionType;
use IntrAgile\EInvoicing\Spain\TaxType;
use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\MappingRefused;
use IntrAgile\EInvoicing\Spain\Verifactu\Fingerprint;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordMapper;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxBreakdownLine;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxRate;
use IntrAgile\EInvoicing\Spain\Verifactu\VerifactuQr;
use IntrAgile\EInvoicing\Spain\VerifactuEnvironment;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Verifactu\RecordReader;
use IntrAgile\EInvoicing\Validation\Exception\UnusableSchema;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;
use Throwable;

/**
 * Cross-checks the VERI*FACTU record B2Brouter generated for an invoice against our own engine,
 * in five steps:
 *
 * 1. Schema: the report carries record XML that loads safely, is valid against
 *    SuministroInformacion.xsd and reads as a billing record. When this step fails, steps 2 to 5
 *    are Skipped.
 * 2. Fingerprint: the Huella recomputed from the literal text of the hash fields equals both the
 *    record's Huella and the report's fingerprint.
 * 3. Chain: with a previous report, RegistroAnterior must name the previous record's IDFactura
 *    and carry the previous report's fingerprint (and the previous record's own Huella); a record
 *    marked PrimerRegistro fails when a previous report is given. Without a previous report, a
 *    PrimerRegistro record passes and a chained record is Skipped. B2Brouter's previous_id is not
 *    a tax report id and is not used.
 * 4. Semantics: the record carries what RecordMapper maps the invoice to: the IDFactura triple,
 *    TipoFactura, TipoRectificativa, the breakdown lines (tax, regime, qualification or
 *    exemption, rate, base and quota, compared by value after sorting both sides the same way),
 *    CuotaTotal and ImporteTotal. A refused mapping fails the step and lists the problems.
 * 5. QR: with a decoder and a QR image, the decoded text must equal VerifactuQr::url() of
 *    B2Brouter's record exactly. Without a decoder, without an image, or when decoding fails, the
 *    fallback compares the URL parameters of B2Brouter's record with those of our draft (the
 *    amount by value) and notes that the fallback was used.
 *
 * Only two divergences are known and accepted (KnownDivergence), both in step 4: an exempt line
 * whose OperacionExenta differs from ours (B2Brouter does not take the exemption article from the
 * invoice), and a credit note by difference (TipoRectificativa I) whose amounts B2Brouter writes
 * positive where our engine writes them negated. Every other difference fails its step.
 *
 * B2Brouter prints the production ValidarQR host in its QR codes, in its sandbox too, so callers
 * checking B2Brouter reports pass VerifactuEnvironment::Production; the Test default is the AEAT
 * pre-production host.
 */
final class VerifactuCrossCheck
{
    public function __construct(
        private readonly VerifactuSchemas $schemas,
        private readonly VerifactuEnvironment $environment = VerifactuEnvironment::Test,
        private readonly ?QrDecoder $qrDecoder = null,
        private readonly RecordMapper $mapper = new RecordMapper,
    ) {}

    /**
     * @param  TaxReport|null  $previous  the report of the record before this one in the chain,
     *                                    from the caller's own store
     *
     * @throws UnusableSchema when the installed VERI*FACTU schema set cannot be used
     */
    public function verify(TaxReport $report, ?TaxReport $previous, Invoice $invoice): CrossCheckResult
    {
        $run = new CrossCheckRun($report->id);

        $read = $this->schema($report, $run);
        if ($read === null) {
            foreach ([CrossCheckStep::Fingerprint, CrossCheckStep::Chain, CrossCheckStep::Semantics, CrossCheckStep::Qr] as $step) {
                $run->skip($step);
            }

            return $run->result();
        }
        [$root, $record] = $read;

        $this->fingerprint($report, $root, $record, $run);
        $this->chain($record, $previous, $run);
        $draft = $this->semantics($record, $invoice, $run);
        $this->qr($report, $record, $draft, $run);

        return $run->result();
    }

    /**
     * @return array{DOMElement, BillingRecord}|null
     */
    private function schema(TaxReport $report, CrossCheckRun $run): ?array
    {
        $step = CrossCheckStep::Schema;
        if ($report->recordXml === null) {
            $run->fail($step, 'xml_base64', '', '', 'The tax report carries no record XML.');

            return $run->close($step);
        }

        try {
            $document = SafeXml::load($report->recordXml);
        } catch (UnsupportedDocument|UnsafeXml $e) {
            $run->fail($step, 'xml_base64', '', '', 'The record XML cannot be loaded: '.$e->getMessage());

            return $run->close($step);
        }

        $errors = (new SchemaValidator)->validate($document, $this->schemas->informationXsd(), $this->schemas->localPrefixes());
        foreach ($errors as $error) {
            $run->fail($step, 'xml_base64', '', '', sprintf('Line %d: %s', $error->line, $error->message));
        }
        $root = $document->documentElement;
        if ($errors !== [] || ! $root instanceof DOMElement) {
            return $run->close($step);
        }

        try {
            $record = (new RecordReader)->readElement($root);
        } catch (UnsupportedDocument|InvalidRecord $e) {
            $run->fail($step, 'xml_base64', '', '', 'The record cannot be read: '.$e->getMessage());

            return $run->close($step);
        }
        $run->close($step);

        return [$root, $record];
    }

    private function fingerprint(TaxReport $report, DOMElement $root, BillingRecord $record, CrossCheckRun $run): void
    {
        $step = CrossCheckStep::Fingerprint;
        $recomputed = Fingerprint::recompute($root);
        if ($recomputed !== $record->fingerprint()) {
            $run->fail($step, 'Huella', $recomputed, $record->fingerprint(), 'The Huella does not recompute from the record\'s hash fields.');
        }
        if ($recomputed !== $report->fingerprint) {
            $run->fail($step, 'fingerprint', $recomputed, $report->fingerprint ?? '', 'The report\'s fingerprint is not the recomputed Huella.');
        }
        $run->close($step);
    }

    private function chain(BillingRecord $record, ?TaxReport $previous, CrossCheckRun $run): void
    {
        $step = CrossCheckStep::Chain;
        $link = $record->chainLink();

        if ($previous === null) {
            if ($link->isFirst()) {
                $run->close($step);
            } else {
                $run->skip($step, 'Chain not checked: the record names a previous record (RegistroAnterior), but no previous report was given.');
            }

            return;
        }

        if ($link->isFirst()) {
            $run->fail($step, 'PrimerRegistro', '', 'S', sprintf('The record is marked as the first of its chain, but previous report %s was given.', $previous->id));
            $run->close($step);

            return;
        }

        try {
            $previousRecord = $previous->record();
        } catch (UnsupportedDocument|UnsafeXml|InvalidRecord $e) {
            $run->fail($step, 'RegistroAnterior', '', '', sprintf('The record of previous report %s cannot be read: %s', $previous->id, $e->getMessage()));
            $run->close($step);

            return;
        }

        $named = $link->previous();
        if ($named === null || ! $named->equals($previousRecord->invoiceId())) {
            $run->fail(
                $step,
                'RegistroAnterior.IDFactura',
                self::idText($previousRecord->invoiceId()),
                $named === null ? '' : self::idText($named),
                sprintf('RegistroAnterior does not name the record of previous report %s.', $previous->id),
            );
        }

        $carried = $link->previousFingerprint();
        $expected = $previous->fingerprint ?? '';
        if ($carried !== $expected) {
            $run->fail($step, 'RegistroAnterior.Huella', $expected, $carried, sprintf('RegistroAnterior does not carry the fingerprint of previous report %s.', $previous->id));
        }
        if ($carried !== $previousRecord->fingerprint() && $previousRecord->fingerprint() !== $expected) {
            $run->fail($step, 'RegistroAnterior.Huella', $previousRecord->fingerprint(), $carried, sprintf('RegistroAnterior does not carry the Huella of the record of previous report %s.', $previous->id));
        }
        $run->close($step);
    }

    private function semantics(BillingRecord $record, Invoice $invoice, CrossCheckRun $run): ?RegistrationDraft
    {
        $step = CrossCheckStep::Semantics;
        if (! $record instanceof RegistrationRecord) {
            $run->fail($step, 'RegistroAlta', '', '', 'The record is a RegistroAnulacion, not the RegistroAlta of an issued invoice.');
            $run->close($step);

            return null;
        }

        try {
            $ours = $this->mapper->map($invoice);
        } catch (MappingRefused $e) {
            $run->fail($step, 'invoice', '', '', 'Our engine refuses to map the invoice: '.implode('; ', $e->problems));
            $run->close($step);

            return null;
        }
        $theirs = $record->draft;
        $difference = $ours->correctionType === CorrectionType::Difference;

        if (! $ours->id->equals($theirs->id)) {
            $run->fail($step, 'IDFactura', self::idText($ours->id), self::idText($theirs->id), 'The invoice id differs.');
        }
        self::compareText($run, 'TipoFactura', $ours->type->value, $theirs->type->value);
        self::compareText($run, 'TipoRectificativa', $ours->correctionType->value ?? '', $theirs->correctionType->value ?? '');

        $oursLines = self::sorted($ours->breakdown);
        $theirsLines = self::sorted($theirs->breakdown);
        if (count($oursLines) !== count($theirsLines)) {
            $run->fail($step, 'Desglose', (string) count($oursLines), (string) count($theirsLines), 'The number of breakdown lines (DetalleDesglose) differs.');
        } else {
            foreach ($oursLines as $index => $line) {
                self::compareLine($run, sprintf('DetalleDesglose[%d].', $index + 1), $line, $theirsLines[$index], $difference);
            }
        }

        self::compareAmount($run, 'CuotaTotal', $ours->totalTax, $theirs->totalTax, $difference);
        self::compareAmount($run, 'ImporteTotal', $ours->total, $theirs->total, $difference);
        $run->close($step);

        return $ours;
    }

    private function qr(TaxReport $report, BillingRecord $record, ?RegistrationDraft $draft, CrossCheckRun $run): void
    {
        $step = CrossCheckStep::Qr;
        if (! $record instanceof RegistrationRecord) {
            $run->skip($step, 'QR not checked: the record is not a RegistroAlta.');

            return;
        }
        $expected = VerifactuQr::url($record, $this->environment);

        $decoder = $this->qrDecoder;
        $png = $report->qrPng;
        if ($decoder === null) {
            $reason = 'no QR decoder is configured';
        } elseif ($png === null) {
            $reason = 'the report carries no QR image';
        } else {
            try {
                $decoded = $decoder->decode($png);
                if ($decoded !== $expected) {
                    $run->fail($step, 'qr', $expected, $decoded, 'The QR code does not hold the verification URL of the record.');
                }
                $run->close($step);

                return;
            } catch (Throwable $e) {
                $reason = 'the QR image could not be decoded: '.$e->getMessage();
            }
        }

        if ($draft === null) {
            $run->skip($step, sprintf('QR not checked: %s, and the fallback needs our own draft, which step 4 could not build.', $reason));

            return;
        }

        $theirs = $record->draft;
        foreach ([
            'nif' => [$draft->id->issuer->value, $theirs->id->issuer->value],
            'numserie' => [$draft->id->number, $theirs->id->number],
            'fecha' => [$draft->id->dateText(), $theirs->id->dateText()],
        ] as $parameter => [$ours, $carried]) {
            if ($ours !== $carried) {
                $run->fail($step, $parameter, $ours, $carried, sprintf('The QR parameter %s of the record differs from ours.', $parameter));
            }
        }
        if (! $draft->total->isEqualTo($theirs->total)) {
            $run->fail($step, 'importe', $draft->total->text, $theirs->total->text, 'The QR parameter importe of the record differs from ours.');
        }
        $run->note(sprintf('QR fallback: %s; compared the URL parameters of B2Brouter\'s record (%s) with our own draft\'s, importe by value.', $reason, $expected));
        $run->close($step);
    }

    private static function compareLine(CrossCheckRun $run, string $prefix, TaxBreakdownLine $ours, TaxBreakdownLine $theirs, bool $difference): void
    {
        self::compareText($run, $prefix.'Impuesto', self::taxCode($ours), self::taxCode($theirs));
        self::compareText($run, $prefix.'ClaveRegimen', $ours->regime->value ?? '', $theirs->regime->value ?? '');
        self::compareText($run, $prefix.'CalificacionOperacion', $ours->qualification->value ?? '', $theirs->qualification->value ?? '');

        if ($ours->exemption !== null && $theirs->exemption !== null && $ours->exemption !== $theirs->exemption) {
            $run->diverge(
                CrossCheckStep::Semantics,
                $prefix.'OperacionExenta',
                $ours->exemption->value,
                $theirs->exemption->value,
                'Known divergence: B2Brouter does not take the exemption article (OperacionExenta) from the invoice.',
            );
        } else {
            self::compareText($run, $prefix.'OperacionExenta', $ours->exemption->value ?? '', $theirs->exemption->value ?? '');
        }

        if (! self::sameRate($ours->rate, $theirs->rate)) {
            $run->fail(CrossCheckStep::Semantics, $prefix.'TipoImpositivo', $ours->rate->text ?? '', $theirs->rate->text ?? '', 'The value differs.');
        }
        self::compareAmount($run, $prefix.'BaseImponibleOimporteNoSujeto', $ours->base, $theirs->base, $difference);
        self::compareAmount($run, $prefix.'CuotaRepercutida', $ours->quota, $theirs->quota, $difference);
    }

    private static function compareText(CrossCheckRun $run, string $field, string $ours, string $theirs): void
    {
        if ($ours !== $theirs) {
            $run->fail(CrossCheckStep::Semantics, $field, $ours, $theirs, 'The value differs.');
        }
    }

    /**
     * Compares two amounts by value. When our draft is a correction by difference, our amount is
     * negative and B2Brouter's equals it negated, the difference is the known sign divergence.
     */
    private static function compareAmount(CrossCheckRun $run, string $field, ?Amount $ours, ?Amount $theirs, bool $difference): void
    {
        if ($ours === null || $theirs === null) {
            if ($ours !== $theirs) {
                $run->fail(CrossCheckStep::Semantics, $field, $ours->text ?? '', $theirs->text ?? '', 'The value differs.');
            }

            return;
        }
        if ($ours->isEqualTo($theirs)) {
            return;
        }
        if ($difference && $ours->value->isNegative() && $ours->value->negated()->isEqualTo($theirs->value)) {
            $run->diverge(
                CrossCheckStep::Semantics,
                $field,
                $ours->text,
                $theirs->text,
                'Known divergence: B2Brouter writes the amounts of a correction by difference (TipoRectificativa I) positive.',
            );

            return;
        }
        $run->fail(CrossCheckStep::Semantics, $field, $ours->text, $theirs->text, 'The value differs.');
    }

    private static function sameRate(?TaxRate $ours, ?TaxRate $theirs): bool
    {
        return $ours === null || $theirs === null ? $ours === $theirs : $ours->isEqualTo($theirs);
    }

    /** Impuesto as written, an absent one counting as 01 (IVA). */
    private static function taxCode(TaxBreakdownLine $line): string
    {
        return ($line->tax ?? TaxType::Iva)->value;
    }

    /**
     * The lines in a sign-independent order: by tax, exempt or qualification, rate and absolute
     * base, so that both sides line up even when their exemption codes or signs differ.
     *
     * @param  list<TaxBreakdownLine>  $lines
     * @return list<TaxBreakdownLine>
     */
    private static function sorted(array $lines): array
    {
        $key = static fn (TaxBreakdownLine $line): array => [
            self::taxCode($line),
            $line->exemption !== null ? 'E' : ($line->qualification->value ?? ''),
            $line->rate->value ?? BigDecimal::of(-1),
            $line->base->abs(),
        ];
        usort($lines, static function (TaxBreakdownLine $a, TaxBreakdownLine $b) use ($key): int {
            [$taxA, $kindA, $rateA, $baseA] = $key($a);
            [$taxB, $kindB, $rateB, $baseB] = $key($b);

            return [$taxA, $kindA] <=> [$taxB, $kindB]
                ?: $rateA->compareTo($rateB)
                ?: $baseA->compareTo($baseB);
        });

        return $lines;
    }

    private static function idText(InvoiceId $id): string
    {
        return sprintf('%s %s %s', $id->issuer->value, $id->number, $id->dateText());
    }
}
