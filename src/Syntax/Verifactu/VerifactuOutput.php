<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Verifactu;

use DateTimeImmutable;
use DOMElement;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\ForeignId;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;
use IntrAgile\EInvoicing\Spain\Verifactu\TaxParty;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\XmlOutput;
use IntrAgile\EInvoicing\Validation\SchemaError;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;

/**
 * A VERI*FACTU record or submission under construction.
 *
 * Every value is written from the text the domain objects hold (Amount::$text, TaxRate::$text,
 * RecordTimestamp::$text, InvoiceId::dateText()), so the bytes in the document are the bytes the
 * fingerprint was computed over. Optional texts that are null or empty are left out rather than
 * written as empty elements.
 *
 * @internal
 */
final class VerifactuOutput extends XmlOutput
{
    /**
     * @param  string  $rootName  qualified name of the document element, "sum1:…" or "sum:…"
     * @param  bool  $submission  whether the LR namespace is declared on the root as well
     */
    public function __construct(string $rootName, bool $submission = false)
    {
        $namespaces = $submission
            ? [VerifactuFormat::LR_PREFIX => VerifactuFormat::LR_NS, VerifactuFormat::SF_PREFIX => VerifactuFormat::SF_NS]
            : [VerifactuFormat::SF_PREFIX => VerifactuFormat::SF_NS];
        [$prefix] = explode(':', $rootName, 2);

        parent::__construct($namespaces, $namespaces[$prefix], $rootName);
    }

    /**
     * Serialises the document and, when schemas are given, validates it against $xsd first.
     *
     * @param  callable(VerifactuSchemas): string  $xsd  the schema path of the installed set
     *
     * @throws InvalidRecord listing every schema error; a valid model never produces one, so
     *                       an error here is a defect of the writer or of the installed set
     */
    public function checkedXml(?VerifactuSchemas $schemas, callable $xsd): string
    {
        $xml = $this->xml();
        if ($schemas === null) {
            return $xml;
        }

        $path = $xsd($schemas);
        $errors = (new SchemaValidator)->validate(SafeXml::load($xml), $path, $schemas->localPrefixes());
        if ($errors !== []) {
            throw new InvalidRecord(sprintf(
                'The written VERI*FACTU document fails %s: %s',
                basename($path),
                implode('; ', array_map(
                    static fn (SchemaError $error): string => sprintf('line %d: %s', $error->line, rtrim(trim($error->message), '.')),
                    $errors,
                )),
            ));
        }

        return $xml;
    }

    /**
     * Appends an optional text element; null or an empty string writes nothing.
     */
    public function optional(DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->text($parent, $name, $value);
        }
    }

    /**
     * Appends an S/N element that exists only in the affirmative: S when true, nothing otherwise.
     */
    public function flag(DOMElement $parent, string $name, bool $value): void
    {
        if ($value) {
            $this->text($parent, $name, 'S');
        }
    }

    /**
     * Appends a mandatory S/N element (SiNoType).
     */
    public function yesNo(DOMElement $parent, string $name, bool $value): void
    {
        $this->text($parent, $name, $value ? 'S' : 'N');
    }

    /**
     * Appends a date in the AEAT format dd-mm-yyyy; null writes nothing.
     */
    public function date(DOMElement $parent, string $name, ?DateTimeImmutable $date): void
    {
        if ($date !== null) {
            $this->text($parent, $name, $date->format('d-m-Y'));
        }
    }

    /**
     * Appends the three invoice identifier elements (IDEmisorFactura, NumSerieFactura,
     * FechaExpedicionFactura, or the names given) directly under $parent.
     *
     * @param  array{string, string, string}  $names
     */
    public function invoiceIdFields(
        DOMElement $parent,
        InvoiceId $id,
        array $names = ['sum1:IDEmisorFactura', 'sum1:NumSerieFactura', 'sum1:FechaExpedicionFactura'],
    ): void {
        $this->text($parent, $names[0], $id->issuer->value);
        $this->text($parent, $names[1], $id->number);
        $this->text($parent, $names[2], $id->dateText());
    }

    /**
     * Appends a Spanish party (PersonaFisicaJuridicaESType): NombreRazon and NIF.
     */
    public function spanishParty(DOMElement $parent, string $name, string $partyName, Nif $nif): void
    {
        $party = $this->add($parent, $name);
        $this->text($party, 'sum1:NombreRazon', $partyName);
        $this->text($party, 'sum1:NIF', $nif->value);
    }

    /**
     * Appends a party (PersonaFisicaJuridicaType): NombreRazon, then NIF or IDOtro.
     */
    public function party(DOMElement $parent, string $name, TaxParty $party): void
    {
        $element = $this->add($parent, $name);
        $this->text($element, 'sum1:NombreRazon', $party->name);
        $this->identity($element, $party->nif, $party->foreignId);
    }

    /**
     * Appends the NIF or IDOtro choice of a party or computer system.
     */
    public function identity(DOMElement $parent, ?Nif $nif, ?ForeignId $foreignId): void
    {
        if ($nif !== null) {
            $this->text($parent, 'sum1:NIF', $nif->value);

            return;
        }
        if ($foreignId !== null) {
            $other = $this->add($parent, 'sum1:IDOtro');
            $this->text($other, 'sum1:CodigoPais', $foreignId->country?->value);
            $this->text($other, 'sum1:IDType', $foreignId->type->value);
            $this->text($other, 'sum1:ID', $foreignId->id);
        }
    }
}
