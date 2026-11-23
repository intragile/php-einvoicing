<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use DOMElement;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Syntax\Verifactu\VerifactuFormat;

/**
 * The SHA-256 fingerprint (Huella) of a VERI*FACTU billing record.
 *
 * AEAT hash specification v0.1.2 §5: the hashed fields are written as "name=value" pairs joined
 * by "&", without URL encoding, a missing value as "Name=", and the UTF-8 bytes are hashed with
 * SHA-256 and written as upper-case hexadecimal.
 */
final class Fingerprint
{
    private function __construct() {}

    public static function of(BillingRecord $record): string
    {
        return self::ofInput(self::input($record));
    }

    /** The exact string that is hashed for the record. */
    public static function input(BillingRecord $record): string
    {
        return implode('&', array_map(
            static fn (array $field): string => $field[0].'='.$field[1],
            $record->hashFields(),
        ));
    }

    /**
     * The string that is hashed for a written RegistroAlta (eight fields) or RegistroAnulacion
     * (five fields), rebuilt from the literal text of its elements in the SuministroInformacion
     * namespace. Each value is trimmed; a missing element counts as empty, as the previous
     * Huella of the first record in a chain does.
     *
     * @throws InvalidRecord when the element is neither record
     */
    public static function inputOf(DOMElement $record): string
    {
        $paths = match (true) {
            $record->namespaceURI !== VerifactuFormat::SF_NS => null,
            $record->localName === 'RegistroAlta' => [
                'IDEmisorFactura' => ['IDFactura', 'IDEmisorFactura'],
                'NumSerieFactura' => ['IDFactura', 'NumSerieFactura'],
                'FechaExpedicionFactura' => ['IDFactura', 'FechaExpedicionFactura'],
                'TipoFactura' => ['TipoFactura'],
                'CuotaTotal' => ['CuotaTotal'],
                'ImporteTotal' => ['ImporteTotal'],
                'Huella' => ['Encadenamiento', 'RegistroAnterior', 'Huella'],
                'FechaHoraHusoGenRegistro' => ['FechaHoraHusoGenRegistro'],
            ],
            $record->localName === 'RegistroAnulacion' => [
                'IDEmisorFacturaAnulada' => ['IDFactura', 'IDEmisorFacturaAnulada'],
                'NumSerieFacturaAnulada' => ['IDFactura', 'NumSerieFacturaAnulada'],
                'FechaExpedicionFacturaAnulada' => ['IDFactura', 'FechaExpedicionFacturaAnulada'],
                'Huella' => ['Encadenamiento', 'RegistroAnterior', 'Huella'],
                'FechaHoraHusoGenRegistro' => ['FechaHoraHusoGenRegistro'],
            ],
            default => null,
        };
        if ($paths === null) {
            throw new InvalidRecord(sprintf('The element {%s}%s is not a RegistroAlta or RegistroAnulacion.', $record->namespaceURI ?? '', $record->localName ?? $record->nodeName));
        }

        $pairs = [];
        foreach ($paths as $name => $path) {
            $pairs[] = $name.'='.self::textAt($record, $path);
        }

        return implode('&', $pairs);
    }

    public static function ofInput(string $input): string
    {
        return strtoupper(hash('sha256', $input));
    }

    /**
     * @param  list<string>  $path  local names of nested child elements
     */
    private static function textAt(DOMElement $element, array $path): string
    {
        foreach ($path as $localName) {
            $next = null;
            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMElement && $child->namespaceURI === VerifactuFormat::SF_NS && $child->localName === $localName) {
                    $next = $child;
                    break;
                }
            }
            if ($next === null) {
                return '';
            }
            $element = $next;
        }

        return trim($element->textContent);
    }
}
