<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use BackedEnum;
use DateTimeImmutable;
use DOMElement;
use Exception;
use IntrAgile\EInvoicing\Exception\UnsupportedDocument;
use IntrAgile\EInvoicing\Spain\Nif;
use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\InvoiceId;
use IntrAgile\EInvoicing\Syntax\Verifactu\VerifactuFormat;
use InvalidArgumentException;

/**
 * Reads the payloads of AEAT VERI*FACTU responses: the submission response
 * (RespuestaSuministro.xsd) and the record query response (RespuestaConsultaLR.xsd).
 *
 * The payload is the element SoapEnvelope::open() returns. Elements are located by namespace and
 * local name, so the prefixes AEAT chooses do not matter.
 */
final class ResponseReader
{
    private const SUBMISSION_ROOT = 'RespuestaRegFactuSistemaFacturacion';

    private const LOOKUP_ROOT = 'RespuestaConsultaFactuSistemaFacturacion';

    private const DATE_TIME = '/\A-?\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})?\z/';

    /**
     * @throws UnsupportedDocument when the root is not RespuestaRegFactuSistemaFacturacion, a
     *                             mandatory element is missing or a value is not one the schema allows
     */
    public function submission(DOMElement $payload): SubmissionResponse
    {
        $ns = AeatFormat::RESPONSE_NS;
        self::expectRoot($payload, $ns, self::SUBMISSION_ROOT);

        $csv = self::optionalText($payload, $ns, 'CSV');
        $presenterNif = null;
        $presentedAt = null;
        $presentation = self::child($payload, $ns, 'DatosPresentacion');
        if ($presentation !== null) {
            $presenterNif = self::requiredText($presentation, VerifactuFormat::SF_NS, 'NIFPresentador');
            $presentedAt = self::dateTime($presentation, VerifactuFormat::SF_NS, 'TimestampPresentacion');
        }
        self::required($payload, $ns, 'Cabecera');

        $wait = self::requiredText($payload, $ns, 'TiempoEsperaEnvio');
        if (preg_match('/\A\d{0,4}\z/', $wait) !== 1) {
            throw new UnsupportedDocument(sprintf('TiempoEsperaEnvio "%s" is not a number of seconds.', $wait));
        }
        $state = self::enum(SubmissionState::class, $payload, $ns, 'EstadoEnvio');

        $lines = [];
        foreach (self::children($payload, $ns, 'RespuestaLinea') as $line) {
            $lines[] = self::line($line);
        }

        return new SubmissionResponse(
            $csv,
            $presenterNif,
            $presentedAt,
            (int) $wait,
            $state,
            $lines,
            self::serialise($payload),
        );
    }

    /**
     * The record AEAT holds for the wanted invoice, or null when the query found no data or no
     * returned record is that invoice.
     *
     * @throws UnsupportedDocument when the root is not RespuestaConsultaFactuSistemaFacturacion, a
     *                             mandatory element is missing or a value is not one the schema allows
     */
    public function lookup(DOMElement $payload, InvoiceId $wanted): ?RecordStatus
    {
        $ns = AeatFormat::QUERY_RESPONSE_NS;
        self::expectRoot($payload, $ns, self::LOOKUP_ROOT);

        $result = self::requiredText($payload, $ns, 'ResultadoConsulta');
        if ($result === 'SinDatos') {
            return null;
        }
        if ($result !== 'ConDatos') {
            throw new UnsupportedDocument(sprintf('ResultadoConsulta "%s" is neither ConDatos nor SinDatos.', $result));
        }

        foreach (self::children($payload, $ns, 'RegistroRespuestaConsultaFactuSistemaFacturacion') as $entry) {
            $id = self::required($entry, $ns, 'IDFactura');
            if (! self::identifies($id, $wanted)) {
                continue;
            }

            $data = self::required($entry, $ns, 'DatosRegistroFacturacion');
            $status = self::required($entry, $ns, 'EstadoRegistro');

            return new RecordStatus(
                $wanted,
                self::enum(LookupState::class, $status, $ns, 'EstadoRegistro'),
                self::errorCode($status, $ns),
                self::optionalText($status, $ns, 'DescripcionErrorRegistro'),
                self::optionalText($data, $ns, 'Huella'),
                self::dateTime($status, $ns, 'TimestampUltimaModificacion'),
            );
        }

        return null;
    }

    private static function line(DOMElement $line): RecordResponse
    {
        $ns = AeatFormat::RESPONSE_NS;
        $operation = self::required($line, $ns, 'Operacion');
        $type = self::requiredText($operation, VerifactuFormat::SF_NS, 'TipoOperacion');
        if ($type !== RecordResponse::ALTA && $type !== RecordResponse::ANULACION) {
            throw new UnsupportedDocument(sprintf('TipoOperacion "%s" is neither Alta nor Anulacion.', $type));
        }

        $duplicateState = null;
        $duplicateRequestId = null;
        $duplicate = self::child($line, $ns, 'RegistroDuplicado');
        if ($duplicate !== null) {
            $duplicateRequestId = self::requiredText($duplicate, VerifactuFormat::SF_NS, 'IdPeticionRegistroDuplicado');
            $duplicateState = self::enum(DuplicateState::class, $duplicate, VerifactuFormat::SF_NS, 'EstadoRegistroDuplicado');
        }

        return new RecordResponse(
            self::invoiceId(self::required($line, $ns, 'IDFactura')),
            $type,
            self::optionalText($line, $ns, 'RefExterna'),
            self::enum(RecordState::class, $line, $ns, 'EstadoRegistro'),
            self::errorCode($line, $ns),
            self::optionalText($line, $ns, 'DescripcionErrorRegistro'),
            $duplicateState,
            $duplicateRequestId,
        );
    }

    private static function invoiceId(DOMElement $id): InvoiceId
    {
        $sf = VerifactuFormat::SF_NS;
        $issuer = self::requiredText($id, $sf, 'IDEmisorFactura');
        $number = self::requiredText($id, $sf, 'NumSerieFactura');
        $dateText = self::requiredText($id, $sf, 'FechaExpedicionFactura');

        $date = DateTimeImmutable::createFromFormat('!d-m-Y', $dateText);
        if ($date === false || $date->format('d-m-Y') !== $dateText) {
            throw new UnsupportedDocument(sprintf('IDFactura/FechaExpedicionFactura "%s" is not a dd-mm-yyyy date.', $dateText));
        }

        try {
            return new InvoiceId(Nif::parse($issuer), $number, $date);
        } catch (InvalidArgumentException|InvalidRecord $exception) {
            throw new UnsupportedDocument('IDFactura does not identify an invoice: '.$exception->getMessage(), 0, $exception);
        }
    }

    /** Compares an IDFactura with the wanted invoice by its literal text. */
    private static function identifies(DOMElement $id, InvoiceId $wanted): bool
    {
        $sf = VerifactuFormat::SF_NS;

        return self::requiredText($id, $sf, 'IDEmisorFactura') === $wanted->issuer->value
            && self::requiredText($id, $sf, 'NumSerieFactura') === $wanted->number
            && self::requiredText($id, $sf, 'FechaExpedicionFactura') === $wanted->dateText();
    }

    private static function expectRoot(DOMElement $payload, string $ns, string $name): void
    {
        if ($payload->namespaceURI !== $ns || $payload->localName !== $name) {
            throw new UnsupportedDocument(sprintf(
                'Expected {%s}%s, found {%s}%s.',
                $ns,
                $name,
                (string) $payload->namespaceURI,
                (string) $payload->localName,
            ));
        }
    }

    private static function errorCode(DOMElement $parent, string $ns): ?int
    {
        $code = self::optionalText($parent, $ns, 'CodigoErrorRegistro');
        if ($code === null) {
            return null;
        }
        if (preg_match('/\A[+-]?\d{1,9}\z/', $code) !== 1) {
            throw new UnsupportedDocument(sprintf('CodigoErrorRegistro "%s" is not an integer.', $code));
        }

        return (int) $code;
    }

    private static function dateTime(DOMElement $parent, string $ns, string $name): DateTimeImmutable
    {
        $text = self::requiredText($parent, $ns, $name);
        if (preg_match(self::DATE_TIME, $text) === 1) {
            try {
                return new DateTimeImmutable($text);
            } catch (Exception) {
                // Reported below.
            }
        }

        throw new UnsupportedDocument(sprintf('%s "%s" is not an xs:dateTime.', $name, $text));
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T
     */
    private static function enum(string $enum, DOMElement $parent, string $ns, string $name): BackedEnum
    {
        $text = self::requiredText($parent, $ns, $name);
        $case = $enum::tryFrom($text);
        if ($case === null) {
            throw new UnsupportedDocument(sprintf('%s "%s" is not a value the schema allows.', $name, $text));
        }

        return $case;
    }

    private static function requiredText(DOMElement $parent, string $ns, string $name): string
    {
        return trim(self::required($parent, $ns, $name)->textContent);
    }

    private static function optionalText(DOMElement $parent, string $ns, string $name): ?string
    {
        $child = self::child($parent, $ns, $name);

        return $child === null ? null : trim($child->textContent);
    }

    private static function required(DOMElement $parent, string $ns, string $name): DOMElement
    {
        return self::child($parent, $ns, $name) ?? throw new UnsupportedDocument(sprintf(
            '%s has no {%s}%s.',
            (string) $parent->localName,
            $ns,
            $name,
        ));
    }

    private static function child(DOMElement $parent, string $ns, string $name): ?DOMElement
    {
        return self::children($parent, $ns, $name)[0] ?? null;
    }

    /** @return list<DOMElement> */
    private static function children(DOMElement $parent, string $ns, string $name): array
    {
        $found = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === $ns && $child->localName === $name) {
                $found[] = $child;
            }
        }

        return $found;
    }

    private static function serialise(DOMElement $payload): string
    {
        $xml = $payload->ownerDocument?->saveXML($payload);
        if ($xml === false || $xml === null) {
            throw new UnsupportedDocument('The response payload could not be serialised.');
        }

        return $xml;
    }
}
