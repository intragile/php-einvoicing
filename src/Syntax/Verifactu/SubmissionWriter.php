<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Verifactu;

use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;
use IntrAgile\EInvoicing\Spain\Verifactu\Submission;
use IntrAgile\EInvoicing\Validation\Spain\VerifactuSchemas;

/**
 * Writes a VERI*FACTU submission: sum:RegFactuSistemaFacturacion with the header (sum:Cabecera)
 * followed by one sum:RegistroFactura per record, each written by RecordWriter.
 *
 * With installed schemas, the written submission is validated against SuministroLR.xsd and any
 * schema error is thrown; without them nothing is checked.
 */
final class SubmissionWriter
{
    private readonly RecordWriter $records;

    public function __construct(private readonly ?VerifactuSchemas $schemas = null)
    {
        // The whole submission is validated once below, so records are not checked one by one.
        $this->records = new RecordWriter;
    }

    /**
     * @throws InvalidRecord when a record holds text XML cannot carry or, with schemas, the
     *                       written submission fails SuministroLR.xsd
     */
    public function write(Submission $submission): string
    {
        $header = $submission->header;
        $output = new VerifactuOutput('sum:RegFactuSistemaFacturacion', submission: true);
        $root = $output->root;

        $cabecera = $output->add($root, 'sum:Cabecera');
        $output->spanishParty($cabecera, 'sum1:ObligadoEmision', $header->issuerName, $header->issuer);
        if ($header->representative !== null) {
            $output->spanishParty($cabecera, 'sum1:Representante', $header->representative->name, $header->representative->nif);
        }
        if ($header->verifactuEndDate !== null || $header->incident !== null) {
            $voluntary = $output->add($cabecera, 'sum1:RemisionVoluntaria');
            $output->date($voluntary, 'sum1:FechaFinVeriFactu', $header->verifactuEndDate);
            if ($header->incident !== null) {
                $output->yesNo($voluntary, 'sum1:Incidencia', $header->incident);
            }
        }

        foreach ($submission->records as $record) {
            $this->records->appendTo($output->add($root, 'sum:RegistroFactura'), $record);
        }

        return $output->checkedXml(
            $this->schemas,
            static fn (VerifactuSchemas $schemas): string => $schemas->submissionXsd(),
        );
    }
}
