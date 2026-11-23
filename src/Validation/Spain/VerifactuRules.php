<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Validation\Spain;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use IntrAgile\EInvoicing\Schematron\LocationPath;
use IntrAgile\EInvoicing\Spain\RegimeKey;
use IntrAgile\EInvoicing\Spain\TaxType;
use IntrAgile\EInvoicing\Spain\Verifactu\Fingerprint;
use IntrAgile\EInvoicing\Syntax\Verifactu\VerifactuFormat;
use IntrAgile\EInvoicing\Validation\Finding;
use IntrAgile\EInvoicing\Validation\Severity;

/**
 * The VERI*FACTU business rules that the XML Schema cannot express, taken from the AEAT
 * "Validaciones y errores" document, version 1.2.2, and its error catalogue (errores.properties).
 *
 * AEAT numbers its errors, not its rules, so the rules carry their own ids ES-VF-01 to ES-VF-24
 * with the AEAT error codes they stand for. A rule whose error AEAT rejects the record for is
 * fatal; a rule whose error AEAT accepts the record with ("aceptado con errores") is a warning.
 * Each finding's location is the path of the node concerned and its test names the section and
 * the AEAT error code. The rules work on any DOM, including one that fails the schema: a missing
 * or malformed field makes the rules that need it stay silent rather than fail.
 *
 * Rules that need the submission (the issuer against the header, the existence of agreements at
 * AEAT) or the current date are left to AEAT.
 */
final class VerifactuRules
{
    public const ID = 'es-verifactu';

    public const VERSION = '1.2.2';

    private const DOCUMENT = 'Validaciones v'.self::VERSION.' ';

    private const NAMESPACES = [
        VerifactuFormat::SF_PREFIX => VerifactuFormat::SF_NS,
        VerifactuFormat::LR_PREFIX => VerifactuFormat::LR_NS,
    ];

    /** The day the VERI*FACTU ministerial order entered into force. */
    private const IN_FORCE = '2024-10-28';

    /** Corrective invoice types (TipoFactura). */
    private const CORRECTIVE = ['R1', 'R2', 'R3', 'R4', 'R5'];

    /** Invoice types that must identify their recipients. */
    private const WITH_RECIPIENTS = ['F1', 'F3', 'R1', 'R2', 'R3', 'R4'];

    /** Invoice types issued without identifying the recipient (simplified invoices). */
    private const WITHOUT_RECIPIENTS = ['F2', 'R5'];

    /** ImporteTotal from which Macrodato must be S, in absolute value. */
    private const MACRODATO_THRESHOLD = '100000000.00';

    /** The margin AEAT admits on computed quotas and totals, in euros (inclusive). */
    private const TOLERANCE = '10.00';

    /** The limit of a simplified invoice (3,000.00 euros) plus the admitted margin. */
    private const SIMPLIFIED_LIMIT = '3010.00';

    /** Regime keys whose records skip the CuotaTotal and ImporteTotal sum checks. */
    private const WITHOUT_SUM_CHECK = ['03', '05', '06', '08', '09'];

    private function __construct() {}

    /**
     * Every rule by id, in id order: its severity, the AEAT error codes it stands for (comma
     * separated) and the sections of the validation document it is taken from.
     *
     * @return array<string, array{severity: Severity, code: string, source: string}>
     */
    public static function rules(): array
    {
        $fatal = Severity::Fatal;
        $warning = Severity::Warning;

        return [
            'ES-VF-01' => ['severity' => $fatal, 'code' => '1152', 'source' => '§3.1.3.1'],
            'ES-VF-02' => ['severity' => $fatal, 'code' => '1153, 1161', 'source' => '§3.1.3.2'],
            'ES-VF-03' => ['severity' => $fatal, 'code' => '1114, 1115', 'source' => '§3.1.3.3'],
            'ES-VF-04' => ['severity' => $fatal, 'code' => '1117', 'source' => '§3.1.3.4'],
            'ES-VF-05' => ['severity' => $fatal, 'code' => '1116', 'source' => '§3.1.3.5'],
            'ES-VF-06' => ['severity' => $fatal, 'code' => '1118, 1119', 'source' => '§3.1.3.6'],
            'ES-VF-07' => ['severity' => $fatal, 'code' => '1183', 'source' => '§3.1.3.8'],
            'ES-VF-08' => ['severity' => $fatal, 'code' => '1185', 'source' => '§3.1.3.9'],
            'ES-VF-09' => ['severity' => $fatal, 'code' => '1138, 1139', 'source' => '§3.1.3.10'],
            'ES-VF-10' => ['severity' => $fatal, 'code' => '1186, 1187', 'source' => '§3.1.3.11-12'],
            'ES-VF-11' => ['severity' => $fatal, 'code' => '1189, 1190', 'source' => '§3.1.3.13'],
            'ES-VF-12' => ['severity' => $fatal, 'code' => '1188, 1193', 'source' => '§3.1.3.12-13'],
            'ES-VF-13' => ['severity' => $fatal, 'code' => '1245, 1246, 1260', 'source' => '§3.1.3.15.6'],
            'ES-VF-14' => ['severity' => $fatal, 'code' => '1199', 'source' => '§3.1.3.15.5'],
            'ES-VF-15' => ['severity' => $fatal, 'code' => '1238', 'source' => '§3.1.3.15.5'],
            'ES-VF-16' => ['severity' => $fatal, 'code' => '1198', 'source' => '§3.1.3.15.4'],
            'ES-VF-17' => ['severity' => $fatal, 'code' => '1207', 'source' => '§3.1.3.15.7'],
            'ES-VF-18' => ['severity' => $fatal, 'code' => '1208', 'source' => '§3.1.3.15.7'],
            'ES-VF-19' => ['severity' => $fatal, 'code' => '1142, 1143', 'source' => '§3.1.3.15.7'],
            'ES-VF-20' => ['severity' => $fatal, 'code' => '1150', 'source' => '§3.1.3.15.8'],
            'ES-VF-21' => ['severity' => $warning, 'code' => '2006', 'source' => '§3.1.3.16'],
            'ES-VF-22' => ['severity' => $warning, 'code' => '2005', 'source' => '§3.1.3.17'],
            'ES-VF-23' => ['severity' => $warning, 'code' => '2000', 'source' => '§3.1.3.23, §3.1.4.7'],
            'ES-VF-24' => ['severity' => $warning, 'code' => '2002, 2003, 2008', 'source' => '§3.1.3.18, §3.1.4.4'],
        ];
    }

    /**
     * Checks every RegistroAlta and RegistroAnulacion of the document, a bare record or the
     * records of a submission.
     *
     * @return list<Finding> record by record in document order, then in rule order
     */
    public static function check(DOMDocument $document): array
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('sf', VerifactuFormat::SF_NS);
        $findings = [];
        foreach (self::all($xpath, '//sf:RegistroAlta | //sf:RegistroAnulacion') as $record) {
            if ($record->localName === 'RegistroAlta') {
                array_push($findings, ...self::checkRegistration($xpath, $record));
            }
            array_push($findings, ...self::checkFingerprints($xpath, $record));
        }

        return $findings;
    }

    /**
     * ES-VF-01 to ES-VF-22, which apply to a RegistroAlta only.
     *
     * @return list<Finding>
     */
    private static function checkRegistration(DOMXPath $xpath, DOMElement $record): array
    {
        $findings = [];
        $report = static function (string $id, string $code, DOMNode $node, string $detail) use (&$findings): void {
            $findings[] = self::finding($id, $code, $node, $detail);
        };

        // ES-VF-01
        $issueDate = self::first($xpath, 'sf:IDFactura/sf:FechaExpedicionFactura', $record);
        $issued = self::date($issueDate);
        if ($issueDate !== null && $issued !== null && $issued < new DateTimeImmutable(self::IN_FORCE)) {
            $report('ES-VF-01', '1152', $issueDate, 'FechaExpedicionFactura is '.self::text($issueDate).'.');
        }

        // ES-VF-02
        $rejection = self::first($xpath, 'sf:RechazoPrevio', $record);
        $rejectionValue = self::text($rejection);
        $correctionFlag = self::text(self::first($xpath, 'sf:Subsanacion', $record));
        if ($rejection !== null && $correctionFlag !== 'S' && in_array($rejectionValue, ['X', 'S'], true)) {
            $report('ES-VF-02', $rejectionValue === 'X' ? '1153' : '1161', $rejection, sprintf(
                'RechazoPrevio is %s and Subsanacion is %s.',
                $rejectionValue,
                $correctionFlag ?? 'absent',
            ));
        }

        $typeElement = self::first($xpath, 'sf:TipoFactura', $record);
        $type = self::text($typeElement);
        $typeDetail = "TipoFactura is {$type}.";
        $isCorrective = in_array($type, self::CORRECTIVE, true);

        // ES-VF-03
        $correctionKind = self::first($xpath, 'sf:TipoRectificativa', $record);
        if ($typeElement !== null && $isCorrective && $correctionKind === null) {
            $report('ES-VF-03', '1114', $typeElement, $typeDetail);
        }
        if ($type !== null && ! $isCorrective && $correctionKind !== null) {
            $report('ES-VF-03', '1115', $correctionKind, $typeDetail);
        }

        // ES-VF-04
        $corrected = self::first($xpath, 'sf:FacturasRectificadas', $record);
        if ($type !== null && ! $isCorrective && $corrected !== null) {
            $report('ES-VF-04', '1117', $corrected, $typeDetail);
        }

        // ES-VF-05
        $substituted = self::first($xpath, 'sf:FacturasSustituidas', $record);
        if ($type !== null && $type !== 'F3' && $substituted !== null) {
            $report('ES-VF-05', '1116', $substituted, $typeDetail);
        }

        // ES-VF-06
        $correctionKindValue = self::text($correctionKind);
        $correctionAmounts = self::first($xpath, 'sf:ImporteRectificacion', $record);
        if ($correctionKind !== null && $correctionKindValue === 'S' && $correctionAmounts === null) {
            $report('ES-VF-06', '1118', $correctionKind, '');
        }
        if ($correctionAmounts !== null && $correctionKindValue !== 'S') {
            $report('ES-VF-06', '1119', $correctionAmounts, 'TipoRectificativa is '.($correctionKindValue ?? 'absent').'.');
        }

        // ES-VF-07
        $simplifiedArt7273 = self::first($xpath, 'sf:FacturaSimplificadaArt7273', $record);
        if ($type !== null && self::text($simplifiedArt7273) === 'S' && ! in_array($type, self::WITH_RECIPIENTS, true)) {
            $report('ES-VF-07', '1183', $simplifiedArt7273 ?? $record, $typeDetail);
        }

        // ES-VF-08
        $withoutRecipient = self::first($xpath, 'sf:FacturaSinIdentifDestinatarioArt61d', $record);
        $isWithoutRecipient = self::text($withoutRecipient) === 'S';
        if ($type !== null && $isWithoutRecipient && ! in_array($type, self::WITHOUT_RECIPIENTS, true)) {
            $report('ES-VF-08', '1185', $withoutRecipient ?? $record, $typeDetail);
        }

        // ES-VF-09
        $macrodato = self::first($xpath, 'sf:Macrodato', $record);
        $totalElement = self::first($xpath, 'sf:ImporteTotal', $record);
        $total = self::decimal($totalElement);
        if ($totalElement !== null && $total !== null) {
            $isLarge = $total->abs()->isGreaterThanOrEqualTo(self::MACRODATO_THRESHOLD);
            $isMarked = self::text($macrodato) === 'S';
            if ($isMarked && ! $isLarge) {
                $report('ES-VF-09', '1138', $macrodato ?? $record, "ImporteTotal is {$total}.");
            } elseif ($isLarge && ! $isMarked) {
                $report('ES-VF-09', '1139', $totalElement, "ImporteTotal is {$total}.");
            }
        }

        // ES-VF-10
        $issuedBy = self::first($xpath, 'sf:EmitidaPorTerceroODestinatario', $record);
        $thirdParty = self::first($xpath, 'sf:Tercero', $record);
        $issuedByThirdParty = self::text($issuedBy) === 'T';
        if ($issuedBy !== null && $issuedByThirdParty && $thirdParty === null) {
            $report('ES-VF-10', '1186', $issuedBy, '');
        }
        if ($thirdParty !== null && ! $issuedByThirdParty) {
            $report('ES-VF-10', '1187', $thirdParty, 'EmitidaPorTerceroODestinatario is '.(self::text($issuedBy) ?? 'absent').'.');
        }

        // ES-VF-11
        $recipients = self::first($xpath, 'sf:Destinatarios', $record);
        $hasRecipient = self::first($xpath, 'sf:Destinatarios/sf:IDDestinatario', $record) !== null;
        if ($typeElement !== null && in_array($type, self::WITH_RECIPIENTS, true) && ! $hasRecipient) {
            $report('ES-VF-11', '1189', $typeElement, $typeDetail);
        }
        if ($recipients !== null && in_array($type, self::WITHOUT_RECIPIENTS, true)) {
            $report('ES-VF-11', '1190', $recipients, $typeDetail);
        }

        // ES-VF-12
        $issuer = self::text(self::first($xpath, 'sf:IDFactura/sf:IDEmisorFactura', $record));
        if ($issuer !== null && $issuer !== '') {
            foreach (self::all($xpath, 'sf:Tercero/sf:NIF', $record) as $nif) {
                if (self::text($nif) === $issuer) {
                    $report('ES-VF-12', '1188', $nif, "The third party NIF is the issuer {$issuer}.");
                }
            }
            foreach (self::all($xpath, 'sf:Destinatarios/sf:IDDestinatario/sf:NIF', $record) as $nif) {
                if (self::text($nif) === $issuer) {
                    $report('ES-VF-12', '1193', $nif, "The recipient NIF is the issuer {$issuer}.");
                }
            }
        }

        $lines = self::all($xpath, 'sf:Desglose/sf:DetalleDesglose', $record);
        $checksQuota = $correctionKindValue !== 'I' && ! in_array($type, ['R2', 'R3'], true);
        foreach ($lines as $line) {
            array_push($findings, ...self::checkLine($xpath, $line, $checksQuota));
        }

        // ES-VF-20
        $agreement = self::text(self::first($xpath, 'sf:NumRegistroAcuerdoFacturacion', $record));
        if ($typeElement !== null && $type === 'F2' && ($agreement === null || $agreement === '') && ! $isWithoutRecipient) {
            $sum = self::sum($xpath, $lines, ['sf:BaseImponibleOimporteNoSujeto', 'sf:CuotaRepercutida']);
            if ($sum !== null && $sum->isGreaterThan(self::SIMPLIFIED_LIMIT)) {
                $report('ES-VF-20', '1150', $typeElement, "The lines add up to {$sum}.");
            }
        }

        // ES-VF-21 and ES-VF-22
        $special = array_filter($lines, static fn (DOMElement $line): bool => in_array(
            self::text(self::first($xpath, 'sf:ClaveRegimen', $line)),
            self::WITHOUT_SUM_CHECK,
            true,
        ));
        if ($special === []) {
            $totals = [
                ['ES-VF-21', '2006', 'CuotaTotal', ['sf:CuotaRepercutida', 'sf:CuotaRecargoEquivalencia']],
                ['ES-VF-22', '2005', 'ImporteTotal', ['sf:BaseImponibleOimporteNoSujeto', 'sf:CuotaRepercutida', 'sf:CuotaRecargoEquivalencia']],
            ];
            foreach ($totals as [$id, $code, $name, $parts]) {
                $element = self::first($xpath, "sf:{$name}", $record);
                $given = self::decimal($element);
                $sum = self::sum($xpath, $lines, $parts);
                if ($element !== null && $given !== null && $sum !== null && $given->minus($sum)->abs()->isGreaterThan(self::TOLERANCE)) {
                    $report($id, $code, $element, "{$name} is {$given}; the lines add up to {$sum}.");
                }
            }
        }

        return $findings;
    }

    /**
     * ES-VF-23 and ES-VF-24, which apply to both records.
     *
     * @return list<Finding>
     */
    private static function checkFingerprints(DOMXPath $xpath, DOMElement $record): array
    {
        $findings = [];
        $own = self::first($xpath, 'sf:Huella', $record);
        $ownValue = self::text($own);

        // ES-VF-23
        if ($own !== null) {
            $expected = Fingerprint::recompute($record);
            if ($ownValue !== $expected) {
                $findings[] = self::finding('ES-VF-23', '2000', $own, "Huella is {$ownValue}; the record hashes to {$expected}.");
            }
        }

        // ES-VF-24
        $previous = self::first($xpath, 'sf:Encadenamiento/sf:RegistroAnterior/sf:Huella', $record);
        $previousValue = self::text($previous) ?? '';
        if ($previous !== null) {
            $detail = "The previous Huella is \"{$previousValue}\".";
            if (strlen($previousValue) !== 64) {
                $findings[] = self::finding('ES-VF-24', '2002', $previous, $detail);
            } elseif (preg_match('/\A[0-9A-F]{64}\z/', $previousValue) !== 1) {
                $findings[] = self::finding('ES-VF-24', '2003', $previous, $detail);
            }
            if ($previousValue === $ownValue) {
                $findings[] = self::finding('ES-VF-24', '2008', $previous, $detail.' It is the record\'s own Huella.');
            }
        }

        return $findings;
    }

    /**
     * ES-VF-13 to ES-VF-19 on one DetalleDesglose.
     *
     * @param  bool  $checksQuota  false when the quota of S1 lines is not compared with the base
     *                             (TipoRectificativa I, TipoFactura R2 or R3)
     * @return list<Finding>
     */
    private static function checkLine(DOMXPath $xpath, DOMElement $line, bool $checksQuota): array
    {
        $findings = [];
        $report = static function (string $id, string $code, DOMNode $node, string $detail) use (&$findings): void {
            $findings[] = self::finding($id, $code, $node, $detail);
        };
        $taxElement = self::first($xpath, 'sf:Impuesto', $line);
        $tax = $taxElement === null ? TaxType::Iva : TaxType::tryFrom(self::text($taxElement) ?? '');
        $regimeElement = self::first($xpath, 'sf:ClaveRegimen', $line);
        $regime = self::text($regimeElement);
        $qualification = self::text(self::first($xpath, 'sf:CalificacionOperacion', $line));
        $exemptionElement = self::first($xpath, 'sf:OperacionExenta', $line);
        $exemption = self::text($exemptionElement);
        $rateElement = self::first($xpath, 'sf:TipoImpositivo', $line);
        $quotaElement = self::first($xpath, 'sf:CuotaRepercutida', $line);
        $rate = self::decimal($rateElement);
        $quota = self::decimal($quotaElement);

        // ES-VF-13
        if ($tax === TaxType::Other && $regimeElement !== null) {
            $report('ES-VF-13', '1260', $regimeElement, 'Impuesto is 05.');
        } elseif ($tax !== null && $tax !== TaxType::Other && $regimeElement === null) {
            $report('ES-VF-13', '1245', $line, "Impuesto is {$tax->value}.");
        } elseif ($tax !== null && $regimeElement !== null && ! (RegimeKey::tryFrom($regime ?? '')?->allowedFor($tax) ?? false)) {
            $report('ES-VF-13', '1246', $regimeElement, "ClaveRegimen {$regime} is not admitted for Impuesto {$tax->value}.");
        }

        // ES-VF-14
        if (in_array($tax, [TaxType::Iva, TaxType::Igic], true) && $regime === '01' && in_array($exemption, ['E2', 'E3'], true)) {
            $report('ES-VF-14', '1199', $exemptionElement ?? $line, "OperacionExenta is {$exemption}.");
        }

        // ES-VF-15
        if ($exemptionElement !== null) {
            $taxed = [];
            foreach (['TipoImpositivo', 'CuotaRepercutida', 'TipoRecargoEquivalencia', 'CuotaRecargoEquivalencia'] as $name) {
                if (self::first($xpath, "sf:{$name}", $line) !== null) {
                    $taxed[] = $name;
                }
            }
            if ($taxed !== []) {
                $report('ES-VF-15', '1238', $exemptionElement, 'The line gives '.implode(', ', $taxed).'.');
            }
        }

        // ES-VF-16
        if ($qualification === 'S2') {
            $missing = [];
            foreach (['TipoImpositivo' => [$rateElement, $rate], 'CuotaRepercutida' => [$quotaElement, $quota]] as $name => [$element, $value]) {
                if ($element === null || self::text($element) === '') {
                    $missing[] = $name;
                } elseif ($value !== null && ! $value->isZero()) {
                    $report('ES-VF-16', '1198', $element, "{$name} is {$value}.");
                }
            }
            if ($missing !== []) {
                $report('ES-VF-16', '1198', $line, implode(' and ', $missing).' missing or empty.');
            }
        }

        // ES-VF-17
        if ($quotaElement !== null && $quota !== null && ! $quota->isZero() && $qualification !== 'S1') {
            $report('ES-VF-17', '1207', $quotaElement, 'CalificacionOperacion is '.($qualification ?? 'absent').'.');
        }

        // ES-VF-18 and ES-VF-19
        if ($qualification === 'S1') {
            $costBase = self::first($xpath, 'sf:BaseImponibleACoste', $line);
            if ($costBase === null && ($rateElement === null || $quotaElement === null)) {
                $missing = array_keys(array_filter(['TipoImpositivo' => $rateElement, 'CuotaRepercutida' => $quotaElement], static fn (?DOMElement $element): bool => $element === null));
                $report('ES-VF-18', '1208', $line, implode(' and ', $missing).' missing.');
            }

            $base = self::decimal($costBase ?? self::first($xpath, 'sf:BaseImponibleOimporteNoSujeto', $line));
            if ($checksQuota && $quotaElement !== null && $quota !== null && $rate !== null && $base !== null) {
                $expected = $base->multipliedBy($rate)->dividedBy(100, 6, RoundingMode::HalfUp);
                if ($quota->getSign() * $base->getSign() < 0) {
                    $report('ES-VF-19', '1143', $quotaElement, "CuotaRepercutida is {$quota} and the base {$base}.");
                } elseif ($quota->minus($expected)->abs()->isGreaterThan(self::TOLERANCE)) {
                    $report('ES-VF-19', '1142', $quotaElement, "CuotaRepercutida is {$quota}; {$base} x {$rate} / 100 is {$expected->toScale(2, RoundingMode::HalfUp)}.");
                }
            }
        }

        return $findings;
    }

    /**
     * The message of each rule.
     *
     * @return array<string, string>
     */
    private static function messages(): array
    {
        return [
            'ES-VF-01' => 'The issue date (FechaExpedicionFactura) must not be earlier than 28 October 2024, when the VERI*FACTU order entered into force.',
            'ES-VF-02' => 'RechazoPrevio X or S is only allowed together with Subsanacion S.',
            'ES-VF-03' => 'TipoRectificativa is required on corrective invoices (R1 to R5) and not allowed on any other TipoFactura.',
            'ES-VF-04' => 'FacturasRectificadas is only allowed on corrective invoices (R1 to R5).',
            'ES-VF-05' => 'FacturasSustituidas is only allowed when TipoFactura is F3.',
            'ES-VF-06' => 'ImporteRectificacion is required when TipoRectificativa is S (substitution) and not allowed otherwise.',
            'ES-VF-07' => 'FacturaSimplificadaArt7273 S is only allowed when TipoFactura is F1, F3, R1, R2, R3 or R4.',
            'ES-VF-08' => 'FacturaSinIdentifDestinatarioArt61d S is only allowed when TipoFactura is F2 or R5.',
            'ES-VF-09' => 'Macrodato must be S exactly when the absolute ImporteTotal is 100,000,000.00 or more.',
            'ES-VF-10' => 'The Tercero block is required when EmitidaPorTerceroODestinatario is T and allowed only then.',
            'ES-VF-11' => 'Destinatarios is required when TipoFactura is F1, F3, R1, R2, R3 or R4 and not allowed when it is F2 or R5.',
            'ES-VF-12' => 'The NIF of the third party (Tercero) and of every recipient must differ from the issuer (IDEmisorFactura).',
            'ES-VF-13' => 'ClaveRegimen is required for IVA (also when Impuesto is absent), IPSI and IGIC and must be a key admitted for that tax; it is not allowed for other taxes (05).',
            'ES-VF-14' => 'With IVA or IGIC and ClaveRegimen 01, OperacionExenta must not be E2 or E3.',
            'ES-VF-15' => 'An exempt line (OperacionExenta) must not carry TipoImpositivo, CuotaRepercutida, TipoRecargoEquivalencia or CuotaRecargoEquivalencia.',
            'ES-VF-16' => 'On a reverse-charge line (CalificacionOperacion S2), TipoImpositivo and CuotaRepercutida must be given and be 0.',
            'ES-VF-17' => 'CuotaRepercutida may differ from 0 only when CalificacionOperacion is S1.',
            'ES-VF-18' => 'A line with CalificacionOperacion S1 and without BaseImponibleACoste needs TipoImpositivo and CuotaRepercutida.',
            'ES-VF-19' => 'On a line with CalificacionOperacion S1, CuotaRepercutida must have the sign of the base and equal base x TipoImpositivo / 100 within 10.00 euros (not checked when TipoRectificativa is I or TipoFactura is R2 or R3).',
            'ES-VF-20' => 'On a simplified invoice (F2), the sum of BaseImponibleOimporteNoSujeto and CuotaRepercutida over all lines must not exceed 3,000.00 euros plus a 10.00 euro margin, unless NumRegistroAcuerdoFacturacion is given or FacturaSinIdentifDestinatarioArt61d is S.',
            'ES-VF-21' => 'CuotaTotal should equal the sum of CuotaRepercutida and CuotaRecargoEquivalencia over all lines within 10.00 euros (not checked when a line has ClaveRegimen 03, 05, 06, 08 or 09).',
            'ES-VF-22' => 'ImporteTotal should equal the sum of BaseImponibleOimporteNoSujeto, CuotaRepercutida and CuotaRecargoEquivalencia over all lines within 10.00 euros (not checked when a line has ClaveRegimen 03, 05, 06, 08 or 09).',
            'ES-VF-23' => 'The Huella should be the SHA-256 fingerprint of the record as the AEAT hash specification v0.1.2 defines it.',
            'ES-VF-24' => 'The previous record\'s Huella (RegistroAnterior) should be 64 upper-case hexadecimal characters and differ from the record\'s own Huella.',
        ];
    }

    private static function finding(string $id, string $code, DOMNode $node, string $detail): Finding
    {
        $rule = self::rules()[$id];

        return new Finding(
            $id,
            $rule['severity'],
            LocationPath::of($node, self::NAMESPACES),
            self::messages()[$id].($detail === '' ? '' : ' '.$detail),
            self::DOCUMENT.$rule['source'].'; AEAT error '.$code,
        );
    }

    private static function first(DOMXPath $xpath, string $expression, DOMNode $context): ?DOMElement
    {
        return self::all($xpath, $expression, $context)[0] ?? null;
    }

    /**
     * @return list<DOMElement>
     */
    private static function all(DOMXPath $xpath, string $expression, ?DOMNode $context = null): array
    {
        $nodes = $xpath->query($expression, $context);
        $elements = [];
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $elements[] = $node;
                }
            }
        }

        return $elements;
    }

    /**
     * The sum of the named children over all lines, a missing child counting as zero; null when
     * one of them is not a decimal.
     *
     * @param  list<DOMElement>  $lines
     * @param  list<string>  $names  child expressions such as "sf:CuotaRepercutida"
     */
    private static function sum(DOMXPath $xpath, array $lines, array $names): ?BigDecimal
    {
        $sum = BigDecimal::zero();
        foreach ($lines as $line) {
            foreach ($names as $name) {
                $element = self::first($xpath, $name, $line);
                if ($element === null) {
                    continue;
                }
                $value = self::decimal($element);
                if ($value === null) {
                    return null;
                }
                $sum = $sum->plus($value);
            }
        }

        return $sum;
    }

    private static function text(?DOMElement $element): ?string
    {
        return $element === null ? null : trim($element->textContent);
    }

    /**
     * The element's dd-mm-yyyy date; null when the element is absent or holds no such date.
     */
    private static function date(?DOMElement $element): ?DateTimeImmutable
    {
        $text = self::text($element);
        if ($text === null || preg_match('/\A\d{2}-\d{2}-\d{4}\z/', $text) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', $text);

        return $date !== false && $date->format('d-m-Y') === $text ? $date : null;
    }

    /**
     * The element's amount or rate as a decimal ("+" sign and a trailing "." admitted, as the
     * schema does); null when the element is absent or not a decimal.
     */
    private static function decimal(?DOMElement $element): ?BigDecimal
    {
        $text = self::text($element);
        if ($text === null || preg_match('/\A([+-]?)(\d+)(?:\.(\d*))?\z/', $text, $parts) !== 1) {
            return null;
        }
        try {
            return BigDecimal::of(($parts[1] === '-' ? '-' : '').$parts[2].(($parts[3] ?? '') === '' ? '' : '.'.$parts[3]));
        } catch (MathException) {
            return null;
        }
    }
}
