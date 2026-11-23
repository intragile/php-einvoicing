<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Verifactu;

use BackedEnum;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Syntax\XmlDocument;
use InvalidArgumentException;

/**
 * A loaded VERI*FACTU billing record: a bare RegistroAlta or RegistroAnulacion of
 * SuministroInformacion.xsd (tikeV1.0). Elements are addressed with the prefix "sf" for the
 * SuministroInformacion namespace and "ds" for XML-DSig.
 *
 * The constructor checks the element tree against the record structure of the schema before
 * anything is mapped: an element the schema does not define at its place, a single-occurrence
 * element given twice, or a leaf with element children is unsupported and named by its path. The
 * optional ds:Signature directly under the record is accepted and not looked into. Element
 * order and the remaining cardinalities are left to the schema validator.
 *
 * Every value is read trimmed; an element that is empty after trimming counts as absent. A value
 * the domain refuses is reported as InvalidRecord prefixed with the element's path.
 *
 * @internal
 */
final class VerifactuDocument extends XmlDocument
{
    /** The XML-DSig namespace of the optional ds:Signature. */
    public const DS_NS = 'http://www.w3.org/2000/09/xmldsig#';

    /** The elements that may occur more than once under their parent. */
    private const REPEATED = ['IDFacturaRectificada', 'IDFacturaSustituida', 'IDDestinatario', 'DetalleDesglose'];

    /**
     * The children each structure admits: local name => structure of the child, or null for a
     * leaf holding text.
     *
     * @var array<string, array<string, ?string>>
     */
    private const STRUCTURE = [
        'RegistroAlta' => [
            'IDVersion' => null,
            'IDFactura' => 'IDFactura',
            'RefExterna' => null,
            'NombreRazonEmisor' => null,
            'Subsanacion' => null,
            'RechazoPrevio' => null,
            'TipoFactura' => null,
            'TipoRectificativa' => null,
            'FacturasRectificadas' => 'FacturasRectificadas',
            'FacturasSustituidas' => 'FacturasSustituidas',
            'ImporteRectificacion' => 'ImporteRectificacion',
            'FechaOperacion' => null,
            'DescripcionOperacion' => null,
            'FacturaSimplificadaArt7273' => null,
            'FacturaSinIdentifDestinatarioArt61d' => null,
            'Macrodato' => null,
            'EmitidaPorTerceroODestinatario' => null,
            'Tercero' => 'Party',
            'Destinatarios' => 'Destinatarios',
            'Cupon' => null,
            'Desglose' => 'Desglose',
            'CuotaTotal' => null,
            'ImporteTotal' => null,
            'Encadenamiento' => 'Encadenamiento',
            'SistemaInformatico' => 'SistemaInformatico',
            'FechaHoraHusoGenRegistro' => null,
            'NumRegistroAcuerdoFacturacion' => null,
            'IdAcuerdoSistemaInformatico' => null,
            'TipoHuella' => null,
            'Huella' => null,
        ],
        'RegistroAnulacion' => [
            'IDVersion' => null,
            'IDFactura' => 'IDFacturaAnulada',
            'RefExterna' => null,
            'SinRegistroPrevio' => null,
            'RechazoPrevio' => null,
            'GeneradoPor' => null,
            'Generador' => 'Party',
            'Encadenamiento' => 'Encadenamiento',
            'SistemaInformatico' => 'SistemaInformatico',
            'FechaHoraHusoGenRegistro' => null,
            'TipoHuella' => null,
            'Huella' => null,
        ],
        'IDFactura' => ['IDEmisorFactura' => null, 'NumSerieFactura' => null, 'FechaExpedicionFactura' => null],
        'IDFacturaAnulada' => ['IDEmisorFacturaAnulada' => null, 'NumSerieFacturaAnulada' => null, 'FechaExpedicionFacturaAnulada' => null],
        'FacturasRectificadas' => ['IDFacturaRectificada' => 'IDFactura'],
        'FacturasSustituidas' => ['IDFacturaSustituida' => 'IDFactura'],
        'ImporteRectificacion' => ['BaseRectificada' => null, 'CuotaRectificada' => null, 'CuotaRecargoRectificado' => null],
        'Party' => ['NombreRazon' => null, 'NIF' => null, 'IDOtro' => 'IDOtro'],
        'IDOtro' => ['CodigoPais' => null, 'IDType' => null, 'ID' => null],
        'Destinatarios' => ['IDDestinatario' => 'Party'],
        'Desglose' => ['DetalleDesglose' => 'DetalleDesglose'],
        'DetalleDesglose' => [
            'Impuesto' => null,
            'ClaveRegimen' => null,
            'CalificacionOperacion' => null,
            'OperacionExenta' => null,
            'TipoImpositivo' => null,
            'BaseImponibleOimporteNoSujeto' => null,
            'BaseImponibleACoste' => null,
            'CuotaRepercutida' => null,
            'TipoRecargoEquivalencia' => null,
            'CuotaRecargoEquivalencia' => null,
        ],
        'Encadenamiento' => ['PrimerRegistro' => null, 'RegistroAnterior' => 'RegistroAnterior'],
        'RegistroAnterior' => ['IDEmisorFactura' => null, 'NumSerieFactura' => null, 'FechaExpedicionFactura' => null, 'Huella' => null],
        'SistemaInformatico' => [
            'NombreRazon' => null,
            'NIF' => null,
            'IDOtro' => 'IDOtro',
            'NombreSistemaInformatico' => null,
            'IdSistemaInformatico' => null,
            'Version' => null,
            'NumeroInstalacion' => null,
            'TipoUsoPosibleSoloVerifactu' => null,
            'TipoUsoPosibleMultiOT' => null,
            'IndicadorMultiplesOT' => null,
        ],
    ];

    /**
     * @throws UnsupportedDocument when the root is not a record of SuministroInformacion.xsd or an
     *                             element is not defined at its place
     */
    public function __construct(DOMDocument $document)
    {
        parent::__construct($document, [VerifactuFormat::SF_NS => 'sf', self::DS_NS => 'ds']);
        $root = $this->root;

        if ($root->namespaceURI !== VerifactuFormat::SF_NS || ! in_array($root->localName, ['RegistroAlta', 'RegistroAnulacion'], true)) {
            throw new UnsupportedDocument(sprintf(
                'Unsupported root element {%s}%s; expected a VERI*FACTU RegistroAlta or RegistroAnulacion in %s.',
                (string) $root->namespaceURI,
                (string) $root->localName,
                VerifactuFormat::SF_NS,
            ));
        }

        $this->checkStructure($root, (string) $root->localName);
    }

    public function isRegistration(): bool
    {
        return $this->root->localName === 'RegistroAlta';
    }

    public function child(string $name, DOMElement $context): ?DOMElement
    {
        return $this->one('sf:'.$name, $context);
    }

    /**
     * @throws UnsupportedDocument when the element is missing
     */
    public function requiredChild(string $name, DOMElement $context): DOMElement
    {
        return $this->child($name, $context) ?? throw $this->missing('sf:'.$name, $context, $name);
    }

    /**
     * @return list<DOMElement>
     */
    public function children(string $name, DOMElement $context): array
    {
        return $this->all('sf:'.$name, $context);
    }

    /**
     * The trimmed text of a child; null when it is absent or blank.
     */
    public function value(string $name, DOMElement $context): ?string
    {
        return $this->token('sf:'.$name, $context);
    }

    /**
     * @throws UnsupportedDocument when the child is absent or blank
     */
    public function requiredValue(string $name, DOMElement $context): string
    {
        return $this->value($name, $context) ?? throw $this->missing('sf:'.$name, $context, $name);
    }

    /**
     * Converts the trimmed text of a child; null when it is absent or blank.
     *
     * @template T
     *
     * @param  callable(string): T  $convert  throws InvalidArgumentException for a refused value
     * @return T|null
     *
     * @throws InvalidRecord naming the element's path when the conversion fails
     */
    public function parsed(string $name, DOMElement $context, callable $convert): mixed
    {
        $element = $this->child($name, $context);
        if ($element === null) {
            return null;
        }
        $this->use($element);
        $value = trim($element->textContent);
        if ($value === '') {
            return null;
        }

        try {
            return $convert($value);
        } catch (InvalidArgumentException $e) {
            throw new InvalidRecord(sprintf('%s: %s', $this->path($element), $e->getMessage()), 0, $e);
        }
    }

    /**
     * @template T
     *
     * @param  callable(string): T  $convert  throws InvalidArgumentException for a refused value
     * @return T
     *
     * @throws UnsupportedDocument when the child is absent or blank
     * @throws InvalidRecord naming the element's path when the conversion fails
     */
    public function requiredParsed(string $name, DOMElement $context, callable $convert): mixed
    {
        return $this->parsed($name, $context, $convert) ?? throw $this->missing('sf:'.$name, $context, $name);
    }

    /**
     * Resolves a code held in a child; null when it is absent or blank.
     *
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     *
     * @throws InvalidRecord when the list does not contain the code
     */
    public function codeValue(string $enum, string $name, DOMElement $context): ?BackedEnum
    {
        return $this->parsed(
            $name,
            $context,
            static fn (string $value): BackedEnum => $enum::tryFrom($value) ?? throw new InvalidArgumentException(sprintf("invalid code '%s'.", $value)),
        );
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T
     *
     * @throws UnsupportedDocument when the child is absent or blank
     * @throws InvalidRecord when the list does not contain the code
     */
    public function requiredCodeValue(string $enum, string $name, DOMElement $context): BackedEnum
    {
        return $this->codeValue($enum, $name, $context) ?? throw $this->missing('sf:'.$name, $context, $name);
    }

    /**
     * A date in the AEAT format dd-mm-yyyy; null when the child is absent or blank.
     *
     * @throws InvalidRecord when the text is not a valid date in that format
     */
    public function dateValue(string $name, DOMElement $context): ?DateTimeImmutable
    {
        return $this->parsed(
            $name,
            $context,
            static fn (string $value): DateTimeImmutable => self::parseDayMonthYear($value)
                ?? throw new InvalidArgumentException(sprintf("invalid date '%s'; expected dd-mm-yyyy.", $value)),
        );
    }

    /**
     * @throws UnsupportedDocument when the child is absent or blank
     * @throws InvalidRecord when the text is not a valid date in that format
     */
    public function requiredDateValue(string $name, DOMElement $context): DateTimeImmutable
    {
        return $this->dateValue($name, $context) ?? throw $this->missing('sf:'.$name, $context, $name);
    }

    /**
     * An S/N flag: true for S, false for N or an absent element.
     *
     * @throws InvalidRecord for any other value
     */
    public function flagValue(string $name, DOMElement $context): bool
    {
        return $this->parsed($name, $context, self::yesNo(...)) ?? false;
    }

    /**
     * A mandatory S/N element (SiNoType).
     *
     * @throws UnsupportedDocument when the child is absent or blank
     * @throws InvalidRecord for a value other than S or N
     */
    public function requiredYesNo(string $name, DOMElement $context): bool
    {
        return $this->requiredParsed($name, $context, self::yesNo(...));
    }

    protected function parseDate(DOMElement $element): ?DateTimeImmutable
    {
        return self::parseDayMonthYear(trim($element->textContent));
    }

    private static function parseDayMonthYear(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);

        // The round trip refuses rolled-over days (31-02) and short forms (1-2-2026), so the
        // date writes back to the text it was read from.
        return $date !== false && $date->format('d-m-Y') === $value ? $date : null;
    }

    private static function yesNo(string $value): bool
    {
        return match ($value) {
            'S' => true,
            'N' => false,
            default => throw new InvalidArgumentException(sprintf("invalid value '%s'; expected S or N.", $value)),
        };
    }

    /**
     * @throws UnsupportedDocument naming the first element that the structure does not admit
     */
    private function checkStructure(DOMElement $element, string $structure): void
    {
        $admitted = self::STRUCTURE[$structure];
        $seen = [];

        for ($child = $element->firstElementChild; $child !== null; $child = $child->nextElementSibling) {
            if ($element->isSameNode($this->root) && $child->namespaceURI === self::DS_NS && $child->localName === 'Signature') {
                // The record signature is not part of the billing data; it is not read.
                $this->use($child);

                continue;
            }

            $name = (string) $child->localName;
            if ($child->namespaceURI !== VerifactuFormat::SF_NS || ! array_key_exists($name, $admitted)) {
                throw new UnsupportedDocument(sprintf('Unsupported element %s in a VERI*FACTU record.', $this->path($child)));
            }
            if (isset($seen[$name]) && ! in_array($name, self::REPEATED, true)) {
                throw new UnsupportedDocument(sprintf('The element %s may occur only once.', $this->path($child)));
            }
            $seen[$name] = true;

            $childStructure = $admitted[$name];
            if ($childStructure !== null) {
                $this->checkStructure($child, $childStructure);
            } elseif ($child->firstElementChild !== null) {
                throw new UnsupportedDocument(sprintf('Unsupported element %s in a VERI*FACTU record.', $this->path($child->firstElementChild)));
            }
        }
    }
}
