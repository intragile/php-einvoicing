<?php

declare(strict_types=1);

// Negative cases for VerifactuRules; the format is documented on VerifactuRulesTest. Every edited
// record stays valid against the VERI*FACTU schema.

$alta = '/sf:RegistroAlta';
$line = "{$alta}/sf:Desglose/sf:DetalleDesglose";
$anulacion = '/sf:RegistroAnulacion';
$domestic = 'record-1064493.xml';
$exempt = 'record-1064500.xml';
$correction = 'record-1064501.xml';
$recipients = "{$alta}/sf:Destinatarios";

$case = static fn (string $rule, string $base, array $edits): array => [
    'rule' => $rule,
    'base' => $base,
    'edits' => $edits,
];

return [
    // Issued before VERI*FACTU entered into force.
    $case('ES-VF-01', $domestic, [
        ["{$alta}/sf:IDFactura/sf:FechaExpedicionFactura", 'set', '27-10-2024'],
    ]),
    // RechazoPrevio X without Subsanacion S (1153).
    $case('ES-VF-02', $domestic, [
        ["{$alta}/sf:TipoFactura", 'before', '<sf:RechazoPrevio>X</sf:RechazoPrevio>'],
    ]),
    // RechazoPrevio S with Subsanacion N (1161).
    $case('ES-VF-02', $domestic, [
        ["{$alta}/sf:TipoFactura", 'before', '<sf:Subsanacion>N</sf:Subsanacion><sf:RechazoPrevio>S</sf:RechazoPrevio>'],
    ]),
    // A corrective invoice without TipoRectificativa (1114).
    $case('ES-VF-03', $correction, [
        ["{$alta}/sf:TipoRectificativa", 'remove'],
    ]),
    // A plain invoice with TipoRectificativa (1115).
    $case('ES-VF-03', $domestic, [
        ["{$alta}/sf:DescripcionOperacion", 'before', '<sf:TipoRectificativa>I</sf:TipoRectificativa>'],
    ]),
    // A plain invoice listing corrected invoices.
    $case('ES-VF-04', $correction, [
        ["{$alta}/sf:TipoFactura", 'set', 'F1'],
        ["{$alta}/sf:TipoRectificativa", 'remove'],
    ]),
    // An F1 listing substituted invoices.
    $case('ES-VF-05', $domestic, [
        ["{$alta}/sf:DescripcionOperacion", 'before', '<sf:FacturasSustituidas><sf:IDFacturaSustituida>'
            .'<sf:IDEmisorFactura>B64829179</sf:IDEmisorFactura><sf:NumSerieFactura>PROBE-ES-0001</sf:NumSerieFactura>'
            .'<sf:FechaExpedicionFactura>23-11-2026</sf:FechaExpedicionFactura></sf:IDFacturaSustituida></sf:FacturasSustituidas>'],
    ]),
    // A correction by substitution without ImporteRectificacion (1118).
    $case('ES-VF-06', $correction, [
        ["{$alta}/sf:TipoRectificativa", 'set', 'S'],
    ]),
    // A correction by difference with ImporteRectificacion (1119).
    $case('ES-VF-06', $correction, [
        ["{$alta}/sf:DescripcionOperacion", 'before', '<sf:ImporteRectificacion><sf:BaseRectificada>0</sf:BaseRectificada>'
            .'<sf:CuotaRectificada>0</sf:CuotaRectificada></sf:ImporteRectificacion>'],
    ]),
    // A simplified F2 marked as an art. 72-73 invoice.
    $case('ES-VF-07', $domestic, [
        ["{$alta}/sf:TipoFactura", 'set', 'F2'],
        [$recipients, 'remove'],
        ["{$alta}/sf:Desglose", 'before', '<sf:FacturaSimplificadaArt7273>S</sf:FacturaSimplificadaArt7273>'],
    ]),
    // An F1 marked as issued without identifying the recipient (art. 61d).
    $case('ES-VF-08', $domestic, [
        [$recipients, 'before', '<sf:FacturaSinIdentifDestinatarioArt61d>S</sf:FacturaSinIdentifDestinatarioArt61d>'],
    ]),
    // Macrodato S on a small invoice (1138).
    $case('ES-VF-09', $domestic, [
        [$recipients, 'before', '<sf:Macrodato>S</sf:Macrodato>'],
    ]),
    // An invoice of 121 million without Macrodato (1139).
    $case('ES-VF-09', $domestic, [
        ["{$line}/sf:BaseImponibleOimporteNoSujeto", 'set', '100000000.00'],
        ["{$line}/sf:CuotaRepercutida", 'set', '21000000.00'],
        ["{$alta}/sf:CuotaTotal", 'set', '21000000.00'],
        ["{$alta}/sf:ImporteTotal", 'set', '121000000.00'],
    ]),
    // Issued by a third party without the Tercero block (1186).
    $case('ES-VF-10', $domestic, [
        [$recipients, 'before', '<sf:EmitidaPorTerceroODestinatario>T</sf:EmitidaPorTerceroODestinatario>'],
    ]),
    // A Tercero block without EmitidaPorTerceroODestinatario T (1187).
    $case('ES-VF-10', $domestic, [
        [$recipients, 'before', '<sf:Tercero><sf:NombreRazon>Third Party S.L.</sf:NombreRazon><sf:NIF>B63276174</sf:NIF></sf:Tercero>'],
    ]),
    // An F1 without recipients (1189).
    $case('ES-VF-11', $domestic, [
        [$recipients, 'remove'],
    ]),
    // A simplified F2 with recipients (1190).
    $case('ES-VF-11', $domestic, [
        ["{$alta}/sf:TipoFactura", 'set', 'F2'],
    ]),
    // The third party is the issuer (1188).
    $case('ES-VF-12', $domestic, [
        [$recipients, 'before', '<sf:EmitidaPorTerceroODestinatario>T</sf:EmitidaPorTerceroODestinatario>'
            .'<sf:Tercero><sf:NombreRazon>Same Issuer S.L.</sf:NombreRazon><sf:NIF>B64829179</sf:NIF></sf:Tercero>'],
    ]),
    // The recipient is the issuer (1193).
    $case('ES-VF-12', $domestic, [
        ["{$recipients}/sf:IDDestinatario/sf:NIF", 'set', 'B64829179'],
    ]),
    // An IVA line without ClaveRegimen (1245).
    $case('ES-VF-13', $domestic, [
        ["{$line}/sf:ClaveRegimen", 'remove'],
    ]),
    // An IVA line with the IGIC-only key 21 (1246).
    $case('ES-VF-13', $domestic, [
        ["{$line}/sf:ClaveRegimen", 'set', '21'],
    ]),
    // A line of another tax (05) with ClaveRegimen (1260).
    $case('ES-VF-13', $domestic, [
        ["{$line}/sf:Impuesto", 'set', '05'],
    ]),
    // General regime with the export exemption E2.
    $case('ES-VF-14', $exempt, [
        ["{$line}/sf:OperacionExenta", 'set', 'E2'],
    ]),
    // An exempt line with a tax rate.
    $case('ES-VF-15', $exempt, [
        ["{$line}/sf:BaseImponibleOimporteNoSujeto", 'before', '<sf:TipoImpositivo>0</sf:TipoImpositivo>'],
    ]),
    // A reverse-charge (S2) line with a non-zero rate.
    $case('ES-VF-16', $domestic, [
        ["{$line}/sf:CalificacionOperacion", 'set', 'S2'],
        ["{$line}/sf:CuotaRepercutida", 'set', '0'],
        ["{$alta}/sf:CuotaTotal", 'set', '0'],
        ["{$alta}/sf:ImporteTotal", 'set', '100.0'],
    ]),
    // A reverse-charge (S2) line without TipoImpositivo and CuotaRepercutida.
    $case('ES-VF-16', $domestic, [
        ["{$line}/sf:CalificacionOperacion", 'set', 'S2'],
        ["{$line}/sf:TipoImpositivo", 'remove'],
        ["{$line}/sf:CuotaRepercutida", 'remove'],
        ["{$alta}/sf:CuotaTotal", 'set', '0'],
        ["{$alta}/sf:ImporteTotal", 'set', '100.0'],
    ]),
    // A non-subject (N1) line with a quota.
    $case('ES-VF-17', $domestic, [
        ["{$line}/sf:CalificacionOperacion", 'set', 'N1'],
    ]),
    // An S1 line without a tax rate.
    $case('ES-VF-18', $domestic, [
        ["{$line}/sf:TipoImpositivo", 'remove'],
    ]),
    // Quota off by 10.01 (1142).
    $case('ES-VF-19', $domestic, [
        ["{$line}/sf:CuotaRepercutida", 'set', '31.01'],
        ["{$alta}/sf:CuotaTotal", 'set', '31.01'],
        ["{$alta}/sf:ImporteTotal", 'set', '131.01'],
    ]),
    // Quota and base of opposite signs (1143).
    $case('ES-VF-19', $domestic, [
        ["{$line}/sf:BaseImponibleOimporteNoSujeto", 'set', '-100.0'],
        ["{$alta}/sf:ImporteTotal", 'set', '-79.0'],
    ]),
    // A simplified invoice above 3,000 euros plus the 10 euro margin.
    $case('ES-VF-20', $domestic, [
        ["{$alta}/sf:TipoFactura", 'set', 'F2'],
        [$recipients, 'remove'],
        ["{$line}/sf:BaseImponibleOimporteNoSujeto", 'set', '2500.00'],
        ["{$line}/sf:CuotaRepercutida", 'set', '525.00'],
        ["{$alta}/sf:CuotaTotal", 'set', '525.00'],
        ["{$alta}/sf:ImporteTotal", 'set', '3025.00'],
    ]),
    // CuotaTotal 10.01 above the line quotas.
    $case('ES-VF-21', $domestic, [
        ["{$alta}/sf:CuotaTotal", 'set', '31.01'],
    ]),
    // ImporteTotal 10.01 above the line amounts.
    $case('ES-VF-22', $domestic, [
        ["{$alta}/sf:ImporteTotal", 'set', '131.01'],
    ]),
    // One hex digit of the Huella changed.
    $case('ES-VF-23', $domestic, [
        ["{$alta}/sf:Huella", 'set', 'D60F9FD2DDDD19313359612C52222CC74E0B7C6E3C0D64AFBC797F2CE54EF813'],
    ]),
    // A cancellation whose Huella is not its fingerprint.
    $case('ES-VF-23', 'anulacion', [
        ["{$anulacion}/sf:Huella", 'set', 'not-the-fingerprint'],
    ]),
    // A previous Huella in lower case (2003).
    $case('ES-VF-24', $domestic, [
        ["{$alta}/sf:Encadenamiento/sf:RegistroAnterior/sf:Huella", 'set', '1e823150439412ab8a572fbfd66d676ca366074f933949010ec70fddc1d5fc73'],
    ]),
    // A previous Huella of the wrong length (2002).
    $case('ES-VF-24', 'anulacion', [
        ["{$anulacion}/sf:Encadenamiento/sf:RegistroAnterior/sf:Huella", 'set', 'ABC123'],
    ]),
];
