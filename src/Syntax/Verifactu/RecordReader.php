<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Verifactu;

use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\CodeList\CountryCode;
use IntrAgile\EInvoicing\Exception\UnsafeXml;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Spain\CorrectionType;
use IntrAgile\EInvoicing\Spain\ExemptOperation;
use IntrAgile\EInvoicing\Spain\ForeignIdType;
use IntrAgile\EInvoicing\Spain\GeneratedBy;
use IntrAgile\EInvoicing\Spain\InvoiceType;
use IntrAgile\EInvoicing\Spain\IssuedBy;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\OperationQualification;
use IntrAgile\EInvoicing\Spain\RegimeKey;
use IntrAgile\EInvoicing\Spain\TaxType;
use IntrAgile\EInvoicing\Spain\Verifactu\AltaOperation;
use IntrAgile\EInvoicing\Spain\Verifactu\Amount;
use IntrAgile\EInvoicing\Spain\Verifactu\AnulacionOperation;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\ComputerSystem;
use IntrAgile\EInvoicing\Spain\Verifactu\CorrectionAmounts;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\ForeignId;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;
use IntrAgile\EInvoicing\Spain\Verifactu\RecordTimestamp;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationDraft;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxBreakdownLine;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxParty;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxRate;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use InvalidArgumentException;
use RuntimeException;

/**
 * Reads a bare VERI*FACTU RegistroAlta or RegistroAnulacion (SuministroInformacion.xsd,
 * tikeV1.0) back into a billing record; the inverse of RecordWriter.
 *
 * The literal text of every value is kept: amounts and rates through Amount::fromLiteral() and
 * TaxRate::fromLiteral(), the generation timestamp through RecordTimestamp::fromLiteral(), so a
 * record written by other software ("100.0", "41.4", "21.00") hashes and writes back exactly as
 * it was read. Every value is trimmed first. The record keeps the Huella it carries, even when it
 * does not match the fingerprint recomputed from its fields (see Fingerprint::recompute()).
 * AEAT registers records with a miscomputed Huella or a previous Huella of the wrong length or
 * content with errors (errores.properties 2000, 2002, 2003), so their content is not checked:
 * the record's own Huella is kept verbatim when it has exactly 64 characters (a different length
 * is rejected by AEAT, error 1262), the previous Huella when it has 1 to 64 characters.
 *
 * Flags of "N" and absent flags are the same; the Subsanacion/RechazoPrevio and
 * SinRegistroPrevio/RechazoPrevio pairs must be admitted by the operation tables of
 * DsRegistroVeriFactu.xlsx. The optional ds:Signature is skipped.
 *
 * A document that is not such a record, carries an element the schema does not define at its
 * place, or lacks a mandatory element is unsupported (UnsupportedDocument). A value the
 * specification refuses (a NIF with a wrong check character, an amount outside the AEAT pattern,
 * an unknown code, a date that is not dd-mm-yyyy, an inadmissible flag pair) is an InvalidRecord
 * that names the element's path where it has one.
 */
final class RecordReader
{
    /**
     * @throws UnsafeXml when the document carries a DOCTYPE declaration or declares entities
     * @throws UnsupportedDocument when the document is not a VERI*FACTU record
     * @throws InvalidRecord when a value is refused by the specification
     */
    public function read(string $xml): BillingRecord
    {
        return $this->map(new VerifactuDocument(SafeXml::load($xml)));
    }

    /**
     * Reads a record element of a loaded document, e.g. the RegistroAlta under a
     * sum:RegistroFactura of a submission. The element is copied; its document is not changed.
     *
     * The caller's DOM is trusted as given: no DOCTYPE or entity check is made here. A document
     * from a SOAP response, B2Brouter or any other external source must be loaded through
     * {@see SafeXml::load()} before its elements are passed in.
     *
     * @throws UnsupportedDocument when the element is not a VERI*FACTU record
     * @throws InvalidRecord when a value is refused by the specification
     */
    public function readElement(DOMElement $element): BillingRecord
    {
        $document = new DOMDocument;
        $copy = $document->importNode($element, true);
        if (! $copy instanceof DOMElement) {
            throw new RuntimeException('The record element could not be copied.');
        }
        $document->appendChild($copy);

        return $this->map(new VerifactuDocument($document));
    }

    private function map(VerifactuDocument $document): BillingRecord
    {
        $root = $document->root;

        $version = $document->requiredValue('IDVersion', $root);
        if ($version !== VerifactuFormat::ID_VERSION) {
            throw new UnsupportedDocument(sprintf("Unsupported IDVersion '%s'; expected %s.", $version, VerifactuFormat::ID_VERSION));
        }
        $document->requiredParsed('TipoHuella', $root, static fn (string $value): string => $value === VerifactuFormat::HASH_TYPE
            ? $value
            : throw new InvalidArgumentException(sprintf("unsupported hash type '%s'; expected %s (SHA-256).", $value, VerifactuFormat::HASH_TYPE)));

        $link = self::chainLink($document, $document->requiredChild('Encadenamiento', $root));
        $system = self::system($document, $document->requiredChild('SistemaInformatico', $root));
        $at = $document->requiredParsed('FechaHoraHusoGenRegistro', $root, RecordTimestamp::fromLiteral(...));
        $fingerprint = self::huella($document, $root, static function (string $value): string {
            if (mb_strlen($value) !== 64) {
                throw new InvalidArgumentException('a Huella must be 64 characters (AEAT error 1262).');
            }

            return $value;
        });

        return $document->isRegistration()
            ? RegistrationRecord::restore(self::registration($document, $root), $link, $system, $at, $fingerprint)
            : CancellationRecord::restore(self::cancellation($document, $root), $link, $system, $at, $fingerprint);
    }

    private static function registration(VerifactuDocument $document, DOMElement $root): RegistrationDraft
    {
        $corrected = [];
        $list = $document->child('FacturasRectificadas', $root);
        foreach ($list === null ? [] : $document->children('IDFacturaRectificada', $list) as $id) {
            $corrected[] = self::invoiceId($document, $id);
        }
        $substituted = [];
        $list = $document->child('FacturasSustituidas', $root);
        foreach ($list === null ? [] : $document->children('IDFacturaSustituida', $list) as $id) {
            $substituted[] = self::invoiceId($document, $id);
        }
        $recipients = [];
        $list = $document->child('Destinatarios', $root);
        foreach ($list === null ? [] : $document->children('IDDestinatario', $list) as $recipient) {
            $recipients[] = self::party($document, $recipient);
        }
        $breakdown = [];
        foreach ($document->children('DetalleDesglose', $document->requiredChild('Desglose', $root)) as $detail) {
            $breakdown[] = self::breakdownLine($document, $detail);
        }
        $correction = $document->child('ImporteRectificacion', $root);
        $thirdParty = $document->child('Tercero', $root);

        return new RegistrationDraft(
            id: self::invoiceId($document, $document->requiredChild('IDFactura', $root)),
            issuerName: $document->requiredValue('NombreRazonEmisor', $root),
            type: $document->requiredCodeValue(InvoiceType::class, 'TipoFactura', $root),
            correctionType: $document->codeValue(CorrectionType::class, 'TipoRectificativa', $root),
            corrected: $corrected,
            substituted: $substituted,
            correctionAmounts: $correction === null ? null : new CorrectionAmounts(
                $document->requiredParsed('BaseRectificada', $correction, Amount::fromLiteral(...)),
                $document->requiredParsed('CuotaRectificada', $correction, Amount::fromLiteral(...)),
                $document->parsed('CuotaRecargoRectificado', $correction, Amount::fromLiteral(...)),
            ),
            operationDate: $document->dateValue('FechaOperacion', $root),
            description: $document->requiredValue('DescripcionOperacion', $root),
            simplifiedArt7273: $document->flagValue('FacturaSimplificadaArt7273', $root),
            withoutRecipientArt61d: $document->flagValue('FacturaSinIdentifDestinatarioArt61d', $root),
            macrodato: $document->flagValue('Macrodato', $root),
            issuedBy: $document->codeValue(IssuedBy::class, 'EmitidaPorTerceroODestinatario', $root),
            thirdParty: $thirdParty === null ? null : self::party($document, $thirdParty),
            recipients: $recipients,
            coupon: $document->flagValue('Cupon', $root),
            breakdown: $breakdown,
            totalTax: $document->requiredParsed('CuotaTotal', $root, Amount::fromLiteral(...)),
            total: $document->requiredParsed('ImporteTotal', $root, Amount::fromLiteral(...)),
            externalReference: $document->value('RefExterna', $root),
            agreementNumber: $document->value('NumRegistroAcuerdoFacturacion', $root),
            systemAgreementId: $document->value('IdAcuerdoSistemaInformatico', $root),
            operation: AltaOperation::fromFlags(
                $document->value('Subsanacion', $root),
                $document->value('RechazoPrevio', $root),
            ),
        );
    }

    private static function cancellation(VerifactuDocument $document, DOMElement $root): CancellationDraft
    {
        $generator = $document->child('Generador', $root);

        return CancellationDraft::of(
            annulled: self::invoiceId($document, $document->requiredChild('IDFactura', $root), [
                'IDEmisorFacturaAnulada',
                'NumSerieFacturaAnulada',
                'FechaExpedicionFacturaAnulada',
            ]),
            operation: AnulacionOperation::fromFlags(
                $document->value('SinRegistroPrevio', $root),
                $document->value('RechazoPrevio', $root),
            ),
            generatedBy: $document->codeValue(GeneratedBy::class, 'GeneradoPor', $root),
            generator: $generator === null ? null : self::party($document, $generator),
            externalReference: $document->value('RefExterna', $root),
        );
    }

    /**
     * @param  array{string, string, string}  $names  issuer, number and date element names
     */
    private static function invoiceId(
        VerifactuDocument $document,
        DOMElement $parent,
        array $names = ['IDEmisorFactura', 'NumSerieFactura', 'FechaExpedicionFactura'],
    ): InvoiceId {
        $issuer = $document->requiredParsed($names[0], $parent, Nif::parse(...));
        $date = $document->requiredDateValue($names[2], $parent);

        return $document->requiredParsed($names[1], $parent, static fn (string $number): InvoiceId => new InvoiceId($issuer, $number, $date));
    }

    private static function chainLink(VerifactuDocument $document, DOMElement $chain): ChainLink
    {
        $previous = $document->child('RegistroAnterior', $chain);
        if ($previous === null) {
            $document->requiredParsed('PrimerRegistro', $chain, static fn (string $value): string => $value === 'S'
                ? $value
                : throw new InvalidArgumentException(sprintf("invalid value '%s'; expected S.", $value)));

            return ChainLink::first();
        }
        if ($document->child('PrimerRegistro', $chain) !== null) {
            throw new UnsupportedDocument(sprintf('%s carries both PrimerRegistro and RegistroAnterior.', $document->path($chain)));
        }

        $id = self::invoiceId($document, $previous);

        return self::huella($document, $previous, static fn (string $value): ChainLink => ChainLink::restore($id, $value));
    }

    private static function system(VerifactuDocument $document, DOMElement $system): ComputerSystem
    {
        [$nif, $foreignId] = self::identity($document, $system);

        return new ComputerSystem(
            $document->value('NombreRazon', $system) ?? '',
            $nif,
            $foreignId,
            $document->value('NombreSistemaInformatico', $system) ?? '',
            $document->requiredValue('IdSistemaInformatico', $system),
            $document->value('Version', $system) ?? '',
            $document->value('NumeroInstalacion', $system) ?? '',
            $document->requiredYesNo('TipoUsoPosibleSoloVerifactu', $system),
            $document->requiredYesNo('TipoUsoPosibleMultiOT', $system),
            $document->requiredYesNo('IndicadorMultiplesOT', $system),
        );
    }

    private static function party(VerifactuDocument $document, DOMElement $party): TaxParty
    {
        [$nif, $foreignId] = self::identity($document, $party);

        try {
            return new TaxParty($document->value('NombreRazon', $party) ?? '', $nif, $foreignId);
        } catch (InvalidRecord $e) {
            throw new InvalidRecord(sprintf('%s: %s', $document->path($party), $e->getMessage()), 0, $e);
        }
    }

    /**
     * @return array{?Nif, ?ForeignId}
     */
    private static function identity(VerifactuDocument $document, DOMElement $parent): array
    {
        $nif = $document->parsed('NIF', $parent, Nif::parse(...));
        $other = $document->child('IDOtro', $parent);
        if ($other === null) {
            return [$nif, null];
        }

        $type = $document->requiredCodeValue(ForeignIdType::class, 'IDType', $other);
        $country = $document->codeValue(CountryCode::class, 'CodigoPais', $other);

        return [$nif, $document->requiredParsed('ID', $other, static fn (string $id): ForeignId => new ForeignId($type, $id, $country))];
    }

    private static function breakdownLine(VerifactuDocument $document, DOMElement $detail): TaxBreakdownLine
    {
        $tax = $document->codeValue(TaxType::class, 'Impuesto', $detail);
        $regime = $document->codeValue(RegimeKey::class, 'ClaveRegimen', $detail);
        $qualification = $document->codeValue(OperationQualification::class, 'CalificacionOperacion', $detail);
        $exemption = $document->codeValue(ExemptOperation::class, 'OperacionExenta', $detail);
        $rate = $document->parsed('TipoImpositivo', $detail, TaxRate::fromLiteral(...));
        $base = $document->requiredParsed('BaseImponibleOimporteNoSujeto', $detail, Amount::fromLiteral(...));
        $costBase = $document->parsed('BaseImponibleACoste', $detail, Amount::fromLiteral(...));
        $quota = $document->parsed('CuotaRepercutida', $detail, Amount::fromLiteral(...));
        $equivalenceRate = $document->parsed('TipoRecargoEquivalencia', $detail, TaxRate::fromLiteral(...));
        $equivalenceQuota = $document->parsed('CuotaRecargoEquivalencia', $detail, Amount::fromLiteral(...));

        try {
            return new TaxBreakdownLine($tax, $regime, $qualification, $exemption, $rate, $base, $costBase, $quota, $equivalenceRate, $equivalenceQuota);
        } catch (InvalidRecord $e) {
            throw new InvalidRecord(sprintf('%s: %s', $document->path($detail), $e->getMessage()), 0, $e);
        }
    }

    /**
     * Converts the Huella under $parent; a present but blank Huella is converted as the empty
     * text, so the conversion refuses it naming the path.
     *
     * @template T
     *
     * @param  callable(string): T  $convert
     * @return T
     *
     * @throws UnsupportedDocument when the element is missing
     * @throws InvalidRecord naming the path when the conversion refuses the text
     */
    private static function huella(VerifactuDocument $document, DOMElement $parent, callable $convert): mixed
    {
        $element = $document->requiredChild('Huella', $parent);
        $value = trim($element->textContent);

        try {
            return $convert($value);
        } catch (InvalidArgumentException $e) {
            throw new InvalidRecord(sprintf('%s: %s', $document->path($element), $e->getMessage()), 0, $e);
        }
    }
}
