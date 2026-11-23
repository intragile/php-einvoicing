<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Verifactu;

use DOMElement;
use IntrAgile\EInvoicing\Spain\Verifactu\BillingRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\CancellationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\ChainLink;
use IntrAgile\EInvoicing\Spain\Verifactu\ComputerSystem;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\RegistrationRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxBreakdownLine;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;
use InvalidArgumentException;
use RuntimeException;

/**
 * Writes a VERI*FACTU billing record as a bare sum1:RegistroAlta or sum1:RegistroAnulacion.
 *
 * Elements follow the sequence of SuministroInformacion.xsd (tikeV1.0) from IDVersion to Huella;
 * the optional ds:Signature is never written. Amounts, rates, dates and the generation timestamp
 * are written from their kept text, so the fingerprint input rebuilt from the document
 * (Fingerprint::inputOf()) equals the one the record was hashed with.
 *
 * Flags are written only when they deviate from the default: Subsanacion, RechazoPrevio and
 * SinRegistroPrevio as the operation states them (an absent flag means N), and Macrodato,
 * Cupon, FacturaSimplificadaArt7273 and FacturaSinIdentifDestinatarioArt61d only as S. The three
 * usage flags of SistemaInformatico are mandatory and always written. Optional texts that are
 * empty after trimming are left out.
 *
 * With installed schemas, the written record is validated against SuministroInformacion.xsd and
 * any schema error is thrown; without them nothing is checked. The AEAT business rules are not
 * run here.
 */
final class RecordWriter
{
    public function __construct(private readonly ?VerifactuSchemas $schemas = null) {}

    /**
     * @throws InvalidRecord when the record holds text XML cannot carry or, with schemas, the
     *                       written record fails SuministroInformacion.xsd
     */
    public function write(BillingRecord $record): string
    {
        return $this->build($record)->checkedXml(
            $this->schemas,
            static fn (VerifactuSchemas $schemas): string => $schemas->informationXsd(),
        );
    }

    /**
     * Writes the record and appends it to $parent (in its document), e.g. under a
     * sum:RegistroFactura of a submission.
     *
     * @return DOMElement the appended record element
     *
     * @throws InvalidRecord as write()
     */
    public function appendTo(DOMElement $parent, BillingRecord $record): DOMElement
    {
        $output = $this->build($record);
        $output->checkedXml($this->schemas, static fn (VerifactuSchemas $schemas): string => $schemas->informationXsd());

        $document = $parent->ownerDocument ?? throw new RuntimeException('The parent element does not belong to a document.');
        $element = $document->importNode($output->root, true);
        if (! $element instanceof DOMElement) {
            throw new RuntimeException('The record element could not be imported.');
        }
        $parent->appendChild($element);

        return $element;
    }

    private function build(BillingRecord $record): VerifactuOutput
    {
        try {
            return match (true) {
                $record instanceof RegistrationRecord => $this->registration($record),
                $record instanceof CancellationRecord => $this->cancellation($record),
                default => throw new InvalidRecord(sprintf('Unsupported billing record %s.', $record::class)),
            };
        } catch (InvalidRecord $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            // Text XML 1.0 cannot carry (see XmlOutput::text()).
            throw new InvalidRecord($e->getMessage(), 0, $e);
        }
    }

    private function registration(RegistrationRecord $record): VerifactuOutput
    {
        $draft = $record->draft;
        $output = new VerifactuOutput('sum1:RegistroAlta');
        $root = $output->root;

        $output->text($root, 'sum1:IDVersion', VerifactuFormat::ID_VERSION);
        $output->invoiceIdFields($output->add($root, 'sum1:IDFactura'), $draft->id);
        $output->optional($root, 'sum1:RefExterna', $draft->externalReference);
        $output->text($root, 'sum1:NombreRazonEmisor', $draft->issuerName);
        $output->text($root, 'sum1:Subsanacion', $draft->operation->subsanacion());
        $output->text($root, 'sum1:RechazoPrevio', $draft->operation->rechazoPrevio());
        $output->text($root, 'sum1:TipoFactura', $draft->type->value);
        $output->text($root, 'sum1:TipoRectificativa', $draft->correctionType?->value);
        if ($draft->corrected !== []) {
            $list = $output->add($root, 'sum1:FacturasRectificadas');
            foreach ($draft->corrected as $id) {
                $output->invoiceIdFields($output->add($list, 'sum1:IDFacturaRectificada'), $id);
            }
        }
        if ($draft->substituted !== []) {
            $list = $output->add($root, 'sum1:FacturasSustituidas');
            foreach ($draft->substituted as $id) {
                $output->invoiceIdFields($output->add($list, 'sum1:IDFacturaSustituida'), $id);
            }
        }
        if ($draft->correctionAmounts !== null) {
            $amounts = $output->add($root, 'sum1:ImporteRectificacion');
            $output->text($amounts, 'sum1:BaseRectificada', $draft->correctionAmounts->base->text);
            $output->text($amounts, 'sum1:CuotaRectificada', $draft->correctionAmounts->quota->text);
            $output->text($amounts, 'sum1:CuotaRecargoRectificado', $draft->correctionAmounts->equivalenceQuota?->text);
        }
        $output->date($root, 'sum1:FechaOperacion', $draft->operationDate);
        $output->text($root, 'sum1:DescripcionOperacion', $draft->description);
        $output->flag($root, 'sum1:FacturaSimplificadaArt7273', $draft->simplifiedArt7273);
        $output->flag($root, 'sum1:FacturaSinIdentifDestinatarioArt61d', $draft->withoutRecipientArt61d);
        $output->flag($root, 'sum1:Macrodato', $draft->macrodato);
        $output->text($root, 'sum1:EmitidaPorTerceroODestinatario', $draft->issuedBy?->value);
        if ($draft->thirdParty !== null) {
            $output->party($root, 'sum1:Tercero', $draft->thirdParty);
        }
        if ($draft->recipients !== []) {
            $list = $output->add($root, 'sum1:Destinatarios');
            foreach ($draft->recipients as $recipient) {
                $output->party($list, 'sum1:IDDestinatario', $recipient);
            }
        }
        $output->flag($root, 'sum1:Cupon', $draft->coupon);
        $breakdown = $output->add($root, 'sum1:Desglose');
        foreach ($draft->breakdown as $line) {
            self::breakdownLine($output, $output->add($breakdown, 'sum1:DetalleDesglose'), $line);
        }
        $output->text($root, 'sum1:CuotaTotal', $draft->totalTax->text);
        $output->text($root, 'sum1:ImporteTotal', $draft->total->text);
        self::chain($output, $root, $record->chainLink());
        self::system($output, $root, $record->system());
        $output->text($root, 'sum1:FechaHoraHusoGenRegistro', $record->generatedAt()->text);
        $output->optional($root, 'sum1:NumRegistroAcuerdoFacturacion', $draft->agreementNumber);
        $output->optional($root, 'sum1:IdAcuerdoSistemaInformatico', $draft->systemAgreementId);
        $output->text($root, 'sum1:TipoHuella', VerifactuFormat::HASH_TYPE);
        $output->text($root, 'sum1:Huella', $record->fingerprint());

        return $output;
    }

    private function cancellation(CancellationRecord $record): VerifactuOutput
    {
        $draft = $record->draft;
        $output = new VerifactuOutput('sum1:RegistroAnulacion');
        $root = $output->root;

        $output->text($root, 'sum1:IDVersion', VerifactuFormat::ID_VERSION);
        $output->invoiceIdFields($output->add($root, 'sum1:IDFactura'), $draft->annulled, [
            'sum1:IDEmisorFacturaAnulada',
            'sum1:NumSerieFacturaAnulada',
            'sum1:FechaExpedicionFacturaAnulada',
        ]);
        $output->optional($root, 'sum1:RefExterna', $draft->externalReference);
        $output->text($root, 'sum1:SinRegistroPrevio', $draft->operation->sinRegistroPrevio());
        $output->text($root, 'sum1:RechazoPrevio', $draft->operation->rechazoPrevio());
        $output->text($root, 'sum1:GeneradoPor', $draft->generatedBy?->value);
        if ($draft->generator !== null) {
            $output->party($root, 'sum1:Generador', $draft->generator);
        }
        self::chain($output, $root, $record->chainLink());
        self::system($output, $root, $record->system());
        $output->text($root, 'sum1:FechaHoraHusoGenRegistro', $record->generatedAt()->text);
        $output->text($root, 'sum1:TipoHuella', VerifactuFormat::HASH_TYPE);
        $output->text($root, 'sum1:Huella', $record->fingerprint());

        return $output;
    }

    private static function breakdownLine(VerifactuOutput $output, DOMElement $detail, TaxBreakdownLine $line): void
    {
        $output->text($detail, 'sum1:Impuesto', $line->tax?->value);
        $output->text($detail, 'sum1:ClaveRegimen', $line->regime?->value);
        $output->text($detail, 'sum1:CalificacionOperacion', $line->qualification?->value);
        $output->text($detail, 'sum1:OperacionExenta', $line->exemption?->value);
        $output->text($detail, 'sum1:TipoImpositivo', $line->rate?->text);
        $output->text($detail, 'sum1:BaseImponibleOimporteNoSujeto', $line->base->text);
        $output->text($detail, 'sum1:BaseImponibleACoste', $line->costBase?->text);
        $output->text($detail, 'sum1:CuotaRepercutida', $line->quota?->text);
        $output->text($detail, 'sum1:TipoRecargoEquivalencia', $line->equivalenceRate?->text);
        $output->text($detail, 'sum1:CuotaRecargoEquivalencia', $line->equivalenceQuota?->text);
    }

    private static function chain(VerifactuOutput $output, DOMElement $root, ChainLink $link): void
    {
        $chain = $output->add($root, 'sum1:Encadenamiento');
        $previous = $link->previous();
        if ($previous === null) {
            $output->text($chain, 'sum1:PrimerRegistro', 'S');

            return;
        }
        $anterior = $output->add($chain, 'sum1:RegistroAnterior');
        $output->invoiceIdFields($anterior, $previous);
        $output->text($anterior, 'sum1:Huella', $link->previousFingerprint());
    }

    private static function system(VerifactuOutput $output, DOMElement $root, ComputerSystem $system): void
    {
        $element = $output->add($root, 'sum1:SistemaInformatico');
        $output->text($element, 'sum1:NombreRazon', $system->name);
        $output->identity($element, $system->nif, $system->foreignId);
        $output->text($element, 'sum1:NombreSistemaInformatico', $system->systemName);
        $output->text($element, 'sum1:IdSistemaInformatico', $system->systemId);
        $output->text($element, 'sum1:Version', $system->version);
        $output->text($element, 'sum1:NumeroInstalacion', $system->installationNumber);
        $output->yesNo($element, 'sum1:TipoUsoPosibleSoloVerifactu', $system->verifactuOnly);
        $output->yesNo($element, 'sum1:TipoUsoPosibleMultiOT', $system->multipleTaxpayersPossible);
        $output->yesNo($element, 'sum1:IndicadorMultiplesOT', $system->multipleTaxpayersIndicator);
    }
}
