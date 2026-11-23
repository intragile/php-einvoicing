<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Spain\Verifactu;

use IntrAgile\EInvoicing\Spain\Verifactu\Exception\InvalidRecord;

/**
 * One RegFactuSistemaFacturacion submission: a header and 1 to 1000 billing records of the
 * header's issuer (Validaciones v1.2.2 §3.1.3.1).
 */
final readonly class Submission
{
    public const MAX_RECORDS = 1000;

    /**
     * @param  list<BillingRecord>  $records
     */
    public function __construct(
        public SubmissionHeader $header,
        public array $records,
    ) {
        $count = count($records);
        if ($count < 1 || $count > self::MAX_RECORDS) {
            throw new InvalidRecord(sprintf('A submission holds 1 to %d records, %d given.', self::MAX_RECORDS, $count));
        }
        foreach ($records as $index => $record) {
            $issuer = $record->invoiceId()->issuer->value;
            if ($issuer !== $header->issuer->value) {
                throw new InvalidRecord(sprintf(
                    'Record %d belongs to issuer %s, not to the submission issuer %s (Validaciones v1.2.2 §3.1.3.1).',
                    $index + 1,
                    $issuer,
                    $header->issuer->value,
                ));
            }
        }
    }
}
