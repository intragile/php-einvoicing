<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Aeat;

use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Syntax\SafeXml;
use IntrAgile\EInvoicing\Syntax\Verifactu\VerifactuFormat;
use IntrAgile\EInvoicing\Syntax\XmlOutput;
use IntrAgile\EInvoicing\Validation\SchemaError;
use IntrAgile\EInvoicing\Validation\SchemaValidator;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;
use InvalidArgumentException;

/**
 * Writes a record query: con:ConsultaFactuSistemaFacturacion with the issuer in the header and a
 * filter on the accounting period, invoice number and issue date of one invoice.
 *
 * The written query is validated against ConsultaLR.xsd; a schema error is thrown.
 */
final class LookupWriter
{
    public function __construct(private readonly VerifactuSchemas $schemas) {}

    /**
     * @throws InvalidRecord when the query holds text XML cannot carry or fails ConsultaLR.xsd
     */
    public function write(LookupRequest $request): string
    {
        $query = AeatFormat::QUERY_PREFIX;
        $sf = VerifactuFormat::SF_PREFIX;
        $namespaces = [$query => AeatFormat::QUERY_NS, $sf => VerifactuFormat::SF_NS];
        $output = new class($namespaces, AeatFormat::QUERY_NS, $query.':ConsultaFactuSistemaFacturacion') extends XmlOutput {};

        try {
            $header = $output->add($output->root, $query.':Cabecera');
            $output->text($header, $sf.':IDVersion', VerifactuFormat::ID_VERSION);
            $issuer = $output->add($header, $sf.':ObligadoEmision');
            $output->text($issuer, $sf.':NombreRazon', trim($request->issuerName));
            $output->text($issuer, $sf.':NIF', $request->issuer->value);

            $filter = $output->add($output->root, $query.':FiltroConsulta');
            $period = $output->add($filter, $query.':PeriodoImputacion');
            $output->text($period, $sf.':Ejercicio', $request->invoice->issueDate->format('Y'));
            $output->text($period, $sf.':Periodo', $request->invoice->issueDate->format('m'));
            $output->text($filter, $query.':NumSerieFactura', $request->invoice->number);
            $issued = $output->add($filter, $query.':FechaExpedicionFactura');
            $output->text($issued, $sf.':FechaExpedicionFactura', $request->invoice->dateText());
        } catch (InvalidArgumentException $e) {
            throw new InvalidRecord($e->getMessage(), 0, $e);
        }

        $xml = $output->xml();
        $path = $this->schemas->queryXsd();
        $errors = (new SchemaValidator)->validate(SafeXml::load($xml), $path, $this->schemas->localPrefixes());
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
}
